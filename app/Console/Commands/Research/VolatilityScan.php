<?php

namespace App\Console\Commands\Research;

use App\Models\Research\KeywordIntelligence;
use App\Models\Research\SerpResult;
use App\Models\Research\SerpSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Computes a per-keyword volatility metric: 1 - Jaccard between the
 * top-10 organic domain set of the latest two snapshots within the
 * window. Stores the score on keyword_intelligence.volatility_score so
 * the alerts engine can z-score it.
 */
class VolatilityScan extends Command
{
    protected $signature = 'ebq:research-volatility-scan
                            {--days=7 : Compare snapshots within the last N days}
                            {--limit=2000 : Max keywords to scan this run}';

    protected $description = 'Score SERP volatility per keyword using top-10 Jaccard between recent snapshots.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $limit = max(1, (int) $this->option('limit'));

        $since = Carbon::now()->subDays($days);

        $keywordIds = SerpSnapshot::query()
            ->where('fetched_at', '>=', $since)
            ->select('keyword_id')
            ->groupBy('keyword_id')
            ->havingRaw('COUNT(*) >= 2')
            ->limit($limit)
            ->pluck('keyword_id');

        $scored = 0;

        foreach ($keywordIds as $keywordId) {
            $snapshots = SerpSnapshot::query()
                ->where('keyword_id', $keywordId)
                ->where('fetched_at', '>=', $since)
                ->orderByDesc('fetched_at')
                ->limit(2)
                ->get();
            if ($snapshots->count() < 2) {
                continue;
            }

            $a = $this->topDomains($snapshots[0]->id);
            $b = $this->topDomains($snapshots[1]->id);
            $score = 1.0 - $this->jaccard($a, $b);

            KeywordIntelligence::query()->updateOrCreate(
                ['keyword_id' => (int) $keywordId],
                ['volatility_score' => round($score, 4), 'last_serp_at' => $snapshots[0]->fetched_at]
            );
            $scored++;
        }

        $this->info("VolatilityScan: scored {$scored} keyword(s).");

        return self::SUCCESS;
    }

    /** @return array<string, true> */
    private function topDomains(int $snapshotId): array
    {
        return SerpResult::query()
            ->where('snapshot_id', $snapshotId)
            ->where('result_type', 'organic')
            ->orderBy('rank')
            ->limit(10)
            ->pluck('domain')
            ->mapWithKeys(fn ($d) => [(string) $d => true])
            ->all();
    }

    /**
     * @param  array<string, true>  $a
     * @param  array<string, true>  $b
     */
    private function jaccard(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }
        $i = count(array_intersect_key($a, $b));
        $u = count($a + $b);

        return $u === 0 ? 0.0 : $i / $u;
    }
}
