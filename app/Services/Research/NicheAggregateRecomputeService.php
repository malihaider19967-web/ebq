<?php

namespace App\Services\Research;

use App\Models\Research\NicheAggregate;
use App\Models\Research\NicheKeywordMap;
use App\Models\SearchConsoleData;
use App\Services\Research\Privacy\PrivacyGuard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds the anonymised cross-client aggregate per niche and per
 * (niche, keyword). Privacy floor is enforced **at write time** —
 * `sample_site_count >= PrivacyGuard::SAMPLE_FLOOR` is a hard
 * precondition for inserting the row. Below the floor we delete any
 * stale rows so consumers can never accidentally read them.
 */
class NicheAggregateRecomputeService
{
    public function __construct(
        private readonly PrivacyGuard $guard = new PrivacyGuard(),
    ) {}

    public function recompute(): void
    {
        $now = Carbon::now();

        // Per (niche, keyword) — CTR by position.
        $keywordRows = DB::table('niche_keyword_map')
            ->select('niche_id', 'keyword_id')
            ->groupBy('niche_id', 'keyword_id')
            ->get();

        foreach ($keywordRows as $row) {
            $this->recomputeKeyword((int) $row->niche_id, (int) $row->keyword_id, $now);
        }

        // Per niche — overall.
        $nicheIds = NicheKeywordMap::query()->distinct()->pluck('niche_id');
        foreach ($nicheIds as $nicheId) {
            $this->recomputeNiche((int) $nicheId, $now);
        }
    }

    private function recomputeKeyword(int $nicheId, int $keywordId, Carbon $now): void
    {
        $rows = DB::table('search_console_data')
            ->select('website_id', 'position', 'ctr', 'impressions')
            ->where('keyword_id', $keywordId)
            ->whereDate('date', '>=', Carbon::now()->subDays(30)->toDateString())
            ->where('impressions', '>', 0)
            ->get();

        $sampleSites = $rows->pluck('website_id')->unique()->count();

        if ($sampleSites < PrivacyGuard::SAMPLE_FLOOR) {
            NicheAggregate::query()
                ->where('niche_id', $nicheId)
                ->where('keyword_id', $keywordId)
                ->delete();

            return;
        }

        $ctrByPosition = [];
        foreach ($rows as $row) {
            $bucket = max(1, min(20, (int) round((float) $row->position)));
            $ctrByPosition[$bucket][] = (float) $row->ctr;
        }
        $ctrByPosition = array_map(fn ($values) => round(array_sum($values) / count($values), 4), $ctrByPosition);
        ksort($ctrByPosition);

        NicheAggregate::query()->updateOrCreate(
            ['niche_id' => $nicheId, 'keyword_id' => $keywordId],
            [
                'avg_ctr_by_position' => $ctrByPosition,
                'sample_site_count' => $sampleSites,
                'last_recomputed_at' => $now,
            ]
        );
    }

    private function recomputeNiche(int $nicheId, Carbon $now): void
    {
        $sampleSites = DB::table('website_niche_map')
            ->where('niche_id', $nicheId)
            ->distinct('website_id')
            ->count('website_id');

        if ($sampleSites < PrivacyGuard::SAMPLE_FLOOR) {
            NicheAggregate::query()
                ->where('niche_id', $nicheId)
                ->whereNull('keyword_id')
                ->delete();

            return;
        }

        NicheAggregate::query()->updateOrCreate(
            ['niche_id' => $nicheId, 'keyword_id' => null],
            [
                'sample_site_count' => $sampleSites,
                'last_recomputed_at' => $now,
            ]
        );
    }
}
