<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * System-wide key/value settings table.
 *
 * Initial use case: `global_feature_flags` — the master kill-switch map
 * that propagates via the public `/wordpress/plugin/version` endpoint to
 * every plugin install (connected and unconnected). Designed to grow:
 * any future system-wide config (e.g., maintenance-mode banner text,
 * feature-flag rollout %, EBQ-app-level toggles) lives in this table
 * keyed by name.
 *
 * Storage shape: `key` is the primary identifier (string up to 191
 * chars to fit MySQL utf8mb4 indexes); `value` is JSON to accommodate
 * arrays / objects without per-key migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            // 191 chars is the MySQL utf8mb4 indexable limit on older
            // MySQL/MariaDB versions; safe across all supported engines.
            $table->string('key', 191)->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
