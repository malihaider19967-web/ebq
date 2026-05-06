<?php

namespace App\Console\Commands\Research;

use App\Jobs\Research\DiscoverEmergingNichesJob;
use Illuminate\Console\Command;

class DiscoverEmergingNiches extends Command
{
    protected $signature = 'ebq:discover-emerging-niches';

    protected $description = 'Stub for Phase-2; persistence in Phase-3. Dispatches DiscoverEmergingNichesJob.';

    public function handle(): int
    {
        DiscoverEmergingNichesJob::dispatch();
        $this->info('Dispatched DiscoverEmergingNichesJob (Phase-2 stub).');

        return self::SUCCESS;
    }
}
