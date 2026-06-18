<?php

namespace App\Jobs\Fleet;

use App\Models\DbNode;
use App\Services\Fleet\DbFleetService;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Run the schema migrations against one node over its network connection.
 * FLEET queue (web box, root) — migrate of the full set can exceed the request
 * timeout, so it never runs in the admin request.
 */
class MigrateDbNodeJob implements ShouldQueue
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
            $fleet->migrateNode($node);
        }
    }
}
