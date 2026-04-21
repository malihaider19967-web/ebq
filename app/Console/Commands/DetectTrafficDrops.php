<?php

namespace App\Console\Commands;

use App\Jobs\DetectTrafficDrops as DetectTrafficDropsJob;
use App\Models\Website;
use Illuminate\Console\Command;

class DetectTrafficDrops extends Command
{
    protected $signature = 'ebq:detect-traffic-drops';
    protected $description = 'Dispatch anomaly-detection jobs for every website';

    public function handle(): int
    {
        Website::query()->select('id')->chunkById(100, function ($websites): void {
            foreach ($websites as $website) {
                DetectTrafficDropsJob::dispatch($website->id);
            }
        });

        $this->info('EBQ traffic-drop detection dispatched.');

        return self::SUCCESS;
    }
}
