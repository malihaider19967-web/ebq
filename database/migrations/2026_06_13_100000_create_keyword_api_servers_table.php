<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fleet of self-hosted keyword-data API servers (each drives Google Keyword
 * Planner through a headless browser). Admin-managed: one row per server with
 * its base URL, API key, and webhook secret. Health/queue columns are kept
 * warm by the `ebq:check-keyword-servers` command so the load balancer
 * ({@see \App\Services\KeywordFinder\KeywordFinderPool}) can route to the
 * least-busy healthy server and fail over on errors.
 *
 * `api_key` and `webhook_secret` are stored encrypted (model-level cast) so a
 * DB leak doesn't expose them — hence `text` columns (ciphertext is longer
 * than the plaintext).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('keyword_api_servers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('base_url');
            $table->text('api_key');
            $table->text('webhook_secret');

            $table->string('default_location')->default('United States');
            $table->string('default_language')->default('English');

            // Higher weight = preferred when queue depths tie.
            $table->unsignedInteger('weight')->default(1);
            $table->boolean('is_active')->default(true);

            // Health snapshot (null = never checked yet).
            $table->boolean('is_healthy')->nullable();
            $table->boolean('logged_in')->nullable();
            $table->unsignedInteger('last_queue_waiting')->nullable();
            $table->unsignedInteger('last_queue_running')->nullable();
            $table->timestamp('last_health_at')->nullable();
            $table->string('last_error')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'is_healthy']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_api_servers');
    }
};
