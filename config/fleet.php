<?php

return [
    /*
    | Identifies THIS physical worker box for per-box queue metrics
    | ({@see App\Support\FleetMetrics}). Injected per box into its .env at
    | bootstrap ({@see App\Services\Fleet\WorkerFleetService::bootstrap}); the
    | pinned worker has it set in .env.worker. NULL on the web box — which is why
    | the web box does not record per-box counters (it isn't a crawl worker).
    */
    'node_id' => env('FLEET_NODE_ID'),
];
