<?php

namespace App\Services\Fleet;

use App\Models\DbNode;
use App\Support\DbFleetConfig;
use App\Support\ShardManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Orchestration over the database-node fleet — the DbNode equivalent of
 * {@see WorkerFleetService}. Provisions/bootstraps/destroys MariaDB boxes via
 * {@see HetznerClient}, runs the schema on them from the web box, and supports
 * registering an already-existing node (the pinned primary, or a local box used
 * to validate moves). Stateless; resolve per call.
 *
 * DB nodes are PURE MariaDB (no app code): bootstrap configures the server +
 * opens the firewall, then the web box runs `migrate` over the network against
 * the node's registered connection. Holds no secrets — nodes use the shared app
 * DB credential the web box already knows.
 */
class DbFleetService
{
    public function __construct(private HetznerClient $hetzner) {}

    /** Record the existing primary (Box A) as the pinned node. Idempotent. */
    public function registerPrimary(string $privateIp, string $dbName): DbNode
    {
        return DbNode::updateOrCreate(
            ['is_pinned' => true],
            [
                'name' => 'ebq-db-primary',
                'role' => DbNode::ROLE_PRIMARY,
                'status' => DbNode::STATUS_ACTIVE,
                'private_ip' => $privateIp,
                'db_name' => $dbName,
                'is_healthy' => true,
                'last_seen_at' => now(),
                'provisioned_at' => now(),
            ],
        );
    }

    /** Register an already-running MariaDB box as a node (no Hetzner provision). */
    public function registerExisting(string $name, string $role, string $privateIp, string $dbName, ?int $port = null): DbNode
    {
        $node = DbNode::create([
            'name' => $name,
            'role' => $role,
            'status' => DbNode::STATUS_ACTIVE,
            'private_ip' => $privateIp,
            'db_name' => $dbName,
            'is_healthy' => true,
            'last_seen_at' => now(),
            'provisioned_at' => now(),
            'labels' => $port !== null ? ['port' => $port] : null,
        ]);
        ShardManager::flush();

        return $node;
    }

    /** Create a Hetzner MariaDB server + tracking row (status=provisioning). */
    public function provision(string $role = DbNode::ROLE_TENANT): DbNode
    {
        $node = DbNode::create([
            'name' => 'ebq-db-pending',
            'role' => $role,
            'status' => DbNode::STATUS_PROVISIONING,
            'server_type' => DbFleetConfig::serverType(),
            'db_name' => (string) config('database.connections.global.database', 'ebq'),
            'provisioned_at' => now(),
        ]);
        $node->update(['name' => "ebq-db-{$role}-{$node->id}"]);

        $image = DbFleetConfig::snapshotId();
        if (! $image) {
            $node->update([
                'status' => DbNode::STATUS_FAILED,
                'last_error' => 'No DB snapshot configured. Build a MariaDB snapshot, then set HCLOUD_DB_IMAGE (or snapshot_id at /admin/db-fleet).',
            ]);

            return $node->refresh();
        }

        $result = $this->hetzner->createServer($node->name, [
            'server_type' => DbFleetConfig::serverType(),
            'image' => $image,
            'labels' => ['role' => 'ebq-db-node', 'db-role' => $role],
        ]);
        if (! $result['ok']) {
            $node->update(['status' => DbNode::STATUS_FAILED, 'last_error' => $result['error']]);
            Log::error('DbFleet: provision failed', ['node' => $node->id, 'error' => $result['error']]);

            return $node->refresh();
        }

        $node->update([
            'hetzner_server_id' => $result['server_id'],
            'private_ip' => $result['private_ip'],
            'public_ip' => $result['public_ip'],
        ]);
        ShardManager::flush();
        Log::info('DbFleet: provisioned', ['node' => $node->id, 'server' => $result['server_id']]);

        return $node->refresh();
    }

    /**
     * Configure MariaDB on the box (app db + user + grants + firewall), then run
     * the schema from the web box against the node connection. Idempotent.
     */
    public function bootstrap(DbNode $node): bool
    {
        if (! $node->private_ip || ! $node->db_name) {
            return false;
        }
        $ip = $node->private_ip;
        $ssh = 'ssh -i /root/.ssh/id_ed25519_worker -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10';
        if (! $this->waitForSsh($ip, $ssh)) {
            $node->update(['last_error' => 'box not SSH-reachable within timeout']);

            return false;
        }

        $dbUser = (string) config('database.connections.global.username');
        $dbPass = (string) config('database.connections.global.password');
        $db = $node->db_name;
        $sql = sprintf(
            "CREATE DATABASE IF NOT EXISTS \\`%s\\`; "
            ."CREATE USER IF NOT EXISTS '%s'@'10.0.0.%%' IDENTIFIED BY '%s'; "
            ."GRANT ALL PRIVILEGES ON \\`%s\\`.* TO '%s'@'10.0.0.%%'; FLUSH PRIVILEGES;",
            $db, $dbUser, addslashes($dbPass), $db, $dbUser
        );
        // The snapshot must allow local root (socket) mysql; configure + firewall.
        $r1 = $this->run("{$ssh} root@{$ip} \"mysql -e \\\"{$sql}\\\" && ufw allow from 10.0.0.0/24 to any port 3306 proto tcp || true\"");

        ShardManager::flush();
        $migrated = $this->migrateNode($node);

        $ok = $r1 && $migrated;
        $node->update($ok
            ? ['status' => DbNode::STATUS_ACTIVE, 'is_healthy' => true, 'last_seen_at' => now(), 'last_error' => null]
            : ['last_error' => 'bootstrap (mysql/migrate) failed']);
        Log::info('DbFleet: bootstrap '.($ok ? 'ok' : 'FAILED'), ['node' => $node->id, 'ip' => $ip]);

        return $ok;
    }

    /**
     * Run the schema on a node from the web box over its registered connection.
     * Both tiers run the full migration set (cross-central FKs are dropped, so
     * the tables stand alone); unused central tables are harmless.
     */
    public function migrateNode(DbNode $node): bool
    {
        $conn = $node->connectionName();
        if (! config("database.connections.{$conn}")) {
            (new ShardManager)->register();
        }
        if (! config("database.connections.{$conn}")) {
            $node->update(['last_error' => "connection {$conn} not registered (no private_ip/db_name?)"]);

            return false;
        }
        try {
            Artisan::call('migrate', ['--database' => $conn, '--force' => true]);

            return true;
        } catch (\Throwable $e) {
            $node->update(['last_error' => 'migrate failed: '.$e->getMessage()]);
            Log::error('DbFleet: migrate failed', ['node' => $node->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function drain(DbNode $node): void
    {
        $node->update(['status' => DbNode::STATUS_DRAINING, 'drain_started_at' => now()]);
        ShardManager::flush();
    }

    /** Destroy a node — only if empty + not pinned. Move its tenants/sites off first. */
    public function destroy(DbNode $node): bool
    {
        if ($node->is_pinned) {
            return false;
        }
        if (! $node->isEmpty()) {
            $node->update(['last_error' => 'refusing to destroy a node that still hosts tenants/sites — move them off first']);

            return false;
        }
        if ($node->hetzner_server_id) {
            $this->hetzner->deleteServer($node->hetzner_server_id);
        }
        $node->delete();
        ShardManager::flush();
        Log::info('DbFleet: destroyed', ['node' => $node->id]);

        return true;
    }

    private function waitForSsh(string $ip, string $ssh): bool
    {
        for ($i = 0; $i < 30; $i++) {
            if ($this->run("{$ssh} root@{$ip} 'true'")) {
                return true;
            }
            sleep(5);
        }

        return false;
    }

    private function run(string $cmd): bool
    {
        try {
            return Process::timeout(600)->run($cmd)->successful();
        } catch (\Throwable $e) {
            Log::warning('DbFleet: command failed', ['cmd' => $cmd, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
