<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One competitor auto-discovery run per website. Tracks lifecycle + a small
 * cost ledger (how many SERP calls the fan-out made) so the service can enforce
 * a refresh cadence and never re-bill SERP within the configured window.
 *
 * Pairs with `discovered_competitors` (the ranked output, grouped by `run_id`).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('competitor_discovery_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->uuid('run_id')->unique();
            $table->foreignUlid('website_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();

            // queued | running | completed | failed
            $table->string('status', 16)->default('queued');
            // How many keywords we planned to scan vs SERP calls actually made.
            $table->unsignedInteger('keywords_planned')->default(0);
            $table->unsignedInteger('serp_calls_made')->default(0);
            // 'gsc' (top queries) | 'manual' (user-entered seeds).
            $table->string('seed_source', 16)->nullable();
            $table->string('error')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['website_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_discovery_runs');
    }
};
