<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('competitor_outlinks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competitor_scan_id')->constrained('competitor_scans')->cascadeOnDelete();
            $table->foreignId('from_page_id')->constrained('competitor_pages')->cascadeOnDelete();
            $table->string('to_url', 2048);
            $table->string('to_url_hash', 64);
            $table->string('to_domain', 255);
            $table->string('anchor_text', 512)->nullable();
            $table->boolean('is_external')->default(false);
            $table->timestamps();

            $table->index(['competitor_scan_id', 'is_external']);
            $table->index('to_domain');
            $table->index('from_page_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_outlinks');
    }
};
