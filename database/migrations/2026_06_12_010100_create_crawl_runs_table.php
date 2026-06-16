<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per crawl of a website — run metadata, counters (used for the
 * recrawl-optimization "mostly 304" check), and the wholesale crawl-blocked
 * state (CAPTCHA / 403 / 429) so we never present a blocked site as empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawl_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('trigger', 16)->default('scheduled'); // scheduled|on_create|manual|backfill
            $table->string('status', 16)->default('running');    // running|completed|failed|aborted
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('pages_seen')->default(0);
            $table->unsignedInteger('pages_fetched')->default(0);
            $table->unsignedInteger('pages_304')->default(0);
            $table->unsignedInteger('pages_changed')->default(0);
            $table->unsignedInteger('pages_error')->default(0);
            $table->unsignedInteger('findings_total')->default(0);
            $table->string('blocked_reason', 32)->nullable();    // blocked|captcha|rate_limited|login_required
            $table->string('notes', 1024)->nullable();
            $table->timestamps();

            $table->index(['website_id', 'started_at']);
            $table->index(['website_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_runs');
    }
};
