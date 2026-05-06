<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hierarchical niche taxonomy. Curated rows ship via NicheTaxonomySeeder
 * with is_dynamic=false; auto-discovered rows from
 * DiscoverEmergingNichesJob carry is_dynamic=true and need admin review
 * before they are eligible for assignment.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('niches', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 128);
            $table->string('name', 255);
            $table->foreignId('parent_id')->nullable()->constrained('niches')->nullOnDelete();
            $table->boolean('is_dynamic')->default(false);
            $table->boolean('is_approved')->default(true);
            $table->binary('embedding')->nullable();
            $table->timestamps();

            $table->unique('slug');
            $table->index(['parent_id']);
            $table->index(['is_dynamic', 'is_approved']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niches');
    }
};
