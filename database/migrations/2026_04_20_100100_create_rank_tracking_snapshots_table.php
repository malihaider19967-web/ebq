<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rank_tracking_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rank_tracking_keyword_id')
                ->constrained('rank_tracking_keywords')
                ->cascadeOnDelete();

            $table->dateTime('checked_at');
            $table->unsignedInteger('position')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('title', 512)->nullable();
            $table->text('snippet')->nullable();

            $table->unsignedInteger('total_results')->nullable();
            $table->decimal('search_time', 8, 3)->nullable();

            $table->json('serp_features')->nullable();
            $table->json('competitor_positions')->nullable();
            $table->json('top_results')->nullable();
            $table->json('related_searches')->nullable();
            $table->json('people_also_ask')->nullable();

            $table->string('status', 32)->default('ok');
            $table->text('error')->nullable();
            $table->boolean('forced')->default(false);

            $table->timestamps();

            $table->index(['rank_tracking_keyword_id', 'checked_at'], 'rts_keyword_checked_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rank_tracking_snapshots');
    }
};
