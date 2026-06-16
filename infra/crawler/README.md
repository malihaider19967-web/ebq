# Crawler subsystem

Full documentation for the EBQ site crawler — the **shared single-crawl store**: each domain
is crawled **once**, at the **max page cap** among the users who added it, and every user
reads that one crawl through a **cap-limited, privacy-scoped per-user view**.

## Read in this order

| Doc | What it covers |
|---|---|
| [architecture.md](./architecture.md) | **Start here.** The shared `crawl_site` model, shared-vs-per-user split, `value_rank` cap windowing, lifecycle (subscribe / charge / GC). The invariants. |
| [data-model.md](./data-model.md) | The 5 tables + overlay: full schema, indexes, models, `normalizeDomain`, the Website hooks, the subscribe charge. |
| [pipeline.md](./pipeline.md) | The write path: frontier → multi-pass loop → page batches → analysis. Jobs, services, value ordering, adaptive recrawl, scheduling. |
| [read-path.md](./read-path.md) | `CrawlReportService` — the single read surface. The three per-user scopes (cap window, ignore/resolve overlay, read-time impact), every method, every consumer, caching. |
| [findings-and-scoring.md](./findings-and-scoring.md) | The finding catalog (categories, types, severity rules, 404 referrers) and the 0–100 health-score formula. |
| [adjacent-systems.md](./adjacent-systems.md) | Sitemaps, incremental recrawl, redirect/404 bridging, term extraction, plugin API, AI — and which are per-website vs per-crawl_site. |
| [operations.md](./operations.md) | Runbook: watching crawls (`/admin/crawler`), triggering, deploying, and the failure modes (congestion, stuck crawls, leaked locks, orphans) with fixes. |
| [autoscaling.md](./autoscaling.md) | Elastic worker fleet on Hetzner: fleet model, provisioning, the autoscaler roadmap. |
| [known-issues.md](./known-issues.md) | Verified gaps and accepted trade-offs (incl. the `LinkStructurePanel` cap-window leak). |

Infra-wide topology (2 boxes, queues, deploy, the rollout postmortem) lives one level up:
[../deployment-and-queues.md](../deployment-and-queues.md).

## One paragraph

A domain used to be crawled **once per website** — 10 users adding `basepaws.com` meant 10
fetches on 10 schedules. Now a **`crawl_sites`** row (one per normalized domain) owns the
crawl; the four crawl tables (`website_pages`, `website_internal_links`, `crawl_runs`,
`crawl_findings`) are keyed by **`crawl_site_id`**. The domain is fetched once at the max
subscriber cap; each user reads it filtered to their own cap window (`value_rank <= cap`),
their own ignore/resolve overlay, and their own GSC-derived impact.

## Invariants (do not break)

1. **Crawl data is keyed by `crawl_site_id`, never `website_id`** (the `website_id` column is
   nullable, FK-less, unused cruft).
2. **A domain is fetched once** — `CrawlWebsitePagesJob` is unique per `crawl-site-{id}`; one
   in-flight run serves all subscribers and extends to a higher cap mid-flight.
3. **Per-user data never leaks** — impact + click severity are read-time from each website's
   own GSC; shared findings store `impact = 0`; ignore/resolve is the per-website overlay.
4. **Each user sees only their cap window** — reads filter `value_rank <= the owner's cap`.
5. **Deleting a website never deletes a crawl others still use** — the `website_id` cascade FK
   was dropped; GC happens only when the last subscriber leaves.

## Key code

- Models — `app/Models/{CrawlSite,Website,CrawlRun,WebsitePage,WebsiteInternalLink,CrawlFinding,WebsiteFindingState}.php`
- Write — `app/Jobs/{CrawlWebsitePagesJob,CrawlPassJob,CrawlPageBatchJob,AnalyzeSiteJob,CrawlSitemapDeltaJob}.php`,
  `app/Services/Crawler/{CrawlFrontierBuilder,PageCrawlProcessor,SiteGraphAnalyzer,SiteIssueDetector,InternalLinkSuggester}.php`,
  `app/Support/Crawler/CrawlValueRank.php`
- Read — `app/Services/Crawler/CrawlReportService.php`
- Lifecycle — `app/Services/Crawler/CrawlSiteBootstrapper.php`
- Admin — `app/Livewire/Admin/CrawlerProgress.php` (`/admin/crawler`)
- Tests — `tests/Feature/SharedCrawlTest.php`, `tests/Unit/CrawlSiteNormalizeDomainTest.php`
