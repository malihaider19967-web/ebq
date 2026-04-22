<?php

namespace App\Console\Commands;

use App\Jobs\SyncSearchConsoleData;
use App\Models\Website;
use Illuminate\Console\Command;

/**
 * One-shot backfill after the GSC sync starts requesting country + device
 * dimensions. Dispatches SyncSearchConsoleData with a configurable lookback
 * window so existing country='' rows get replaced with properly-dimensioned
 * rows during the next upsert.
 *
 * Run once after deploy:
 *   php artisan ebq:resync-gsc --days=30
 *
 * Single-site variant:
 *   php artisan ebq:resync-gsc --days=30 --website=42
 */
class ResyncGsc extends Command
{
    protected $signature = 'ebq:resync-gsc
                            {--days=30 : Days of GSC history to re-sync}
                            {--website= : Resync only the given website ID}';

    protected $description = 'Queue SyncSearchConsoleData with an extended lookback to backfill the country + device dimensions.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $websiteId = $this->option('website');

        $query = Website::query()->select('id', 'domain');
        if ($websiteId !== null && $websiteId !== '') {
            $query->whereKey((int) $websiteId);
        }

        $count = 0;
        $query->chunkById(100, function ($websites) use ($days, &$count): void {
            foreach ($websites as $website) {
                SyncSearchConsoleData::dispatch($website->id, $days);
                $this->line(sprintf('queued → %s (id=%d, %d days)', (string) $website->domain, (int) $website->id, $days));
                $count++;
            }
        });

        if ($count === 0) {
            $this->warn('No matching websites found.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Queued %d SyncSearchConsoleData job(s) with a %d-day window.', $count, $days));

        return self::SUCCESS;
    }
}
