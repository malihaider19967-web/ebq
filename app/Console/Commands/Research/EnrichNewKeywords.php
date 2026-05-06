<?php

namespace App\Console\Commands\Research;

use App\Jobs\Research\EnrichKeywordJob;
use App\Models\Research\Keyword;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class EnrichNewKeywords extends Command
{
    protected $signature = 'ebq:research-enrich-new-keywords
                            {--limit=200 : Max keywords to enqueue this run}
                            {--stale-days=30 : Re-enrich rows whose last_metrics_at is older than N days}
                            {--dry-run : Print the count without enqueuing}';

    protected $description = 'Enqueue EnrichKeywordJob for keywords with no intelligence row or stale metrics.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $staleDays = max(1, (int) $this->option('stale-days'));
        $dryRun = (bool) $this->option('dry-run');

        $cutoff = Carbon::now()->subDays($staleDays);

        $candidates = Keyword::query()
            ->leftJoin('keyword_intelligence', 'keyword_intelligence.keyword_id', '=', 'keywords.id')
            ->whereRaw('(keyword_intelligence.id IS NULL OR keyword_intelligence.last_metrics_at IS NULL OR keyword_intelligence.last_metrics_at < ?)', [$cutoff])
            ->orderBy('keywords.id')
            ->limit($limit)
            ->pluck('keywords.id');

        $count = $candidates->count();
        $this->line("<fg=cyan>EnrichNewKeywords:</> {$count} candidate(s) (limit={$limit}, stale-days={$staleDays}).");

        if ($dryRun || $count === 0) {
            return self::SUCCESS;
        }

        foreach ($candidates as $id) {
            EnrichKeywordJob::dispatch((int) $id);
        }

        $this->info("Queued {$count} EnrichKeywordJob(s).");

        return self::SUCCESS;
    }
}
