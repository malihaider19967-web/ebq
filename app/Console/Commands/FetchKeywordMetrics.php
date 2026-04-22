<?php

namespace App\Console\Commands;

use App\Jobs\FetchKeywordMetricsJob;
use App\Models\KeywordMetric;
use App\Models\SearchConsoleData;
use App\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Scan search_console_data for queries above the impression threshold, drop
 * any that already have a fresh keyword_metrics row, and dispatch
 * FetchKeywordMetricsJob for the rest.
 *
 * Follows the same shape as ebq:resync-gsc / ebq:purge-sync-data:
 *   - optional --website to restrict scope
 *   - --dry-run prints the plan without dispatching
 *   - --force ignores freshness (refetch everything)
 */
class FetchKeywordMetrics extends Command
{
    protected $signature = 'ebq:fetch-keyword-metrics
                            {--website= : Limit discovery to one website ID}
                            {--country=global : Country code for the fetch batch (global, us, uk, …)}
                            {--min-impressions=100 : GSC impression threshold}
                            {--days=28 : Lookback window in days}
                            {--limit=500 : Max number of keywords to queue this run}
                            {--force : Refetch even if the cached row is still fresh}
                            {--dry-run : Print the candidate keyword list without dispatching}';

    protected $description = 'Queue Keywords Everywhere lookups for GSC queries above the impression threshold.';

    public function handle(): int
    {
        $websiteOption = $this->option('website');
        $websiteId = null;
        if ($websiteOption !== null && $websiteOption !== '') {
            $websiteId = (int) $websiteOption;
            if ($websiteId <= 0 || ! Website::query()->whereKey($websiteId)->exists()) {
                $this->error("Website #{$websiteOption} not found.");

                return self::FAILURE;
            }
        }

        $country = strtolower(trim((string) $this->option('country'))) ?: 'global';
        $minImpr = max(1, (int) $this->option('min-impressions'));
        $days = max(1, (int) $this->option('days'));
        $limit = max(1, (int) $this->option('limit'));
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $since = Carbon::today()->subDays($days)->toDateString();

        $query = SearchConsoleData::query()
            ->selectRaw('query, SUM(impressions) as total_impressions')
            ->whereDate('date', '>=', $since)
            ->where('query', '!=', '')
            ->groupBy('query')
            ->havingRaw('SUM(impressions) >= ?', [$minImpr])
            ->orderByDesc('total_impressions')
            ->limit($limit);

        if ($websiteId) {
            $query->where('website_id', $websiteId);
        }

        $candidates = $query->pluck('query')->all();
        if ($candidates === []) {
            $this->info('No queries cross the threshold. Nothing to queue.');

            return self::SUCCESS;
        }

        $skipFresh = ! $force;
        if ($skipFresh) {
            $freshHashes = KeywordMetric::query()
                ->whereIn('keyword_hash', array_map(fn ($k) => KeywordMetric::hashKeyword((string) $k), $candidates))
                ->where('country', $country)
                ->where('expires_at', '>', now())
                ->pluck('keyword_hash')
                ->all();

            if ($freshHashes !== []) {
                $flip = array_flip($freshHashes);
                $candidates = array_values(array_filter(
                    $candidates,
                    fn ($kw) => ! isset($flip[KeywordMetric::hashKeyword((string) $kw)])
                ));
            }
        }

        if ($candidates === []) {
            $this->info('All candidates are already fresh. Use --force to refetch.');

            return self::SUCCESS;
        }

        $this->line(sprintf(
            "<fg=yellow>Plan:</> queue %s keyword(s) for country=%s%s (window: last %d days, min-impressions: %d).",
            number_format(count($candidates)),
            $country,
            $websiteId ? ", website #{$websiteId}" : '',
            $days,
            $minImpr
        ));

        if ($dryRun) {
            foreach (array_slice($candidates, 0, 25) as $kw) {
                $this->line('  · '.$kw);
            }
            if (count($candidates) > 25) {
                $this->line('  … and '.number_format(count($candidates) - 25).' more.');
            }
            $this->comment('Dry run — nothing dispatched.');

            return self::SUCCESS;
        }

        foreach (array_chunk($candidates, 100) as $chunk) {
            FetchKeywordMetricsJob::dispatch(array_values($chunk), $country);
        }

        $this->info(sprintf(
            'Queued %d keyword(s) across %d batch(es).',
            count($candidates),
            (int) ceil(count($candidates) / 100)
        ));

        return self::SUCCESS;
    }
}
