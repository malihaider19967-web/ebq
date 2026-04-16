<?php

namespace App\Console\Commands;

use App\Jobs\SyncAnalyticsData;
use App\Jobs\SyncPageIndexingStatus;
use App\Jobs\SyncSearchConsoleData;
use App\Models\Website;
use Illuminate\Console\Command;

class SyncDailyData extends Command
{
    protected $signature = 'ebq:sync-daily-data';
    protected $description = 'Refresh GA4 and GSC data for all websites';

    public function handle(): int
    {
        Website::query()->select('id')->chunkById(100, function ($websites) {
            foreach ($websites as $website) {
                SyncAnalyticsData::dispatch($website->id);
                SyncSearchConsoleData::dispatch($website->id);
                SyncPageIndexingStatus::dispatch($website->id);
            }
        });

        $this->info('EBQ daily sync jobs dispatched.');
        return self::SUCCESS;
    }
}
