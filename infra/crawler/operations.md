# Operations runbook

Day-to-day operation of the crawler: how to watch it, deploy it, trigger crawls, and
diagnose the failure modes we've actually hit. For the topology/queue details this references,
see [../deployment-and-queues.md](../deployment-and-queues.md).

## Watching crawls

- **Admin UI:** `/admin/crawler` — the fleet panel (`Livewire/Admin/CrawlerProgress`), live
  (5s poll). Shows, per crawl_site: status, cap-relative progress, crawled pages, errors, open
  issues, health, subscribers/cap, last activity — plus summary cards incl. the **crawl-queue
  backlog**. The backlog card is the first thing to check when crawls feel slow.
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

### Congestion / "a domain looks stuck"
**Symptom:** one domain's run hasn't advanced for many minutes while other domains crawl;
queue backlog in the thousands. **Cause:** the single FIFO `crawl` queue + a large domain (or
a full `--backfill`) monopolising all 5 workers; the parked domain's continuation jobs are
behind the flood. **Not a crash** — `pages_fetched` would move if its pass had run.
**Diagnose:** compare each run's `updated_at`; check which crawl_site the recent
`last_crawled_at` writes belong to. **Fix options:** wait it out (it will surface), or do a
focused reset — abort the other runs + clear the `crawl` queue (NOT `sync`) + re-dispatch the
target domain alone. Aborting other in-flight crawls means they re-crawl later.

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
