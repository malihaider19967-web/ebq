<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compact per-page SEO signal bag the issue detector reads: h1 count,
 * heading-order, image/alt counts, schema types, OG/Twitter tag counts, and a
 * capped sample of external link targets (for the broken-external-link pass).
 * Additive + nullable — safe on the production table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_pages', function (Blueprint $table): void {
            $table->json('seo_signals')->nullable()->after('headings_json');
        });
    }

    public function down(): void
    {
        Schema::table('website_pages', function (Blueprint $table): void {
            $table->dropColumn('seo_signals');
        });
    }
};
