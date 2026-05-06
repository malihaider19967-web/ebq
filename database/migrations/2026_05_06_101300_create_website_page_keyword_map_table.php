<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Page x keyword association with provenance: gsc (resolved from
 * search_console_data), content (extracted from page text), or brief
 * (declared in a content brief). Carries the 30-day GSC numbers so
 * coverage / opportunity views avoid joining the raw GSC table.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('website_page_keyword_map', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained('website_pages')->cascadeOnDelete();
            $table->foreignId('keyword_id')->constrained('keywords')->cascadeOnDelete();
            $table->string('source', 16)->default('gsc');
            $table->decimal('position_avg', 6, 2)->nullable();
            $table->unsignedInteger('clicks_30d')->nullable();
            $table->unsignedInteger('impressions_30d')->nullable();
            $table->timestamps();

            $table->unique(['page_id', 'keyword_id', 'source'], 'wpkm_page_keyword_source_unique');
            $table->index(['keyword_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_page_keyword_map');
    }
};
