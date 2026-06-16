<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correlation + result store for asynchronous keyword-data requests. The
 * self-hosted API is async: we POST a request carrying `request_id`, the
 * server acks instantly and processes the job, then calls our webhook
 * (`/webhooks/keyword-finder`) with that same `request_id`. This row tracks
 * the request lifecycle (`queued → running → completed/failed`) and stores the
 * delivered `result` so the UI can poll for it.
 *
 * Mirrors the shape of {@see \App\Models\GuestKeywordVolume}.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('keyword_api_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('keyword_api_server_id')->nullable()
                ->constrained('keyword_api_servers')->nullOnDelete();

            // 'ideas' (discovery) | 'volume' (metrics lookup).
            $table->string('type', 16);
            // ideas mode: 'keywords' | 'website' | 'page' (null for volume).
            $table->string('mode', 16)->nullable();

            // Echo of what we sent (seeds/url/scope/keywords/location/language).
            $table->json('payload')->nullable();

            $table->string('status', 16)->default('queued');
            $table->json('result')->nullable();
            $table->string('error')->nullable();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('website_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_api_requests');
    }
};
