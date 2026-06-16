# Adjacent systems

Systems that sit next to the crawler and how they connect to the shared crawl. The rule of
thumb: **page/link/finding data is per-crawl_site (shared); anything tied to a user's own
Google traffic stays per-website.**

## Sitemaps

- **Discovery is per-website.** `SyncSitemaps` (queue `sync`) reads each website's GSC
  sitemap list into `website_sitemaps` (keyed by `website_id`). No crawl trigger.
- **The frontier unions sitemaps across all subscribers** (`CrawlFrontierBuilder`);
  `source_sitemap` on a shared page = "in ANY subscriber's sitemap".
- **`CrawlSitemapDeltaJob`** (daily, queue `crawl`, unique per crawl_site) extracts sitemap
  URLs, seeds genuinely-new ones into `website_pages` (crawl_site-keyed, due now), updates
  `sitemap_lastmod`, and — if the domain's lastmod is trusted — pulls changed pages forward.
  It then dispatches `CrawlWebsitePagesJob($website->id)`; the unique lock collapses multiple
  subscribers to one crawl (see [known-issues.md](./known-issues.md)).
- **Lastmod trust is per-crawl_site.** `sitemap_lastmod_true/false` live on `crawl_sites`,
  incremented atomically in `PageCrawlProcessor` per fetch outcome. Trusted when ≥20 samples
  and ≥30% predictive. `CrawlSitemapDeltaJob` checks this before early-recrawling.

## Incremental / change detection

Per-page, shared. `PageCrawlProcessor` computes `content_simhash`; if the Hamming distance
from the previous simhash exceeds `crawler.simhash_threshold` (default 3) the page is
"changed" (resets `consecutive_unchanged`), else unchanged (increments it). `next_crawl_at`
backs off geometrically — `min_days * 2^consecutive_unchanged`, clamped `[3, 30]` days.
Conditional GET (etag/last-modified → 304) skips re-download entirely.

## Redirects / 404 bridging — per-website

`redirect_suggestions` and `AiRedirectMatcherService` are tied to the user's own GSC traffic
and redirects, so they stay **per-website**:
- `AnalyzeSiteJob::bridge404s` reads the shared open `broken_internal`/`broken_page` findings,
  then dispatches `MatchRedirectFor404Job(website_id, path, …)` **per subscriber**.
- `AiRedirectMatcherService` builds candidate inventory from that website's **own**
  `SearchConsoleData` (top ~200 URLs by clicks) and writes `redirect_suggestions` keyed by
  `website_id`. Each user gets redirect suggestions weighted by *their* traffic.

## Term extraction & link suggestions — shared

- `PageCrawlProcessor` extracts weighted significant terms (title/slug-boosted TF + bigrams)
  into `website_pages.content_terms` during the crawl (language-agnostic, no stopword lists).
- `AnalyzeSiteJob` builds a sampled document-frequency table over the crawl_site, then
  `InternalLinkSuggester` scores topical overlap (TF-IDF) and writes `suggested`
  `website_internal_links` (orphans/deep pages ← authority pages, ≤3 per target). All shared —
  suggestions are a pure function of the shared crawl.
- `CRAWLER_PRUNE_BODY_TEXT` (opt-in) trims `body_text` to an excerpt **after** analysis.

## Plugin API — safe

`PluginHqController` reads **no crawl tables directly**. Its endpoints read
`SearchConsoleData` / `RankTrackingKeyword` / `PageIndexingStatus`, and its insights call
`ReportDataService` (which is GSC-scoped or routes through `CrawlReportService`). Per-website
Sanctum token → inherits the same scoping. No leak surface here.

## AI — scoped via the read service

`Services/Ai/ContextBuilder` pulls crawl signals only through
`CrawlReportService::pageIntel()` (cap window + overlay + per-user impact).
`AiContentBriefService` uses GSC-clicked URLs, not raw crawl tables.

## Verified unaffected by the shared-crawl change

`DetectTrafficDrops`, `SyncPageIndexingStatus`, `GenerateAiInsights`, and the Pages tables
read GSC / indexing data, **not** the crawl tables — re-verify if they ever start reading
`website_pages`/`crawl_findings` directly.
