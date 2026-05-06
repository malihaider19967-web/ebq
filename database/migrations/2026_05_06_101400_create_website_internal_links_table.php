<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Discovered + suggested internal links per website. Powers the
 * InternalLinkSuggestions Livewire — proposed edges live here with
 * status='suggested' until the user accepts/rejects them.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('website_internal_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
            $table->foreignId('from_page_id')->constrained('website_pages')->cascadeOnDelete();
            $table->foreignId('to_page_id')->constrained('website_pages')->cascadeOnDelete();
            $table->string('anchor_text', 512)->nullable();
            $table->string('status', 16)->default('discovered');
            $table->timestamp('discovered_at')->nullable();
            $table->timestamps();

            $table->index(['website_id', 'status']);
            $table->index(['from_page_id']);
            $table->index(['to_page_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_internal_links');
    }
};
