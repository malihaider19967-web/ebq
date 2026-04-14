<?php

namespace App\Console\Commands;

use App\Jobs\SyncAnalyticsData;
use App\Jobs\SyncSearchConsoleData;
use App\Models\Website;
use Illuminate\Console\Command;

class ImportHistoricalData extends Command
{
    protected $signature = 'growthhub:import-historical
                            {--days=480 : Number of days of history to import (GSC max ~16 months)}
                            {--website= : Import only a specific website ID}';

    protected $description = 'Import full historical data from GA4 and Search Console';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $websiteId = $this->option('website');

        $query = Website::query()->select('id', 'domain');

        if ($websiteId) {
            $query->where('id', $websiteId);
        }

        $websites = $query->get();

        if ($websites->isEmpty()) {
            $this->error('No websites found.');
            return self::FAILURE;
        }

        $this->info("Importing {$days} days of data for {$websites->count()} website(s)...");
        $this->newLine();

        foreach ($websites as $website) {
            $this->line("  Dispatching jobs for <comment>{$website->domain}</comment> (ID: {$website->id})");

            SyncAnalyticsData::dispatch($website->id, $days);
            SyncSearchConsoleData::dispatch($website->id, $days);
        }

        $this->newLine();
        $this->info('Jobs dispatched. Make sure your queue worker is running:');
        $this->line('  <comment>php artisan queue:work --timeout=900</comment>');

        return self::SUCCESS;
    }
}
