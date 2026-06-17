# Sharding — full-ULID, multi-node tenant + crawl sharding

> Status: **built on branch `feature/db-sharding-ulid`** (not yet merged/deployed). Validated on
> sqlite (suite: 0 new failures vs baseline) and end-to-end on a throwaway docker MariaDB. The
> production cutover (re-derive onto a ULID schema) + real Hetzner node provisioning are gated on
> operator setup. Plan: repo-root `SHARDING_PLAN.md`.

EBQ shards its database across MariaDB nodes in **two independent dimensions**, behind one routing
layer. In single-node mode every anchor is NULL → the default connection → behaviour is identical to
pre-sharding (so it ships safely and activates per-tenant).

## Three tiers

| Tier | Shard key | Central anchor (on the primary) | What lives there |
|---|---|---|---|
| **Central** (`global`/default conn) | — | — | identity, billing, `websites`, `crawl_sites`, `website_user`, global catalogs/caches (`keywords`, `serp_*`, `competitor_backlinks`, `niches`…), fleet (`db_nodes`, `worker_nodes`), framework tables |
| **Tenant shard** | owner (user) | `users.db_node_id`, `websites.db_node_id` | per-website fact data: `search_console_data`, `analytics_data`, `rank_tracking_*`, `page_audit_*`, `backlinks`, `ai_insights`, `writer_projects`, `client_activities`, … (20 tables, `App\Models\Concerns\UsesTenantConnection`) |
| **Crawl shard** | domain (crawl_site) | `crawl_sites.crawl_node_id` | `website_pages`, `website_internal_links`, `crawl_runs`, `crawl_findings`, `website_finding_states` (`UsesCrawlConnection`) |

**Why crawl is its own tier:** a crawl_site is shared across owners, so it can't live on one owner's
shard. Sharding it by domain relieves the central crawl-write bottleneck flagged in
[../crawler/autoscaling.md](../crawler/autoscaling.md).

## Identity = ULID

All app tables use **ULID `char(26)`** primary/foreign keys (`HasUlids`); framework queue tables
(`jobs`/`failed_jobs`) + the Sanctum `personal_access_tokens` / `website_user` surrogate ids stay
auto-increment bigint (not FK-referenced, not moved). Globally-unique ids make a tenant **move** a clean
copy with no id remapping.

## Routing (`App\Support\`)

- **`DbNode`** (`db_nodes`) — one row per MariaDB box; `role` primary|tenant-shard|crawl-shard,
  `is_pinned` primary, status lifecycle, `connectionName()` = `node:{id}`.
- **`ShardManager`** — at boot (`AppServiceProvider::boot`) registers a live `node:{id}` Laravel
  connection per active node (clones the `global` config, overrides host/db/port). Cached, graceful.
- **`ShardContext`** (singleton) — per-request/job state. `forWebsite($id)` resolves the central anchors
  (`websites.db_node_id` + linked `crawl_sites.crawl_node_id`) to connections; NULL → default.
  Set by **`ResolveShardContext`** web middleware (session `current_website_id`), **`WebsiteApiAuth`**
  (token's website), and write jobs (`SyncSearchConsoleData`, `CrawlPassJob`, … call
  `forWebsite`/`forCrawlSite`).
- Tier models route via `getConnectionName()` → `ShardContext`. No SQL query crosses a tier boundary
  (the crawl read-path already merges per-user GSC clicks + shared findings in PHP — see
  [../crawler/read-path.md](../crawler/read-path.md)).

## Cross-tier integrity (option A — app-enforced)

Cross-tier FKs are **dropped** (a shard node has no central tables to reference): tenant→
{websites,users,keywords}, crawl→{crawl_sites,websites} — see
`2026_06_19_020000_drop_cross_tier_foreign_keys` (**MySQL-only**; sqlite keeps them). Within-tier FKs
are kept. Integrity is now app-enforced:
- **`App\Services\Sharding\ShardCleanup`** — deletes a website's tenant rows / a crawl-site's crawl rows
  on the right node connection, child-before-parent, via the **`App\Support\ShardTables`** registry.
  Wired into `Website::deleted` + `User::deleting` (replaces DB cascade).
- Source of truth for which tables/filters belong to each tier: `ShardTables::TENANT` / `::CRAWL`.

## Fleet (admin-managed, like the crawl fleet)

- **`App\Services\Fleet\DbFleetService`** (reuses `HetznerClient`): `registerPrimary` / `registerExisting`
  / `provision` (Hetzner box from a MariaDB snapshot) / `bootstrap` (configure MariaDB + firewall, then
  the web box runs `migrate` over the node connection) / `migrateNode` / `drain` / `destroy` (refuses a
  non-empty node).
- **`App\Support\DbFleetConfig`** — `Setting`-backed defaults (server_type, snapshot_id, placement, caps).
- **`ebq:db-node`** command + **`/admin/db-fleet`** panel (`DbFleetController`).
- `config/services.php` `hetzner.db_image` + `hetzner.db_firewall_id`.

## Move / backup (`ebq:shard`)

**`App\Services\Sharding\ShardMover`** — `moveTenant(userId, targetNode)` / `moveCrawlSite(crawlSiteId,
targetNode)`: chunked copy over node connections → per-table row-count **verify** (aborts leaving source
intact on mismatch) → flip the central anchor in a tx → **purge the source** (`ShardCleanup`). Reversible
until the purge. CLI: `ebq:shard move tenant|crawl <id> --to=<node>`; admin: the move form on
`/admin/db-fleet`. **Validated** end-to-end on docker MariaDB (3 dbs): seed tenant on node_a → move →
node_b has the rows, node_a purged, anchors flipped.

> In-flight safety: a per-tenant **migrating lock** ({@see App\Support\ShardLock}) is held for the whole
> move, so tenant/crawl write jobs (GSC sync, audits, rank, crawl) re-queue themselves (`release(30)`)
> instead of writing to the source during the window — no lost writes. Mover copy is chunked (bounded
> memory); for very large tenants a `mariabackup`/`mysqldump` streaming path is the next optimization.

## Known follow-ups (deferred)
- Boundary-join CI test (run read paths with each tier on a separate sqlite connection).
- Migration path split (`database/migrations/{tenant,crawl}/`) so a node creates only its tier's tables
  (today nodes run the full set; cross-tier FKs are dropped so the unused central tables are harmless).
- Entity-keyed job routing (`TrackKeywordRankJob`, `RunCustomPageAudit`, `RunCompetitorDiscovery` resolve
  their website then set `ShardContext`).
- Production re-derive cutover + real Hetzner node provisioning (xplate.com acceptance) — operator-gated.

## Key files
Models: `app/Models/DbNode.php`, `Concerns/UsesTenantConnection.php`, `Concerns/UsesCrawlConnection.php`.
Routing: `app/Support/{ShardManager,ShardContext,ShardTables,DbFleetConfig}.php`,
`app/Http/Middleware/ResolveShardContext.php`. Fleet: `app/Services/Fleet/DbFleetService.php`,
`app/Console/Commands/DbNodeCommand.php`, `app/Http/Controllers/Admin/DbFleetController.php`.
Sharding ops: `app/Services/Sharding/{ShardCleanup,ShardMover}.php`, `app/Console/Commands/ShardCommand.php`.
Migrations: `…_create_db_nodes_table`, `…_add_shard_anchors`, `…_drop_cross_tier_foreign_keys`.
