<?php

namespace App\Services\Fleet;

use App\Models\WorkerNode;
use App\Support\AutoscalerConfig;
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
        $excludes = implode(' ', array_map(fn ($e) => "--exclude='{$e}'", self::RSYNC_EXCLUDES));
        $sshKey = '/root/.ssh/id_ed25519_worker';
        $ssh = "ssh -i {$sshKey} -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=15";

        // 1) push code (NO --delete — that wipes the worker-only compose/Dockerfile)
        $r1 = $this->run("rsync -az {$excludes} -e \"{$ssh}\" /var/www/ebq/ root@{$ip}:/var/www/ebq/");
        // 2) push the worker .env (operator-maintained on the web box: 10.0.0.2 hosts/secrets)
        $r2 = $this->run("rsync -az -e \"{$ssh}\" /var/www/ebq/.env.worker root@{$ip}:/var/www/ebq/.env");
        // 3) install the CRAWL-ONLY compose (overrides whatever the snapshot shipped —
        //    e.g. a snapshot of the pinned box has finalize+sync workers we must NOT run
        //    on an ephemeral box, since a drain could then kill a finalize).
        $r3 = $this->run("{$ssh} root@{$ip} 'cp /var/www/ebq/docker-compose.ephemeral.yml /var/www/ebq/docker-compose.worker.yml'");
        // 4) clear cached config + start the crawl workers. --remove-orphans tears down
        //    any finalize/sync containers a snapshot of the pinned box auto-started on boot.
        $r4 = $this->run("{$ssh} root@{$ip} 'rm -f /var/www/ebq/bootstrap/cache/*.php; docker compose -f /var/www/ebq/docker-compose.worker.yml up -d --remove-orphans'");

        $ok = $r1 && $r2 && $r3 && $r4;
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
            $sshKey = '/root/.ssh/id_ed25519_worker';
            $this->run("ssh -i {$sshKey} -o BatchMode=yes -o ConnectTimeout=15 root@{$node->private_ip} "
                ."'docker compose -f /var/www/ebq/docker-compose.worker.yml stop'");
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
