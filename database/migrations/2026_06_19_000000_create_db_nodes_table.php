<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The database-node fleet — one row per MariaDB box that hosts a shard.
 * Mirrors `worker_nodes` (the crawl-worker fleet): an admin/`ebq:db-node`
 * creates rows, {@see \App\Services\Fleet\DbFleetService} provisions/drains/
 * destroys them via the Hetzner API, and {@see \App\Support\ShardManager}
 * registers a live Laravel DB connection (`node:{id}`) for each at boot.
 *
 * Two shard dimensions both point at rows here:
 *   - tenant shards (by owner)  — `websites.db_node_id` / `users.db_node_id`
 *   - crawl shards  (by domain) — `crawl_sites.crawl_node_id`
 * `role` records which kind of data a node is intended to hold; the pinned
 * primary (Box A) carries `role=primary` and is never destroyed.
 *
 * Holds NO secrets — nodes authenticate with the shared app DB credential from
 * the web box `.env`. ULID id (the whole schema is ULID) so node ids never
 * collide across environments.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('db_nodes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            // Null while a create call is in flight; set on success. Unique so a
            // server is tracked exactly once. (MySQL allows multiple NULLs.)
            $table->unsignedBigInteger('hetzner_server_id')->nullable()->unique();
            $table->string('private_ip')->nullable();
            $table->string('public_ip')->nullable();
            $table->string('server_type')->nullable();

            // primary | tenant-shard | crawl-shard
            $table->string('role', 16)->default('tenant-shard');
            // provisioning | active | draining | deleting | failed
            $table->string('status', 16)->default('provisioning');
            // The MariaDB schema name the app connects to on this box.
            $table->string('db_name')->nullable();
            // The primary/Box A — never drained or destroyed.
            $table->boolean('is_pinned')->default(false);

            // Placement counters (how many tenants / crawl-sites live here).
            $table->unsignedInteger('tenant_count')->default(0);
            $table->unsignedInteger('site_count')->default(0);

            // Health snapshot (null = never checked yet).
            $table->boolean('is_healthy')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_error')->nullable();

            // Lifecycle timing for cost accounting + drain-timeout enforcement.
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('drain_started_at')->nullable();
            $table->json('labels')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('db_nodes');
    }
};
