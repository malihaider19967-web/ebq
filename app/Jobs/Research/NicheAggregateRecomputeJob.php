<?php

namespace App\Jobs\Research;

use App\Services\Research\NicheAggregateRecomputeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NicheAggregateRecomputeJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;
    public int $tries = 1;

    public function handle(NicheAggregateRecomputeService $service): void
    {
        $service->recompute();
    }
}
