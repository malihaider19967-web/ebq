<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ranked competitor list per website, produced by `CompetitorDiscoveryService`:
 * for our top keywords we tally which domains recur across the SERP. One row
 * per (website, competitor_domain), upserted each run and pruned to the latest
 * `run_id`. Feeds the Keyword Gap Analysis competitor inputs + the rank tracker.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('discovered_competitors', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('website_id')->constrained()->cascadeOnDelete();

            // Normalized bare host (no scheme/www) — see CompetitorBacklink::extractDomain.
            $table->string('competitor_domain');
            // How many of the sampled SERPs this domain appeared in, out of N.
            $table->unsignedInteger('appearances')->default(0);
            $table->unsignedInteger('keywords_sampled')->default(0);
            $table->decimal('avg_position', 5, 2)->nullable();
            $table->unsignedSmallInteger('best_position')->nullable();
            // 0–100 ranked competitor score (frequency + position weighted).
            $table->decimal('score', 6, 2)->default(0);
            // Copied from the CompetitorBacklink DA cache when available (informational).
            $table->unsignedTinyInteger('domain_authority')->nullable();
            // Up to ~10 representative keywords where this domain ranked (UI tooltip).
            $table->json('sample_keywords')->nullable();

            $table->uuid('run_id');
            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamps();

            $table->unique(['website_id', 'competitor_domain']);
            $table->index(['website_id', 'run_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovered_competitors');
    }
};
