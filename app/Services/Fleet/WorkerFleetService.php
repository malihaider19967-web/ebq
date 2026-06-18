<?php

namespace App\Services\Fleet;

use App\Models\WorkerNode;
use App\Support\AutoscalerConfig;
use App\Support\FleetMetrics;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Orchestration layer over the worker fleet — the WorkerNode equivalent of
 * {@see \App\Services\KeywordFinder\KeywordFinderPool}. Provisions/drains/destroys
 * boxes via {@see HetznerClient}, and owns the pure scaling math the autoscaler
 * uses. Stateless; safe to resolve per call.
 *
 * Bootstrap model = web-box PUSH (reuses today's deploy): a new box boots from a
 * snapshot (Docker + the ebq-worker image + docker-compose.worker.yml + the web
 * box's PUBLIC deploy key authorised), then the web box rsyncs code + a worker
 * .env to it and runs `docker compose up -d` over SSH with id_ed25519_worker.
 * No private key / API token ever lands on an ephemeral box.
 */
class WorkerFleetService
{
    /** rsync excludes — same set as the deploy doc; NEVER use --delete on the worker. */
    private const RSYNC_EXCLUDES = [
        '.env', '.git/', 'storage/', 'node_modules/', 'vendor/', 'public/build/',
        'bootstrap/cache/', 'ebq-wordpress-plugin/', 'ebq-seo-wp/',
    ];

    public function __construct(private HetznerClient $hetzner) {}

    // ---- scaling math (pure, unit-testable) ----

    /** Desired billable box count for a given crawl-queue backlog. */
    public static function desiredFromBacklog(int $backlog): int
    {
        $min = AutoscalerConfig::minBoxes();
        $max = AutoscalerConfig::maxBoxes();
        $per = max(1, AutoscalerConfig::targetBacklogPerBox());
        $desired = (int) ceil(max(0, $backlog) / $per);

        return max($min, min($max, $desired));
    }

    public function billableCount(): int
    {
        return WorkerNode::billable()->count();
    }

    public function activeCount(): int
    {
        return WorkerNode::active()->count();
    }

    // ---- lifecycle ----

    /** Create a Hetzner server + tracking row (status=provisioning). Does NOT bootstrap. */
    public function provision(): WorkerNode
    {
        $node = WorkerNode::create([
            'name' => 'ebq-crawl-worker-pending',
            'status' => WorkerNode::STATUS_PROVISIONING,
            'server_type' => AutoscalerConfig::serverType(),
            'containers' => 5,
            'provisioned_at' => now(),
        ]);
        $node->update(['name' => "ebq-crawl-worker-{$node->id}"]);

        // Hetzner requires a valid `image` — a snapshot id (int) or a system image
        // name. Fail clearly if none is configured rather than surfacing the raw
        // "invalid input field 'image'" API error.
        $image = AutoscalerConfig::snapshotId();
        if (! $image) {
            $node->update([
                'status' => WorkerNode::STATUS_FAILED,
                'last_error' => 'No worker image configured. Build a worker snapshot, then set HCLOUD_WORKER_IMAGE (or snapshot_id at /admin/fleet) to its id.',
            ]);

            return $node->refresh();
        }

        $result = $this->hetzner->createServer($node->name, [
            'server_type' => AutoscalerConfig::serverType(),
            'image' => $image,
        ]);

        if (! $result['ok']) {
            $node->update(['status' => WorkerNode::STATUS_FAILED, 'last_error' => $result['error']]);
            Log::error('WorkerFleet: provision failed', ['node' => $node->id, 'error' => $result['error']]);

            return $node->refresh();
        }

        $node->update([
            'hetzner_server_id' => $result['server_id'],
            'private_ip' => $result['private_ip'],
            'public_ip' => $result['public_ip'],
            'labels' => ['role' => 'ebq-crawl-worker'],
        ]);
        Log::info('WorkerFleet: provisioned', ['node' => $node->id, 'hetzner_server_id' => $result['server_id'], 'type' => $node->server_type]);

        return $node->refresh();
    }

    /**
     * Push code + worker .env and start the containers over SSH, then mark active.
     * Idempotent: safe to re-run. Returns true on success.
     */
    public function bootstrap(WorkerNode $node): bool
    {
        if (! $node->private_ip) {
            return false;
        }
        $ip = $node->private_ip;
        $ssh = $this->sshCmd();

        // A fresh box needs ~30-60s to boot before it accepts SSH — wait for it.
        if (! $this->waitForSsh($ip, $ssh)) {
            $node->update(['last_error' => 'box not SSH-reachable within timeout']);
            Log::warning('WorkerFleet: bootstrap aborted — box not reachable', ['node' => $node->id, 'ip' => $ip]);

            return false;
        }

        // 0) STOP whatever the snapshot auto-started (old code, restart:always) the
        //    instant we can reach the box — so stale workers stop pulling jobs during
        //    the (minutes-long) rsync, and a later failed step never leaves OLD code
        //    running. The fresh Horizon container is started in step 4. Best-effort.
        $this->remote($ssh, $ip, 'docker compose -f docker-compose.worker.yml down --remove-orphans 2>/dev/null || true');

        // 1) push code (NO --delete — that wipes the worker-only compose/Dockerfile)
        $excludes = implode(' ', array_map(fn ($e) => "--exclude='{$e}'", self::RSYNC_EXCLUDES));
        $r1 = $this->run("rsync -az {$excludes} -e \"{$ssh}\" /var/www/ebq/ root@{$ip}:/var/www/ebq/");
        // 1b) push vendor/ separately, WITH --delete scoped to vendor/ only. vendor is
        //     excluded from the main push (step 1), so without this a Composer change would
        //     never reach a worker — its vendor would stay frozen at whatever the snapshot
        //     baked. Pushing it here makes "composer change → redeploy" enough (no snapshot
        //     rebuild). Web box (PHP 8.3) is the source of truth, binary-compatible with the
        //     ebq-worker:8.3 container.
        $rv = $this->run("rsync -az --delete -e \"{$ssh}\" /var/www/ebq/vendor/ root@{$ip}:/var/www/ebq/vendor/");
        // 2) push the worker .env (operator-maintained on the web box: 10.0.0.2 hosts/secrets)
        $r2 = $this->run("rsync -az -e \"{$ssh}\" /var/www/ebq/.env.worker root@{$ip}:/var/www/ebq/.env");
        // 2b) stamp PER-BOX identity into the freshly-pushed .env (the shared .env.worker
        //     can't carry these). APP_ENV=worker-ephemeral selects the crawl-ONLY Horizon
        //     supervisor (config/horizon.php); FLEET_NODE_ID drives this box's per-box queue
        //     counters (App\Support\FleetMetrics); HORIZON_NAME labels its master in the
        //     dashboard. sed-replace-or-append so it's idempotent across re-bootstraps.
        $envFile = '/var/www/ebq/.env';
        $stampCmds = [];
        foreach (['APP_ENV' => 'worker-ephemeral', 'FLEET_NODE_ID' => $node->id, 'HORIZON_NAME' => $node->id] as $k => $v) {
            // Replace the line if present, else append. Single-quoted patterns + sed
            // 's|...|...|' so nothing nests inside the outer double-quoted ssh command
            // (the earlier double-quote-in-double-quote version silently no-op'd).
            $stampCmds[] = "grep -q '^{$k}=' {$envFile} && sed -i 's|^{$k}=.*|{$k}={$v}|' {$envFile} || echo '{$k}={$v}' >> {$envFile}";
        }
        $r2b = $this->run("{$ssh} root@{$ip} \"".implode(' ; ', $stampCmds)."\"");
        // 3) install the CRAWL-ONLY compose (overrides whatever the snapshot shipped —
        //    e.g. a snapshot of the pinned box has finalize+sync workers we must NOT run
        //    on an ephemeral box, since a drain could then kill a finalize). The compose
        //    now runs `horizon` (crawl-only via the worker-ephemeral env), not raw queue:work.
        $r3 = $this->remote($ssh, $ip, 'cp docker-compose.ephemeral.yml docker-compose.worker.yml');
        // 4) clear cached config + (re)start the worker. --force-recreate is ESSENTIAL:
        //    the box boots from a snapshot whose containers auto-start (restart:always)
        //    on OLD code, and `up -d` alone will NOT recreate a container whose service
        //    config is unchanged — so a code-only deploy would leave the worker running
        //    STALE code in memory (the volume-mounted files update, the long-running PHP
        //    process does not). --force-recreate rebuilds the container every bootstrap so
        //    the Horizon master always reloads the freshly-rsynced code. --remove-orphans
        //    tears down any finalize/sync containers the snapshot auto-started on boot.
        $r4 = $this->remote($ssh, $ip, 'rm -f bootstrap/cache/*.php; docker compose -f docker-compose.worker.yml up -d --force-recreate --remove-orphans');

        $ok = $r1 && $rv && $r2 && $r2b && $r3 && $r4;
        if ($ok) {
            // Fresh start → no in-flight jobs; clear any crash-drift on the gauge.
            FleetMetrics::resetRunning($node->id);
        }
        $node->update($ok
            ? ['status' => WorkerNode::STATUS_ACTIVE, 'is_healthy' => true, 'last_seen_at' => now(), 'last_error' => null]
            : ['last_error' => 'bootstrap rsync/ssh failed']);
        Log::info('WorkerFleet: bootstrap '.($ok ? 'ok' : 'FAILED'), ['node' => $node->id, 'ip' => $ip]);

        return $ok;
    }

    /** Begin a graceful drain: stop the containers (SIGTERM, stop_grace_period lets the current job finish). */
    public function drain(WorkerNode $node): void
    {
        $node->update(['status' => WorkerNode::STATUS_DRAINING, 'drain_started_at' => now()]);
        if ($node->private_ip) {
            $this->remote($this->sshCmd(15), $node->private_ip, 'docker compose -f docker-compose.worker.yml stop');
        }
        Log::info('WorkerFleet: draining', ['node' => $node->id]);
    }

    /** Delete the Hetzner server and remove the row. */
    public function destroy(WorkerNode $node): void
    {
        if ($node->is_pinned) {
            return; // never destroy the permanent box
        }
        if ($node->hetzner_server_id) {
            $this->hetzner->deleteServer($node->hetzner_server_id);
        }
        FleetMetrics::clear($node->id);
        Log::info('WorkerFleet: destroyed', ['node' => $node->id, 'hetzner_server_id' => $node->hetzner_server_id]);
        $node->delete();
    }

    /**
     * Reconcile DB rows against real Hetzner state: mark vanished servers failed,
     * and surface orphan servers (label match, no billable row) for cleanup.
     *
     * @return array{orphans:array<int,int>, vanished:int}
     */
    public function reconcile(): array
    {
        $list = $this->hetzner->listByLabel();
        if (! $list['ok']) {
            return ['orphans' => [], 'vanished' => 0];
        }
        $liveIds = array_map(fn ($s) => $s['id'], $list['servers']);
        $trackedIds = WorkerNode::billable()->whereNotNull('hetzner_server_id')->pluck('hetzner_server_id')->all();

        // Rows whose server no longer exists → failed.
        $vanished = WorkerNode::billable()->whereNotNull('hetzner_server_id')
            ->whereNotIn('hetzner_server_id', $liveIds ?: [0])
            ->update(['status' => WorkerNode::STATUS_FAILED, 'last_error' => 'server missing on Hetzner']);

        // Servers that exist on Hetzner but we don't track → orphans (a half-failed
        // provision). Reported, not auto-deleted, so an operator can confirm first.
        $orphans = array_values(array_diff($liveIds, array_map('intval', $trackedIds)));
        if ($orphans !== []) {
            Log::warning('WorkerFleet: orphan Hetzner servers (labelled but untracked)', ['ids' => $orphans]);
        }

        return ['orphans' => $orphans, 'vanished' => (int) $vanished];
    }

    /** Poll until the box accepts SSH (boot + cloud-init), up to ~2.5 min. */
    private function waitForSsh(string $ip, string $ssh): bool
    {
        // Up to ~7.5 min: a snapshot boot + cloud-init occasionally exceeds 5 min
        // (a box that came up at ~6-7 min used to fall through the old 5-min wait and
        // get stuck on snapshot code). The autoscaler also re-bootstraps stuck boxes,
        // so this is belt-and-suspenders.
        for ($i = 0; $i < 90; $i++) {
            if ($this->run("{$ssh} root@{$ip} 'true'")) {
                return true;
            }
            sleep(5);
        }

        return false;
    }

    /**
     * SSH command prefix with the flags EVERY box command needs. Critically
     * StrictHostKeyChecking=no + UserKnownHostsFile=/dev/null: ephemeral boxes RECYCLE
     * private IPs, so a returning IP has a new host key — without these the connection
     * trips "REMOTE HOST IDENTIFICATION HAS CHANGED" and fails. (drain() omitted these
     * once, so drains silently no-op'd on recycled IPs — the box never stopped.) Always
     * build the ssh string here so bootstrap + drain can't diverge again.
     */
    private function sshCmd(int $connectTimeout = 10): string
    {
        return "ssh -i /root/.ssh/id_ed25519_worker -o BatchMode=yes -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout={$connectTimeout}";
    }

    /**
     * Run a remote shell command on the box, ALWAYS from the project dir
     * (`cd /var/www/ebq && …`). This makes the recurring "no such file" path bug
     * structurally impossible: a relative `docker compose -f docker-compose.worker.yml`
     * can never resolve against the SSH login dir (~) instead of the project. Use this
     * for every box command (don't hand-build ssh strings with absolute paths).
     * NOTE: $cmd must not contain single quotes (it's wrapped in them).
     */
    private function remote(string $ssh, string $ip, string $cmd): bool
    {
        return $this->run("{$ssh} root@{$ip} 'cd /var/www/ebq && {$cmd}'");
    }

    private function run(string $cmd): bool
    {
        try {
            return Process::timeout(600)->run($cmd)->successful();
        } catch (\Throwable $e) {
            Log::warning('WorkerFleet: command failed', ['cmd' => $cmd, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
