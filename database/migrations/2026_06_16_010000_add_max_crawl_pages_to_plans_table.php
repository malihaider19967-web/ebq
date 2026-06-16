<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-plan crawl page cap. Bounds how many pages a single crawl run fetches for
 * a website on this plan (the crawler crawls the highest-value pages first —
 * GSC-trafficked → sitemap → shallow). NULL = unlimited (falls back to the
 * global crawler.max_pages_per_run). Mirrors max_websites. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedInteger('max_crawl_pages')->nullable()->after('max_websites');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('max_crawl_pages');
        });
    }
};
