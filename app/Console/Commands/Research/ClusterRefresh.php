<?php

namespace App\Console\Commands\Research;

use App\Jobs\Research\ClusterKeywordsJob;
use App\Models\Research\Keyword;
use App\Models\Research\SerpSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ClusterRefresh extends Command
{
    protected $signature = 'ebq:research-cluster-refresh
                            {--days=7 : Cluster keywords with a SERP snapshot in the last N days}
                            {--batch=200 : Keywords per ClusterKeywordsJob}
                            {--dry-run : Print the plan without dispatching}';

    protected $description = 'Recluster recently-snapshotted keywords (SERP-overlap). Runs weekly.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $batch = max(1, (int) $this->option('batch'));
        $dryRun = (bool) $this->option('dry-run');

        $since = Carbon::now()->subDays($days);

        $keywordIds = SerpSnapshot::query()
            ->where('fetched_at', '>=', $since)
            ->pluck('keyword_id')
            ->unique()
            ->all();

        if ($keywordIds === []) {
            $this->info('No keywords with recent snapshots — nothing to cluster.');

            return self::SUCCESS;
        }

        $countriesById = Keyword::query()
            ->whereIn('id', $keywordIds)
            ->pluck('country', 'id')
            ->all();

        $byCountry = [];
        foreach ($countriesById as $id => $country) {
            $byCountry[$country][] = (int) $id;
        }

        $totalBatches = 0;
        foreach ($byCountry as $country => $ids) {
            foreach (array_chunk($ids, $batch) as $chunk) {
                $totalBatches++;
                if (! $dryRun) {
                    ClusterKeywordsJob::dispatch($chunk);
                }
            }
            $this->line(sprintf('  · country=%s — %d keyword(s)', $country, count($ids)));
        }

        if ($dryRun) {
            $this->comment("Dry run: would dispatch {$totalBatches} ClusterKeywordsJob(s).");
        } else {
            $this->info("Dispatched {$totalBatches} ClusterKeywordsJob(s).");
        }

        return self::SUCCESS;
    }
}
