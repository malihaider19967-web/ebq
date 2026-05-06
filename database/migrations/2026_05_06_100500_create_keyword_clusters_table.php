<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cluster of related keywords. Hierarchical via parent_cluster_id so a
 * "running shoes" parent can have "trail running shoes" / "best running
 * shoes for flat feet" sub-clusters. centroid_keyword_id is the
 * representative keyword chosen by ClusteringService.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('keyword_clusters', function (Blueprint $table): void {
            $table->id();
            $table->string('cluster_name', 255);
            $table->foreignId('parent_cluster_id')
                ->nullable()
                ->constrained('keyword_clusters')
                ->nullOnDelete();
            $table->foreignId('centroid_keyword_id')
                ->nullable()
                ->constrained('keywords')
                ->nullOnDelete();
            $table->string('signal', 24)->default('serp_overlap');
            $table->timestamp('last_recomputed_at')->nullable();
            $table->timestamps();

            $table->index(['parent_cluster_id']);
            $table->index(['centroid_keyword_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_clusters');
    }
};
