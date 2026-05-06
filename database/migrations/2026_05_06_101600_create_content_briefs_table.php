<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persisted content briefs produced by ContentBriefGenerator (Livewire) /
 * ContentBriefService. Payload holds title candidates, H2/H3 outline,
 * keyword usage, PAA-derived questions, target word count.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('content_briefs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
            $table->foreignId('keyword_id')->constrained('keywords')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload');
            $table->timestamps();

            $table->index(['website_id', 'created_at']);
            $table->index(['keyword_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_briefs');
    }
};
