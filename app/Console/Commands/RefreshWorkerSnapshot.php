<?php

namespace App\Console\Commands;

use App\Support\AutoscalerConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Keep the crawl-worker snapshot in sync with the deployed code. When the web box's
 * git HEAD differs from the HEAD the current snapshot was built from, rebuild the
 * snapshot ({@see scripts/worker/build-worker-snapshot.sh}) and point the autoscaler
 * at the new image. Scheduled in the BACKGROUND so it never blocks provisioning —
 * meanwhile the autoscaler uses the current snapshot and bootstrap rsyncs current
 * code, so a new box is never actually stale.
 *
 * Kill-switch: AutoscalerConfig `auto_snapshot` (turn OFF at /admin/fleet while working
 * directly on the server, so it doesn't rebuild repeatedly). `--force` ignores both the
 * toggle's HEAD check and rebuilds now.
 */
class RefreshWorkerSnapshot extends Command
{
    protected $signature = 'ebq:refresh-worker-snapshot {--force : rebuild even if HEAD matches}';

    protected $description = 'Rebuild the crawl-worker snapshot when git HEAD drifts; point the autoscaler at it.';

    private const LOCK = 'fleet:worker-snapshot:building';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        if (! $force && ! AutoscalerConfig::autoSnapshot()) {
            $this->info('auto-snapshot disabled — skipping');

            return self::SUCCESS;
        }

        $head = trim((string) Process::run('git -C /var/www/ebq rev-parse HEAD')->output());
        if ($head === '') {
            $this->warn('could not read git HEAD — skipping');

            return self::SUCCESS;
        }
        if (! $force && $head === AutoscalerConfig::snapshotHead()) {
            $this->info("snapshot up to date (HEAD {$head})");

            return self::SUCCESS;
        }

        // One build at a time — held for the whole ~15-min build window.
        $lock = Cache::lock(self::LOCK, 2400);
        if (! $lock->get()) {
            $this->info('a snapshot build is already running — skipping');

            return self::SUCCESS;
        }

        try {
            $this->info("HEAD drift ({$head}) — building a fresh worker snapshot…");
            Log::info('RefreshWorkerSnapshot: building', ['head' => $head]);

            $result = Process::timeout(1800)->run('bash /var/www/ebq/scripts/worker/build-worker-snapshot.sh');
            if (! $result->successful()) {
                Log::error('RefreshWorkerSnapshot: build FAILED', ['tail' => mb_substr($result->output().$result->errorOutput(), -800)]);
                $this->error('snapshot build failed (see laravel.log)');

                return self::FAILURE;
            }

            $image = trim((string) @file_get_contents('/tmp/ebq_worker_snapshot_id'));
            if ($image === '') {
                $this->error('build finished but no image id was written');

                return self::FAILURE;
            }

            // Point the autoscaler at the fresh image + record the HEAD it matches.
            AutoscalerConfig::update(['snapshot_id' => $image, 'snapshot_head' => $head]);
            Cache::forget('fleet:snapshots:worker'); // refresh the admin dropdown
            $this->info("snapshot refreshed → image {$image} (HEAD {$head}); autoscaler now uses it");
            Log::info('RefreshWorkerSnapshot: refreshed', ['image' => $image, 'head' => $head]);

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
