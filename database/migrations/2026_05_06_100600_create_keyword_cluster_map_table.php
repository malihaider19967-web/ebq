<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Many-to-many: a keyword can sit in multiple clusters with different
 * confidences (rare but supported by the doc's clustering design).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('keyword_cluster_map', function (Blueprint $table): void {
            $table->foreignId('keyword_id')->constrained('keywords')->cascadeOnDelete();
            $table->foreignId('cluster_id')->constrained('keyword_clusters')->cascadeOnDelete();
            $table->decimal('confidence', 5, 4)->default(1);
            $table->timestamps();

            $table->primary(['keyword_id', 'cluster_id']);
            $table->index(['cluster_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_cluster_map');
    }
};
