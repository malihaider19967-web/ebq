<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single source of truth for keyword text. Global (no website_id) — every
 * client that ever asks about "best running shoes / us" reads the same row.
 * Embeddings column is intentionally nullable so phase-1 ships without it.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('keywords', function (Blueprint $table): void {
            $table->id();
            $table->string('query', 512);
            $table->string('normalized_query', 512);
            $table->string('query_hash', 64);
            $table->string('language', 8)->default('en');
            $table->string('country', 16)->default('global');
            $table->binary('embedding')->nullable();
            $table->timestamps();

            $table->unique(['query_hash', 'country', 'language'], 'keywords_hash_country_lang_unique');
            $table->index(['country', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keywords');
    }
};
