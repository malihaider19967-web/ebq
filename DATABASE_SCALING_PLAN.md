# EBQ Database Scaling — Production Plan (MariaDB on Hetzner)

## Context

EBQ is pre-revenue (no real clients yet) but will hit DB scaling walls once it does. Worker/crawler
autoscaling and the keyword-finder fleet are already solved; **the database is the biggest remaining
risk**. Today it is a **single MariaDB 10.11 connection** co-located on Box A (4 vCPU / 7.6 GiB)
sharing RAM with Postal + Jitsi, **no read replica, no read/write split, no backups, binlog off**, and
**no active data retention anywhere**. Several tables grow unbounded (`search_console_data`,
`rank_tracking_snapshots`, `client_activities`), four caches have `expires_at` columns that **nothing
ever prunes**, and all PKs are auto-increment BIGINT (shard-unsafe).

**Constraints (from the user):** MariaDB/MySQL only — **no ClickHouse**; **cost is a major factor**
(early-stage). **Decisions locked:** (1) retention = windowed raw + monthly rollups; (2) keep DB
co-located for now, isolate behind a trigger; (3) lean now, scale paid capacity on triggers; (4) build
shard-ready prep now (additive, zero-risk).

**Outcome:** a phased roadmap where every cheap/free + zero-risk item ships now (while tables are tiny),
and every paid capacity step is deferred behind a concrete, measurable trigger. The net effect
reproduces what ClickHouse would have given us — bounded time-series growth, fast range reads, instant
retention — entirely within MariaDB via native partitioning + rollups + compression.

> ⛔ **DB safety (root `CLAUDE.md`):** prod, no backups, binlog off → data loss permanent. Every
> migration below is additive or runs on near-empty tables; the only deletions are retention `DROP
> PARTITION`s, gated by the configured window + a dry-run. No `migrate:fresh/refresh/rollback`,
> `db:wipe`. Two-box lockstep: shared-schema migrations hit both boxes — run `migrate --force` on Box A
> only (shared MariaDB), restart the worker containers after.

---

## Guiding principle

**Do the disruptive, structural work NOW while tables are empty** (partitioning, PK restructuring,
compression, shard-prep). Doing these later — on live multi-GB tables with paying clients — means table
rebuilds, lock windows, and high blast radius on a no-backup prod DB. **Defer only the things that cost
money** (replica, dedicated box, sharding) behind triggers.

---

## Phase 0 — Stop the bleeding (NOW · free/near-free · highest priority)

### 0.1 Backups — fix the existential risk first
The "no backups" state is the single biggest threat, independent of scale. Cheap fix:
- **Enable binlog** (`log_bin`, `binlog_format=ROW`, `binlog_expire_logs_seconds≈3 days`) in MariaDB
  config. Gives point-in-time recovery **and** is the prerequisite for a future read replica. Disk cost
  is a few GB on a 75 GB disk at ~19% used. Update root `CLAUDE.md` once backups exist (the "no
  backups" warning can be softened to "restore procedure: …").
- **Scheduled physical backup** via `mariabackup` (or nightly `mariadb-dump` while small) → push to a
  **Hetzner Storage Box** (BX11, ~€3.80/mo, 1 TB, offsite). Add a cron + a documented restore drill.
- This also yields the "first backup + staging seed" the scaling-roadmap memory wanted.

### 0.2 Retention sweeper for the expired caches (free, immediate)
New command `ebq:prune-expired` (scheduled daily), deleting rows past `expires_at` in batches:
- `keyword_metrics`, `serp_cache`, `competitor_backlinks`, `website_invitations` — all have `expires_at`
  but no pruner. Use their existing `expires_at` index (add one where missing).
- Batch-delete (`limit(N)->delete()` loop) to avoid long locks; structured log of rows removed.
- Files: `app/Console/Commands/PruneExpiredCaches.php` (new), schedule in `routes/console.php`.

### 0.3 `client_activities` bound (free)
Unbounded audit ledger with no `created_at`-only index. Add a `created_at` index and prune/rollup rows
older than the billing-relevant window (e.g. keep 18 months raw; older → monthly aggregate per
`user_id`/`provider` for lifetime-usage stats). Same `ebq:prune-expired` command.

**Phase 0 exit:** backups exist + restore tested; nightly pruning live; no table grows purely from
expired/stale rows.

---

## Phase 1 — Native partitioning + compression (NOW · free · do while tables are tiny)

This is the ClickHouse replacement. RANGE-partition the time-series tables by date so that (a) range
queries prune to a few partitions, and (b) **retention becomes `ALTER TABLE … DROP PARTITION`** —
instant, lock-light, no bloat, no `DELETE` churn.

### 1.1 Partition the big append-only tables
| Table | Partition by | PK change required |
|---|---|---|
| `search_console_data` | `RANGE (TO_DAYS(date))`, monthly | PK `(id)` → `(id, date)`; unique already includes `date` ✓ |
| `rank_tracking_snapshots` | `RANGE` on `checked_at`, monthly | PK `(id)` → `(id, checked_at)` |
| `analytics_data` | `RANGE` on `date`, monthly | unique already includes `date` ✓; PK → `(id, date)` |
| `client_activities` | `RANGE` on `created_at`, monthly | PK → `(id, created_at)` |

> MariaDB requires every UNIQUE/PRIMARY key to contain the partition column — hence the composite-PK
> tweaks. **Trivial now (near-empty); a full rebuild later.** Migrations must be re-entry-safe and
> no-op on sqlite (per existing convention — see `database/migrations` MySQL-online-DDL pattern).

Add a small **partition-maintenance command** (`ebq:manage-partitions`, monthly) that pre-creates next
month's partition and `DROP PARTITION`s those beyond the retention window (Phase 2). Gated by config +
`--dry-run`.

### 1.2 Compression on the JSON/longtext-heavy tables (free space win)
Apply `ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8` (portable on ext4/xfs; no ZFS needed yet) to the bloat
tables: `website_pages` (`body_text`, `headings_json`), `competitor_pages` (`body_text`),
`rank_tracking_snapshots` (5 JSON cols), `page_audit_reports`/`custom_page_audits` (`result`),
`ai_insights` (`payload`), `writer_projects` (`generated_html`, `chat_history`). Expect ~2–4× on
text/JSON. Also flip on the existing **`CRAWLER_PRUNE_BODY_TEXT`** opt-in (term-extraction already
replaces `body_text` for the link suggester — `content_terms` covers the read path).
- Purge soft-deleted `writer_projects`/`ai_writer_prompts` past a grace window (free).

**Phase 1 exit:** the four growth tables are partitioned; retention is a partition drop; text/JSON
tables compressed. Growth is now bounded and predictable.

---

## Phase 2 — Windowed retention + monthly rollups (NOW · free · the chosen retention policy)

Per the locked decision (window + monthly rollups):
- **Raw GSC** (`search_console_data`): keep ~**16 months** daily (matches Google's own window); drop
  older partitions.
- **Raw rank** (`rank_tracking_snapshots`): keep ~**90 days** at full check cadence; older → downsample.
- **Rollup tables** (new, summary-grain, kept long-term):
  - `search_console_monthly` — `(website_id, year_month, query_hash|page_hash, country, device)` with
    summed clicks/impressions + weighted position. Populated by `RollupSearchConsoleData` job before the
    raw partition is dropped.
  - `rank_tracking_daily` (or weekly) — one downsampled point per `(rank_tracking_keyword_id, day)` from
    the 12-hourly snapshots, kept long-term for trend charts.
- **Read-path fallback:** trend/history views must read **rollups for old ranges, raw for recent**.
  Touch the read services: data-sources `ReportDataService` (GSC trends), `RankTrackingService`
  (history charts), and any `CrawlReportService` method reading GSC impact over long windows. Add a
  `windowResolver` helper that picks raw-vs-rollup by date cutoff.
- New jobs: `app/Jobs/RollupSearchConsoleData.php`, `app/Jobs/RollupRankSnapshots.php`; scheduled
  monthly/daily in `routes/console.php`; rollup-table migrations.

**Phase 2 exit:** time-series footprint is flat-lined to the window + compact rollups; deep history
still available at coarser grain.

---

## Phase 3 — Shard-ready prep (NOW · additive · zero behavior change)

Builds exactly the deferred prep from the `scaling-roadmap` memory so future sharding is
config-not-refactor. **No data moves; everything still uses the default connection.**
- **`shard` column** (`unsignedSmallInteger`, default `0`, indexed) on `users` and `websites`.
- **`ShardResolver`** (`app/Support/ShardResolver.php`) — given a user/website, returns a connection
  name; today always returns the default `mysql`. Single choke point for the future.
- **`config/database.php`**: add a `global` connection entry (alias of `mysql` today) so global/shared
  tables can be addressed explicitly now and physically split later with no code change.
- **ULIDs on NEW tenant tables only** — adopt `HasUlids` convention going forward (auto-increment
  collides across shards; ULIDs don't). Document the convention; do **not** re-key existing tables.
- **Cross-tenant query audit (doc, not code):** classify every table (done — see below) and flag the
  cross-tenant read sites so a future shard cutover knows what straddles shards:
  - **Cleanly tenant-scoped** (`website_id`/`user_id`): `search_console_data`, `analytics_data`,
    `rank_tracking_*`, `page_audit_reports`, `backlinks`, `ai_insights`, `writer_projects`, … → live on
    the owner's shard.
  - **Global/shared caches & catalogs** (no tenant key, intentionally cross-client): `keywords`,
    `keyword_metrics`, `serp_cache`, `competitor_backlinks`, `niches`, `niche_aggregates`,
    `niche_keyword_map`, `serp_*` → live on the **`global`** connection.
  - **Hard case — shared crawl** (`crawl_site_id`, shared by websites of *different* users):
    `crawl_sites`, `website_pages`, `website_internal_links`, `crawl_runs`, `crawl_findings`
    (+ per-user overlay `website_finding_states`). A crawl_site can be subscribed by multiple owners, so
    it **cannot live on one owner's shard**. Decision recorded for the sharding phase: **crawl tables go
    on the `global`/shared-crawl tier**, read via `CrawlReportService` (which already resolves
    website→crawl_site→cap-window). `website_finding_states` is the only crawl-side per-user table — it
    can stay tenant-side keyed by `website_id`+`finding_id`.

**Phase 3 exit:** schema + code are shard-ready; sharding later is a config/data-move exercise, not a
refactor.

---

## Deferred phases — paid capacity, behind explicit triggers

These cost money, so they wait for a measured signal. Define the triggers now; act when hit.

### Phase 4 — Read replica + read/write split  *(trigger: read load)*
- **Trigger:** primary CPU sustained >60% in business hours for a week, OR dashboard p95 query latency
  regresses, OR you want rollup/analytics jobs off the primary.
- MariaDB async replication to a second Hetzner box (binlog already on from Phase 0). Add `read`/`write`
  arrays to `config/database.php` `mysql` connection (`sticky => true`). Point heavy read jobs (rollups,
  reports) at the replica. ~€20–40/mo.

### Phase 5 — Dedicated DB box on ZFS  *(trigger: contention / size)*
- **Trigger:** InnoDB buffer-pool hit ratio <99% sustained (working set > RAM), OR Box A swaps, OR
  Postal/Jitsi contention is measurable, OR DB size > ~35% of Box A disk.
- Move MariaDB to a **Hetzner dedicated (Robot) server** — far cheaper RAM/NVMe per € than Cloud
  (e.g. EX/AX line, 64–128 GB ECC + NVMe for ~€40–90/mo). Run it on **ZFS + zstd** (2–4× compression on
  the JSON/crawl data + cheap snapshots = a stronger backup story than Phase 0). Migrate via replica
  promotion (zero-downtime) using the Phase 4 replication.

### Phase 6 — Vertical scale-up  *(trigger: buffer pool)*
- **Trigger:** hot working set outgrows RAM on the current box.
- Cheapest lever of all: a bigger Hetzner box / more RAM so InnoDB buffer pool holds the working set.
  "Shard late" — one well-provisioned ZFS-NVMe box lasts a very long time.

### Phase 7 — Sharding by account/owner  *(trigger: single-primary ceiling — far out)*
- **Trigger:** the largest Hetzner dedicated box is still I/O/CPU-bound on the primary, i.e. one box
  can no longer hold the biggest tenant + the rest. Realistically many dozens of heavy paying tenants
  away.
- DIY app-level sharding using Phase 3 prep: `ShardResolver` returns a per-owner connection; tenant
  tables move with the owner; **global + shared-crawl tiers stay on the `global` connection**. Build &
  rehearse in a **staging env with a sanitized prod copy + synthetic tenants** (the Phase 0 backup is
  the seed) before any cutover — online rebalancing is the high-blast-radius part.
- **TiDB/Vitess explicitly deferred** at this stage: a TiDB cluster needs ≥3 PD + ≥3 TiKV nodes —
  cost-prohibitive for an early-stage startup. Revisit only if DIY sharding proves too painful AND
  revenue supports the cluster. Aurora/managed is out (5–15× Hetzner cost; kills the $5–35/mo margins).

---

## Monitoring (NOW · cheap · so triggers are observable)
- Add `ebq:db-health` (or extend an existing admin panel) reporting per-table size, partition counts,
  buffer-pool hit ratio, replica lag (once Phase 4), and rows-pruned-last-run. Surface on `/admin`.
  Without this, the deferred-phase triggers are invisible.

---

## Critical files
- **New commands/jobs:** `app/Console/Commands/PruneExpiredCaches.php`,
  `app/Console/Commands/ManagePartitions.php`, `app/Console/Commands/DbHealth.php`,
  `app/Jobs/RollupSearchConsoleData.php`, `app/Jobs/RollupRankSnapshots.php`; schedule in
  `routes/console.php`.
- **Migrations** (`database/migrations/`): partition + composite-PK for the 4 big tables;
  `ROW_FORMAT=COMPRESSED` on the bloat tables; rollup-table creates; `shard` column on `users`/`websites`;
  `created_at` index + retention on `client_activities`; missing `expires_at` indexes. All re-entry-safe,
  no-op on sqlite, additive (online DDL where possible).
- **Code:** `app/Support/ShardResolver.php` (new); `config/database.php` (`global` connection,
  later `read`/`write`); read-path fallbacks in data-sources `ReportDataService`,
  `app/Services/RankTrackingService.php`, `app/Services/Crawler/CrawlReportService.php`.
- **Ops (no app code):** MariaDB config (`log_bin`, retention); `mariabackup` cron → Hetzner Storage Box.
- **Reuse:** existing `expires_at` indexes; `CRAWLER_PRUNE_BODY_TEXT` + `content_terms`;
  `ClientActivityLogger`; the MySQL-online-DDL migration pattern already in the repo.

## Docs to update (per the infra protocol — task isn't done until these are true)
- New `infra/scaling.md` (or `infra/reference/database-scaling.md`) — the phased plan + live trigger
  state; link it from `infra/main.md` System Map + a Knowledge Changelog line.
- `infra/reference/database.md` — partitioning, rollups, compression, `shard` column, retention.
- `infra/server-deployment.md` — binlog on, backup target, (later) replica/dedicated box.
- `infra/reference/jobs-and-scheduler.md` — new commands/jobs + schedule.
- Update root `CLAUDE.md` once backups exist; mirror durable decisions into the `scaling-roadmap` memory.

---

## Verification
1. **Migrations (sqlite, safe):** `php artisan config:clear` → confirm `config('database.default')` is
   sqlite `:memory:` (TestCase guard) → run the suite. Partition/PK/compression migrations must no-op on
   sqlite and the schema must build clean.
2. **Partitioning (MariaDB, after `migrate --force` on Box A):** `SHOW CREATE TABLE search_console_data`
   shows monthly partitions; `EXPLAIN PARTITIONS` on a dated range query prunes to the right partitions.
3. **Retention:** seed an old partition on a scratch/staging copy → `ebq:manage-partitions --dry-run`
   lists it → real run drops only out-of-window partitions; `ebq:prune-expired --dry-run` reports
   expired-cache counts, real run clears them.
4. **Rollups:** run `RollupSearchConsoleData`/`RollupRankSnapshots` over seeded data → rollup totals
   reconcile with raw; long-range report view reads rollups, recent reads raw, numbers continuous across
   the cutoff.
5. **Compression:** compare `information_schema.tables` `data_length` before/after on `website_pages`.
6. **Shard-prep:** `ShardResolver` returns the default connection for any user/website; full suite green
   (zero behavior change). Cross-tenant audit doc matches the table classification above.
7. **Backups:** take a backup → restore into a throwaway MariaDB → row counts match. Document the drill.
8. **No regression:** crawler read-path + GSC/rank dashboards verified via the `verify` skill against
   the running app (FPM restart after PHP changes — opcache `validate_timestamps=0`).
