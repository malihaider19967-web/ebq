<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('competitor_topic_pages', function (Blueprint $table): void {
            $table->foreignId('competitor_topic_id')->constrained('competitor_topics')->cascadeOnDelete();
            $table->foreignId('competitor_page_id')->constrained('competitor_pages')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['competitor_topic_id', 'competitor_page_id'], 'competitor_topic_pages_pk');
            $table->index('competitor_page_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_topic_pages');
    }
};
