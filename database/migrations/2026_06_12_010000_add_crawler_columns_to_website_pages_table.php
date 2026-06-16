<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive columns turning the (previously-scaffolded, never-populated)
 * website_pages table into a full crawl inventory: HTTP/indexability state,
 * conditional-GET caches (etag / last-modified / content hash), the internal
 * link-graph derived counts (inbound/outbound/click-depth), discovery source,
 * and the recrawl staleness scheduler (next_crawl_at). All nullable/defaulted
 * so this is safe on the already-migrated production table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_pages', function (Blueprint $table): void {
            $table->unsignedSmallInteger('http_status')->nullable()->after('title');
            $table->string('meta_description', 1024)->nullable()->after('http_status');
            $table->string('canonical_url', 2048)->nullable()->after('meta_description');
            $table->boolean('is_indexable')->default(true)->after('canonical_url');
            $table->string('robots_directives', 255)->nullable()->after('is_indexable');
            $table->string('redirect_target', 2048)->nullable()->after('robots_directives');
            $table->char('content_hash', 40)->nullable()->after('redirect_target');
            $table->string('etag', 255)->nullable()->after('content_hash');
            $table->string('last_modified_header', 255)->nullable()->after('etag');
            $table->unsignedInteger('word_count')->nullable()->after('content_length');
            $table->unsignedInteger('internal_link_count')->default(0)->after('word_count');
            $table->unsignedInteger('external_link_count')->default(0)->after('internal_link_count');
            $table->unsignedInteger('inbound_link_count')->default(0)->after('external_link_count');
            $table->unsignedSmallInteger('click_depth')->nullable()->after('inbound_link_count');
            $table->boolean('source_gsc')->default(false)->after('click_depth');
            $table->boolean('source_sitemap')->default(false)->after('source_gsc');
            $table->unsignedSmallInteger('page_score')->nullable()->after('source_sitemap');
            $table->string('http_error', 255)->nullable()->after('page_score');
            $table->timestamp('discovered_at')->nullable()->after('http_error');
            $table->timestamp('last_changed_at')->nullable()->after('discovered_at');
            $table->timestamp('next_crawl_at')->nullable()->after('last_changed_at');
            $table->timestamp('removed_at')->nullable()->after('next_crawl_at');

            $table->index(['website_id', 'next_crawl_at'], 'website_pages_site_next_crawl_idx');
            $table->index(['website_id', 'is_indexable'], 'website_pages_site_indexable_idx');
            $table->index(['website_id', 'inbound_link_count'], 'website_pages_site_inbound_idx');
        });
    }

    public function down(): void
    {
        Schema::table('website_pages', function (Blueprint $table): void {
            $table->dropIndex('website_pages_site_next_crawl_idx');
            $table->dropIndex('website_pages_site_indexable_idx');
            $table->dropIndex('website_pages_site_inbound_idx');
            $table->dropColumn([
                'http_status', 'meta_description', 'canonical_url', 'is_indexable',
                'robots_directives', 'redirect_target', 'content_hash', 'etag',
                'last_modified_header', 'word_count', 'internal_link_count',
                'external_link_count', 'inbound_link_count', 'click_depth',
                'source_gsc', 'source_sitemap', 'page_score', 'http_error',
                'discovered_at', 'last_changed_at', 'next_crawl_at', 'removed_at',
            ]);
        });
    }
};
