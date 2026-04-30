<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-website feature toggles for the WordPress plugin.
 *
 * Storage: nullable JSON column on `websites`. Each row is the canonical
 * `EBQ_Feature_Flags::KNOWN_FEATURES` map (chatbot, ai_writer, ai_inline,
 * live_audit, hq, redirects, dashboard_widget, post_column) with values
 * being booleans. Missing keys mean "default for that feature" (true).
 *
 * NULL column → no overrides → plugin sees all-true on this website.
 * `{"chatbot": false}` → only chatbot disabled, everything else default.
 *
 * Read by `PluginInsightsController::websiteFeatures()` and merged into
 * the response sent to the WP plugin's `EBQ_Feature_Flags::store()`,
 * which caches in a 12-hour transient client-side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            // JSON because Laravel handles cast → array natively across
            // MySQL 5.7+ / MariaDB 10.2+ / Postgres / SQLite. Nullable so
            // existing rows don't need a backfill — null reads as "no
            // overrides set" upstream.
            $table->json('feature_flags')->nullable()->after('tier');
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            $table->dropColumn('feature_flags');
        });
    }
};
