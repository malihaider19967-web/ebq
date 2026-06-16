<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compact, language-agnostic significant-terms per page (JSON: weighted-TF term
 * candidates + bigram phrases), computed during the crawl. Replaces full
 * body_text as the input to the internal-link suggester (term-overlap matching),
 * which lets body_text be pruned. ~250 B/page vs ~3–28 KB of body text.
 * Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_pages', function (Blueprint $table): void {
            $table->text('content_terms')->nullable()->after('seo_signals');
        });
    }

    public function down(): void
    {
        Schema::table('website_pages', function (Blueprint $table): void {
            $table->dropColumn('content_terms');
        });
    }
};
