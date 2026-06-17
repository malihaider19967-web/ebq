<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkerNode;
use App\Services\Fleet\WorkerFleetService;
use App\Support\AutoscalerConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin "Fleet" panel: live crawl-worker fleet status + editable autoscaler
 * settings + manual operator actions (provision / drain / reconcile). The live
 * table is the App\Livewire\Admin\FleetStatus component; this controller serves
 * the page shell, persists settings, and runs operator actions. Mirrors the
 * keyword-servers admin pattern.
 */
class FleetController extends Controller
{
    /** Hetzner server types the admin can pick (label => human). cx = shared Intel. */
    private const SERVER_TYPES = [
        'cx22' => 'cx22 — 2 vCPU / 4 GB',
        'cx23' => 'cx23 — 4 vCPU / 8 GB',
        'cx32' => 'cx32 — 4 vCPU / 8 GB',
        'cx33' => 'cx33 — 8 vCPU / 16 GB',
        'cpx31' => 'cpx31 — 4 vCPU / 8 GB (AMD)',
        'cpx41' => 'cpx41 — 8 vCPU / 16 GB (AMD)',
    ];

    public function index(): View
    {
        return view('admin.fleet.index', [
            'cfg' => AutoscalerConfig::all(),
            'serverTypes' => self::SERVER_TYPES,
        ]);
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'min_boxes' => ['required', 'integer', 'min:1', 'max:20'],
            'max_boxes' => ['required', 'integer', 'min:1', 'max:50'],
            'target_backlog_per_box' => ['required', 'integer', 'min:50', 'max:100000'],
            'server_type' => ['required', 'string', 'in:'.implode(',', array_keys(self::SERVER_TYPES))],
            'snapshot_id' => ['nullable', 'string', 'max:64'],
            'scale_up_cooldown_s' => ['required', 'integer', 'min:30', 'max:3600'],
            'scale_down_idle_s' => ['required', 'integer', 'min:60', 'max:86400'],
            'min_box_lifetime_s' => ['required', 'integer', 'min:0', 'max:86400'],
            'per_domain_rate' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
        $data['enabled'] = $request->boolean('enabled');

        AutoscalerConfig::update($data);

        return back()->with('status', 'Autoscaler settings saved.');
    }

    public function provision(WorkerFleetService $fleet): RedirectResponse
    {
        $node = $fleet->provision();
        if ($node->status === WorkerNode::STATUS_FAILED) {
            return back()->with('error', "Provision failed: {$node->last_error}");
        }
        $fleet->bootstrap($node);

        return back()->with('status', "Provisioned + bootstrapped node {$node->id}.");
    }

    public function drain(WorkerNode $node, WorkerFleetService $fleet): RedirectResponse
    {
        if ($node->is_pinned) {
            return back()->with('error', 'The pinned permanent box cannot be drained.');
        }
        $fleet->drain($node);

        return back()->with('status', "Draining node {$node->id} (containers stopping gracefully).");
    }

    public function destroy(WorkerNode $node, WorkerFleetService $fleet): RedirectResponse
    {
        if ($node->is_pinned) {
            return back()->with('error', 'The pinned permanent box cannot be destroyed.');
        }
        $fleet->destroy($node);

        return back()->with('status', "Destroyed node {$node->id}.");
    }

    public function reconcile(WorkerFleetService $fleet): RedirectResponse
    {
        $r = $fleet->reconcile();

        return back()->with('status', "Reconciled: {$r['vanished']} vanished, ".count($r['orphans']).' orphan(s).');
    }
}
