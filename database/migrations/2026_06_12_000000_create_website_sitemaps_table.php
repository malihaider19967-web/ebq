<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_sitemaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('path', 700);                 // The sitemap URL
            // Where this row came from: 'gsc' (pulled from Search Console)
            // or 'manual' (a client added it on the Sitemaps page).
            $table->string('source', 16)->default('manual');
            $table->string('type', 32)->nullable();      // e.g. SITEMAP, SITEMAP_INDEX
            $table->boolean('is_pending')->default(false);
            $table->boolean('is_sitemaps_index')->default(false);
            $table->unsignedInteger('errors')->default(0);
            $table->unsignedInteger('warnings')->default(0);
            $table->unsignedInteger('submitted_urls')->nullable();
            $table->unsignedInteger('indexed_urls')->nullable();
            $table->timestamp('last_submitted_at')->nullable();
            $table->timestamp('last_downloaded_at')->nullable();
            $table->timestamp('last_synced_at')->nullable(); // when we last pulled it from GSC
            $table->timestamps();
            $table->unique(['website_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_sitemaps');
    }
};
