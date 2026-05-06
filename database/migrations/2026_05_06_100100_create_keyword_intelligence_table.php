<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-keyword aggregate that extends `keyword_metrics` (which is purely the
 * KE response cache). Holds derived signals — difficulty, SERP strength,
 * volatility, intent, last-seen timestamps — populated by EnrichKeywordJob.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('keyword_intelligence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('keyword_id')->constrained('keywords')->cascadeOnDelete();

            $table->unsignedInteger('search_volume')->nullable();
            $table->decimal('cpc', 10, 4)->nullable();
            $table->decimal('competition', 5, 4)->nullable();
            $table->string('intent', 24)->nullable();
            $table->unsignedTinyInteger('difficulty_score')->nullable();
            $table->unsignedTinyInteger('serp_strength_score')->nullable();
            $table->decimal('volatility_score', 6, 3)->nullable();

            $table->timestamp('last_serp_at')->nullable();
            $table->timestamp('last_metrics_at')->nullable();
            $table->timestamps();

            $table->unique('keyword_id');
            $table->index(['intent']);
            $table->index(['difficulty_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_intelligence');
    }
};
