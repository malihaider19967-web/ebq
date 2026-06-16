<?php

namespace App\Jobs;

use App\Models\CompetitorDiscoveryRun;
use App\Services\Competitive\CompetitorDiscoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs ONE competitor auto-discovery fan-out off the web request. The heavy
 * lifting (SERP sampling + tally) lives in {@see CompetitorDiscoveryService::run}.
 *
 * Unique per run_id so a double-dispatch can't double-bill SERP. tries=1: a
 * partial result is better than re-running the whole (paid) fan-out; on failure
 * we record a terminal status so the UI poller stops.
 */
class RunCompetitorDiscovery implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public int $uniqueFor = 1800;

    /**
     * @param  list<string>  $keywords
     */
    public function __construct(
        public readonly string $runId,
        public readonly array $keywords,
    ) {
        $this->onQueue(\App\Support\Queues::INTERACTIVE);
    }

    public function uniqueId(): string
    {
        return 'competitor-discovery:'.$this->runId;
    }

    public function handle(CompetitorDiscoveryService $service): void
    {
        $service->run($this->runId, $this->keywords);
    }

    public function failed(?\Throwable $e): void
    {
        $run = CompetitorDiscoveryRun::query()->where('run_id', $this->runId)->first();
        if ($run instanceof CompetitorDiscoveryRun && ! $run->isFinished()) {
            $run->markFailed('Competitor discovery timed out. Please try again.');
        }
    }
}
