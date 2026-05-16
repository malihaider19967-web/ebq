<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Seeds the four canonical plan rows. Mirrors the marketing
 * `pricing.blade.php` so the public API endpoint, the WordPress plugin
 * wizard, and the marketing page all show identical plan content.
 *
 * Slug taxonomy (renamed 2026-05-17):
 *   free    — anonymous / free tier
 *   pro     — entry-level paid (was "starter")
 *   startup — growth tier      (was "pro")
 *   agency  — top tier         (unchanged)
 *
 * `plan_features` is the authoritative entitlement matrix the WP plugin
 * honours. Treat the seeded values as a sensible starting point —
 * operators tune them live from /admin/plans/<id>/edit without a deploy.
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
                'is_active' => true,
                'features' => [
                    '1 connected website',
                    'WordPress plugin (full)',
                    'Search Console performance + indexing',
                    '5 tracked keywords',
                    '10 page audits / month',
                ],
                'plan_features' => [
                    // Core editor enhancements (free baseline).
                    'chatbot'          => false,
                    'ai_writer'        => false,
                    'ai_inline'        => false,
                    'live_audit'       => true,
                    'hq'               => true,
                    'redirects'        => true,
                    'dashboard_widget' => true,
                    'post_column'      => true,
                    // Rank-Math-parity additions: every paid-tier flag is
                    // off on free. Speakable + schema_extras stay free
                    // because they are pure schema enrichment with no
                    // server-side cost.
                    'internal_links'   => false,
                    'link_genius'      => false,
                    'news_sitemap'     => false,
                    'local_multi'      => false,
                    'image_bulk'       => false,
                    'woo_pro'          => false,
                    'analytics_pro'    => false,
                    'white_label'      => false,
                    'sitewide_audit'   => false,
                    'role_manager'     => false,
                    'instant_indexing' => false,
                    'llms_txt'         => true,
                    'speakable'        => true,
                    'schema_spy'       => false,
                    'schema_extras'    => true,
                ],
                'api_limits' => [
                    'keywords_everywhere' => ['monthly_credits' => 100],
                    'serper'              => ['monthly_calls'   => 50],
                    'mistral'             => ['monthly_tokens'  => 50_000],
                    'rank_tracker'        => ['max_active_keywords' => 5],
                ],
            ],
            [
                // Renamed: starter → pro. This is the new entry-level
                // paid tier (formerly known as "Starter").
                'slug' => 'pro',
                'name' => 'Pro',
                'tagline' => 'For one site you actively grow.',
                'price_monthly_usd' => 15,
                'price_yearly_usd' => 180,
                'trial_days' => 30,
                'max_websites' => 1,
                'display_order' => 2,
                'is_highlighted' => true,
                'is_active' => true,
                'features' => [
                    'Everything in Free',
                    '50 tracked keywords',
                    '100 page audits / month',
                    'Topical-gap analysis (top-5 SERP)',
                    'AI snippet rewrites + content brief',
                    'AI inline editor (// slash commands)',
                    'Backlink monitoring (own domain)',
                ],
                'plan_features' => [
                    'chatbot'          => true,
                    'ai_writer'        => false,
                    'ai_inline'        => true,
                    'live_audit'       => true,
                    'hq'               => true,
                    'redirects'        => true,
                    'dashboard_widget' => true,
                    'post_column'      => true,
                    // Pro tier unlocks the publisher / WooCommerce / admin-
                    // hygiene additions; Startup adds analytics + media.
                    'internal_links'   => true,
                    'link_genius'      => true,
                    'news_sitemap'     => false,
                    'local_multi'      => false,
                    'image_bulk'       => false,
                    'woo_pro'          => true,
                    'analytics_pro'    => false,
                    'white_label'      => false,
                    'sitewide_audit'   => false,
                    'role_manager'     => true,
                    'instant_indexing' => true,
                    'llms_txt'         => true,
                    'speakable'        => true,
                    'schema_spy'       => false,
                    'schema_extras'    => true,
                ],
                'api_limits' => [
                    'keywords_everywhere' => ['monthly_credits' => 2_500],
                    'serper'              => ['monthly_calls'   => 500],
                    'mistral'             => ['monthly_tokens'  => 1_000_000],
                    'rank_tracker'        => ['max_active_keywords' => 50],
                ],
            ],
            [
                // Renamed: pro → startup. Same SKU + price as the
                // previously-shipped Pro tier; just relabelled to make
                // room for the cheaper entry-level "Pro" above.
                'slug' => 'startup',
                'name' => 'Startup',
                'tagline' => 'For agencies and growth teams.',
                'price_monthly_usd' => 39,
                'price_yearly_usd' => 468,
                'trial_days' => 30,
                'max_websites' => 5,
                'display_order' => 3,
                'is_highlighted' => false,
                'is_active' => true,
                'features' => [
                    'Everything in Pro',
                    '5 websites, 250 tracked keywords',
                    '500 page audits / month',
                    'AI Writer (full draft)',
                    'Competitor backlink prospecting',
                    'Cross-site benchmarks',
                    'Quick-submit (Google Indexing API)',
                    'Priority email + chat support',
                ],
                'plan_features' => [
                    'chatbot'          => true,
                    'ai_writer'        => true,
                    'ai_inline'        => true,
                    'live_audit'       => true,
                    'hq'               => true,
                    'redirects'        => true,
                    'dashboard_widget' => true,
                    'post_column'      => true,
                    'internal_links'   => true,
                    'link_genius'      => true,
                    'news_sitemap'     => true,
                    'local_multi'      => false,
                    'image_bulk'       => true,
                    'woo_pro'          => true,
                    'analytics_pro'    => true,
                    'white_label'      => false,
                    'sitewide_audit'   => false,
                    'role_manager'     => true,
                    'instant_indexing' => true,
                    'llms_txt'         => true,
                    'speakable'        => true,
                    'schema_spy'       => true,
                    'schema_extras'    => true,
                ],
                'api_limits' => [
                    'keywords_everywhere' => ['monthly_credits' => 15_000],
                    'serper'              => ['monthly_calls'   => 2_500],
                    'mistral'             => ['monthly_tokens'  => 5_000_000],
                    'rank_tracker'        => ['max_active_keywords' => 250],
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
                'is_active' => true,
                'features' => [
                    'Everything in Startup',
                    '25+ websites, 1,500 tracked keywords',
                    '2,500 page audits / month',
                    'White-label client reports (PDF)',
                    'Bulk operations + batch URL submit',
                    'SSO + role-based access',
                    'Dedicated success manager',
                ],
                'plan_features' => [
                    'chatbot'          => true,
                    'ai_writer'        => true,
                    'ai_inline'        => true,
                    'live_audit'       => true,
                    'hq'               => true,
                    'redirects'        => true,
                    'dashboard_widget' => true,
                    'post_column'      => true,
                    'internal_links'   => true,
                    'link_genius'      => true,
                    'news_sitemap'     => true,
                    'local_multi'      => true,
                    'image_bulk'       => true,
                    'woo_pro'          => true,
                    'analytics_pro'    => true,
                    'white_label'      => true,
                    'sitewide_audit'   => true,
                    'role_manager'     => true,
                    'instant_indexing' => true,
                    'llms_txt'         => true,
                    'speakable'        => true,
                    'schema_spy'       => true,
                    'schema_extras'    => true,
                ],
                'api_limits' => [
                    'keywords_everywhere' => ['monthly_credits' => 100_000],
                    'serper'              => ['monthly_calls'   => 15_000],
                    'mistral'             => ['monthly_tokens'  => 25_000_000],
                    'rank_tracker'        => ['max_active_keywords' => 1_500],
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
