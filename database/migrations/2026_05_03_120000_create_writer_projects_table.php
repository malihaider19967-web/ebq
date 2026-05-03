<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persisted AI Writer projects — multi-step wizard state (topic → brief
 * → images → summary → completed). Replaces the previous stateless
 * single-screen flow so users can leave and resume mid-draft, and so
 * EBQ Content Credits can be tracked per project.
 *
 * `external_id` is the UUID surfaced to the WP plugin so internal IDs
 * stay private to Laravel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('writer_projects', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title', 300);
            $table->string('focus_keyword', 200);
            $table->json('additional_keywords')->nullable();

            $table->string('step', 16)->default('topic'); // topic|brief|images|summary|completed
            $table->json('brief')->nullable();
            $table->json('chat_history')->nullable();
            $table->json('images')->nullable();

            $table->longText('generated_html')->nullable();
            $table->unsignedBigInteger('wp_post_id')->nullable();

            $table->unsignedInteger('credits_used')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['website_id', 'user_id', 'updated_at']);
            $table->index(['website_id', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('writer_projects');
    }
};
