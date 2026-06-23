# Sync jobs & the degradation rule

The three jobs that pull Google data onto the **`sync` queue** (`App\Support\Queues::SYNC`),
their cadence and windowing, and the GSC/GA presence-degradation contract every feature obeys.

## Key components

| Job | Source / API | Writes | Cadence |
|---|---|---|---|
| `SyncSearchConsoleData` (`app/Jobs/SyncSearchConsoleData.php`) | GSC Search Analytics | `search_console_data` (upsert) | Nightly via `ebq:sync-daily-data` (30-day window); 365-day backfill on create/connect. |
| `SyncAnalyticsData` (`app/Jobs/SyncAnalyticsData.php`) | GA4 Data API | `analytics_data` (upsert) | Same nightly fan-out; 365-day backfill on create/connect. |
| `SyncPageIndexingStatus` (`app/Jobs/SyncPageIndexingStatus.php`) | GSC URL Inspection | `page_indexing_statuses` (updateOrCreate) | **On demand** — not in the nightly fan-out. Dispatched from page/plugin flows for top-clicked pages. |

`SyncAnalyticsData`/indexing `timeout=600`; **`SyncSearchConsoleData` is `timeout=3600` on the
dedicated `redis-long` connection** (retry_after 3900 > timeout, same pattern as `AnalyzeSiteJob` —
see `config/queue.php`) because high-volume GSC accounts (tens of thousands of rows/week) can
exceed 600s paging + upserting a 30-day window. SC/GA `tries=2` (SC also `backoff=120`), indexing
`tries=1` (per-page loop is idempotent but the URL-Inspection quota is tight, so no auto-retry).
`SyncSearchConsoleData` also carries a `WithoutOverlapping('sync-search-console:'.$websiteId)`
job middleware (`expireAfter($timeout+300)`, `dontRelease()`, same pattern as `AnalyzeSiteJob`) —
without it, a run that outlives `retry_after` gets a duplicate dispatched on top of it (two
concurrent copies fighting over the same upserts; this actually happened, see incident below).

## Cadence & dispatch

- **Nightly**: `Schedule::command('ebq:sync-daily-data')->daily()` (`routes/console.php:11`) →
  `SyncDailyData` chunks all websites and dispatches `SyncAnalyticsData` + `SyncSearchConsoleData`
  per site with the **default 30-day** window. (`SyncWebsiteData` action is the same pair for
  manual/per-website use.)
- **On website create**: the `Website::created` hook (`app/Models/Website.php:76`) dispatches the
  two syncs with a **365-day** backfill — but only the sources that are connected (`hasGa()`/
  `hasGsc()` guards) and only if **not frozen**.
- **On late connect**: when a user connects GSC/GA after onboarding (`ConnectGoogle`,
  `IntegrationsPanel`), the same 365-day backfill is re-dispatched, and `Website::updated`
  fires `ReprocessCompetitiveData` on the genuine `gsc_google_account_id` null→set edge.
- **Backfill commands**: `ImportHistoricalData` and `ResyncGsc` dispatch with a configurable
  lookback (e.g. to repopulate the country/device dimensions).

## Date-window strategy

- **GSC** paginates *time*: it walks the lookback in **7-day windows** (`cursor` += 7d, window
  end = `cursor+6d`), because GSC Search Analytics is slow/large and the service caps each call
  at 25k rows — `SearchConsoleService::fetchSearchAnalytics` paginates `startRow` up to a 200k
  safety ceiling per window (`SearchConsoleService.php:86`). Dimensions requested:
  **date, query, page, country, device** (so reports can slice by market/device).
- **GA4** fetches the whole lookback in one `runReport` (limit 10k rows), dimensions
  **date + sessionSource**, metrics **totalUsers, sessions, bounceRate**
  (`GoogleAnalyticsService::fetchDailyTraffic`). GA4 dates come back as `YYYYMMDD` and are
  reformatted to ISO.
- This is **incremental-by-upsert, not delta**: each run re-fetches the window and upserts, so
  late-arriving GSC data (GSC backfills ~2–3 days) is corrected on the next run. No high-water mark.
- `SyncSearchConsoleData` updates `last_search_console_sync_at` (+ `ReportCache::flushWebsite`)
  **after every 7-day window**, not just at the end — so a run that dies partway through a
  high-volume account still leaves the rows it already upserted visible, and the watermark
  reflects partial progress instead of staying permanently null. Each window also logs its row
  count (`storage/logs/laravel.log`) so a stalled run is visible instead of silent.

## Upsert keys

| Table | Unique key (upsert) | Updated columns |
|---|---|---|
| `search_console_data` | `(website_id, date, query, page, country, device)` | `clicks, impressions, position, ctr, updated_at` |
| `analytics_data` | `(website_id, date, source)` | `users, sessions, bounce_rate, updated_at` |
| `page_indexing_statuses` | `(website_id, page)` | the `google_*` verdict columns + checked-at |

GSC `page` is run through `UrlNormalizer::normalize` before upsert; chunked at 500 rows. After a
GSC sync, `ReportCache::flushWebsite` bumps the cache version, and queries that cleared a
**100-impression gate** in the window are queued for Keywords-Everywhere enrichment
(budget-safe, skips ones already fresh in `keyword_metrics`).

`SyncPageIndexingStatus` selects the **top `maxPages` (default 25) by clicks** over the last 30
days of `search_console_data`, calls URL Inspection per URL, and stores the
verdict/coverage/indexing-state payload; on first sight of a page it kicks off a `PageAuditService`
audit.

## The degradation rule (4 presence combinations)

A website can have any of: **GSC yes/no × GA yes/no**. There is no combined helper — features
gate on the two independent checks:

- `Website::hasGsc()` (`Website.php:559`) = `gsc_site_url` non-empty **and** `gsc_google_account_id` set.
- `Website::hasGa()` (`Website.php:549`) = `ga_property_id` non-empty **and** `ga_google_account_id` set.

(The empty-string-vs-null distinction matters: a "pay-first placeholder" site stores `''`, which
reads as *absent*.) The **PageSpeed-only** combination is simply `!hasGsc() && !hasGa()`.

**Enforcement layers:**
1. **Dispatch guards** — `created`/connect hooks only dispatch a sync for a connected source.
2. **Job guards** — each job re-checks `gsc_site_url`/`ga_property_id` emptiness *and*
   `gscAccountResolved()`/`gaAccountResolved()` null, and no-ops (logged) rather than erroring.
3. **Read/UI gating** — consumers branch on `hasGsc()`/`hasGa()`: `ReportDataService` sets
   `ga`/`gsc` payload flags; `KeywordGapService` falls back to non-GSC discovery when GSC is
   absent; `SitemapsManager`/`CompetitorDiscovery`/`SiteHealthStats` require GSC (or sitemaps/
   manual seeds) for their GSC-backed surfaces; blade banners (`connect-source-banner`) prompt
   to connect the missing source. **Own-keyword data is always available** even without GSC via
   crawler/website-mode discovery — see project memory `gsc-ga-degradation`.

## The freeze guard (quota protection)

`Website::isFrozen()` (computed, no column — delegates to `User::frozenWebsiteIds()`,
plan-limit over-cap, oldest sites kept) is checked at the **top of both `SyncSearchConsoleData`
and `SyncAnalyticsData`** and in the `created` hook. A frozen site is invisible to the user
until they upgrade, so syncing it would waste Google quota — the job returns early (info log).

## Gotchas / known issues

- **`SyncPageIndexingStatus` is not scheduled** — it only runs when a page/plugin flow asks for
  it, and only for the top-25-by-clicks pages. Index state for long-tail pages is never refreshed
  automatically. URL Inspection has a low Google quota, hence `tries=1` and the small cap.
- **No high-water mark** — nightly always re-fetches the full 30-day window per site. Cheap for
  GA4, but for high-traffic sites GSC re-fetches a lot; this is deliberate (corrects GSC's own
  2–3 day backfill) but is the main quota cost.
- **`search_console_data` is the big table** (tens of millions of rows on large sites). Heavy
  aggregation reads forced index work — see `data-model.md` for the online-ALTER index history.
- A missing/failed Google token throws inside the job's API call; jobs catch per-website (warning
  log) so one bad account doesn't abort the whole nightly fan-out.
- **2026-06-23 incident — `namesforfreefire.com` GSC stale since 2026-04-16.** A single
  high-volume GSC account (~38k rows, the single biggest in `search_console_data`) consistently
  exceeded the old `timeout=600`, with nothing logged (the timeout kill happens before `handle()`
  gets a chance to log anything). First fix attempt (`timeout` 600→3600 + redis-long + per-window
  watermark/logging) was deployed and re-tested live — the job still hung on window 1 for over an
  hour with **zero CPU progress** and no DB query in flight. Root-caused to two compounding bugs,
  confirmed live:
  1. **`Google\Client`'s default HTTP client has no read timeout.** A manual replay of the exact
     same `searchanalytics.query` call (5 dimensions, 7-day window, paginated to 124,582 rows)
     completed in ~15s when an explicit Guzzle `timeout` was set — proving the API/account/quota
     were never the problem. Without that explicit timeout, a stalled response can block
     `curl_exec()` indefinitely, and the job's own `$timeout` (pcntl `SIGALRM`) doesn't reliably
     interrupt a blocking libcurl read — so raising the job timeout alone never actually fixed
     anything, it just delayed the same unbounded hang. Fixed in `GoogleClientFactory::make`
     (`connect_timeout=10`, `timeout=120` — see `google-oauth.md`).
  2. **No overlap guard.** Once one run outlived `redis-long`'s `retry_after` (3900s), Redis
     dispatched a duplicate on top of the still-running original — confirmed via the
     `*:sync:reserved` zset showing **two live reservations for the same website** simultaneously,
     each fighting the other over the same `search_console_data` upserts. Fixed with
     `WithoutOverlapping('sync-search-console:'.$websiteId)` (see above).
  Confirmed pcntl is present on the worker Docker image (`ebq-worker:8.3`) — not a missing
  extension, purely the missing client-side timeout + missing overlap guard.

## Key files

- `app/Jobs/{SyncSearchConsoleData,SyncAnalyticsData,SyncPageIndexingStatus}.php`
- `app/Console/Commands/{SyncDailyData,ImportHistoricalData,ResyncGsc}.php`, `app/Actions/SyncWebsiteData.php`
- `app/Services/Google/{SearchConsoleService,GoogleAnalyticsService}.php`
- `app/Models/Website.php` (`hasGsc/hasGa/isFrozen/gscAccountResolved/gaAccountResolved`)
- `routes/console.php` (schedule)
