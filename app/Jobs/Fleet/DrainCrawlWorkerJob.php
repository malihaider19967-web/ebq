<?php

namespace App\Jobs\Fleet;

use App\Models\WorkerNode;
use App\Services\Fleet\WorkerFleetService;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Gracefully drain a crawl-worker box (stop its Docker queue workers over SSH).
 * FLEET queue (web box, root) — the graceful stop SSHes to the box with root's
 * key, so it can't run as www-data in the request. The status flip to draining
 * is immediate; the SSH stop completes in the background.
 */
class DrainCrawlWorkerJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public string $nodeId)
    {
        $this->onQueue(Queues::FLEET);
    }

    public function handle(WorkerFleetService $fleet): void
    {
        $node = WorkerNode::find($this->nodeId);
        if ($node !== null) {
            $fleet->drain($node);
        }
    }
}
