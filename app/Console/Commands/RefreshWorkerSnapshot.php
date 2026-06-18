<?php

namespace App\Console\Commands;

use App\Jobs\Fleet\RefreshWorkerSnapshotJob;
use App\Support\AutoscalerConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
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
        if (Cache::has(RefreshWorkerSnapshotJob::IN_PROGRESS)) {
            $this->info('a snapshot build is already running — skipping');

            return self::SUCCESS;
        }

        // The build SSHes (root key); this command runs via the scheduler as www-data,
        // which can't. Dispatch to the ROOT fleet queue, where the build actually runs.
        RefreshWorkerSnapshotJob::dispatch();
        $this->info("HEAD drift ({$head}) — dispatched worker-snapshot rebuild to the fleet queue");

        return self::SUCCESS;
    }
}
