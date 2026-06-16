<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-run site Health Score (0-100) so the Site Health page can trend it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawl_runs', function (Blueprint $table): void {
            $table->unsignedSmallInteger('health_score')->nullable()->after('findings_total');
        });
    }

    public function down(): void
    {
        Schema::table('crawl_runs', function (Blueprint $table): void {
            $table->dropColumn('health_score');
        });
    }
};
