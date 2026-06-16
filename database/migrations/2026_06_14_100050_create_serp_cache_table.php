<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global, cross-client cache of live organic SERP results, keyed on
 * (query_hash, gl). A SERP — who ranks where for a keyword in a country — is a
 * client-agnostic fact, so one fetch serves every client (and every competitive
 * feature: gap verification, opportunity scoring, competitor discovery) until it
 * goes stale. Mirrors the "shared cache, never re-bill on fresh" pattern of
 * keyword_metrics / competitor_backlinks. Rankings shift faster than search
 * volume, so the default TTL is shorter (7 days).
 *
 * (Distinct from the snapshot-oriented `serp_results` table used by the SERP
 * feature tracker — this is a lightweight query→payload lookup cache.)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('serp_cache', function (Blueprint $table): void {
            $table->id();
            $table->string('query_hash', 64);
            $table->string('query');
            $table->string('gl', 2);
            // Normalized slice: { organic: [{position, link, domain}], answerBox?, ... }
            $table->json('payload');
            $table->timestamp('fetched_at');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['query_hash', 'gl']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serp_cache');
    }
};
