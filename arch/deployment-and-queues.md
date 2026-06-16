# Deployment & queues

## Topology — two boxes, one database, one Redis

```
        ┌──────────────────────────┐        ┌──────────────────────────┐
        │  WEB BOX (this host)      │        │  WORKER BOX (separate)   │
        │  10.0.0.2                 │        │  10.0.0.3 (Docker php8.3) │
        │                          │        │  docker-compose.worker.yml│
        │  • PHP-FPM (the web app) │        │                          │
        │  • supervisor queues:     │        │  • queue:work --queue=    │
        │      interactive, default │        │      crawl, sync          │
        │  • schedule:work (cron)   │        │    (heavy/long jobs)      │
        └─────────────┬────────────┘        └─────────────┬────────────┘
                      │   shared MySQL `ebq` + shared Redis │
                      └──────────────────────────────────────┘
```

- **MySQL `ebq`** and **Redis** are shared by both boxes. (No DB backups; binary
  logging off — data loss is permanent. See the top of `CLAUDE.md`.)
- The web box's `.env` has `REDIS_HOST=127.0.0.1`; the worker box points its
  `REDIS_HOST` at the web box so both use the **same** Redis (queues + cache).
- Redis key prefix is `ebq-database-`; cache (locks) add `ebq-cache-`, so a unique
  lock key looks like
  `ebq-database-ebq-cache-laravel_unique_job:App\Jobs\CrawlWebsitePagesJob:crawl-site-8`.

## Queues

| Queue | Runs on | Workers | Notes |
|-------|---------|---------|-------|
| `interactive` | web box | supervisor `ebq-queue-interactive` (×2) | user-facing, low latency |
| `default` | web box | supervisor `ebq-queue-general` | mail, misc; mailables (`ShouldQueue`) land here |
| `crawl` | **worker box** | docker `queue:work --queue=crawl` | the whole crawl pipeline (`->onQueue(Queues::CRAWL)`) |
| `sync` | **worker box** | docker `queue:work --queue=sync` | GA/GSC sync, large imports |

Because crawl jobs go to the `crawl` queue, **they only run on the worker box.** The
web box never crawls.

## Deploying a code change

1. **Both boxes** get the new code (the web box checkout *and* the worker box's
   docker checkout — they are independent).
2. **Run migrations once** (shared DB): `php artisan migrate --force`.
3. **Web box:** `sudo systemctl restart php8.3-fpm` — opcache runs with
   `opcache.validate_timestamps=0`, so a graceful reload is **not** enough; a full
   restart rebuilds the opcache SHM (the master PID changes). Then
   `php artisan queue:restart` for the web-box workers.
4. **Worker box:** deploy the code, then restart the crawl/sync workers
   (`docker compose -f docker-compose.worker.yml restart`, or `queue:restart`).

`opcache.enable_cli = 0`, so `artisan` / `tinker` / queue workers compile fresh —
but a long-running `queue:work` process still holds loaded **classes** in memory, so
it must be restarted (or `queue:restart`-signalled) to pick up new class code.
Compiled Blade views are re-checked by mtime, so `view:clear` + a restart is the
safe combo for view changes.

## Two-box deploy gotcha (read this)

A migration changes the **shared** schema instantly for **both** boxes. If only one
box has the matching code, the other runs old code against the new schema. This bit
the shared-crawl rollout:

- After migrating, the worker box still ran **old** crawl code. It created
  `crawl_runs` keyed by `website_id` (the new schema keys by `crawl_site_id`), so
  the new dashboards couldn't see them.
- Worse, a **uniqueId mismatch** silently broke dispatch: the web box (new code)
  dispatches `CrawlWebsitePagesJob` with `uniqueId = "crawl-site-{id}"`, but the
  worker box (old code) processed it and released the **old** `uniqueId =
  "crawl-website-{websiteId}"`. The new-format lock was never released → every
  subsequent dispatch was de-duplicated by `ShouldBeUnique` and **never queued**.
  Symptom: "crawls not starting" with an empty `crawl` queue and a held lock.

**Rule:** for any change to a queued job's identity/serialization (constructor
args, `uniqueId`), deploy **both** boxes together, or pause the worker box's queue
while the schema/code is mid-migration. To clear a stuck unique lock manually
(what the admin "recrawl" button does):

```php
$uid = (new App\Jobs\CrawlWebsitePagesJob($websiteId))->uniqueId();
foreach ([
  'laravel_unique_job:'.App\Jobs\CrawlWebsitePagesJob::class.':'.$uid,
  'laravel_unique_job:'.App\Jobs\CrawlWebsitePagesJob::class.$uid,
] as $k) { Cache::lock($k)->forceRelease(); }
```
(If `forceRelease` doesn't clear it because the lock came from a *different*
code/uniqueId, the deterministic fix is to make both boxes run the same code; the
lock then releases normally.)

## Sizing / timeouts

- The shared `php8.3-fpm` pool was widened (≈20 workers, `terminate_timeout` 120s)
  after site-wide 504s caused by a small pool + slow synchronous external calls.
- `REDIS_QUEUE_RETRY_AFTER=1320` must stay **above** the longest job timeout
  (`AnalyzeSiteJob` 1200s), on **both** boxes, or long crawls get re-dispatched
  while still running.
- PHP must be **8.3** on both boxes (8.5 breaks queued-closure serialization).
