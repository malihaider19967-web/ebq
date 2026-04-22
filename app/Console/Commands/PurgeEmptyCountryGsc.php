<?php

namespace App\Console\Commands;

use App\Models\SearchConsoleData;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Clean up the legacy `country = ''` rows that predate the GSC per-country
 * sync. After `ebq:resync-gsc --days=30` repopulates the last 30 days with
 * real country data, any remaining empty-country rows are strictly historical
 * and never queried (every country-aware report filters country != '').
 *
 * Safe by default: only deletes rows older than the default 30-day sync
 * window, so active data isn't touched. Dry-run mode prints counts without
 * deleting.
 *
 * Usage:
 *   php artisan ebq:purge-empty-country-gsc --dry-run
 *   php artisan ebq:purge-empty-country-gsc --older-than=30 --website=42
 */
class PurgeEmptyCountryGsc extends Command
{
    protected $signature = 'ebq:purge-empty-country-gsc
                            {--older-than=30 : Only delete rows older than N days}
                            {--website= : Limit to a single website ID}
                            {--dry-run : Print counts without deleting}';

    protected $description = "Delete legacy country='' GSC rows older than the resync window.";

    public function handle(): int
    {
        $olderThan = max(1, (int) $this->option('older-than'));
        $websiteId = $this->option('website');
        $cutoff = Carbon::today()->subDays($olderThan)->toDateString();

        $query = SearchConsoleData::query()
            ->where('country', '')
            ->whereDate('date', '<', $cutoff);

        if ($websiteId !== null && $websiteId !== '') {
            $query->where('website_id', (int) $websiteId);
        }

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('Nothing to purge.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info(sprintf('Would delete %s empty-country rows older than %s%s.',
                number_format($count),
                $cutoff,
                $websiteId ? " for website {$websiteId}" : ''
            ));

            return self::SUCCESS;
        }

        $deleted = $query->delete();
        $this->info(sprintf('Deleted %s empty-country rows older than %s%s.',
            number_format((int) $deleted),
            $cutoff,
            $websiteId ? " for website {$websiteId}" : ''
        ));

        return self::SUCCESS;
    }
}
