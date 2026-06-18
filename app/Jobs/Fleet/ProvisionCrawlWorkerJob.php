<?php

namespace App\Jobs\Fleet;

use App\Services\Fleet\WorkerFleetService;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Provision a Hetzner crawl-worker box then bootstrap it (push code + start the
 * Docker queue workers). FLEET queue (web box, root) — it rsyncs code + SSHes to
 * the new box with root's key, and the full bring-up exceeds the request timeout.
 * The /admin/fleet page (wire:poll) shows the new node provisioning → active.
 */
class ProvisionCrawlWorkerJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct()
    {
        $this->onQueue(Queues::FLEET);
    }

    public function handle(WorkerFleetService $fleet): void
    {
        $node = $fleet->provision();
        $fleet->bootstrap($node);
    }
}
