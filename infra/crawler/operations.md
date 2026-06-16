# Operations runbook

Day-to-day operation of the crawler: how to watch it, deploy it, trigger crawls, and
diagnose the failure modes we've actually hit. For the topology/queue details this references,
see [../deployment-and-queues.md](../deployment-and-queues.md).

## Watching crawls

- **Admin UI:** `/admin/crawler` — the fleet panel (`Livewire/Admin/CrawlerProgress`), live
  (5s poll). Per crawl_site: **which website(s)/client(s)** it serves, status, **progress as
  crawled / total-discovered** (inventory-based, matching the client banner — *not* the
  internal per-pass counter), errors, open issues, health, subscribers/cap, last activity —
  plus summary cards incl. the **crawl-queue backlog**, and a built-in "How to read this
  screen" legend. The backlog card is the first thing to check when crawls feel slow.
- **Queue depth (CLI):** `Queue::connection('redis')->size('crawl')`.
- **Throughput sanity check:** count `website_pages.last_crawled_at >= now()-Ninterval`. A
  healthy fleet sustains ~**5–7 pages/sec** across the 5 crawl workers.

## Triggering crawls

- **Per website (on-demand / admin recrawl):** dispatch `CrawlWebsitePagesJob($websiteId,
  trigger, force)`. The `crawl-site-{id}` unique lock + start-lock prevent duplicates.
- **Scheduler:** `ebq:crawl-websites` (weekly, Mon 02:00) iterates crawl_sites needing a run;
  `ebq:crawl-websites --sitemap-deltas` (daily 04:30) dispatches `CrawlSitemapDeltaJob`.
- **Backfill (manual, post-deploy):** `php artisan ebq:crawl-websites --backfill` — crawls
  never-crawled crawl_sites. ⚠️ This fans out **all** pending domains at once and floods the
  single FIFO `crawl` queue (see Congestion below). Prefer staggering on a loaded fleet.

## Deploying a crawl-code change

Both boxes must run the same code (a shared-schema migration hits both instantly). Summary —
full detail + the incident that proves why in [../deployment-and-queues.md](../deployment-and-queues.md):

1. Web box: pull code, `php artisan migrate --force`.
2. Worker box: **rsync** code (it's a plain dir, not a git repo), `rm bootstrap/cache/*.php`,
   `docker compose -f docker-compose.worker.yml up -d` (stop→start so `queue:work` reloads
   classes).
3. Web box: `sudo systemctl restart php8.3-fpm` (opcache `validate_timestamps=0` → reload is
   NOT enough), then `php artisan queue:restart`.
4. Verify: `grep -c crawl_site_id` on the worker's copy of `CrawlWebsitePagesJob.php`, and
   dispatch one crawl → confirm the new `crawl_runs` row is `crawl_site_id`-keyed.

## Failure modes & fixes

### A domain looks stuck (congestion or wedge)
**Symptom:** one domain's run hasn't advanced for many minutes while others crawl. **Two
causes:**
- **Congestion** — too many sites crawling at once on the single FIFO `crawl` queue. Largely
  fixed by per-pass fairness (`crawler.pages_per_pass`): a big site now yields after each
  1,000-page pass instead of enqueuing its whole frontier. A genuinely slow fetch rate
  (~1.5 pages/sec) can still make big sites take a long time.
- **Wedge** — the multi-pass `Bus::batch` callback was lost on a worker recycle, so the chain
  died (`updated_at` stale, `job_batches` row stuck unfinished). **`ebq:crawl-supervisor`**
  auto-recovers it within `stall_minutes` (10). **Don't panic-reset** — give the watchdog
  ~10 min first.

**Diagnose:** compare each run's `updated_at` (stale = wedged or queued); count
`job_batches WHERE finished_at IS NULL`; confirm workers are alive + `failed_jobs` ≈ 0.
**Force recovery now:** `php artisan ebq:crawl-supervisor` (lower the window for a one-off with
`CRAWLER_STALL_MINUTES=5 php artisan ebq:crawl-supervisor`). Clean leftover orphan batch rows
with `DB::table('job_batches')->where('created_at','<',now()->subMinutes(30)->timestamp)->delete()`.

### "Crawls not starting" / empty queue + held lock
**Cause:** a leaked `ShouldBeUnique` lock — classically a **two-box code mismatch** (old-code
worker releases the old `uniqueId`, new code set a new one). **Fix:** make both boxes match,
then clear the leaked `*unique_job*` key (redis-cli `DEL` the prefixed key; `forceRelease`
won't clear a key created under a different uniqueId). The admin "recrawl" button clears it.

### Orphan rows (`crawl_site_id IS NULL`)
**Cause:** old-code worker wrote `website_id`-keyed rows after the re-key migration. **Fix:**
delete `WHERE crawl_site_id IS NULL` on the four crawl tables (children first), and get the
worker box onto the new code. Verify zero with the health check below.

### AnalyzeSiteJob timeouts on huge sites
The 1200s analyze timeout + in-memory graph BFS are the large-site ceiling. The run still
finalizes `completed` (best-effort) rather than wedging. See [known-issues.md](./known-issues.md).

## Health checks (paste into tinker)

```php
// fleet snapshot
App\Models\CrawlRun::whereIn('status',['running','finalizing'])->count();   // active crawls
Illuminate\Support\Facades\Queue::connection('redis')->size('crawl');        // backlog
// integrity (both should be 0)
App\Models\CrawlRun::whereNotNull('website_id')->whereNull('crawl_site_id')->count(); // old-code runs
App\Models\WebsitePage::whereNull('crawl_site_id')->count();                  // orphan pages
// recent failures
DB::table('failed_jobs')->where('failed_at','>=',now()->subMinutes(30))->count();
```

## Safety rails (do not violate)

- **No DB backups, binary logging off — data loss is permanent.** Never run
  `migrate:fresh/refresh/rollback`, `db:wipe`, or raw `DROP/TRUNCATE` without explicit
  per-command confirmation. Tests must run on sqlite `:memory:` (the `TestCase` guard).
- **Don't purge the shared `sync` queue** — it carries unrelated GA/GSC jobs.
- **Use `/root/.ssh/id_ed25519_worker`** for the worker box — never repurpose other services'
  credentials.
- PHP must be **8.3** on both boxes; FPM needs a full restart, not a reload.
