<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache table for Keywords Everywhere lookups. Keyed on
 * (keyword_hash, country, data_source) so a "saas seo audit" global lookup
 * serves every website that ever asks for it — no website_id scoping.
 *
 * Freshness lives in `expires_at` (default fetched_at + 30 days). The
 * KeywordMetricsService never re-bills the API while a row is fresh; the
 * scheduled refresh job picks up stale rows in bulk.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('keyword_metrics', function (Blueprint $table): void {
            $table->id();
            $table->string('keyword');
            $table->string('keyword_hash', 64);
            $table->string('country', 16)->default('global');
            $table->string('data_source', 16)->default('gkp');

            $table->unsignedInteger('search_volume')->nullable();
            $table->decimal('cpc', 10, 4)->nullable();
            $table->string('currency', 8)->nullable();
            $table->decimal('competition', 5, 4)->nullable();
            $table->json('trend_12m')->nullable();

            $table->timestamp('fetched_at');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['keyword_hash', 'country', 'data_source']);
            $table->index(['expires_at']);
            $table->index(['keyword_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_metrics');
    }
};
