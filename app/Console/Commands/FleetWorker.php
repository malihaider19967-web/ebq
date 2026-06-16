<?php

namespace App\Console\Commands;

use App\Models\WorkerNode;
use App\Services\Fleet\WorkerFleetService;
use Illuminate\Console\Command;

/**
 * Manual operator control over the crawl-worker fleet (Phase 1 — before the
 * autoscaler loop exists). Everything the autoscaler will do, an operator can do
 * by hand here to validate provisioning + drain end-to-end against the live
 * system:
 *
 *   php artisan ebq:fleet-worker list
 *   php artisan ebq:fleet-worker register-pinned --ip=10.0.0.3 --server=<hetzner-id>
 *   php artisan ebq:fleet-worker provision            # create + bootstrap a box
 *   php artisan ebq:fleet-worker drain --id=12        # graceful stop
 *   php artisan ebq:fleet-worker destroy --id=12      # delete the server + row
 *   php artisan ebq:fleet-worker reconcile            # DB vs Hetzner
 */
class FleetWorker extends Command
{
    protected $signature = 'ebq:fleet-worker
        {action : list|provision|bootstrap|drain|destroy|reconcile|register-pinned}
        {--id= : worker_nodes id (drain/destroy/bootstrap)}
        {--ip= : private IP (register-pinned)}
        {--server= : Hetzner server id (register-pinned)}
        {--no-bootstrap : provision without pushing code/starting containers}';

    protected $description = 'Manually provision / drain / destroy crawl-worker boxes (Phase 1 fleet control).';

    public function handle(WorkerFleetService $fleet): int
    {
        return match ($this->argument('action')) {
            'list' => $this->list(),
            'provision' => $this->provision($fleet),
            'bootstrap' => $this->bootstrap($fleet),
            'drain' => $this->drain($fleet),
            'destroy' => $this->destroy($fleet),
            'reconcile' => $this->reconcile($fleet),
            'register-pinned' => $this->registerPinned(),
            default => $this->fail('unknown action'),
        };
    }

    private function list(): int
    {
        $rows = WorkerNode::orderByDesc('is_pinned')->orderBy('id')->get()
            ->map(fn (WorkerNode $n) => [
                $n->id, $n->name, $n->status, $n->is_pinned ? 'yes' : '', $n->private_ip ?? '—',
                $n->hetzner_server_id ?? '—', $n->server_type ?? '—', $n->ageMinutes().'m',
                $n->last_seen_at?->diffForHumans() ?? '—',
            ])->all();
        $this->table(['id', 'name', 'status', 'pinned', 'private_ip', 'hetzner_id', 'type', 'age', 'last_seen'], $rows);
        $this->info('billable: '.WorkerNode::billable()->count().' | active: '.WorkerNode::active()->count());

        return self::SUCCESS;
    }

    private function provision(WorkerFleetService $fleet): int
    {
        $node = $fleet->provision();
        if ($node->status === WorkerNode::STATUS_FAILED) {
            $this->error("provision failed: {$node->last_error}");

            return self::FAILURE;
        }
        $this->info("provisioned node {$node->id} (hetzner {$node->hetzner_server_id}, ip {$node->private_ip})");
        if (! $this->option('no-bootstrap')) {
            $this->line('bootstrapping (rsync code + .env + docker compose up)…');
            if (! $fleet->bootstrap($node)) {
                $this->warn("bootstrap failed for node {$node->id}: {$node->fresh()->last_error}");

                return self::FAILURE;
            }
            $this->info("node {$node->id} active");
        }

        return self::SUCCESS;
    }

    private function bootstrap(WorkerFleetService $fleet): int
    {
        $node = $this->node();
        if (! $node) {
            return self::FAILURE;
        }
        $ok = $fleet->bootstrap($node);
        $this->{$ok ? 'info' : 'error'}("bootstrap ".($ok ? 'ok' : 'failed')." for node {$node->id}");

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function drain(WorkerFleetService $fleet): int
    {
        $node = $this->node();
        if (! $node) {
            return self::FAILURE;
        }
        $fleet->drain($node);
        $this->info("draining node {$node->id} (containers stopping; in-flight job finishes within stop_grace_period)");

        return self::SUCCESS;
    }

    private function destroy(WorkerFleetService $fleet): int
    {
        $node = $this->node();
        if (! $node) {
            return self::FAILURE;
        }
        if ($node->is_pinned) {
            $this->error('refusing to destroy the pinned permanent box');

            return self::FAILURE;
        }
        if ($node->status === WorkerNode::STATUS_ACTIVE) {
            $fleet->drain($node);
            $this->line('drained; deleting server…');
        }
        $fleet->destroy($node);
        $this->info("destroyed node {$node->id}");

        return self::SUCCESS;
    }

    private function reconcile(WorkerFleetService $fleet): int
    {
        $r = $fleet->reconcile();
        $this->info("reconcile: vanished={$r['vanished']} orphans=".(count($r['orphans']) ? implode(',', $r['orphans']) : 'none'));

        return self::SUCCESS;
    }

    private function registerPinned(): int
    {
        $ip = (string) $this->option('ip');
        if ($ip === '') {
            $this->error('--ip is required');

            return self::FAILURE;
        }
        $node = WorkerNode::updateOrCreate(
            ['private_ip' => $ip],
            [
                'name' => 'ebq-crawl-worker-pinned',
                'status' => WorkerNode::STATUS_ACTIVE,
                'is_pinned' => true,
                'hetzner_server_id' => $this->option('server') ? (int) $this->option('server') : null,
                'provisioned_at' => now(),
                'is_healthy' => true,
                'last_seen_at' => now(),
            ],
        );
        $this->info("pinned box registered (node {$node->id}, ip {$ip})");

        return self::SUCCESS;
    }

    private function node(): ?WorkerNode
    {
        $id = (int) $this->option('id');
        $node = $id ? WorkerNode::find($id) : null;
        if (! $node) {
            $this->error('--id is required and must reference an existing worker_nodes row');
        }

        return $node;
    }
}
