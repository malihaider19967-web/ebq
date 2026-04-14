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
        Schema::create('backlinks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->date('tracked_date');
            $table->text('referring_page_url');
            $table->text('target_page_url');
            $table->unsignedTinyInteger('domain_authority')->nullable();
            $table->unsignedTinyInteger('spam_score')->nullable();
            $table->string('anchor_text')->nullable();
            $table->string('type', 64);
            $table->boolean('is_dofollow')->default(true);
            $table->timestamps();

            $table->index(['website_id', 'tracked_date']);
            $table->index(['website_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backlinks');
    }
};
