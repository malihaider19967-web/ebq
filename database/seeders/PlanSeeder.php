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
 * Stripe price IDs are intentionally omitted here — operator must add them
 * via Stripe Dashboard + a follow-up `php artisan tinker` (or migration)
 * once the products exist. `isCheckoutReady()` on the Plan model
 * gracefully refuses checkout sessions until they're populated. Keeping
 * them out of the seeded array means re-running this seeder won't trample
 * the live IDs.
 *
 * Idempotent: uses `updateOrCreate` keyed by `slug` so re-running this
 * seeder won't duplicate rows or trample manually-set Stripe IDs.
 */
class PlanSeeder extends Seeder
{
    /**
     * Tour videos keyed by feature index. Identical across every plan.
     */
    private const FEATURE_VIDEOS = [
        '4'  => 'https://youtu.be/bfo2ei66Pts',
        '5'  => 'https://youtu.be/MHa027Tq9sQ',
        '16' => 'https://youtu.be/Rzme7QvSbLE',
    ];

    /**
     * Builds the marketing feature bullet list. Only the leading website
     * line and the tracked-keywords line differ between tiers.
     */
    private function features(string $websitesLine, string $keywordsLine): array
    {
        return [
            $websitesLine,
            'Search Console performance + indexing',
            'Detailed Audits',
            'AI Studio (47 AI writer tools)',
            'Long Form AI writer',
            'Cannibalization Tracking',
            'Striking Distance tracker',
            'Content Decay tracker',
            'Keyword Quick Win Finder',
            'Page Speed Insights',
            'Detailed Google Search Console Report',
            'Detailed Google Analytics Report',
            'Keywords Report',
            'Pages Report',
            'Team Access',
            $keywordsLine,
            'WordPress plugin (full)',
        ];
    }

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
                'max_crawl_pages' => 300,
                'display_order' => 1,
                'is_highlighted' => false,
                'is_active' => true,
                'features' => $this->features('1 personal website', '10 tracked keywords'),
                'feature_videos' => self::FEATURE_VIDEOS,
                'plan_features' => [
                    'chatbot'           => false,
                    'ai_writer'         => true,
                    'ai_inline'         => true,
                    'live_audit'        => true,
                    'hq'                => true,
                    'redirects'         => true,
                    'dashboard_widget'  => true,
                    'post_column'       => true,
                    'report_whitelabel' => false,
                ],
                'api_limits' => [
                    'keywords_everywhere' => ['monthly_credits' => 100],
                    'serper'              => ['monthly_calls'   => 100],
                    'mistral'             => ['monthly_tokens'  => 100_000],
                    'rank_tracker'        => ['max_active_keywords' => 10],
                ],
            ],
            [
                // Renamed: starter → pro. This is the new entry-level
                // paid tier (formerly known as "Starter").
                'slug' => 'pro',
                'name' => 'Pro',
                'tagline' => 'For one site you actively grow.',
                'price_monthly_usd' => 5,
                'price_yearly_usd' => 60,
                'trial_days' => 30,
                'max_websites' => 2,
                'max_crawl_pages' => 5000,
                'display_order' => 2,
                'is_highlighted' => true,
                'is_active' => true,
                'features' => $this->features('2 websites', '50 tracked keywords'),
                'feature_videos' => self::FEATURE_VIDEOS,
                'plan_features' => [
                    'chatbot'           => true,
                    'ai_writer'         => true,
                    'ai_inline'         => true,
                    'live_audit'        => true,
                    'hq'                => true,
                    'redirects'         => true,
                    'dashboard_widget'  => true,
                    'post_column'       => true,
                    'report_whitelabel' => false,
                ],
                'api_limits' => [
                    'keywords_everywhere' => ['monthly_credits' => 750],
                    'serper'              => ['monthly_calls'   => 1_000],
                    'mistral'             => ['monthly_tokens'  => 1_000_000],
                    'rank_tracker'        => ['max_active_keywords' => 50],
                ],
            ],
            [
                // Renamed: pro → startup. Relabelled to make room for the
                // cheaper entry-level "Pro" above.
                'slug' => 'startup',
                'name' => 'Startup',
                'tagline' => 'For agencies and growth teams.',
                'price_monthly_usd' => 15,
                'price_yearly_usd' => 180,
                'trial_days' => 0,
                'max_websites' => 10,
                'max_crawl_pages' => 25000,
                'display_order' => 3,
                'is_highlighted' => false,
                'is_active' => true,
                'features' => $this->features('10 websites', '200 tracked keywords'),
                'feature_videos' => self::FEATURE_VIDEOS,
                'plan_features' => [
                    'chatbot'           => true,
                    'ai_writer'         => true,
                    'ai_inline'         => true,
                    'live_audit'        => true,
                    'hq'                => true,
                    'redirects'         => true,
                    'dashboard_widget'  => true,
                    'post_column'       => true,
                    'report_whitelabel' => false,
                ],
                'api_limits' => [
                    'keywords_everywhere' => ['monthly_credits' => 4_000],
                    'serper'              => ['monthly_calls'   => 4_000],
                    'mistral'             => ['monthly_tokens'  => 5_000_000],
                    'rank_tracker'        => ['max_active_keywords' => 200],
                ],
            ],
            [
                'slug' => 'agency',
                'name' => 'Agency',
                'tagline' => 'For agencies managing many clients.',
                'price_monthly_usd' => 35,
                'price_yearly_usd' => 420,
                'trial_days' => 0,
                'max_websites' => 50,
                'max_crawl_pages' => 50000,
                'display_order' => 4,
                'is_highlighted' => false,
                'is_active' => true,
                'features' => $this->features('50 websites', '500 tracked keywords'),
                'feature_videos' => self::FEATURE_VIDEOS,
                'plan_features' => [
                    'chatbot'           => true,
                    'ai_writer'         => true,
                    'ai_inline'         => true,
                    'live_audit'        => true,
                    'hq'                => true,
                    'redirects'         => true,
                    'dashboard_widget'  => true,
                    'post_column'       => true,
                    'report_whitelabel' => true,
                ],
                'api_limits' => [
                    'keywords_everywhere' => ['monthly_credits' => 8_000],
                    'serper'              => ['monthly_calls'   => 6_000],
                    'mistral'             => ['monthly_tokens'  => 12_000_000],
                    'rank_tracker'        => ['max_active_keywords' => 500],
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
