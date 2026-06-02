<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional explainer video per marketing bullet.
 *
 * `features` stays a flat list of bullet-copy strings (consumed as-is by
 * the public /api/v1/plans endpoint and the WP wizard). This column holds
 * a sparse map from the bullet's position in that list to a YouTube URL:
 *
 *   { "0": "https://youtu.be/abc12345678", "3": "https://youtu.be/..." }
 *
 * Only bullets that have a video appear in the map; a missing index means
 * "no video". Position-keyed (not text-keyed) because the admin edit form
 * round-trips both arrays from the same newline textarea in one pass, so
 * the indexes always stay aligned with `features`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->json('feature_videos')->nullable()->after('features');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('feature_videos');
        });
    }
};
