<?php

namespace App\Jobs\Research;

use App\Models\Website;
use App\Services\Research\Niche\NicheClassificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ClassifyWebsiteNichesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(
        public int $websiteId,
        public string $source = 'auto',
    ) {}

    public function handle(NicheClassificationService $service): void
    {
        $website = Website::query()->find($this->websiteId);
        if ($website === null) {
            return;
        }

        $assignments = $service->classify($website);
        if ($assignments->isEmpty()) {
            return;
        }

        $service->persist($website, $assignments, $this->source);
    }
}
