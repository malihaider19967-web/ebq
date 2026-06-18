<?php

namespace App\Jobs\Fleet;

use App\Support\AutoscalerConfig;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Rebuild the crawl-worker snapshot (scripts/worker/build-worker-snapshot.sh) and point
 * the autoscaler at the new image. Runs on the ROOT fleet queue because the build SSHes
 * to a temp box with /root/.ssh/id_ed25519_worker (root-only) — the www-data scheduler /
 * autoscaler that TRIGGERS this can't read that key (the same reason the autoscaler
 * dispatches provision/bootstrap here too). Self-locked. On the single-process fleet
 * queue this blocks other fleet jobs for the ~15-min build, which is acceptable:
 * provisioning is deferred while a rebuild is pending anyway (FleetAutoscale::snapshotReady),
 * and rebuilds are rare (only on a real commit/HEAD change).
 */
class RefreshWorkerSnapshotJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    private const LOCK = 'fleet:worker-snapshot:building';

    /** Set while a build runs — dispatchers check this to avoid piling up jobs. */
    public const IN_PROGRESS = 'fleet:worker-snapshot:in-progress';

    public function __construct()
    {
        $this->onQueue(Queues::FLEET);
    }

    public function handle(): void
    {
        $lock = Cache::lock(self::LOCK, 2400);
        if (! $lock->get()) {
            return; // another build is already running
        }
        try {
            Cache::put(self::IN_PROGRESS, 1, 2400);
            $head = trim((string) Process::run('git -C /var/www/ebq rev-parse HEAD')->output());
            Log::info('RefreshWorkerSnapshotJob: building', ['head' => $head]);

            $result = Process::timeout(1700)->run('bash /var/www/ebq/scripts/worker/build-worker-snapshot.sh');
            if (! $result->successful()) {
                Log::error('RefreshWorkerSnapshotJob: build FAILED', ['tail' => mb_substr($result->output().$result->errorOutput(), -800)]);

                return;
            }

            $image = trim((string) @file_get_contents('/tmp/ebq_worker_snapshot_id'));
            if ($image === '') {
                Log::error('RefreshWorkerSnapshotJob: build finished but no image id written');

                return;
            }

            AutoscalerConfig::update(['snapshot_id' => $image, 'snapshot_head' => $head]);
            Cache::forget('fleet:snapshots:worker'); // refresh the admin dropdown
            Log::info('RefreshWorkerSnapshotJob: refreshed', ['image' => $image, 'head' => $head]);
        } finally {
            Cache::forget(self::IN_PROGRESS);
            $lock->release();
        }
    }
}
