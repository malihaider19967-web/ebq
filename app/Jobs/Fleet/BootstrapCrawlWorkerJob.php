<?php

namespace App\Jobs\Fleet;

use App\Models\WorkerNode;
use App\Services\Fleet\WorkerFleetService;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * (Re)bootstrap an EXISTING crawl-worker box — push code + start the Docker/Horizon
 * worker. Runs on the FLEET queue (web box, ROOT) because bootstrap SSHes/rsyncs with
 * /root/.ssh/id_ed25519_worker, which is root-only. The autoscaler (FleetAutoscale runs
 * as www-data via the scheduler and CANNOT read that key) dispatches this instead of
 * calling bootstrap() itself — otherwise SSH fails and the box stays stuck on snapshot
 * code. {@see ProvisionCrawlWorkerJob} is the new-box equivalent.
 */
class BootstrapCrawlWorkerJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public string $nodeId)
    {
        $this->onQueue(Queues::FLEET);
    }

    public function handle(WorkerFleetService $fleet): void
    {
        $node = WorkerNode::find($this->nodeId);
        if ($node && ! $node->is_pinned) {
            $fleet->bootstrap($node);
        }
    }
}
