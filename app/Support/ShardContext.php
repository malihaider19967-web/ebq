<?php

namespace App\Support;

/**
 * Per-request / per-job routing state for the sharded tiers.
 *
 * The app has three logical data tiers (see SHARDING_PLAN.md):
 *   - GLOBAL   — identity, billing, catalogs, the routing anchors (always the
 *                default/central connection).
 *   - TENANT   — per-website fact data, on the owner's shard node.
 *   - CRAWL    — per-domain crawl data, on the domain's crawl node.
 *
 * A model on the tenant/crawl tier asks this singleton for its connection name
 * at query time (via {@see \App\Models\Concerns\UsesTenantConnection} /
 * {@see \App\Models\Concerns\UsesCrawlConnection}). When nothing is set —
 * single-node mode, CLI, tests — both resolve to `null`, i.e. Eloquent's default
 * connection, so behaviour is identical to today. Routing only diverges once a
 * request/job explicitly pins a node connection (resolved from
 * `websites.db_node_id` / `crawl_sites.crawl_node_id`).
 *
 * Bound as a singleton; reset between requests by the routing middleware.
 */
class ShardContext
{
    private ?string $tenantConnection = null;

    private ?string $crawlConnection = null;

    /** Pin the tenant-tier connection (e.g. "node:01J…") for the active website. */
    public function setTenantConnection(?string $name): void
    {
        $this->tenantConnection = $name;
    }

    /** Pin the crawl-tier connection for the active crawl-site / domain. */
    public function setCrawlConnection(?string $name): void
    {
        $this->crawlConnection = $name;
    }

    /** Connection for tenant-tier models; null = Eloquent default (central). */
    public function tenantConnection(): ?string
    {
        return $this->tenantConnection;
    }

    /** Connection for crawl-tier models; null = Eloquent default (central). */
    public function crawlConnection(): ?string
    {
        return $this->crawlConnection;
    }

    public function reset(): void
    {
        $this->tenantConnection = null;
        $this->crawlConnection = null;
    }
}
