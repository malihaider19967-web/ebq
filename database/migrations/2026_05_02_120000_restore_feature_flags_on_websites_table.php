<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restore the per-website `feature_flags` JSON column.
 *
 * The per-user-billing migration earlier in the same release dropped
 * this column on the assumption that user-level plans alone would
 * drive feature gating. In practice admins want per-site overrides
 * (e.g. enable AI Writer for client A but not client B even though
 * both are on Pro), so we add the column back.
 *
 * Storage shape: nullable JSON keyed by feature slug, values are
 * booleans. Empty / null → website inherits the global defaults
 * AND'd against the global kill-switch (matches Website::FEATURE_DEFAULTS).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('websites', 'feature_flags')) {
            Schema::table('websites', function (Blueprint $table) {
                $table->json('feature_flags')->nullable()->after('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            if (Schema::hasColumn('websites', 'feature_flags')) {
                $table->dropColumn('feature_flags');
            }
        });
    }
};
