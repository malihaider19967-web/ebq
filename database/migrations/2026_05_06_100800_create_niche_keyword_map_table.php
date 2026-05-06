<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('niche_keyword_map', function (Blueprint $table): void {
            $table->foreignId('niche_id')->constrained('niches')->cascadeOnDelete();
            $table->foreignId('keyword_id')->constrained('keywords')->cascadeOnDelete();
            $table->decimal('relevance_score', 5, 4)->default(0);
            $table->timestamps();

            $table->primary(['niche_id', 'keyword_id']);
            $table->index(['keyword_id']);
            $table->index(['niche_id', 'relevance_score'], 'nkm_niche_relevance_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niche_keyword_map');
    }
};
