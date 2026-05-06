<?php

namespace App\Jobs\Research;

use App\Models\RankTrackingKeyword;
use App\Models\RankTrackingSnapshot;
use App\Models\Research\KeywordAlert;
use App\Models\Research\KeywordIntelligence;
use App\Models\Research\SerpResult;
use App\Models\Research\SerpSnapshot;
use App\Models\Website;
use App\Services\Research\Intelligence\OpportunityEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Phase-4 alerts. Generalises the existing DetectTrafficDrops flow into
 * four signal types written to `keyword_alerts`:
 *
 *   ranking_drop        rank_tracking_snapshots: today's position vs 7d ago, drop ≥ 5 spots.
 *   serp_change         consecutive serp_snapshots: Jaccard(top-10 domains) < 0.5.
 *   volatility_spike    keyword_intelligence.volatility_score > 0.5
 *                       (z-score gating waits for enough population data).
 *   new_opportunity     OpportunityEngine score > 100 on a GSC (page,kw) pair.
 *
 * Idempotent within a 24h window: dedupes by (website_id, type, keyword_id)
 * so a daily schedule cannot stack repeats. The legacy DetectTrafficDrops
 * job stays in place; this job covers four orthogonal signals.
 */
class DetectResearchSignalsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;
    public int $tries = 1;

    private const RANKING_DROP_THRESHOLD = 5;
    private const SERP_CHANGE_JACCARD = 0.5;
    private const VOLATILITY_THRESHOLD = 0.5;
    private const OPPORTUNITY_THRESHOLD = 100.0;
    private const DEDUPE_WINDOW_HOURS = 24;

    /** @param list<int>|null $websiteIds */
    public function __construct(public ?array $websiteIds = null) {}

    public function handle(OpportunityEngine $opportunity): void
    {
        $query = Website::query()->orderBy('id');
        if ($this->websiteIds !== null) {
            $query->whereIn('id', $this->websiteIds);
        }

        $query->chunkById(50, function ($websites) use ($opportunity): void {
            foreach ($websites as $website) {
                $this->scanRankingDrops($website);
                $this->scanSerpChanges($website);
                $this->scanVolatilitySpikes($website);
                $this->scanOpportunities($website, $opportunity);
            }
        });
    }

    private function scanRankingDrops(Website $website): void
    {
        $rtks = RankTrackingKeyword::query()
            ->where('website_id', $website->id)
            ->get(['id', 'keyword', 'country']);

        foreach ($rtks as $rtk) {
            $latest = RankTrackingSnapshot::query()
                ->where('rank_tracking_keyword_id', $rtk->id)
                ->whereNotNull('position')
                ->orderByDesc('checked_at')
                ->first();
            if ($latest === null) {
                continue;
            }

            $prior = RankTrackingSnapshot::query()
                ->where('rank_tracking_keyword_id', $rtk->id)
                ->whereNotNull('position')
                ->where('checked_at', '<=', Carbon::now()->subDays(7))
                ->orderByDesc('checked_at')
                ->first();
            if ($prior === null) {
                continue;
            }

            $drop = (int) $latest->position - (int) $prior->position;
            if ($drop < self::RANKING_DROP_THRESHOLD) {
                continue;
            }

            // RankTrackingKeyword stores the query as text; resolve to the
            // research Keyword id by hash so the alert links cleanly.
            $keywordId = \App\Models\Research\Keyword::query()
                ->where('query_hash', \App\Models\Research\Keyword::hashFor((string) $rtk->keyword))
                ->where('country', mb_strtolower((string) $rtk->country))
                ->value('id');

            $this->emit(
                $website,
                KeywordAlert::TYPE_RANKING_DROP,
                $keywordId !== null ? (int) $keywordId : null,
                'warn',
                [
                    'from' => (int) $prior->position,
                    'to' => (int) $latest->position,
                    'rank_tracking_keyword_id' => (int) $rtk->id,
                ],
            );
        }
    }

    private function scanSerpChanges(Website $website): void
    {
        $keywordIds = DB::table('search_console_data')
            ->where('website_id', $website->id)
            ->whereNotNull('keyword_id')
            ->whereDate('date', '>=', Carbon::now()->subDays(30)->toDateString())
            ->distinct()
            ->pluck('keyword_id');

        foreach ($keywordIds as $keywordId) {
            $snapshots = SerpSnapshot::query()
                ->where('keyword_id', $keywordId)
                ->orderByDesc('fetched_at')
                ->limit(2)
                ->get();
            if ($snapshots->count() < 2) {
                continue;
            }

            $jaccard = $this->topDomainJaccard((int) $snapshots[0]->id, (int) $snapshots[1]->id);
            if ($jaccard >= self::SERP_CHANGE_JACCARD) {
                continue;
            }

            $this->emit(
                $website,
                KeywordAlert::TYPE_SERP_CHANGE,
                (int) $keywordId,
                'info',
                ['jaccard' => round($jaccard, 3), 'snapshots' => [$snapshots[0]->id, $snapshots[1]->id]],
            );
        }
    }

    private function scanVolatilitySpikes(Website $website): void
    {
        $keywordIds = DB::table('search_console_data')
            ->where('website_id', $website->id)
            ->whereNotNull('keyword_id')
            ->distinct()
            ->pluck('keyword_id')
            ->all();

        if ($keywordIds === []) {
            return;
        }

        $rows = KeywordIntelligence::query()
            ->whereIn('keyword_id', $keywordIds)
            ->where('volatility_score', '>=', self::VOLATILITY_THRESHOLD)
            ->get(['keyword_id', 'volatility_score']);

        foreach ($rows as $row) {
            $this->emit(
                $website,
                KeywordAlert::TYPE_VOLATILITY_SPIKE,
                (int) $row->keyword_id,
                'info',
                ['score' => (float) $row->volatility_score],
            );
        }
    }

    private function scanOpportunities(Website $website, OpportunityEngine $engine): void
    {
        $rows = DB::table('search_console_data')
            ->select('keyword_id', 'page')
            ->selectRaw('SUM(impressions) as imps, SUM(clicks) as clicks, AVG(position) as avg_pos')
            ->where('website_id', $website->id)
            ->whereNotNull('keyword_id')
            ->whereDate('date', '>=', Carbon::now()->subDays(30)->toDateString())
            ->groupBy('keyword_id', 'page')
            ->orderByDesc('imps')
            ->limit(50)
            ->get();

        $intelById = KeywordIntelligence::query()
            ->whereIn('keyword_id', $rows->pluck('keyword_id')->filter())
            ->get()
            ->keyBy('keyword_id');

        foreach ($rows as $row) {
            $intel = $intelById->get($row->keyword_id);
            $score = $engine->score(
                impressions30d: (int) $row->imps,
                currentCtr: $row->imps > 0 ? (float) $row->clicks / (float) $row->imps : 0.0,
                currentPosition: (float) $row->avg_pos,
                searchVolume: $intel?->search_volume,
                difficulty: $intel?->difficulty_score,
                nicheCtrByPosition: [1 => 0.30, 2 => 0.18, 3 => 0.11, 4 => 0.08, 5 => 0.06, 6 => 0.04, 7 => 0.03, 8 => 0.025, 9 => 0.02, 10 => 0.018],
                targetPosition: 3,
            );

            if ($score['score'] < self::OPPORTUNITY_THRESHOLD) {
                continue;
            }

            $this->emit(
                $website,
                KeywordAlert::TYPE_NEW_OPPORTUNITY,
                (int) $row->keyword_id,
                'info',
                [
                    'page' => (string) $row->page,
                    'score' => $score['score'],
                    'avg_position' => round((float) $row->avg_pos, 2),
                ],
            );
        }
    }

    /** @param array<string, mixed> $payload */
    private function emit(Website $website, string $type, ?int $keywordId, string $severity, array $payload): void
    {
        $exists = KeywordAlert::query()
            ->where('website_id', $website->id)
            ->where('type', $type)
            ->when($keywordId !== null, fn ($q) => $q->where('keyword_id', $keywordId))
            ->where('created_at', '>=', Carbon::now()->subHours(self::DEDUPE_WINDOW_HOURS))
            ->exists();

        if ($exists) {
            return;
        }

        KeywordAlert::create([
            'website_id' => $website->id,
            'keyword_id' => $keywordId,
            'type' => $type,
            'severity' => $severity,
            'payload' => $payload,
        ]);
    }

    private function topDomainJaccard(int $aSnapshotId, int $bSnapshotId): float
    {
        $a = SerpResult::query()
            ->where('snapshot_id', $aSnapshotId)
            ->where('result_type', 'organic')
            ->orderBy('rank')
            ->limit(10)
            ->pluck('domain')
            ->mapWithKeys(fn ($d) => [(string) $d => true])
            ->all();

        $b = SerpResult::query()
            ->where('snapshot_id', $bSnapshotId)
            ->where('result_type', 'organic')
            ->orderBy('rank')
            ->limit(10)
            ->pluck('domain')
            ->mapWithKeys(fn ($d) => [(string) $d => true])
            ->all();

        if ($a === [] || $b === []) {
            return 0.0;
        }

        $intersect = count(array_intersect_key($a, $b));
        $union = count($a + $b);

        return $union === 0 ? 0.0 : $intersect / $union;
    }
}
