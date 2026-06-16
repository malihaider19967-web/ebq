<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Header row for one Keyword Gap Analysis run. The discovery side is
 * asynchronous (one {@see \App\Models\KeywordApiRequest} per our-site + each
 * competitor URL), so this row tracks the in-flight request_ids and flips to
 * `completed` once the poller has aggregated all of them into `keyword_gap_rows`.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('keyword_gap_analyses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('our_url');
            // 1–N normalized competitor domains/URLs.
            $table->json('competitor_urls');
            $table->string('country', 16)->default('global');

            // queued | collecting | completed | failed
            $table->string('status', 16)->default('queued');
            // KeywordApiRequest.request_id UUIDs this run is waiting on.
            $table->json('request_ids')->nullable();
            $table->unsignedTinyInteger('total_requests')->default(0);
            $table->unsignedTinyInteger('completed_requests')->default(0);
            // Per-bucket counts once aggregated.
            $table->json('summary')->nullable();
            $table->string('error')->nullable();

            // 30-day cache TTL, aligned with keyword_metrics / backlinks.
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            // Set when upgraded to higher fidelity after a GSC/GA connect.
            $table->timestamp('reprocessed_at')->nullable();
            $table->timestamps();

            $table->index(['website_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_gap_analyses');
    }
};
