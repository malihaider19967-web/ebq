<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-website brand voice fingerprints for the AI Studio.
 *
 * Users paste 2–5 representative posts; the LLM extracts a structured
 * fingerprint (tone, sentence length, signature/avoid phrases, etc.)
 * which gets injected into every AI tool's system prompt. This is the
 * single biggest differentiator vs RankMath — same site, consistent
 * voice across every tool's output.
 *
 * One row per website (unique constraint). Re-uploading samples
 * overwrites the existing row in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_voice_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->unique()->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('samples_count')->default(0);

            // Fingerprint shape:
            //   { tone: string, person: 'first'|'second'|'third',
            //     avg_sentence_words: int, vocabulary_band: string,
            //     formality_score: int (0-100),
            //     signature_phrases: string[], avoid_phrases: string[],
            //     opening_patterns: string[], closing_patterns: string[],
            //     hooks_used: string[], summary: string }
            $table->json('fingerprint')->nullable();

            $table->text('sample_excerpt')->nullable();

            $table->timestamp('last_extracted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_voice_profiles');
    }
};
