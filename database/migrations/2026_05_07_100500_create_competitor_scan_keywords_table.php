<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-(scan, seed_keyword) ranking. Captures total occurrences across all
 * crawled pages plus a top-N pages JSON so the admin/Research UI can
 * answer "which competitor pages target keyword X best?" with one query.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('competitor_scan_keywords', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competitor_scan_id')->constrained('competitor_scans')->cascadeOnDelete();
            $table->foreignId('keyword_id')->constrained('keywords')->cascadeOnDelete();
            $table->unsignedInteger('total_occurrences')->default(0);
            $table->json('top_pages_json')->nullable();
            $table->timestamps();

            $table->unique(['competitor_scan_id', 'keyword_id'], 'csk_scan_keyword_unique');
            $table->index('keyword_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_scan_keywords');
    }
};
