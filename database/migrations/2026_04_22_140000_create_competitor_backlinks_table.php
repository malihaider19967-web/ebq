<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache of backlinks pointing to competitor domains we surface in audit SERP
 * benchmarks. Keyed on (competitor_domain, referring_page_url) so we never
 * double-count the same link.
 *
 * Not scoped to website_id — a competitor domain's backlinks are universal,
 * so a single cache row serves every audit on every site that mentions that
 * competitor. Saves credits and storage.
 *
 * Freshness: every row gets a fetched_at / expires_at stamp (default 30 days).
 * The service treats a competitor_domain as "fresh" when any row for it has
 * expires_at > now().
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('competitor_backlinks', function (Blueprint $table): void {
            $table->id();
            $table->string('competitor_domain', 255);
            // URL can be long; keep the full value for display, index by hash.
            $table->text('referring_page_url');
            $table->char('referring_page_hash', 64);
            $table->string('referring_domain', 255)->nullable();
            $table->text('anchor_text')->nullable();
            $table->unsignedTinyInteger('domain_authority')->nullable();
            $table->string('backlink_type', 16)->nullable(); // dofollow | nofollow | ugc | sponsored
            $table->date('first_seen_at')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamp('expires_at');
            $table->timestamps();

            // Composite unique on the hash — a referring page links to a
            // competitor via a specific URL. Updates replace existing rows.
            $table->unique(['competitor_domain', 'referring_page_hash'], 'cblk_domain_page_unique');
            $table->index(['competitor_domain', 'expires_at']);
            $table->index(['competitor_domain', 'domain_authority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_backlinks');
    }
};
