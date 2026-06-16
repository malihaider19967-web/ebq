# Livewire patterns & component inventory

50 components in `app/Livewire/`, each with a Blade view in `resources/views/livewire/`.
All are **embedded** (`<livewire:area.name />`) inside Blade page views — there are no
full-page Livewire routes. Start with [README.md](./README.md).

## The active-website contract (the single most important pattern)

The current website is **session state**, not a route param or component prop:

- **Writer:** `WebsiteSelector` (top bar, `app.blade.php:201`). On change it sets
  `session(['current_website_id' => $id])` and `dispatch('website-changed', websiteId: $id)`
  (`WebsiteSelector.php:35-45`). It seeds the session in `mount()` to the first accessible
  website if none is set (`:25-32`).
- **Readers (~35 components):** read `(int) session('current_website_id', 0)` in `mount()`,
  then subscribe to the event to live-update without a page reload:
  ```php
  #[On('website-changed')]
  public function switchWebsite(int $websiteId): void { $this->websiteId = $websiteId; }
  ```
- **Authz everywhere:** reads are gated by `Auth::user()->canViewWebsiteId($id)` /
  `hasFeatureAccess(...)` before querying. A component must never trust the session id alone.

Other cross-component events: **`country-changed`** (`Dashboard/CountryFilter` →
`KeywordsTable`, KPI/traffic cards), **`open-connect-sources`** (banners/prompts →
`ConnectSourcesModal`).

## Component map

| Area | Components | Pattern notes |
|---|---|---|
| Top-level | `WebsiteSelector`, `CrawlBanner`, `SiteIssues`, `ConnectSourcesModal` | selector = website writer; CrawlBanner `wire:poll.10s`; ConnectSourcesModal is the app-wide singleton in the layout |
| Dashboard | `KpiCards`, `TrafficChart`, `InsightCards`, `QuickWinsCard`, `SeasonalityCard`, `TopCountriesCard`, `CountryFilter`, `PriorityActionQueue`, `SiteHealthStats`, `SyncAndReportPanel`, `SitemapPrompt` | **all `#[Lazy]`** with skeleton `placeholder()`; query GA/GSC with `Cache::remember`; `CountryFilter` emits `country-changed` |
| Keywords | `KeywordsTable`, `KeywordDetail`, `KeywordResearch`, `KeywordIdeaFinder`, `KeywordVolumeFinder`, `KeywordFixPlaybook` + `Concerns/TracksKeyword` | the *-Finder/Playbook components use **conditional `wire:poll`** (`isPolling()` → `poll`) for async server-side jobs; shared `TracksKeyword` trait |
| Rank Tracking | `RankTrackingManager`, `RankTrackingDetail` | |
| Pages | `PagesTable`, `PageDetail`, `PageAuditDetail`, `CustomAudit`, `PageSpeed` | `CustomAudit`/`PageSpeed` poll while audits run (`pollAudit`/`pollResult`) |
| Sitemaps | `SitemapsManager` | |
| Link Structure | `LinkStructurePanel` | reads crawl data; cap-window leak noted in crawler `known-issues.md` |
| Backlinks | `BacklinksManager` | |
| Competitive | `CompetitorDiscovery`, `KeywordGapAnalysis` | conditional poll (`isPolling()`/`isVerifying()`) |
| Reports | `ReportGenerator`, `ReportPreview`, `InsightsPanel` | |
| Settings | `IntegrationsPanel`, `ProfileSettings`, `MailTransport`, `ReportBranding`, `ReportRecipients`, `GscKeywordWindow`, `WordPressPlugin` | per-tab panels; `IntegrationsPanel` manages GA/GSC connections |
| Onboarding | `Onboarding/ConnectGoogle` | sets `just_onboarded` flash → one-shot welcome modal on dashboard (`dashboard.blade.php`) |
| Websites | `WebsitesList`, `WebsiteTeam` | |
| Billing | `SubscriptionPanel` | |
| Admin | `Admin/CrawlerProgress` (`wire:poll.5s`, `/admin/crawler`), `Admin/ProxyManager` | fleet ops dashboards |

## Polling cheat-sheet

| Component | View | Directive |
|---|---|---|
| `Admin/CrawlerProgress` | `admin/crawler-progress.blade.php:1` | `wire:poll.5s` (always) |
| `CrawlBanner` | `crawl-banner.blade.php:1` | `wire:poll.10s` (always) |
| `KeywordFixPlaybook` | `keywords/keyword-fix-playbook.blade.php:36` | `wire:poll.3s="pollAudit"` |
| `KeywordIdeaFinder` | `keywords/keyword-idea-finder.blade.php:1` | conditional `wire:poll.2000ms="poll"` |
| `KeywordVolumeFinder` | `keywords/keyword-volume-finder.blade.php:8` | conditional `wire:poll.2000ms="poll"` |
| `CompetitorDiscovery` | `competitive/competitor-discovery.blade.php:1` | conditional `wire:poll.3000ms="poll"` |
| `KeywordGapAnalysis` | `competitive/keyword-gap-analysis.blade.php:17` | conditional (`isPolling`/`isVerifying`) |
| `CustomAudit` | `pages/custom-audit.blade.php:5` | conditional `wire:poll.3s` |
| `PageSpeed` | `pages/page-speed.blade.php:61` | `wire:poll.2s="pollResult"` |

**Why conditional polling:** the `wire:poll` directive is wrapped in
`@if($this->isPolling()) … @endif` so polling stops the moment the backing job
finishes — avoids hammering the server (and the queue/Redis) after results land.

## Conventions

- **Skeletons via `placeholder()`** — every `#[Lazy]` component returns a heredoc of
  Tailwind `animate-pulse` markup (`Dashboard/KpiCards.php:19-41`). Those utility classes
  are kept by Tailwind because `app.css` `@source`s the compiled views.
- **Caching reads** — dashboard cards wrap GA/GSC aggregates in `Cache::remember` keyed by
  `website:date:range` to keep poll/re-render cheap.
- **Alpine inside Livewire** — UI-only state (dropdowns, modals, tabs) is plain Alpine
  `x-data` in the Blade; Livewire owns server state. Livewire 3 ships Alpine, so portal
  pages need no separate Alpine boot (contrast marketing — see README JS entrypoints).
- **Dispatch, don't couple** — components communicate by events (`website-changed`,
  `country-changed`, `open-connect-sources`), never by parent/child props across areas.
