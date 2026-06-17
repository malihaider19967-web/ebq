# EBQ — Full-ULID + Two-Dimensional Sharding (admin-managed, with node move)

## Context

EBQ is pre-revenue (only two tiny, regenerable sites: `dubaiexplorer.ae`, `pubgnamegenerator.net`) but
real clients are imminent. The DB is the biggest remaining scaling risk and **all structural change must
land NOW** — afterward it is risky, tricky, and slow. Goals: (1) make EBQ **fully sharded** so **every
high-volume write path scales horizontally**, (2) **admin-panel managed like the crawl fleet**, (3)
**back up + move data between DB nodes**, leaving **nothing behind** that needs a risky later refactor.

**Locked decisions:**
1. **Full ULID** everywhere (time-ordered `Str::ulid()` via `HasUlids`, not random UUIDv4) → IDs globally
   unique → moves are clean inserts, node provisioning needs no ID coordination.
2. **Re-derive** the 2 sites on a fresh ULID schema (no data-migration code; old DB kept as rollback).
3. **Three-tier topology** (below): central + tenant-shards (by owner) + **crawl-shards (by domain)**.
4. Mirror the crawl-worker fleet (`/admin/fleet`) for a new `/admin/db-fleet` managing all node roles.
5. **Posture:** build the *full* routing + schema + fleet + mover NOW (zero future refactor); initially
   place tenant + crawl data on the **primary** node; provision dedicated shard/crawl nodes on trigger;
   the acceptance test **proves one real tenant node and one real crawl node** before clients arrive.
6. **ULID storage = `char(26)`** (Laravel `HasUlids` default — readable, simplest; `binary(16)` is a
   future RAM lever, not now).
7. **`client_activities` lives on the tenant shard** (scales + moves with the owner); global/admin usage
   reporting is a cross-shard fan-out, with an optional **central monthly usage rollup** added later if
   the fan-out gets heavy.
8. **Commitment & order (finalized):** **Backups first**, then the **irreversible schema core A+B**
   (verified on staging), then **C/D/E in following sessions**. This is *not* a one-session build — the
   schema core is "nothing-left-behind"; the fleet (C) + mover (D) are **purely additive code with no
   future schema risk**, so they can follow safely.
9. **Meta-tradeoff (accepted):** DIY app-level sharding trades ongoing ops complexity (N nodes to
   migrate/monitor/back up, no cross-shard transactions, fan-out admin queries, no built-in HA) for low
   infra cost — chosen over managed distributed-SQL (TiDB/Vitess/Spanner) on cost grounds.

> ⛔ **DB safety (root `CLAUDE.md`):** prod, no backups, binlog off. Cutover is a *re-derive* onto a NEW
> schema/DB; old `ebq` DB stays as rollback. No `migrate:fresh`/`db:wipe` on live data. Build + verify on
> a staging copy. Two-box lockstep: the worker box + ephemeral crawl boxes must reach **every** node.

---

## Target architecture — three tiers, one routing layer

The crawl **compute** fleet already autoscales (stateless workers, central Redis queue —
`infra/crawler/autoscaling.md`). This adds horizontal scaling of **storage** in two independent
dimensions so the single MariaDB stops being the "next bottleneck" that doc warns about.

| Tier | Shard key | Central routing anchor | Tables |
|---|---|---|---|
| **Central** (`global` conn) | — | — | `users`, `google_accounts`, `microsoft_accounts`, billing (`subscriptions`/items), **`websites`** (anchor), **`crawl_sites`** (anchor), `website_user`, `website_invitations`, global catalogs/caches (`keywords`, `keyword_metrics`, `serp_*`, `competitor_backlinks`, `niches`, `niche_*`), fleet (`db_nodes`, `worker_nodes`), `settings`/`plans`, `leads`, `guest_*`, `plugin_releases`, `keyword_api_*`, framework (`sessions`, `cache`, `jobs`, `failed_jobs`, `personal_access_tokens`, `password_reset_tokens`) |
| **Tenant shard** (`tenant:{id}`) | owner (`user`) | `websites.db_node_id` | `search_console_data`, `analytics_data`, `rank_tracking_keywords`/`_snapshots`, `page_audit_reports`, `custom_page_audits`, `page_indexing_statuses`, `backlinks`, `ai_insights`, `writer_projects`, `brand_voice_profiles`, `website_sitemaps`, `keyword_alerts`, `keyword_gap_analyses`/`_rows`, `discovered_competitors`, `competitor_discovery_runs`, `outreach_prospects`, `redirect_suggestions`, `crawl_report_sends`, `client_activities`, `report_brandings`(site), `mail_transports`(site) |
| **Crawl shard** (`crawl:{id}`) | domain (`crawl_site`) | `crawl_sites.crawl_node_id` | `website_pages`, `website_internal_links`, `crawl_runs`, `crawl_findings`, **`website_finding_states`** (co-located: read in a `NOT EXISTS` subquery against `crawl_findings`) |

**Placement rule:** a table is **central** unless it is **high-volume fact data queried only by its
shard key with no SQL join to a central table.** Two thin overlays that bind to central anchors stay
central (`website_niche_map`→`niches`). `website_finding_states` moves to the **crawl** tier (not
central) because its only join partner (`crawl_findings`) is on the crawl tier.

**Why no query crosses a tier boundary:** `CrawlReportService` already merges per-user GSC clicks
(tenant tier) with shared findings (crawl tier) **in PHP, not SQL** (`context()` builds a clicks map →
`impactFor()` merges — `infra/crawler/read-path.md`). Confirmed by review: the only same-tier JOINs are
`rank_tracking_snapshots`↔`rank_tracking_keywords` (both tenant) and the crawl-internal joins (all
crawl). Routing is set **once per request from the active website** (and per job from its
`website_id`/`crawl_site_id`).

**Known residual ceiling (named, not hidden):** crawl-sharding distributes *across* domains; a **single
mega-domain still lives on one crawl node**. Bounded by that node, mitigated by per-node partitioning of
`website_pages` (by date/`value_rank`) + a bigger node. True intra-domain splitting is a rare, much-later
concern — this is the analog of "one tenant ≤ one node."

---

## Phase 0 — Backups first (NON-NEGOTIABLE prerequisite; before any re-derive)

Land a real backup story before touching the schema — the cheapest, highest-leverage risk reduction on
a no-backup prod DB, and it makes the re-derive recoverable.
- Enable MariaDB **binlog** (`log_bin`, `binlog_format=ROW`, ~3-day expiry) — PITR + replica-readiness.
- Scheduled **`mariabackup`** (or `mariadb-dump` while tiny) → **Hetzner Storage Box** (BX11 ~€3.80/mo,
  offsite) + a documented, tested restore drill.
- Keep the **old `ebq` DB intact** as the rollback target through the entire ULID cutover.

**Phase 0 exit:** a backup exists and a restore has been verified into a throwaway MariaDB.

---

## Phase A — Full ULID conversion (foundation; lands & verified first)

- **Migrations** (`database/migrations/*`): `id()`→`ulid('id')->primary()`;
  `foreignId()->constrained()`→`foreignUlid()->constrained()`; `morphs()`→`ulidMorphs()` (Sanctum
  `personal_access_tokens.tokenable`); composite-PK pivots → ULID members; `sessions.user_id`→`ulid`.
  Pure framework queue/cache tables (`cache`, `jobs`, `job_batches`, `failed_jobs`, `migrations`) keep
  integer PKs (central infra, never sharded).
- **Models** (all 49): `use HasUlids;`. Existing UUID route keys (`external_id`, `token`, `request_id`,
  `run_id`) unchanged. Update factories/seeders/tests (HasUlids works on sqlite `:memory:`).
- **Re-derive cutover:** verify ULID schema on a **staging copy** + full suite; on prod build the NEW
  central DB, leave old `ebq` intact as rollback, re-create the 2 sites through the app (crawl + GSC/GA
  backfill repopulate; the 2 sites must re-connect Google OAuth). Changing `DB_DATABASE` → `config:clear`
  + FPM restart (the exact cached-config path that once wiped the DB — extra care).
- **Reuse:** `Str::ulid()`, `HasUlids`, `foreignUlid`, `ulidMorphs`. (Note: char(26) keys inflate the big
  central crawl indexes; `binary(16)` storage is a future lever if RAM bites.)

---

## Phase B — Unified routing layer + tier split (single node → zero behavior change)

Everything still physically on the primary (one box = central + tenant:primary + crawl:primary), so the
routing is fully testable before any new node exists.

- **`config/database.php`**: add `global` + connection **templates** for `tenant:*` and `crawl:*`; real
  node connections registered dynamically. Shared app DB user/password from `.env` (same on every node,
  like `.env.worker`).
- **`db_nodes` (central)** + `app/Models/DbNode.php` — mirrors `WorkerNode`: `id`(ulid), `name`,
  `hetzner_server_id`, `private_ip`, `server_type`, `status`, **`role`** (`primary`/`tenant-shard`/
  `crawl-shard`), `is_pinned`, `db_name`, `tenant_count`/`site_count`, health cols, `provisioned_at`/
  `drain_started_at`. Scopes `active`/`billable`/`drainable` (copy `WorkerNode`).
- **`app/Support/ShardManager.php`** (provider boot): register a Laravel connection per `DbNode::active()`
  (`config(['database.connections.{role}:{id}' => […host=private_ip…]])`). Tolerate a missing `db_nodes`
  table (fresh install/migrate) via try/catch (graceful, like `HetznerClient`).
- **`app/Support/ShardContext.php`**: request/job-scoped resolver returning the connection for each
  table-group: `global`; tenant = `websites.db_node_id` for the active website; crawl =
  `crawl_sites.crawl_node_id`. Cache `website_id→db_node_id` and `crawl_site_id→crawl_node_id`
  (change only on move/placement); bust across boxes on change (Redis-tagged).
- **Model base/traits**: `TenantModel` (`getConnectionName()` = tenant connection), `CrawlModel`
  (= crawl connection), `GlobalModel`/`$connection='global'`. Apply to the right models.
- **Anchors:** add `db_node_id`(ulid, default pinned primary) to `users`+`websites`; `crawl_node_id`(ulid,
  default pinned primary) to `crawl_sites`. Assign on creation (placement strategy = least-loaded;
  a user's websites inherit the user's node; a `crawl_site` gets a crawl node on `firstOrCreate`).
- **Routing set-points** (confirmed by review — key off the **active website/site**, not the user):
  - Web: middleware sets `ShardContext` from `session('current_website_id')` (validated via
    `accessibleWebsitesQuery`).
  - HQ API: set in `app/Http/Middleware/WebsiteApiAuth.php` from the token's `api_website`.
  - Jobs: `SyncSearchConsoleData`/`SyncAnalyticsData`/`TrackKeywordRankJob`/audit/`RunCompetitorDiscovery`
    resolve tenant connection from `website_id`; the **crawl pipeline** (`CrawlPageBatchJob`,
    `AnalyzeSiteJob`) resolves crawl connection from `crawl_site_id`. (Review confirmed `AnalyzeSiteJob`
    writes ONLY crawl tables — so the crawl fleet writes the crawl tier, never the tenant tier.)
- **Split migrations**: `database/migrations/` (central, `global`), `…/tenant/`, `…/crawl/`. New nodes
  run only their set; migrations run **from the web box over the network** against each node connection
  (`migrate --database={role}:{id} --path=…`) — **DB nodes are pure MariaDB, no app code on them**. The
  fleet `migrate` action fans out to all active nodes; each node has its own `migrations` table.

### Review-confirmed fixes (must be in this phase)
1. **Cross-connection cascades break** (FK cascade can't cross DBs). Deleting a `websites` row no longer
   cascades to its tenant fact tables, and a `crawl_sites` GC no longer cascades crawl tables. Replace
   with **app-level cleanup**: extend `app/Console/Commands/DeleteWebsiteData.php` and the
   `Website::deleted` / `CrawlSite` GC hooks (`app/Models/Website.php`) to delete fact rows on the
   correct node connection. ~13 tenant tables + 4 crawl tables affected.
2. **`PurgeSyncData` transaction spans fact+central** (`app/Console/Commands/PurgeSyncData.php:101`) —
   split into a tenant-connection delete + a separate central `websites` update (no cross-conn tx).
3. **`keyword_alerts.keyword_id → keywords`** is a tenant→central FK — drop to a **soft ULID ref**
   (app-enforced); same for any other fact→central FK surfaced by the audit.
4. **Boundary-join audit gate**: grep `->join(`/`leftJoin`/`joinSub`/raw multi-table `DB::select` for any
   cross-tier join; convert offenders to two-query PHP merges. Add a test that runs key read paths with
   each tier on a **separate sqlite connection** so any cross-tier join throws in CI.
- **Cross-connection transactions** generally: audit `DB::transaction` blocks for mixed-tier writes
  (only `PurgeSyncData` found so far); use sequence-with-compensation where needed.

**Phase B exit:** all reads/writes route through `ShardContext`; one-node behavior is identical; suite
green; the 2 sites render correctly.

---

## Phase C — DB-node fleet, admin-managed (clone of the crawl fleet)

- **`app/Services/Fleet/DbFleetService.php`** — `provision/bootstrap/drain/destroy/reconcile`
  (copy `WorkerFleetService`). **Reuse `HetznerClient`** (label `role=ebq-db-node`, DB firewall id).
  `provision()` = Hetzner box from a **MariaDB snapshot**; `bootstrap()` (web-box PUSH over SSH) =
  push `my.cnf` (binlog on, buffer-pool sizing), create the shared app DB user + grants, open the node
  firewall to `10.0.0.0/24`, then **web box runs the tier migrations remotely**
  (`migrate --database={role}:{id} --path=database/migrations/{tenant|crawl}`); mark `active`.
  `destroy()` refuses if `is_pinned` or it still hosts tenants/sites (drain/move first).
- **`app/Support/DbFleetConfig.php`** — `Setting`-backed editable config (copy `AutoscalerConfig`):
  `enabled`, `server_type`, `snapshot_id`, `placement` (round-robin/least-loaded), `max_per_node`
  (separately for tenant + crawl roles).
- **`app/Console/Commands/DbNode.php`** — `ebq:db-node {list|provision|bootstrap|drain|destroy|migrate|
  reconcile|register-primary} [--role=]` (copy `FleetWorker`).
- **`/admin/db-fleet`** — controller + `app/Livewire/Admin/DbFleetStatus.php` + blade (copy
  `FleetStatus`/`admin/fleet`): node table (role, status, tenant/site count, health, est cost/hr),
  provision/drain/destroy/reconcile buttons (admin-gated + per-action re-check), settings form,
  `wire:poll`, and a **placement + move** section (Phase D).
- **`config/services.php` `hetzner.*`**: add `db_firewall_id`, `db_image` (MariaDB snapshot).
- **Operator prerequisites** (mirror `autoscaling.md`): MariaDB snapshot id, DB Hetzner firewall
  (block public 3306, allow `10.0.0.0/24`), shared DB creds in `.env`/`.env.worker`, each node's `ufw`
  + the web box `ufw` allowing the subnet, `max_connections` sized for (app boxes × nodes).

**Phase C exit:** an admin can provision a real `tenant-shard` or `crawl-shard` node from `/admin/db-fleet`;
it bootstraps with its tier migrations and goes `active`.

---

## Phase D — Backup & move (tenant by owner, crawl-site by domain)

One generalized mover over a "shard set" (set of tables + a key filter). ULIDs ⇒ no remap.

- **`app/Services/Sharding/ShardSetExporter.php` / `ShardSetImporter.php`** — export a key's rows from a
  tier's tables to a portable archive (`mysqldump --where`, or chunked Eloquent), import into a target
  node. **Tenant set:** tables filtered by `website_id IN (user's websites)`. **Crawl set:** tables
  filtered by `crawl_site_id = ?` (+ its `website_finding_states`).
- **`app/Console/Commands/ShardMove.php`** — `ebq:shard {backup|move} {tenant <user>|crawl <site>}
  [--to=node] [--dry-run]`. **Move sequence:** set a `migrating` lock on the anchor (jobs defer) →
  export from source → import to target → **verify row counts** → flip anchor (`websites.db_node_id` /
  `crawl_sites.crawl_node_id`) + bust the routing cache across boxes + bump `ReportCache` version →
  clear lock → **only after verification** delete from source. Reversible until the final delete.
- **Backups** → push archives to a **Hetzner Storage Box** (BX11 ~€3.80/mo, offsite) = the first real
  backup story; the fleet also configures per-node `mariabackup` + binlog.
- **Admin UI:** wire the placement section's **Move**/**Backup** buttons.

**Phase D exit:** a tenant *or* a crawl-site can be backed up + moved between nodes (CLI + admin),
verified by row counts, app reading correctly after, with an in-flight `migrating` lock.

---

## Phase E — Acceptance test (prove both node types before clients) + docs

1. `/admin/db-fleet` → **provision a `tenant-shard` node** and a **`crawl-shard` node** → both `active`.
2. Create a client (user) + website **`xplate.com`**; place the user on the tenant node and route
   `xplate.com`'s `crawl_site` to the crawl node.
3. Trigger crawl + GSC/GA → verify: `website_pages`/`crawl_findings` land on the **crawl node**,
   `search_console_data`/rank land on the **tenant node**, identity/`websites`/`crawl_sites` stay
   **central**, and the dashboard + HQ API render correctly through `ShardContext`.
4. **Move** the xplate tenant (and its crawl-site) between nodes and back; verify data moved, anchors
   flipped, reads correct, source cleaned; backup → restore into a throwaway DB → counts match.
5. Update docs.

---

## Cross-cutting risks
- **Boundary joins (top risk):** any cross-tier SQL join silently breaks — mitigated by the Phase-B
  audit + separate-connection CI test + moving `website_finding_states` to the crawl tier.
- **Crawl fleet reach:** worker box + ephemeral crawl boxes must connect to **every** crawl node
  (firewalls + `ufw` allow `10.0.0.0/24`; shared DB creds + `APP_KEY` match) — same lesson as
  `autoscaling.md`. The autoscaling premise is unchanged (stateless workers; per-job connection).
- **Move consistency:** the `migrating` lock + routing-cache bust handle in-flight jobs; move during low
  activity.
- **Per-node SPOF:** single-node shards have no replica yet — accept at startup with backups + fast
  snapshot restore; add per-shard replicas on trigger (cost).
- **Schema evolution:** every tier migration must fan out to all nodes of that role on deploy — bake into
  the deploy procedure + `ebq:db-node migrate`.
- **Cashier/Sanctum unaffected** — `users`/`subscriptions`/`websites`/`personal_access_tokens` central.

## Critical files
- **ULID:** all `database/migrations/*` + `app/Models/*` (mechanical).
- **Routing:** `config/database.php`; `app/Models/DbNode.php` + `db_nodes` migration;
  `app/Support/ShardManager.php`; `app/Support/ShardContext.php`; `TenantModel`/`CrawlModel`/`GlobalModel`
  traits; `db_node_id` on `users`/`websites`, `crawl_node_id` on `crawl_sites`; `database/migrations/{tenant,crawl}/`
  split; middleware + `WebsiteApiAuth` + job-side resolution; fixes in `DeleteWebsiteData.php`,
  `PurgeSyncData.php`, `Website.php` hooks, `keyword_alerts` migration.
- **Fleet (clone):** `app/Services/Fleet/DbFleetService.php` (reuses `HetznerClient`),
  `app/Support/DbFleetConfig.php`, `app/Console/Commands/DbNode.php`,
  `app/Http/Controllers/Admin/DbFleetController.php`, `app/Livewire/Admin/DbFleetStatus.php`,
  `resources/views/admin/db-fleet/*`, `routes/web.php`, `config/services.php`.
- **Move:** `app/Services/Sharding/ShardSetExporter.php`/`ShardSetImporter.php`,
  `app/Console/Commands/ShardMove.php`.
- **Reuse:** `HetznerClient`, `WorkerFleetService`/`AutoscalerConfig`/`FleetStatus` templates; `Setting`
  cache-through; `CrawlReportService` PHP-merge; `DomainRateLimiter`/`CrawlSupervisor` (unchanged);
  `ReportCache::flushWebsite`.

## Docs to update (infra protocol)
- New `infra/sharding/README.md` (three-tier topology, routing, placement rule, move, fleet ops) +
  link from `infra/main.md` + Knowledge Changelog; update `infra/crawler/autoscaling.md`
  ("next bottleneck" now solved via crawl-shards), `infra/reference/database.md` (ULID + tier placement),
  `infra/server-deployment.md` (db_nodes, firewalls, snapshot, Storage Box), `infra/reference/
  jobs-and-scheduler.md` (`ebq:db-node`, `ebq:shard`), `infra/admin/README.md` (`/admin/db-fleet`).
  Update root `CLAUDE.md` (backups exist) + `scaling-roadmap` memory.

## Verification
1. **ULID + suite (sqlite, safe):** `config:clear` → default resolves sqlite `:memory:` (TestCase guard)
   → full suite green on the ULID schema.
2. **Boundary-tier test:** key read paths (dashboard `summary`, action queue, GSC trends, rank history,
   crawl issues, HQ API) run with each tier on a separate sqlite connection — any cross-tier join throws.
3. **Single-node routing (B):** one box behaves identically; both real sites render.
4. **Fleet (C):** provision a tenant node + a crawl node via `/admin/db-fleet`; `SHOW TABLES` confirms
   the right tier migrations on each.
5. **Move (D):** `ebq:shard move tenant <user> --to=… --dry-run` then real; `ebq:shard move crawl <site>
   …`; row counts match, anchors flipped, reads correct, source cleaned; backup→restore counts match.
6. **Acceptance (E):** xplate.com end-to-end — crawl data on the crawl node, GSC/rank on the tenant node,
   identity central, dashboard + HQ API correct, both moves + backup verified.
7. **App-level:** restart `php8.3-fpm` after PHP changes (opcache `validate_timestamps=0`); verify live
   via the `verify` skill; confirm autoscaled crawl boxes write to crawl nodes (Redis `CLIENT LIST` +
   per-node row growth).
