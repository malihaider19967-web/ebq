<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill the 15 new `plan_features` keys added in the Rank-Math-parity
 * roadmap onto every existing `plans` row. Pre-existing 8 keys (chatbot,
 * ai_writer, ai_inline, live_audit, hq, redirects, dashboard_widget,
 * post_column) are NOT touched — operators may have already tuned them
 * via /admin/plans/<id>/edit and we must not stomp those edits.
 *
 * The new-flag assignment per slug mirrors PlanSeeder so a freshly
 * seeded install lands in the same state as an upgraded one. Unknown
 * custom slugs (anything not in [free, pro, startup, agency]) get the
 * conservative pro-tier map so paid customers don't lose features
 * mid-roadmap.
 *
 * Idempotent: a re-run preserves operator-edited values because
 * array_replace_recursive keeps existing keys over the defaults.
 */
return new class extends Migration {
    public function up(): void
    {
        $perSlug = [
            'free' => [
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
            'pro' => [
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
            'startup' => [
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
            'agency' => [
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
        ];

        // Conservative default for custom slugs an operator may have
        // added — give them the pro-tier map so existing paid customers
        // don't suddenly lose features in the upgrade.
        $fallback = $perSlug['pro'];

        $rows = DB::table('plans')->get(['id', 'slug', 'plan_features']);
        foreach ($rows as $row) {
            $existing = is_string($row->plan_features)
                ? (json_decode($row->plan_features, true) ?: [])
                : (array) $row->plan_features;
            $base = $perSlug[$row->slug] ?? $fallback;
            // array_replace_recursive lets existing operator-edited keys
            // win over the defaults; only previously-absent keys are
            // filled in.
            $merged = array_replace($base, $existing);
            DB::table('plans')
                ->where('id', $row->id)
                ->update(['plan_features' => json_encode($merged)]);
        }
    }

    public function down(): void
    {
        // Drop the new keys from every plan_features map. Pre-existing
        // 8 keys are preserved.
        $newKeys = [
            'internal_links', 'link_genius', 'news_sitemap', 'local_multi',
            'image_bulk', 'woo_pro', 'analytics_pro', 'white_label',
            'sitewide_audit', 'role_manager', 'instant_indexing',
            'llms_txt', 'speakable', 'schema_spy', 'schema_extras',
        ];
        $rows = DB::table('plans')->get(['id', 'plan_features']);
        foreach ($rows as $row) {
            $existing = is_string($row->plan_features)
                ? (json_decode($row->plan_features, true) ?: [])
                : (array) $row->plan_features;
            foreach ($newKeys as $k) {
                unset($existing[$k]);
            }
            DB::table('plans')
                ->where('id', $row->id)
                ->update(['plan_features' => json_encode($existing)]);
        }
    }
};
