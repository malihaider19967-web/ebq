<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pages crawled in a competitor scan. Distinct from website_pages
 * (client-private content) — kept separate so the privacy invariant
 * from PrivacyIsolationTest keeps holding.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('competitor_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competitor_scan_id')->constrained('competitor_scans')->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('url_hash', 64);
            $table->string('domain', 255);
            $table->string('title', 512)->nullable();
            $table->string('meta_description', 1024)->nullable();
            $table->json('headings_json')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->longText('body_text')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedSmallInteger('depth')->default(0);
            $table->boolean('is_external')->default(false);
            $table->json('seed_keyword_coverage')->nullable();
            $table->timestamps();

            $table->unique(['competitor_scan_id', 'url_hash'], 'competitor_pages_scan_url_unique');
            $table->index('domain');
            $table->index(['competitor_scan_id', 'is_external']);
            $table->index(['competitor_scan_id', 'depth']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_pages');
    }
};
