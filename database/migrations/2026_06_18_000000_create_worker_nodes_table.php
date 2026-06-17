<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The crawl-worker fleet. One row per worker box (Hetzner Cloud server) that
 * runs `queue:work --queue=crawl` against the shared Redis. Mirrors the
 * keyword_api_servers fleet pattern: an admin/autoscaler creates rows, the
 * `ebq:check-worker-nodes` health loop keeps the snapshot warm, and
 * {@see \App\Services\Fleet\WorkerFleetService} provisions/drains/destroys them
 * via the Hetzner API.
 *
 * No secrets are stored here — a node holds no API key. The only fleet secret is
 * HCLOUD_TOKEN, which lives in the web box .env and is read by HetznerClient.
 * The permanent worker box is flagged `is_pinned` so the autoscaler never drains
 * it (baseline capacity + home of the crawl-finalize queue).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('worker_nodes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            // Null while a create call is in flight; set on success. Unique so a
            // server is tracked exactly once. (MySQL allows multiple NULLs.)
            $table->unsignedBigInteger('hetzner_server_id')->nullable()->unique();
            $table->string('private_ip')->nullable();
            $table->string('public_ip')->nullable();
            $table->string('server_type')->nullable();

            // provisioning | active | draining | deleting | failed
            $table->string('status', 16)->default('provisioning');
            $table->unsignedInteger('containers')->default(5);
            // The permanent box — never scaled down; runs the crawl-finalize queue.
            $table->boolean('is_pinned')->default(false);

            // Health snapshot (null = never checked yet), mirroring keyword_api_servers.
            $table->boolean('is_healthy')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedInteger('last_queue_waiting')->nullable();
            $table->unsignedInteger('last_queue_running')->nullable();
            $table->string('last_error')->nullable();

            // Lifecycle timing for cost accounting + drain-timeout enforcement.
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('drain_started_at')->nullable();
            $table->json('labels')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_nodes');
    }
};
