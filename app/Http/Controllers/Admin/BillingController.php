<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin overview of every connected website's subscription state.
 * Shows tier, Stripe status, trial-end date, current-period-end, MRR,
 * and per-row actions (open in Stripe, force tier sync, cancel).
 *
 * Read-only for v1 — operator clicks through to Stripe Dashboard for
 * any modifications. Future: inline cancel/upgrade via Cashier APIs.
 */
class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $statusFilter = (string) $request->query('status', 'all');

        $websites = Website::query()
            ->select(['id', 'domain', 'tier', 'stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at', 'user_id', 'created_at'])
            ->with(['owner:id,name,email'])
            ->when($q !== '', static fn ($qq) => $qq->where('domain', 'like', '%'.$q.'%'))
            ->when($statusFilter === 'paying', static fn ($qq) => $qq->where('tier', Website::TIER_PRO))
            ->when($statusFilter === 'trial',  static fn ($qq) => $qq->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', now()))
            ->when($statusFilter === 'free',   static fn ($qq) => $qq->where('tier', Website::TIER_FREE))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        // Compact summary cards.
        $summary = [
            'total'    => Website::query()->count(),
            'pro'      => Website::query()->where('tier', Website::TIER_PRO)->count(),
            'on_trial' => Website::query()
                ->whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '>', now())
                ->count(),
            'free'     => Website::query()->where('tier', Website::TIER_FREE)->count(),
        ];

        return view('admin.billing.index', [
            'websites' => $websites,
            'q'        => $q,
            'status'   => $statusFilter,
            'summary'  => $summary,
        ]);
    }
}
