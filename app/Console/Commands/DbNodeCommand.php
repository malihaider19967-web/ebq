<?php

namespace App\Console\Commands;

use App\Models\DbNode;
use App\Services\Fleet\DbFleetService;
use Illuminate\Console\Command;

/**
 * Operator control for the database-node fleet — the DbNode equivalent of
 * {@see FleetWorker}. Manual lifecycle (DB nodes are provisioned/retired
 * deliberately, never autoscaled — data has to be migrated on/off them).
 */
class DbNodeCommand extends Command
{
    protected $signature = 'ebq:db-node
        {action : list|register-primary|register|provision|bootstrap|migrate|drain|destroy|reconcile}
        {--id= : db_nodes id (bootstrap/migrate/drain/destroy)}
        {--role=tenant-shard : tenant-shard|crawl-shard (register/provision)}
        {--name= : node name (register)}
        {--ip= : private IP (register/register-primary)}
        {--db= : database name (register/register-primary)}
        {--port= : MariaDB port if non-default (register, e.g. a local validation box)}';

    protected $description = 'Manage the database-shard node fleet';

    public function handle(DbFleetService $fleet): int
    {
        return match ($this->argument('action')) {
            'list' => $this->list(),
            'register-primary' => $this->registerPrimary($fleet),
            'register' => $this->register($fleet),
            'provision' => $this->provision($fleet),
            'bootstrap' => $this->withNode(fn (DbNode $n) => $this->boolResult($fleet->bootstrap($n), 'bootstrap')),
            'migrate' => $this->withNode(fn (DbNode $n) => $this->boolResult($fleet->migrateNode($n), 'migrate')),
            'drain' => $this->withNode(function (DbNode $n) use ($fleet) {
                $fleet->drain($n);
                $this->info("draining {$n->name}");

                return self::SUCCESS;
            }),
            'destroy' => $this->withNode(fn (DbNode $n) => $this->boolResult($fleet->destroy($n), 'destroy', $n->fresh()?->last_error)),
            default => $this->bail('unknown action'),
        };
    }

    private function list(): int
    {
        $rows = DbNode::orderByDesc('is_pinned')->orderBy('role')->get()
            ->map(fn (DbNode $n) => [
                $n->id, $n->name, $n->role, $n->status, $n->is_pinned ? 'yes' : '',
                $n->private_ip, $n->db_name, $n->tenant_count, $n->site_count, $n->last_error,
            ])->all();
        $this->table(['id', 'name', 'role', 'status', 'pinned', 'ip', 'db', 'tenants', 'sites', 'error'], $rows);

        return self::SUCCESS;
    }

    private function registerPrimary(DbFleetService $fleet): int
    {
        $ip = (string) ($this->option('ip') ?: config('database.connections.global.host'));
        $db = (string) ($this->option('db') ?: config('database.connections.global.database'));
        $node = $fleet->registerPrimary($ip, $db);
        $this->info("primary registered: {$node->id} ({$ip} / {$db})");

        return self::SUCCESS;
    }

    private function register(DbFleetService $fleet): int
    {
        $ip = (string) $this->option('ip');
        $db = (string) $this->option('db');
        if ($ip === '' || $db === '') {
            return $this->bail('--ip and --db are required');
        }
        $node = $fleet->registerExisting(
            (string) ($this->option('name') ?: 'ebq-db-'.$this->option('role')),
            (string) $this->option('role'),
            $ip,
            $db,
            $this->option('port') !== null ? (int) $this->option('port') : null,
        );
        $this->info("node registered: {$node->id} ({$node->connectionName()})");

        return self::SUCCESS;
    }

    private function provision(DbFleetService $fleet): int
    {
        $node = $fleet->provision((string) $this->option('role'));
        if ($node->status === DbNode::STATUS_FAILED) {
            return $this->bail($node->last_error ?? 'provision failed');
        }
        $this->info("provisioned {$node->id}; run: ebq:db-node bootstrap --id={$node->id}");

        return self::SUCCESS;
    }

    private function withNode(callable $fn): int
    {
        $node = DbNode::find($this->option('id'));
        if (! $node) {
            return $this->bail('node not found (--id)');
        }

        return $fn($node);
    }

    private function boolResult(bool $ok, string $what, ?string $err = null): int
    {
        if ($ok) {
            $this->info("{$what} ok");

            return self::SUCCESS;
        }

        return $this->bail("{$what} failed".($err ? ": {$err}" : ''));
    }

    private function bail(string $msg): int
    {
        $this->error($msg);

        return self::FAILURE;
    }
}
