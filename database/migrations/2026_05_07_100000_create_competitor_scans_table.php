<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per admin-triggered competitor scrape. Drives the admin
 * monitor: `progress` JSON + `last_heartbeat_at` are written by the
 * Python worker; `status` is the cancellation channel.
 *
 * Active-scan-per-domain dedup is enforced at the controller (not via a
 * partial unique index) so this works identically on MySQL + SQLite test
 * fixtures.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('competitor_scans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('seed_domain', 255);
            $table->string('seed_url', 2048);
            $table->json('seed_keywords')->nullable();
            $table->json('caps')->nullable();
            $table->string('status', 16)->default('queued');
            $table->json('progress')->nullable();
            $table->unsignedInteger('page_count')->default(0);
            $table->unsignedInteger('external_page_count')->default(0);
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['seed_domain', 'status']);
            $table->index(['website_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_scans');
    }
};
