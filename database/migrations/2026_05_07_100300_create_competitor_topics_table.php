<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('competitor_topics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competitor_scan_id')->constrained('competitor_scans')->cascadeOnDelete();
            $table->string('name', 255);
            $table->foreignId('centroid_keyword_id')->nullable()->constrained('keywords')->nullOnDelete();
            $table->unsignedInteger('page_count')->default(0);
            $table->json('top_keyword_ids')->nullable();
            $table->timestamps();

            $table->index('competitor_scan_id');
            $table->index('centroid_keyword_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_topics');
    }
};
