<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Continuous-research queue. Every domain we want to scrape — manually
 * seeded, auto-discovered from a website's SERP, harvested from past
 * scan outlinks — lives here as a `research_targets` row. The
 * `ebq:research-scan-next` schedule picks the highest-priority queued
 * row and dispatches a CompetitorScan against it.
 *
 * Source taxonomy:
 *   manual              admin entered the domain by hand
 *   website-onboarding  derived from a Website row's GSC data
 *   serp-competitor     showed up as a top-N SERP rank for one of our keywords
 *   outlink             linked-to from another scanned site
 *   user-supplied       admin-typed seed keyword resolved to this domain
 *
 * `priority` is a coarse 0..100 dial: 100 = scrape next, 0 = parked.
 * `next_scan_at` lets the weekly re-scan job stagger work.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('research_targets', function (Blueprint $table): void {
            $table->id();
            $table->string('domain', 255);
            $table->string('root_url', 2048)->nullable();
            $table->string('source', 32)->default('manual');
            $table->unsignedTinyInteger('priority')->default(50);
            $table->string('status', 16)->default('queued');
            $table->foreignId('attached_website_id')->nullable()->constrained('websites')->nullOnDelete();
            $table->foreignId('last_scan_id')->nullable()->constrained('competitor_scans')->nullOnDelete();
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamp('next_scan_at')->nullable();
            $table->unsignedInteger('total_scans')->default(0);
            $table->json('seed_keywords')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('domain');
            $table->index(['status', 'priority', 'next_scan_at'], 'rt_status_priority_idx');
            $table->index(['source', 'status']);
            $table->index(['attached_website_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_targets');
    }
};
