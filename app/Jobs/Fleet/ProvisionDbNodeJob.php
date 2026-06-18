<?php

namespace App\Jobs\Fleet;

use App\Models\DbNode;
use App\Services\Fleet\DbFleetService;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Provision a Hetzner DB node then bootstrap it (MariaDB config + grants + schema).
 * Runs on the FLEET queue (pinned web box, as root) so it can SSH to the new box
 * with root's key and isn't bound by the request timeout. The admin UI just
 * dispatches this and polls db_nodes.status (provisioning → active|failed).
 */
class ProvisionDbNodeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public string $role = DbNode::ROLE_TENANT)
    {
        $this->onQueue(Queues::FLEET);
    }

    public function handle(DbFleetService $fleet): void
    {
        $node = $fleet->provision($this->role);
        if ($node->status === DbNode::STATUS_FAILED) {
            Log::error('ProvisionDbNodeJob: provision failed', ['node' => $node->id, 'error' => $node->last_error]);

            return;
        }
        $fleet->bootstrap($node);
    }
}
