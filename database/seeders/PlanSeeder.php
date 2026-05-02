<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Seeds the four canonical plan rows. Mirrors the marketing
 * `pricing.blade.php` so the public API endpoint, the WordPress plugin
 * wizard, and the marketing page all show identical plan content.
 *
 * Stripe price IDs are intentionally NULL here — operator must add them
 * via Stripe Dashboard + a follow-up `php artisan tinker` (or migration)
 * once the products exist. `isCheckoutReady()` on the Plan model
 * gracefully refuses checkout sessions until they're populated.
 *
 * Idempotent: uses `updateOrCreate` keyed by `slug` so re-running this
 * seeder won't duplicate rows or trample manually-set Stripe IDs.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'free',
                'name' => 'Free',
                'tagline' => 'For personal sites and trial runs.',
                'price_monthly_usd' => 0,
                'price_yearly_usd' => 0,
                'trial_days' => 0,
                'max_websites' => 1,
                'display_order' => 1,
                'is_highlighted' => false,
                'features' => [
                    '1 connected website',
                    'WordPress plugin (full)',
                    'Search Console performance + indexing',
                    '5 tracked keywords',
                    '10 page audits / month',
                ],
            ],
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'tagline' => 'For one site you actively grow.',
                'price_monthly_usd' => 15,
                'price_yearly_usd' => 180,
                'trial_days' => 30,
                'max_websites' => 1,
                'display_order' => 2,
                'is_highlighted' => false,
                'features' => [
                    'Everything in Free',
                    '50 tracked keywords',
                    '100 page audits / month',
                    'Topical-gap analysis (top-5 SERP)',
                    'AI snippet rewrites + content brief',
                    'Backlink monitoring (own domain)',
                ],
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro',
                'tagline' => 'For agencies and growth teams.',
                'price_monthly_usd' => 39,
                'price_yearly_usd' => 468,
                'trial_days' => 30,
                'max_websites' => 5,
                'display_order' => 3,
                'is_highlighted' => true,
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
            ],
            [
                'slug' => 'agency',
                'name' => 'Agency',
                'tagline' => 'For agencies managing many clients.',
                'price_monthly_usd' => 125,
                'price_yearly_usd' => 1500,
                'trial_days' => 30,
                // Null = unlimited.
                'max_websites' => null,
                'display_order' => 4,
                'is_highlighted' => false,
                'features' => [
                    'Everything in Pro',
                    '25 websites, 1,500 tracked keywords',
                    '2,500 page audits / month',
                    'White-label client reports (PDF)',
                    'Bulk operations + batch URL submit',
                    'SSO + role-based access',
                    'Dedicated success manager',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
