<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unified SEO issue catalog produced by the crawler — the single source the
 * Priority Action Queue, Site Health page, growth reports, and AI context all
 * read. Idempotent: upsert by (website_id, type, affected_url_hash); issues no
 * longer seen on a later crawl get resolved_at set (self-healing history).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawl_findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->nullable()->constrained('website_pages')->nullOnDelete();
            $table->foreignId('crawl_run_id')->nullable()->constrained('crawl_runs')->nullOnDelete();
            $table->string('category', 32);   // broken_link|redirect|onpage|indexability|internal_links|sitemap|schema|performance|security|crawlability
            $table->string('type', 48);       // broken_internal|dup_title|missing_h1|noindex_important|orphan_page|...
            $table->string('severity', 12)->default('low'); // critical|high|medium|low
            $table->double('impact')->default(0);
            $table->string('affected_url', 2048);
            $table->char('affected_url_hash', 64);
            $table->json('detail')->nullable();
            $table->string('status', 12)->default('open'); // open|resolved|ignored
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['website_id', 'type', 'affected_url_hash'], 'crawl_findings_uniq');
            $table->index(['website_id', 'category', 'status']);
            $table->index(['website_id', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_findings');
    }
};
