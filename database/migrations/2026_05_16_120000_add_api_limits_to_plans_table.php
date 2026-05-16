<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-plan caps on paid external APIs. All keys nullable; null means
 * unlimited for that provider.
 *
 * Shape:
 *   {
 *     "keywords_everywhere": { "monthly_credits": int },
 *     "serper":              { "monthly_calls":   int },
 *     "mistral":             { "monthly_tokens":  int },
 *     "rank_tracker":        { "max_active_keywords": int }
 *   }
 *
 * Windows are anchored to each user's subscription start day (see
 * UsageMeter::currentWindowStart).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->json('api_limits')->nullable()->after('research_limits');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('api_limits');
        });
    }
};
