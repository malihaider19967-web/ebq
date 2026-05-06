<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anonymised cross-client aggregate. NO website_id — privacy-preserving by
 * construction. `sample_site_count` lets the UI hide rows below the n>=3
 * floor enforced by NicheAggregateRecomputeService. A row may be
 * keyword-scoped (keyword_id present) or niche-scoped (keyword_id null).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('niche_aggregates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('niche_id')->constrained('niches')->cascadeOnDelete();
            $table->foreignId('keyword_id')->nullable()->constrained('keywords')->cascadeOnDelete();

            $table->json('avg_ctr_by_position')->nullable();
            $table->unsignedInteger('avg_content_length')->nullable();
            $table->unsignedInteger('avg_backlinks_estimate')->nullable();
            $table->decimal('ranking_probability_score', 5, 4)->nullable();
            $table->unsignedInteger('sample_site_count')->default(0);

            $table->timestamp('last_recomputed_at')->nullable();
            $table->timestamps();

            $table->unique(['niche_id', 'keyword_id'], 'niche_aggregates_scope_unique');
            $table->index(['niche_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niche_aggregates');
    }
};
