<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin overview of every paying user + their connected websites.
 *
 * Per-user-billing migration moved Cashier columns from `websites` to
 * `users` and dropped per-website tier. The admin list now indexes
 * USERS (the actual billable entity) and shows their plan, trial state,
 * and how many sites they own. Per-row click-through to the user's
 * Stripe customer + a "force tier sync" action.
 *
 * Read-only for v1 — operator clicks through to Stripe Dashboard for
 * any modification.
 */
class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $statusFilter = (string) $request->query('status', 'all');

        $users = User::query()
            ->select(['id', 'name', 'email', 'stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at', 'current_plan_slug', 'created_at'])
            ->withCount('websites')
            ->when($q !== '', static fn ($qq) => $qq->where(function ($where) use ($q) {
                $where->where('email', 'like', '%'.$q.'%')
                      ->orWhere('name', 'like', '%'.$q.'%');
            }))
            ->when($statusFilter === 'paying', static fn ($qq) => $qq->whereNotNull('current_plan_slug')->where('current_plan_slug', '!=', 'free'))
            ->when($statusFilter === 'trial',  static fn ($qq) => $qq->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', now()))
            ->when($statusFilter === 'free',   static fn ($qq) => $qq->where(function ($where) {
                $where->whereNull('current_plan_slug')->orWhere('current_plan_slug', 'free');
            }))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        // Compact summary cards.
        $summary = [
            'total'    => User::query()->count(),
            'paying'   => User::query()->whereNotNull('current_plan_slug')->where('current_plan_slug', '!=', 'free')->count(),
            'on_trial' => User::query()
                ->whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '>', now())
                ->count(),
            'free'     => User::query()->where(function ($where) {
                $where->whereNull('current_plan_slug')->orWhere('current_plan_slug', 'free');
            })->count(),
        ];

        return view('admin.billing.index', [
            'users'    => $users,
            'q'        => $q,
            'status'   => $statusFilter,
            'summary'  => $summary,
        ]);
    }
}
