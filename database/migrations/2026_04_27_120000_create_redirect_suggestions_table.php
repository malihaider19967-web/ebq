<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI-suggested redirects derived from 404 logs sent up by the WP plugin.
 *
 * Each row maps a 404 source path on the user's site to a suggested
 * destination — picked by an LLM matching the missing slug against the
 * site's existing posts (titles + URLs). The user can accept, reject, or
 * edit before applying the redirect rule on the WP side.
 *
 * Lifecycle:
 *   pending  → created when matched, awaiting human review in HQ
 *   applied  → user accepted; WP plugin pulls and writes a 301 rule
 *   rejected → user dismissed; we won't re-match this 404 for 30 days
 *   stale    → original 404 hasn't been seen in 30 days; auto-cleared
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirect_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('source_path', 700);
            $table->char('source_path_hash', 64)->index();
            $table->string('suggested_destination', 700);
            $table->unsignedTinyInteger('confidence')->default(0); // 0-100
            $table->string('status', 16)->default('pending')->index();
            $table->text('rationale')->nullable();
            $table->unsignedInteger('hits_30d')->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique(['website_id', 'source_path_hash'], 'redirect_suggestions_site_source_unique');
            $table->index(['website_id', 'status', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirect_suggestions');
    }
};
