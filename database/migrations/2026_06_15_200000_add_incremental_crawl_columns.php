<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Incremental crawling support:
 *  - website_pages.content_simhash: noise-tolerant near-duplicate fingerprint
 *    (see App\Support\Crawler\SimHash) used instead of exact content_hash to
 *    decide whether a page "significantly" changed.
 *  - website_pages.consecutive_unchanged: streak of unchanged crawls; drives the
 *    adaptive recrawl interval (back off stable pages, tighten changing ones).
 *  - website_pages.sitemap_lastmod: the <lastmod> from the sitemap (was parsed
 *    then discarded); compared over time to decide early recrawls on trusted sites.
 *  - websites.sitemap_lastmod_true/false: per-site evidence of whether a lastmod
 *    bump actually predicts a content change → drives Website::sitemapLastmodTrusted().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_pages', function (Blueprint $table): void {
            $table->char('content_simhash', 16)->nullable();
            $table->unsignedSmallInteger('consecutive_unchanged')->default(0);
            $table->dateTime('sitemap_lastmod')->nullable();
        });

        Schema::table('websites', function (Blueprint $table): void {
            $table->unsignedInteger('sitemap_lastmod_true')->default(0);
            $table->unsignedInteger('sitemap_lastmod_false')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('website_pages', function (Blueprint $table): void {
            $table->dropColumn(['content_simhash', 'consecutive_unchanged', 'sitemap_lastmod']);
        });
        Schema::table('websites', function (Blueprint $table): void {
            $table->dropColumn(['sitemap_lastmod_true', 'sitemap_lastmod_false']);
        });
    }
};
