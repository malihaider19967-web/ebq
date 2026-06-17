<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-key the crawl tables onto the shared crawl_site. Adds crawl_site_id to the
 * four crawl tables + websites, value_rank to website_pages, and normalized_domain
 * to websites. The crawl tables' website_id FK is dropped (shared rows are no
 * longer owned by one website — deleting a subscriber must NOT cascade-delete the
 * shared crawl) and the column made nullable; it is removed entirely in the later
 * cleanup phase. The previous crawl data is cleared (rebuild — it had no
 * crawl_site_id) and regenerates on the next crawl; ONLY the four crawl tables are
 * touched (CLAUDE.md scope guard), never the whole DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            $table->foreignUlid('crawl_site_id')->nullable()->after('id')->constrained('crawl_sites')->nullOnDelete();
            $table->string('normalized_domain')->nullable()->after('domain');
            $table->index('normalized_domain', 'websites_normalized_domain_idx');
        });

        // Clear the old (website_id-keyed) crawl data before re-keying — children first.
        foreach (['crawl_findings', 'website_internal_links', 'crawl_runs', 'website_pages'] as $t) {
            DB::table($t)->delete();
        }

        Schema::table('website_pages', function (Blueprint $table): void {
            $table->foreignUlid('crawl_site_id')->nullable()->after('website_id')->constrained('crawl_sites')->nullOnDelete();
            $table->unsignedInteger('value_rank')->nullable()->after('page_score');
            $table->dropForeign(['website_id']);
            $table->ulid('website_id')->nullable()->change();
            $table->unique(['crawl_site_id', 'url_hash'], 'website_pages_crawlsite_url_unique');
            $table->index(['crawl_site_id', 'last_crawled_at'], 'website_pages_cs_crawled_idx');
            $table->index(['crawl_site_id', 'next_crawl_at'], 'website_pages_cs_next_crawl_idx');
            $table->index(['crawl_site_id', 'is_indexable'], 'website_pages_cs_indexable_idx');
            $table->index(['crawl_site_id', 'inbound_link_count'], 'website_pages_cs_inbound_idx');
            $table->index(['crawl_site_id', 'value_rank'], 'website_pages_cs_rank_idx');
        });

        Schema::table('website_internal_links', function (Blueprint $table): void {
            $table->foreignUlid('crawl_site_id')->nullable()->after('website_id')->constrained('crawl_sites')->nullOnDelete();
            $table->dropForeign(['website_id']);
            $table->ulid('website_id')->nullable()->change();
            $table->index(['crawl_site_id', 'status'], 'wil_cs_status_idx');
        });

        Schema::table('crawl_runs', function (Blueprint $table): void {
            $table->foreignUlid('crawl_site_id')->nullable()->after('website_id')->constrained('crawl_sites')->nullOnDelete();
            $table->dropForeign(['website_id']);
            $table->ulid('website_id')->nullable()->change();
            $table->index(['crawl_site_id', 'started_at'], 'crawl_runs_cs_started_idx');
            $table->index(['crawl_site_id', 'status'], 'crawl_runs_cs_status_idx');
        });

        Schema::table('crawl_findings', function (Blueprint $table): void {
            $table->foreignUlid('crawl_site_id')->nullable()->after('website_id')->constrained('crawl_sites')->nullOnDelete();
            $table->dropForeign(['website_id']);
            $table->ulid('website_id')->nullable()->change();
            $table->unique(['crawl_site_id', 'type', 'affected_url_hash'], 'crawl_findings_cs_uniq');
            $table->index(['crawl_site_id', 'category', 'status', 'impact'], 'cf_cs_issue_default_idx');
            $table->index(['crawl_site_id', 'category', 'status', 'type', 'impact'], 'cf_cs_issue_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('crawl_findings', function (Blueprint $table): void {
            $table->dropIndex('cf_cs_issue_default_idx');
            $table->dropIndex('cf_cs_issue_type_idx');
            $table->dropUnique('crawl_findings_cs_uniq');
            $table->dropConstrainedForeignId('crawl_site_id');
        });
        Schema::table('crawl_runs', function (Blueprint $table): void {
            $table->dropIndex('crawl_runs_cs_started_idx');
            $table->dropIndex('crawl_runs_cs_status_idx');
            $table->dropConstrainedForeignId('crawl_site_id');
        });
        Schema::table('website_internal_links', function (Blueprint $table): void {
            $table->dropIndex('wil_cs_status_idx');
            $table->dropConstrainedForeignId('crawl_site_id');
        });
        Schema::table('website_pages', function (Blueprint $table): void {
            $table->dropUnique('website_pages_crawlsite_url_unique');
            $table->dropIndex('website_pages_cs_crawled_idx');
            $table->dropIndex('website_pages_cs_next_crawl_idx');
            $table->dropIndex('website_pages_cs_indexable_idx');
            $table->dropIndex('website_pages_cs_inbound_idx');
            $table->dropIndex('website_pages_cs_rank_idx');
            $table->dropConstrainedForeignId('crawl_site_id');
            $table->dropColumn('value_rank');
        });
        Schema::table('websites', function (Blueprint $table): void {
            $table->dropIndex('websites_normalized_domain_idx');
            $table->dropConstrainedForeignId('crawl_site_id');
            $table->dropColumn('normalized_domain');
        });
    }
};
