<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Move billing from per-website to per-user.
 *
 * Drops the Cashier columns (stripe_id, pm_type, pm_last_four,
 * trial_ends_at) from `websites`, plus the `tier` column (now derived
 * from the owning user's plan) and the per-website `feature_flags`
 * JSON override (per-user plan controls features now; per-site
 * overrides become a separate admin-only feature later).
 *
 * Down: re-adds the columns nullable so a rollback doesn't lose the
 * schema, but the data won't return.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            if (Schema::hasColumn('websites', 'stripe_id')) {
                $table->dropColumn('stripe_id');
            }
        });
        Schema::table('websites', function (Blueprint $table) {
            if (Schema::hasColumn('websites', 'pm_type')) {
                $table->dropColumn('pm_type');
            }
            if (Schema::hasColumn('websites', 'pm_last_four')) {
                $table->dropColumn('pm_last_four');
            }
            if (Schema::hasColumn('websites', 'trial_ends_at')) {
                $table->dropColumn('trial_ends_at');
            }
            if (Schema::hasColumn('websites', 'tier')) {
                $table->dropColumn('tier');
            }
            if (Schema::hasColumn('websites', 'feature_flags')) {
                $table->dropColumn('feature_flags');
            }
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->string('stripe_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('tier', 16)->default('free');
            $table->json('feature_flags')->nullable();
        });
    }
};
