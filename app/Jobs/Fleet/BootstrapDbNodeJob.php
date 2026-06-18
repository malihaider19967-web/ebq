<?php

namespace App\Jobs\Fleet;

use App\Models\DbNode;
use App\Services\Fleet\DbFleetService;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Re-run bootstrap (MariaDB config + grants + schema migrate) on an existing node.
 * FLEET queue (web box, root) — see {@see ProvisionDbNodeJob}.
 */
class BootstrapDbNodeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public string $nodeId)
    {
        $this->onQueue(Queues::FLEET);
    }

    public function handle(DbFleetService $fleet): void
    {
        $node = DbNode::find($this->nodeId);
        if ($node !== null) {
            $fleet->bootstrap($node);
        }
    }
}
