<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crawled pages for a website. Distinct from `page_audit_reports`
 * (one row per audit run) — this is one row per URL, refreshed by
 * CrawlWebsitePagesJob. body_text is longtext; headings is JSON.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('website_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('url_hash', 64);
            $table->string('title', 512)->nullable();
            $table->unsignedInteger('content_length')->nullable();
            $table->json('headings_json')->nullable();
            $table->longText('body_text')->nullable();
            $table->timestamp('last_crawled_at')->nullable();
            $table->timestamps();

            $table->unique(['website_id', 'url_hash'], 'website_pages_site_url_unique');
            $table->index(['website_id', 'last_crawled_at'], 'website_pages_site_crawled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_pages');
    }
};
