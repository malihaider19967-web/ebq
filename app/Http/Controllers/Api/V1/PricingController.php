<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Public anonymous pricing endpoint.
 *
 * Same access pattern as `/wordpress/plugin/version` — no auth, no
 * caller identifier captured. The WordPress plugin's setup wizard
 * fetches this to populate its Pricing step. Cached briefly client-side
 * (the plugin reuses its EBQ_Updater transient pattern).
 *
 * Response shape:
 *   {
 *     plans: [
 *       { slug, name, tagline, price_monthly_usd, trial_days, features:[],
 *         checkout_url, is_highlighted },
 *       ...
 *     ],
 *     promo: { headline, expires_at } | null
 *   }
 *
 * `checkout_url` is null for free or for any plan whose Stripe price ID
 * isn't configured yet — the plugin grays-out the CTA. When a paid
 * plan IS checkout-ready, the URL points at `/billing/checkout?plan=…`
 * which Cashier then turns into a Stripe Hosted Checkout session.
 *
 * Falls back to a hardcoded plan list when the `plans` table is empty
 * (fresh install, before seeder runs) so the wizard never sees a blank
 * step. Once the seeder runs, the DB rows take over.
 */
class PricingController extends Controller
{
    public function public(): JsonResponse
    {
        $plans = Plan::ordered()->get();

        if ($plans->isEmpty()) {
            $plans = collect($this->fallbackPlans());
        }

        $payload = $plans->map(function ($plan): array {
            $isReady = $plan instanceof Plan
                ? $plan->isCheckoutReady()
                : (bool) ($plan['_checkout_ready'] ?? false);

            return [
                'slug' => $plan['slug'],
                'name' => $plan['name'],
                'tagline' => $plan['tagline'] ?? null,
                'price_monthly_usd' => (int) ($plan['price_monthly_usd'] ?? 0),
                'price_yearly_usd' => (int) ($plan['price_yearly_usd'] ?? 0),
                'trial_days' => (int) ($plan['trial_days'] ?? 0),
                'features' => is_array($plan['features'] ?? null)
                    ? array_values($plan['features'])
                    : [],
                'is_highlighted' => (bool) ($plan['is_highlighted'] ?? false),
                'checkout_url' => $isReady
                    ? route('billing.checkout', ['plan' => $plan['slug']])
                    : null,
            ];
        })->values()->all();

        return response()->json([
            'plans' => $payload,
            'promo' => $this->promoBanner(),
        ])->header('Cache-Control', 'public, max-age=900'); // 15 min CDN edge cache
    }

    /**
     * Promotional banner — only set when the global `app.free` config
     * flag is on (matches the existing logic in `pricing.blade.php`).
     * Lets EBQ run "all Pro features free for X months" promotions
     * without a code change to the plugin.
     *
     * @return array{headline:string, expires_at:string|null}|null
     */
    private function promoBanner(): ?array
    {
        if (! config('app.free')) {
            return null;
        }
        return [
            'headline' => 'All Pro features are unlocked free for a limited time.',
            'expires_at' => null,
        ];
    }

    /**
     * Fallback plan data used when the `plans` table is empty (typically
     * just-after-deploy before the seeder has run). Keeps the public API
     * non-empty so downstream consumers (the WP plugin wizard) don't
     * crash on a fresh install.
     *
     * @return list<array<string, mixed>>
     */
    private function fallbackPlans(): array
    {
        return [
            [
                'slug' => 'free',
                'name' => 'Free',
                'tagline' => 'For personal sites and trial runs.',
                'price_monthly_usd' => 0,
                'price_yearly_usd' => 0,
                'trial_days' => 0,
                'features' => [
                    '1 connected website',
                    'WordPress plugin (full)',
                    'Search Console performance + indexing',
                    '5 tracked keywords',
                    '10 page audits / month',
                ],
                'is_highlighted' => false,
                '_checkout_ready' => false,
            ],
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'tagline' => 'For one site you actively grow.',
                'price_monthly_usd' => 15,
                'price_yearly_usd' => 180,
                'trial_days' => 30,
                'features' => [
                    'Everything in Free',
                    '50 tracked keywords',
                    '100 page audits / month',
                    'Topical-gap analysis (top-5 SERP)',
                    'AI snippet rewrites + content brief',
                    'Backlink monitoring (own domain)',
                ],
                'is_highlighted' => false,
                '_checkout_ready' => false,
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro',
                'tagline' => 'For agencies and growth teams.',
                'price_monthly_usd' => 39,
                'price_yearly_usd' => 468,
                'trial_days' => 30,
                'features' => [
                    'Everything in Starter',
                    '5 websites, 250 tracked keywords',
                    '500 page audits / month',
                    'AI Writer (full draft)',
                    'Competitor backlink prospecting',
                    'Cross-site benchmarks',
                    'Quick-submit (Google Indexing API)',
                    'Priority email + chat support',
                ],
                'is_highlighted' => true,
                '_checkout_ready' => false,
            ],
            [
                'slug' => 'agency',
                'name' => 'Agency',
                'tagline' => 'For agencies managing many clients.',
                'price_monthly_usd' => 125,
                'price_yearly_usd' => 1500,
                'trial_days' => 30,
                'features' => [
                    'Everything in Pro',
                    '25 websites, 1,500 tracked keywords',
                    '2,500 page audits / month',
                    'White-label client reports (PDF)',
                    'Bulk operations + batch URL submit',
                    'SSO + role-based access',
                    'Dedicated success manager',
                ],
                'is_highlighted' => false,
                '_checkout_ready' => false,
            ],
        ];
    }
}
