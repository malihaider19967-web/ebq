<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Top-N organic + universal results for a SERP snapshot. `is_low_quality`
 * is set by SerpWeaknessEngine after ingestion (UGC domains, thin content,
 * etc.). `result_type` distinguishes organic from PAA/featured/video/image
 * /local/forum/news rows.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('serp_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('serp_snapshots')->cascadeOnDelete();
            $table->unsignedSmallInteger('rank');
            $table->string('url', 2048);
            $table->string('domain', 255);
            $table->string('title', 512)->nullable();
            $table->text('snippet')->nullable();
            $table->string('result_type', 24)->default('organic');
            $table->boolean('is_low_quality')->default(false);
            $table->timestamps();

            $table->index(['snapshot_id', 'rank'], 'serp_results_snapshot_rank_idx');
            $table->index(['domain']);
            $table->index(['result_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serp_results');
    }
};
