<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-label, weighted niche assignment per website. `is_primary` flags
 * the highest-weight row; `source` distinguishes auto-classified from
 * user-edited from hybrid (auto + user accept).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('website_niche_map', function (Blueprint $table): void {
            $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
            $table->foreignId('niche_id')->constrained('niches')->cascadeOnDelete();
            $table->decimal('weight', 5, 4)->default(0);
            $table->boolean('is_primary')->default(false);
            $table->string('source', 16)->default('auto');
            $table->decimal('confidence', 5, 4)->nullable();
            $table->timestamp('last_classified_at')->nullable();
            $table->timestamps();

            $table->primary(['website_id', 'niche_id']);
            $table->index(['niche_id']);
            $table->index(['website_id', 'is_primary'], 'wnm_website_primary_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_niche_map');
    }
};
