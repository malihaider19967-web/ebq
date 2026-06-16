# Crawl pipeline

How a domain gets crawled, end to end. All jobs run on the `crawl` queue (see
[deployment-and-queues.md](./deployment-and-queues.md)). Everything is keyed by
`crawl_site_id` (see [shared-crawl.md](./shared-crawl.md)).

## Flow

```
CrawlWebsitePagesJob(websiteId)
  ├─ resolve website → crawl_site  (create+link if missing)
  ├─ bail if crawl_site already crawling   (ShouldBeUnique: "crawl-site-{id}")
  ├─ create crawl_runs row (status=running)
  ├─ CrawlFrontierBuilder::build(crawlSite)   ── seeds website_pages frontier
  └─ dispatch CrawlPassJob (pass 1)
        │
        ▼  (multi-pass loop)
CrawlPassJob(runId, crawlSiteId, pass)
  ├─ maxPages = crawlSite.effective_cap        (re-read every pass)
  ├─ stop → AnalyzeSiteJob   if pass cap hit, budget hit, or nothing due
  ├─ select due pages, value-ordered, limited to remaining budget
  └─ Bus::batch(CrawlPageBatchJob…)
        └─ .finally → dispatch CrawlPassJob(pass+1)
              │
              ▼
CrawlPageBatchJob(pageIds, runId)
  └─ PageCrawlProcessor::process(page, crawlSite) per page
        ├─ conditional GET (etag / last-modified) + anti-block proxy policy
        ├─ classify (2xx / 3xx / 4xx-5xx / blocked) + analyze HTML
        ├─ SimHash change detection → adaptive next_crawl_at
        └─ rebuild outbound internal-link edges + stub pages for new links
              │
              ▼  (final pass)
AnalyzeSiteJob(runId)
  ├─ block rollup → if site wholesale-blocked: crawl_blocked finding + abort
  ├─ status → finalizing  ("computing your results")
  ├─ SiteGraphAnalyzer   → inbound counts, click depth (BFS), value_rank
  ├─ SiteIssueDetector   → findings catalog (shared, impact=0, base severity)
  ├─ InternalLinkSuggester → suggested edges (shared; term-overlap)
  ├─ scorePages          → page_score + site health_score
  ├─ resolveStale        → close findings not re-seen this run
  ├─ bridge404s          → per-subscriber MatchRedirectFor404Job
  └─ status → completed  + flush ReportCache for ALL subscribers
```

## The multi-pass design (why it exists)

A single pass only crawls the pre-selected frontier (GSC ∪ sitemaps). The homepage
links to category pages that aren't in the frontier; without crawling them the link
graph is disconnected and everything looks like a false orphan / "too deep". So
each page's `rebuildEdges` creates **stub** `website_pages` rows for discovered
links (due immediately), and the **next pass** crawls those. The loop ends when a
pass finds nothing new, the pass cap is hit, or the per-run page budget
(`effective_cap`) is reached.

## The value ordering (capped crawls buy what matters)

`CrawlValueRank::order()` — `source_gsc desc → source_sitemap desc → CHAR_LENGTH(url)
asc → id`. A capped crawl fetches the highest-value pages first, and the same
ordering is persisted as `value_rank` so each user's cap window is consistent with
what was crawled.

## Incremental / adaptive recrawl

- Conditional GET (etag + last-modified) → `304` skips re-download.
- `content_simhash` + a distance threshold → noise-tolerant change detection.
- `consecutive_unchanged` → geometric backoff of `next_crawl_at` (stable pages
  recrawl less often; changing pages more often).
- Sitemap `<lastmod>` trust is learned per domain (`sitemap_lastmod_true/false` on
  the crawl_site) and used by `CrawlSitemapDeltaJob` to pull genuinely-updated pages
  forward without waiting for the weekly recrawl.

## Frontier (shared across subscribers)

`CrawlFrontierBuilder::build(crawlSite)` unions, across **all** subscriber websites:
their GSC pages + every `<loc>` in their sitemaps, plus always the canonical
homepage (`https://{normalized_domain}`) so a domain-only site still gets crawled.
`source_gsc` becomes an aggregate flag. New rows are due immediately.

## Scheduling

`ebq:crawl-websites` (cron) iterates **crawl_sites** (not websites):
- default: weekly cadence (no run in 7 days);
- `--backfill`: never-crawled crawl_sites only (run after deploy);
- `--sitemap-deltas`: daily, dispatch `CrawlSitemapDeltaJob`.
It dispatches `CrawlWebsitePagesJob` via a representative subscriber website.

## Reliability notes

- `CrawlWebsitePagesJob` / `CrawlPassJob`: `tries = 1` (no blind retries of a whole
  crawl). `AnalyzeSiteJob`: `timeout = 1200s`, and its `failed()` finalizes the run
  (`completed`) instead of leaving it stuck "running" — important on very large
  sites where analysis can exceed the limit. **Repeated AnalyzeSiteJob timeouts on
  huge sites are a known reliability area** (observed as "crawls not finishing").
- `retry_after` (`REDIS_QUEUE_RETRY_AFTER=1320`) must exceed the longest job
  timeout, or a long crawl is re-dispatched while still running.
