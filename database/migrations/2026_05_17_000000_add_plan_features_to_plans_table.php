<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan-level toggles for the 8 plugin feature flags.
 *
 * Shape (boolean map; missing key = false):
 *   {
 *     "chatbot":          bool,
 *     "ai_writer":        bool,
 *     "ai_inline":        bool,
 *     "live_audit":       bool,
 *     "hq":               bool,
 *     "redirects":        bool,
 *     "dashboard_widget": bool,
 *     "post_column":      bool
 *   }
 *
 * The previously-existing `features` column stays — it now serves as
 * the marketing-bullet text on the public pricing card. `plan_features`
 * is the authoritative entitlement matrix that the WP plugin honours.
 *
 * Per-website overrides in `websites.feature_flags` can still narrow
 * (turn off) a flag a user's plan allows, but cannot widen one the
 * plan disallows — the read-time clamp lives in
 * Website::effectiveFeatureFlags().
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->json('plan_features')->nullable()->after('api_limits');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('plan_features');
        });
    }
};
