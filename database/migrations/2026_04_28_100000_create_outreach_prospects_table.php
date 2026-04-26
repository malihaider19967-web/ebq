<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persisted prospects for the backlink-prospecting workflow.
 *
 * Each row is one (website × referring_domain) candidate the user is
 * working through — with status (new → drafted → contacted → replied →
 * converted | declined | snoozed), free-text notes, and the most recent
 * AI-drafted outreach email. The frontend treats this table as the
 * source of truth: every "find prospects" run upserts new candidates,
 * the existing ones keep their status and notes.
 *
 * Why a dedicated table (instead of decorating CompetitorBacklink):
 *   - CompetitorBacklink is the raw firehose — many rows per competitor.
 *     Prospects are deduped by referring DOMAIN.
 *   - Status + notes belong per-prospect-per-OUR-website, not per-row.
 *   - Outreach drafts are owned by the prospect record, not the link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_prospects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('referring_domain', 255);
            $table->unsignedTinyInteger('domain_authority')->nullable();
            $table->json('linked_to_competitors')->nullable(); // list of competitor domains
            $table->json('anchor_examples')->nullable();
            $table->string('status', 16)->default('new')->index();
            $table->text('notes')->nullable();
            $table->json('latest_draft')->nullable(); // { subject, body, generated_at }
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamps();

            $table->unique(['website_id', 'referring_domain'], 'outreach_prospects_site_domain_unique');
            $table->index(['website_id', 'status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_prospects');
    }
};
