<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable `research_limits` JSON column to `plans`. Phase-4 quota
 * lookups consult this first; null falls back to config('services.research.limits').
 *
 * Shape:
 *   { "keyword_lookup": int, "serp_fetch": int, "llm_call": int, "brief": int }
 *
 * Any subset is acceptable — missing keys fall back per-resource.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->json('research_limits')->nullable()->after('features');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('research_limits');
        });
    }
};
