<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Fleet\BootstrapDbNodeJob;
use App\Jobs\Fleet\MigrateDbNodeJob;
use App\Jobs\Fleet\MoveShardJob;
use App\Jobs\Fleet\ProvisionDbNodeJob;
use App\Models\DbNode;
use App\Services\Fleet\DbFleetService;
use App\Support\DbFleetConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin "Database fleet" panel — the DbNode equivalent of {@see FleetController}.
 * Lists shard nodes, edits provisioning defaults, runs operator lifecycle
 * actions (provision / bootstrap / migrate / drain / destroy), and moves a
 * tenant / crawl-site between nodes. DB nodes change rarely, so the page is
 * server-rendered (no live-poll component).
 */
class DbFleetController extends Controller
{
    /**
     * Hetzner server types (slug => human). Slugs + specs verified against the
     * live Hetzner API for this account (fsn1). cx = shared Intel, cpx = shared
     * AMD, ccx = dedicated AMD. Keep in sync if Hetzner's catalog changes.
     * Public: the unified /admin/fleet page ({@see FleetController::index}) reuses it.
     */
    public const SERVER_TYPES = [
        'cx23' => 'cx23 — 2 vCPU / 4 GB',
        'cx33' => 'cx33 — 4 vCPU / 8 GB',
        'cx43' => 'cx43 — 8 vCPU / 16 GB',
        'cpx41' => 'cpx41 — 8 vCPU / 16 GB (AMD)',
        'ccx33' => 'ccx33 — 8 dedicated vCPU / 32 GB',
    ];

    /**
     * The DB-shard fleet now lives as the "Database shards" tab on the unified
     * /admin/fleet page. Keep this GET so old links/bookmarks still resolve.
     */
    public function index(): RedirectResponse
    {
        // '#data' opens the "Database shards" tab on the unified page (read by its JS).
        return redirect(route('admin.fleet.index').'#data');
    }

    /**
     * Move-form options, reused by the unified fleet page. A "tenant" move is keyed
     * by USER id (ShardMover::moveTenant moves that user + all their websites' data),
     * so only users who own websites are valid tenants. A "crawl" move is keyed by
     * crawl_site id (domain). Returns ['tenant' => [{id,label}], 'crawl' => [...]].
     */
    public static function moveOptions(): array
    {
        $tenants = \App\Models\User::whereHas('websites')->orderBy('name')->get(['id', 'name', 'email'])
            ->map(fn ($u) => ['id' => (string) $u->id, 'label' => trim(($u->name ?: '—').' — '.$u->email)])
            ->values();
        $crawlSites = \App\Models\CrawlSite::orderBy('normalized_domain')->get(['id', 'normalized_domain'])
            ->map(fn ($s) => ['id' => (string) $s->id, 'label' => $s->normalized_domain])
            ->values();

        return ['tenant' => $tenants, 'crawl' => $crawlSites];
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'server_type' => ['required', 'string', 'in:'.implode(',', array_keys(self::SERVER_TYPES))],
            'snapshot_id' => ['nullable', 'string', 'max:64'],
            'placement' => ['required', 'in:least_loaded,round_robin'],
            'max_tenants_per_node' => ['required', 'integer', 'min:1', 'max:100000'],
            'max_sites_per_node' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);
        DbFleetConfig::update($data);

        return back()->with('status', 'DB-fleet settings saved.');
    }

    public function registerPrimary(DbFleetService $fleet): RedirectResponse
    {
        $node = $fleet->registerPrimary(
            (string) config('database.connections.global.host'),
            (string) config('database.connections.global.database'),
        );

        return back()->with('status', "Primary node registered ({$node->name}).");
    }

    public function provision(Request $request): RedirectResponse
    {
        $role = $request->input('role') === DbNode::ROLE_CRAWL ? DbNode::ROLE_CRAWL : DbNode::ROLE_TENANT;
        // Provision + bootstrap run on the FLEET queue (web box, root): they SSH to
        // the new box and can take minutes — far past the request timeout.
        ProvisionDbNodeJob::dispatch($role);

        return back()->with('status', 'Provisioning a new '.$role.' node in the background — its status will update from provisioning → active here.');
    }

    public function bootstrap(DbNode $node): RedirectResponse
    {
        BootstrapDbNodeJob::dispatch($node->id);

        return back()->with('status', "Bootstrapping node {$node->id} in the background (configure + migrate).");
    }

    public function migrate(DbNode $node): RedirectResponse
    {
        MigrateDbNodeJob::dispatch($node->id);

        return back()->with('status', "Migrating schema on node {$node->id} in the background.");
    }

    public function drain(DbNode $node, DbFleetService $fleet): RedirectResponse
    {
        $fleet->drain($node);

        return back()->with('status', "Draining node {$node->id}.");
    }

    public function destroy(DbNode $node, DbFleetService $fleet): RedirectResponse
    {
        return $fleet->destroy($node)
            ? back()->with('status', "Destroyed node {$node->id}.")
            : back()->with('error', $node->fresh()?->last_error ?: 'Cannot destroy this node.');
    }

    public function move(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:tenant,crawl'],
            'id' => ['required', 'string'],
            'to' => ['required', 'string', 'exists:db_nodes,id'],
        ]);
        $target = DbNode::findOrFail($data['to']);
        // Runs on the FLEET queue: the move holds a migrating lock + chunk-copies,
        // which can take a while. The node counts / anchors update here when done.
        MoveShardJob::dispatch($data['kind'], $data['id'], $target->id);

        return back()->with('status', "Move started: {$data['kind']} {$data['id']} → {$target->name} (running in background).");
    }
}
