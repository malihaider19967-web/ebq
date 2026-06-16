<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical crawl entity: one row per normalized domain. Many users' websites
 * (websites.crawl_site_id) share one crawl, fetched once at the MAX page cap
 * among subscribers (effective_cap). Domain-level crawl signals that used to
 * live on `websites` (crawl protection + sitemap-lastmod trust) move here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawl_sites', function (Blueprint $table): void {
            $table->id();
            $table->string('normalized_domain')->unique();
            $table->unsignedInteger('effective_cap')->default(0);
            $table->unsignedSmallInteger('health_score')->nullable();
            $table->string('status', 16)->default('pending'); // pending|crawling|ready|blocked
            $table->unsignedInteger('subscriber_count')->default(0);

            // Moved off `websites` (domain-level, not per-user).
            $table->string('crawl_protection')->nullable();
            $table->timestamp('crawl_protection_at')->nullable();
            $table->unsignedInteger('sitemap_lastmod_true')->default(0);
            $table->unsignedInteger('sitemap_lastmod_false')->default(0);

            $table->timestamp('last_crawl_started_at')->nullable();
            $table->timestamp('last_crawl_finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_sites');
    }
};
