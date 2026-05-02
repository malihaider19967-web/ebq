<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin plan management — edit pricing, Stripe price IDs, trial days,
 * feature bullets, display order, active state.
 *
 * Plans drive three surfaces simultaneously:
 *   1. The marketing /pricing page (when migrated to read from DB).
 *   2. The public /api/v1/plans endpoint that powers the WordPress
 *      plugin's setup-wizard pricing step.
 *   3. The /billing/checkout endpoint that hands Stripe price IDs
 *      to Cashier when a user starts a trial.
 *
 * Slug is intentionally read-only after creation — it's the immutable
 * public identifier referenced by Stripe webhook handlers, the WP
 * plugin, and any in-flight checkout sessions. Renaming it would
 * orphan running subscriptions.
 *
 * `is_active=false` deprecates a plan without deleting historical
 * subscription rows that reference it.
 */
class PlanController extends Controller
{
    public function index(): View
    {
        $plans = Plan::query()->orderBy('display_order')->get();

        return view('admin.plans.index', [
            'plans' => $plans,
        ]);
    }

    public function create(): View
    {
        return view('admin.plans.edit', [
            'plan' => new Plan([
                'is_active' => true,
                'is_highlighted' => false,
                'trial_days' => 0,
                'display_order' => Plan::max('display_order') + 1,
                'features' => [],
            ]),
            'isNew' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePlanInput($request, isNew: true);
        $plan = Plan::create($data);

        return redirect()
            ->route('admin.plans.index')
            ->with('status', sprintf('Plan "%s" created.', $plan->name));
    }

    public function edit(Plan $plan): View
    {
        return view('admin.plans.edit', [
            'plan' => $plan,
            'isNew' => false,
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $this->validatePlanInput($request, isNew: false);
        $plan->update($data);

        return redirect()
            ->route('admin.plans.index')
            ->with('status', sprintf('Plan "%s" saved.', $plan->name));
    }

    /**
     * Sanity-validate + normalise plan input. Slug is required only on
     * creation; it can't be changed after that. Features come from the
     * form as a newline-separated textarea; we split into an array on
     * the way in. Stripe price IDs MUST start with `price_` — defends
     * against pasting product IDs (`prod_…`) or unrelated strings.
     */
    private function validatePlanInput(Request $request, bool $isNew): array
    {
        $rules = [
            'name' => 'required|string|max:64',
            'tagline' => 'nullable|string|max:191',
            'price_monthly_usd' => 'required|integer|min:0|max:99999',
            'price_yearly_usd' => 'nullable|integer|min:0|max:999999',
            'stripe_price_id_monthly' => 'nullable|string|max:128|regex:/^price_/',
            'stripe_price_id_yearly' => 'nullable|string|max:128|regex:/^price_/',
            'trial_days' => 'required|integer|min:0|max:365',
            // null / empty string = unlimited. Stored as null in DB.
            'max_websites' => 'nullable|integer|min:0|max:999',
            'features' => 'nullable|string|max:8000',
            'display_order' => 'required|integer|min:0|max:9999',
            'is_active' => 'sometimes|boolean',
            'is_highlighted' => 'sometimes|boolean',
        ];
        if ($isNew) {
            $rules['slug'] = 'required|string|max:32|alpha_dash|unique:plans,slug';
        }

        $data = $request->validate($rules);

        // Features textarea → array. One feature per line, trimmed,
        // empty lines dropped.
        $features = $data['features'] ?? '';
        $data['features'] = collect(preg_split('/\r?\n/', (string) $features))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();

        // Checkboxes only POST when checked.
        $data['is_active'] = $request->boolean('is_active');
        $data['is_highlighted'] = $request->boolean('is_highlighted');

        return $data;
    }
}
