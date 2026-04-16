<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('page_indexing_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('page', 2048);
            $table->timestamp('last_reindex_requested_at')->nullable();
            $table->timestamp('last_google_status_checked_at')->nullable();
            $table->string('google_verdict')->nullable();
            $table->string('google_coverage_state')->nullable();
            $table->string('google_indexing_state')->nullable();
            $table->timestamp('google_last_crawl_at')->nullable();
            $table->json('google_status_payload')->nullable();
            $table->timestamps();

            $table->unique(['website_id', 'page']);
            $table->index(['website_id', 'last_google_status_checked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_indexing_statuses');
    }
};
