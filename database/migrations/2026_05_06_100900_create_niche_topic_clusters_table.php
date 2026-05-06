<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Niche x cluster pivot used by TopicEngine — pre-computes total demand,
 * average difficulty, and a priority score per (niche, cluster) so the
 * Topic Explorer page can render without recomputation.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('niche_topic_clusters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('niche_id')->constrained('niches')->cascadeOnDelete();
            $table->foreignId('cluster_id')->constrained('keyword_clusters')->cascadeOnDelete();
            $table->string('topic_name', 255);
            $table->unsignedBigInteger('total_search_volume')->nullable();
            $table->decimal('avg_difficulty', 6, 2)->nullable();
            $table->decimal('priority_score', 8, 3)->nullable();
            $table->timestamps();

            $table->unique(['niche_id', 'cluster_id']);
            $table->index(['niche_id', 'priority_score'], 'ntc_niche_priority_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niche_topic_clusters');
    }
};
