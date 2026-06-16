# Read path & per-user scoping

Everything users see that is derived from a crawl goes through **one** service:
`app/Services/Crawler/CrawlReportService.php`. It turns the **shared** crawl_site data into a
**per-user view** by applying three scopes on every call. This is what makes a single shared
crawl safe to expose to many users.

## The three scopes (applied in every public method)

1. **Cap window** — `windowPages()` filters pages to `value_rank <= cap` (NULL rank treated
   as in-window — site-level findings + in-progress pages). `cap = Website::crawlPageCap()`
   (the user's plan limit, default 200k).
2. **Overlay** — `findingsBase()` excludes findings the user has ignored/resolved via a
   `NOT EXISTS` against `website_finding_states` (where `status IN ('ignored','resolved')`
   for this `website_id`). No row = open.
3. **Per-user impact** — `impactFor()` reads the user's own clicks for the finding's
   `affected_url_hash` from the memoized clicks map. Stored finding `impact` is 0; the number
   the user sees is *their* clicks-at-risk.

## `context()` memoization

`context(websiteId)` (memoized per request in `$ctx[$websiteId]`) resolves:
- `cs` — `Website.crawl_site_id`
- `cap` — `Website.crawlPageCap()`
- `clicks` — `loadUserClicks()`: `SearchConsoleData WHERE website_id AND date >= now()-28d AND
  page != ''`, summed per URL, keyed by `WebsitePage::hashUrl()`.

Every `impactFor()` reads this map — so impact/severity differ per user with zero cross-leak.

## Public methods

| Method | Returns / purpose | Scope notes |
|---|---|---|
| `summary($websiteId)` | Site health overview: pages, indexables, orphans, findings-by-severity, health, run status | window + overlay |
| `actionGroups($websiteId)` | Open findings grouped by category, summed per-user impact (chunked 2000) | window + overlay + impact |
| `issuesQuery($websiteId, $category, …filters)` | Base builder for paginated category issues | window + overlay, severity+recency order |
| `issueRows($websiteId, $category)` | Detail rows (≤100) via `mapFinding` | as above |
| `typeCounts($websiteId, $category)` | Type breakdown within a category | window + overlay |
| `categoryFindings($websiteId, $category)` | Full category list (≤200), severity then impact | window + overlay + impact ordering |
| `reportBreakdown($websiteId)` | Per-category breakdown + top-N examples (email/report) | built on `actionGroups` + `categoryFindings` |
| `inventory($websiteId, $filter)` | Paginated page list (orphans/broken/noindex/deep) | window |
| `linkGraph($websiteId)` | Compact graph (≤120 nodes / ≤600 edges) for the diagram | windowed nodes |
| `pageLinkStructure($websiteId, $url)` | One page's inbound/outbound/suggested + BFS path home | page cap-checked; **neighbours not windowed** (see known-issues) |
| `pageIntel($websiteId, $url)` | Crawl signals for the AI ContextBuilder | window + overlay + impact |
| `mapFinding($finding, $websiteId)` | Normalize one finding to a UI row, inject impact | impact |
| `bfsParents($websiteId)` (internal) | Cached homepage→page parent map | full graph (cached per crawl_site) |

## Consumers

| Consumer | Calls | For |
|---|---|---|
| `Livewire/Dashboard/SiteHealthStats` | `summary` | dashboard health card |
| `Livewire/Dashboard/PriorityActionQueue` → `ActionQueueService` | `actionGroups`, `issueRows` | the action queue (merged with GSC/rank/audit sources) |
| `Livewire/SiteIssues` | `issuesQuery`, `mapFinding`, `typeCounts` | paginated issue detail |
| `Livewire/LinkStructure/LinkStructurePanel` | `pageLinkStructure` (+ a raw example query — see known-issues) | link-structure explorer |
| `Services/ReportDataService` | `summary`, `actionGroups` | growth-report "Technical SEO" section |
| `Http/Controllers/Admin/MarketingController` | `summary`, `reportBreakdown` | admin email snapshots |
| `Services/Ai/ContextBuilder` | `pageIntel` | AI tool context |
| `Http/Controllers/Api/V1/PluginHqController` | *(none direct)* — via `ReportDataService` | WordPress plugin HQ API |
| `Livewire/Admin/CrawlerProgress` | *(raw, admin-only, all crawl_sites)* | the `/admin/crawler` fleet panel |

## Caching & invalidation

- **`ReportCache`** (`app/Services/ReportCache.php`) — per-website, version-keyed
  (`ws:dataver:{websiteId}` bumped on data change). Cached payloads include the version, so a
  bump orphans stale keys. Keys: `action-queue:{websiteId}:{version}:…` (600s),
  `hq:overview:v1:{websiteId}:…:{version}` (24h).
- **Link-graph BFS cache** — `ls-parents-cs:{crawl_site_id}:{version}` (3600s), keyed on the
  crawl_site (shared) + version.
- **Flush** — `AnalyzeSiteJob::flushSubscribers` calls `ReportCache::flushWebsite()` for
  **every** subscriber website on crawl finalize (any terminal status). Also flushed by
  `SyncSearchConsoleData` and `TrackKeywordRankJob`.

## Scoping-leak audit

All AI / plugin / report reads go through `CrawlReportService` or `ReportDataService`
(GSC-scoped) — **no raw crawl-table reads** there. The one exception is the
`LinkStructurePanel` example-pages query (cap-window leak, low severity) — see
[known-issues.md](./known-issues.md).
