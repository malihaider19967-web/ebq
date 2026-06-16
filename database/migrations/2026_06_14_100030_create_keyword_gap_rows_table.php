<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The diffed output of a Keyword Gap Analysis — one row per keyword, bucketed
 * into missing / weak / strength (or missing / shared when the site has no GSC
 * positions). Enriched with cached volume/competition/cpc and an opportunity
 * score. Cheap to re-bucket in place when GSC connects later (reprocessing).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('keyword_gap_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('keyword_gap_analysis_id')->constrained()->cascadeOnDelete();

            $table->string('keyword');
            $table->string('keyword_hash', 64);
            // missing | weak | strength | shared
            $table->string('bucket', 12);

            $table->unsignedInteger('search_volume')->nullable();
            $table->decimal('competition', 5, 4)->nullable();
            $table->decimal('cpc', 8, 2)->nullable();
            $table->decimal('our_position', 6, 2)->nullable();
            // Which competitor domain(s) surfaced this keyword.
            $table->json('competitor_domains')->nullable();

            $table->unsignedTinyInteger('opportunity_score')->nullable();
            // Transparent breakdown behind the score (for the UI tooltip).
            $table->json('score_components')->nullable();

            $table->timestamps();

            $table->index(['keyword_gap_analysis_id', 'bucket']);
            $table->index('keyword_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_gap_rows');
    }
};
