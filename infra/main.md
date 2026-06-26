# EBQ — Engineering Knowledge Base (entry point)

> **This is the map of the entire application.** If you (Claude or a human) need to
> understand any part of EBQ, **start here** and follow the links. This file is the *spine*:
> it links to every knowledge doc, states the rules that must never break, and defines the
> discipline for keeping all of it true. Depth lives in the linked docs — this file stays a
> map, never a dump.

EBQ is a self-hosted **SEO platform**: it crawls a client's website, pulls their Google
(Search Console + Analytics) data, and turns both into findings, growth reports, keyword &
rank tracking, backlink/competitive intelligence, an AI content suite, and a WordPress-plugin
API. Laravel 11, PHP 8.3, MariaDB + Redis, two-box deploy (see the topology docs).

---

## ⛔ The maintenance protocol — the whole point of this file

This knowledge base is only useful if it stays true. **Whenever you work on EBQ you are
responsible for keeping it current.** Treat documentation as part of the task, not an extra.

**WHEN to update (triggers):**
1. **You changed code / schema / config / architecture** → update the affected subsystem doc
   to match the new reality, *in the same change*. A stale doc is worse than no doc.
2. **You learned something non-obvious** — a gotcha, a runtime fact, *why* it's built this
   way, a production incident, a tuning value → write it in the relevant doc.
3. **You built a new subsystem / feature** → create `infra/<area>/…` docs for it and add a row
   to the System Map below.
4. **You found the docs wrong or outdated** → fix them now and note the correction.

**HOW to update (rules):**
- **Code-grounded.** Verify against the actual code before writing; cite `file:line` /
  class / method. Never document from memory or assumption alone.
- **One fact, one place.** Link, don't copy. Never paste subsystem detail into this file.
- **Date the time-sensitive.** Incidents, "as of", tuning numbers → absolute dates.
- **Edit, don't pile up.** Correct the existing line rather than stacking contradictions.
- **Keep the index honest.** Update the System Map + the "Where docs are thin" list whenever a
  doc/subsystem is added, renamed, removed, or changes coverage.
- **Add a Knowledge Changelog line** (bottom of this file) for any architectural change or new
  doc.
- **Mirror durable, session-spanning facts** into project memory (`MEMORY.md`) too — that
  layer survives even when the repo isn't open.

**If a task is finished but the docs would now be wrong or incomplete, the task is not done.**

---

## How to navigate

- **Starting any task:** read this file, then open the doc(s) for the subsystem you're
  touching. Together they are the full picture.
- **"Read main.md"** = read this + follow the links relevant to the task.
- **Authority order:** repo `infra/` docs (code-grounded) > project memory (`MEMORY.md`,
  operational/session facts) > the code itself (ground truth — when in doubt, read it).

---

## System map — every subsystem

**Status:** ✅ documented · 🟡 partial · ⬜ code-only. As of **2026-06-16 the whole
application is documented** — keep it that way (see the protocol). Each area links its
`README.md`; sub-docs are listed after the arrow.

### Platform & infrastructure ✅
- **Conceptual topology, queues, deploy procedure, rollout postmortem** →
  [deployment-and-queues.md](./deployment-and-queues.md)
- **Live server inventory** (both boxes: hardware, OS, Apache/FPM, MariaDB, Redis, ports,
  integrations, risks) → [server-deployment.md](./server-deployment.md)
- **DB safety rules** (prod, no backups) → repo-root `CLAUDE.md`; memory `never-destructive-db-data`

### Database sharding — full-ULID, multi-node 🟡 (built on a branch, not merged)
[sharding/](./sharding/README.md) — three tiers behind one routing layer: central (identity/billing/
catalogs) + **tenant shards by owner** (`websites.db_node_id`) + **crawl shards by domain**
(`crawl_sites.crawl_node_id`). Whole schema re-keyed to **ULID**; cross-tier FKs dropped (app-enforced
via `ShardCleanup`); admin-managed `db_nodes` fleet (`ebq:db-node` + `/admin/db-fleet`, clones the crawl
fleet) + a tenant/crawl **mover** (`ebq:shard`, validated on MariaDB). On branch
`feature/db-sharding-ulid`; single-node behaviour is unchanged until a node anchor is set. Plan:
repo-root `SHARDING_PLAN.md`.

### Crawler — the heaviest subsystem ✅
[crawler/](./crawler/README.md) → architecture · data-model · pipeline · read-path ·
findings-and-scoring · adjacent-systems · operations · known-issues
— **fairness** (`pages_per_pass`) interleaves sites so no big domain monopolises the queue;
the **`ebq:crawl-supervisor`** watchdog (every 5 min) recovers wedged multi-pass chains;
[autoscaling.md](./crawler/autoscaling.md) — elastic worker fleet on Hetzner (Phase 1 shipped:
`worker_nodes` + `ebq:fleet-worker`; the queue is central so new boxes just pull, no rebalance).

### Data sources — Google & Microsoft ✅
[data-sources/](./data-sources/README.md) → google-oauth · sync-jobs · data-model
— GSC is the **only** search-data source; Microsoft = Outlook mail only (no Bing ingestion).
The GSC/GA degradation rule covers all 4 presence combos.

### Keywords & rank tracking ✅
[keywords/](./keywords/README.md) → keyword-finder (self-hosted Google-Keyword-Planner fleet) ·
keyword-research · rank-tracking

### Backlinks, competitive & SERP ✅
[competitive/](./competitive/README.md) → backlinks · serp
— `serp_cache` is **cross-tenant** (keyed by query+gl); cross-network aggregates fail closed
below a 5-site cohort.

### Reports, action queue & anomaly ✅
[reports/](./reports/README.md) → insights · action-queue · growth-reports
— `ActionQueueService` merges crawl findings + GSC reports + rank drops + audits into one
ranked queue. (`GenerateAiInsights` is still a stub.) Branded PDF exports (Growth Report +
the crawler's Site Audit) both go through `ReportBranding`/`ReportBrandingResolver`
(plan-gated `report_whitelabel`) + dompdf — see "Site Audit PDF export" in
[crawler/known-issues.md](./crawler/known-issues.md).

### AI suite ✅
[ai/](./ai/README.md) → tools · writer · llm
— LLM is **Mistral only** today (`LlmClient` multi-provider is aspirational). Writer pipeline
is synchronous/in-request.

### Audits & performance ✅
[audits/](./audits/README.md) → page-audit · lighthouse-and-performance ·
live-score-and-language · topical-authority — external Lighthouse service; SSRF-guarded fetches.

### WordPress plugin & HQ API ✅
[wordpress-plugin/](./wordpress-plugin/README.md) → **server side:** hq-api · releases —
auth is a **Sanctum token per Website**; the HQ API reads GSC/`ReportDataService` only,
**no raw crawl tables**. **Client side:** plugin-source · plugin-features — the EBQ SEO
plugin codebase (42 PHP classes + React build) is a **separate git repo** checked out at
`/var/www/ebq/ebq-wordpress-plugin/` (gitignored; **never commit it here**), calling the HQ
API via an `EBQ_Rest_Proxy`; core on-page output works offline.

### Guest (public, lead-gen) tools ✅
[guest-tools/](./guest-tools/README.md) — rank / pagespeed / volume / audit; shared
request→queued-job→email-link→results pattern, reCAPTCHA + rate limits + lead capture.

### Billing, plans & usage ✅
[billing/](./billing/README.md) → plans-and-gating · usage
— billing is **per-USER** (not per-website), **yearly only**; caps + feature flags gate
features; `client_activities` + `UsageMeter` track spend.

### Accounts, onboarding, teams ✅
[accounts/](./accounts/README.md) — auth (login errors as banner), Google SSO + source connect,
website selection, teams via `website_user` + `TeamPermissions` (null = full access).

### Admin panel ✅
[admin/](./admin/README.md) — `is_admin` gating + per-Livewire-action re-check, impersonation,
marketing crawl-report sends, proxies, keyword servers, platform settings. (Crawler panel →
crawler/operations; Plugin/Plan/Billing panels → their own subsystem docs.)

### Frontend / UI ✅
[frontend/](./frontend/README.md) → livewire-patterns — Livewire 3 + Alpine + Tailwind 4 +
Vite 7. **No full-page Livewire routes** (Blade `Route::view` embeds `<livewire:…>`); the
**active website is session state** (`current_website_id`, propagated via `website-changed`).

### Cross-cutting reference ✅ (the horizontal layer — `infra/reference/`)
| Doc | Covers |
|---|---|
| [reference/database.md](./reference/database.md) | All **83 tables** grouped by domain + the 49-model index, FK semantics, migration conventions, hash/encrypt patterns |
| [reference/routing.md](./reference/routing.md) | Consolidated **endpoint map** across web/api/auth/channels + `bootstrap/app.php` |
| [reference/http-and-auth.md](./reference/http-and-auth.md) | Middleware, the two guards (session vs **Sanctum per-Website**), authorization, request lifecycle |
| [reference/jobs-and-scheduler.md](./reference/jobs-and-scheduler.md) | All **25 jobs** + **17 commands** + schedule, with a **destructive-commands safety** section |
| [reference/configuration.md](./reference/configuration.md) | All **16 `config/*.php`** + consolidated `.env` knobs (secrets marked) |
| [reference/mail-and-wiring.md](./reference/mail-and-wiring.md) | The 9 mailables + Postal transport, providers, observers/listeners, container bindings |
| [reference/testing.md](./reference/testing.md) | The test suite + **⛔ safe-test-running** (the sqlite guard / prod-wipe story) |

> Co-located non-EBQ apps share Box A: **Postal** (mail), **Jitsi/Prosody** (meet.ebq.io
> video; booking app in `/var/www/marketing` — memory `meet-video-bookings`). Detail in
> server-deployment.md.

---

## Cross-cutting invariants & safety (never break these)

1. **Production DB, no backups, binary logging off — data loss is permanent.** No
   `migrate:fresh/refresh/rollback`, `db:wipe`, `ebq:demo-data` destructive modes, or raw
   `DROP/TRUNCATE` without explicit per-command confirmation. Tests must resolve to sqlite
   `:memory:` (the `TestCase` guard — do not weaken it). See `CLAUDE.md`. (Engine is
   **MariaDB 10.11** via Laravel's `mysql` driver.)
2. **Two-box deploy in lockstep.** A shared-schema migration hits both boxes instantly; the
   worker box runs bind-mounted code pushed by **rsync** (not git) and must be restarted.
   Changing a queued job's identity (`uniqueId`/constructor) requires both boxes to match or
   locks leak. [deployment-and-queues.md](./deployment-and-queues.md) · live state in
   [server-deployment.md](./server-deployment.md).
3. **FPM opcache `validate_timestamps=0`** → a code change needs a *full* `php8.3-fpm`
   restart, not a reload; long-running `queue:work` needs `queue:restart` / container restart.
4. **Crawler per-user scoping** — shared crawl data is exposed only through `CrawlReportService`
   (cap window + ignore/resolve overlay + read-time GSC impact). Shared findings store
   `impact = 0`. [crawler/read-path.md](./crawler/read-path.md).
5. **PHP must be 8.3** on both boxes (8.5 breaks queued-closure serialization).
6. **Redis is the single store for cache + all queues** (`noeviction` policy — eviction would
   drop jobs). **Don't purge the shared `sync` queue** — it carries unrelated GA/GSC jobs.
7. **Use `/root/.ssh/id_ed25519_worker`** for the worker box — never repurpose other
   services' credentials.
8. **Use the code-review-graph MCP tools first** for exploration (per `CLAUDE.md`).

---

## Glossary (key entities & terms)

- **crawl_site** — one row per normalized domain; owns the shared crawl. Many `websites` link
  to it via `crawl_site_id`.
- **value_rank / cap window** — dense page rank in the shared value ordering; reads filter
  `value_rank <= the owner's plan cap`.
- **effective_cap** — max page cap among a crawl_site's subscribers; the crawl runs to this.
- **website_finding_states** — per-user open/ignored/resolved overlay on shared findings.
- **client_activities / UsageMeter** — usage log + monthly spend windows (provider, units,
  billed to the website owner). The crawl `crawl_reuse` charge lives here.
- **sync / crawl / interactive / default** — the four Redis queues (`Support/Queues`).
  crawl + sync run on the worker box; interactive + default + schedule on the web box.
- **Postal** — self-hosted SMTP relay all mail goes through (`MAIL_MAILER=postal`).
- **Mistral** — the only live LLM provider (`MISTRAL_API_KEY`).

---

## Project memory layer

Session-spanning operational facts also live in **project memory**
(`~/.claude/projects/-var-www-ebq/memory/`, indexed by `MEMORY.md`) — e.g. GSC/GA
degradation, keyword-finder limits, email-via-Postal, FPM 504 tuning. Where a memory note and
a repo doc overlap, **the repo doc is authoritative**; migrate durable architecture facts from
memory into the right `infra/` doc over time and leave the memory note as a pointer.

---

## Where the docs are still thin (deepen as you touch these)

The whole app is mapped, but some areas are summarized rather than exhaustive — and a few
known gaps were flagged during the sweep:

- **Admin panel** — `admin/README.md` summarizes the panels; individual screens aren't each
  fully detailed. Expand the one you touch.
- **Stubs / partial features** — `GenerateAiInsights` is a placeholder; `research_limits` is
  declared but not enforced (billing); `LlmClient` multi-provider is aspirational.
- **Known correctness caveats** captured in subsystem docs (read before changing them):
  crawler `known-issues.md` (cap-window leak), billing `usage.md` (non-atomic `assertCanSpend`),
  guest-tools (cookie friction bypass; PageSpeed leads mis-tagged), audits (no content-hash
  re-audit gate), data-sources (null-vs-empty `gsc_site_url`).
- **Latent bugs surfaced by the reference sweep (worth fixing):**
  - `bootstrap/app.php` registers middleware alias **`research.rollout` → a class that doesn't
    exist** (`EnsureResearchRolloutAccess`) — harmless until a route uses it, then 500. (routing)
  - **`EnsureFeatureAccess` fails open** on unknown feature keys — a typo in a `feature:` arg
    silently bypasses gating. (http-and-auth)
  - **CI (`tests.yml`) triggers on `master`/`*.x`, but the default branch is `main`** — pushes
    to `main` get no push-event CI (only PR/nightly). (testing)
  - Orphaned **`content_briefs`** table (no longer referenced); `prod APP_DEBUG=true`. (database / server-deployment)

---

## Knowledge changelog

- **2026-06-23 (keyword finder — admin live-queue panel + monthly shared ideas cache)** —
  User: see which keyword's queued and by which user across the self-hosted keyword API
  fleet; and cache keyword-ideas results for the current calendar month (not rolling days)
  so a repeat search — by anyone — is instant. Added a "Live queue" panel to
  `/admin/keyword-servers` (every queued/running `KeywordApiRequest`, all servers, with
  user + keyword/URL — `user()`/`website()` relations were missing on the model entirely).
  Added `KeywordIdeasMonthlyCache` (`Y-m`-keyed, expires `endOfMonth()`) wired into
  `KeywordIdeaFinder::run()`/`poll()`; `KeywordFinderPool::dispatchIdeas()` split to expose
  `buildIdeasPayload()` so the cache key matches the real dispatch payload exactly. Scoped
  to the ideas/discovery flow only — the Volume Finder's per-keyword metrics already has
  its own separate rolling cache (`KeywordMetricsService`), untouched here.
  Details: [keywords/keyword-finder.md](./keywords/keyword-finder.md).
- **2026-06-23 (crawler — post-crawl aggregates cached, fixing slow audit-results load)** —
  User: crawl audit results loaded too slow, cache until the next audit. `actionGroups()`
  (full `chunk(2000)` scan for per-user impact), `typeBreakdown()`, `categoryFindings()`,
  `auditExport()` now go through a new `CrawlReportService::remember()` — `Cache::remember()`
  keyed by the existing `ReportCache::version($websiteId)`, which `AnalyzeSiteJob` already
  bumps at the end of every run. `summary()` deliberately excluded — it carries the *live*
  run status the crawl-progress banner polls; caching it would freeze that banner mid-crawl.
  Details: [crawler/known-issues.md](./crawler/known-issues.md).
- **2026-06-23 (crawler — exportable Site Audit PDF, reusing the existing whitelabel system)**
  — User: build a production-ready exportable audit report (Semrush's "Site Audit: Issues"
  PDF as the reference) and surface a whitelabel option if the plan system already has one.
  Found it already does — `ReportBranding`/`ReportBrandingResolver`/`report_whitelabel` plan
  flag, previously only used for the Growth Report email PDF (`ReportPdfRenderer` +
  `growth-report-pdf.blade.php`). Built the parallel crawl-audit path: `CrawlReportService
  ::auditExport()` (sitewide rollup across all categories, bucketed Errors/Warnings/Notices
  by severity tier, plus `auditAbout()` — "About this issue" copy for all ~37 types, paired
  with the existing `fixGuidance()`) → `CrawlAuditPdfRenderer` (dompdf, mirrors
  `ReportPdfRenderer`) → `pdf/site-audit.blade.php`. Went beyond Semrush's static export
  (which shows bare counts, no URLs) with: real sample affected URLs per issue (capped 10),
  a health-score-with-letter-grade summary, a "new this week" badge per type
  (`first_seen_at`-based), and a "Start here" top-5 priority shortlist ranked by
  severity×volume so a non-technical reader isn't left to figure out where to begin.
  GSC-sourced types (`isGscSourced()`) get the same amber caveat treatment as the dashboard.
  New route `GET /site-audit/download` (`SiteAuditExportController`, `feature:link_structure`
  + `throttle:10,1`, immediate download not queued) with an Export PDF button on the
  dashboard's Priority Action Queue widget; `whitelabel=0` lets a whitelabel-eligible user
  pull the plain EBQ copy on demand. Details: [crawler/known-issues.md](./crawler/known-issues.md).
- **2026-06-23 (crawler — "Fix" buttons rebuilt into a real Page Health feature)** — User
  flagged: every finding's Fix button landed on the same generic link-structure page with
  no relevant info, regardless of issue type. Added `CrawlReportService::pageFindings()` +
  `fixGuidance()` (concrete per-type "what to do" text for all ~35 types) and rebuilt
  `LinkStructurePanel`'s destination into a "Page Health" section showing every open
  finding for that URL with guidance + type-specific detail (duplicate siblings, hreflang
  table, mixed-content list, etc.), highlighting whichever one sent the user there via a
  new `?issue=` param. `broken_external`/`external_redirect` Fix links now route into our
  app instead of opening the live site in a new tab. Hit the `WebsitePage::id` ULID-as-int
  landmine again mid-build (see [[ulid-formatting-landmines]]) — fixed before shipping.
  Details: [crawler/known-issues.md](./crawler/known-issues.md).
- **2026-06-23 (crawler — crawler findings must stand on crawl data alone, not GSC)** —
  User: the crawler must be GSC-independent for its own findings; GSC can only inform
  severity, and GSC-sourced findings need their own clearly-caveated section since Search
  Console history can be stale. Found `noindex_important`/`canonical_mismatch` were gated
  on GSC clicks for EXISTENCE (not just severity) — re-did both with the crawl-only
  "structurally real" proxy (sitemap/inbound-links/homepage) already used for
  `robots_blocked_important`. `indexed_not_in_sitemap` has no crawl-only equivalent
  ("Google has this indexed" is inherently GSC-only) — left as-is but newly tagged via
  `CrawlReportService::isGscSourced()`, surfaced as a separate amber-highlighted section in
  both the grouped issue-type view and the Page Health panel. Generalizes
  [[crawl-only-over-gsc-gating]] from "new checks" to "audit existing ones too."
  Details: [crawler/known-issues.md](./crawler/known-issues.md).
- **2026-06-23 (crawler — no hreflang detection at all; added two checks)** — Second
  Semrush export, this time namesforfreefire.com: 4 i18n pages flagged
  `No self-referencing hreflang` + `Conflicting hreflang and rel=canonical`. Same
  gap-class as the duplicate-title miss earlier today — `HtmlAuditor::localeSignals()`
  already parsed `<link rel=alternate hreflang>` tags but nothing downstream read them.
  Wired hreflangs through `PageAnalyzer` → `seo_signals` JSON → two new
  `SiteIssueDetector` checks (`missing_self_hreflang`, `hreflang_canonical_conflict`),
  both under `CrawlFinding::CATEGORY_INDEXABILITY`. Placed ahead of the `! $indexable`
  early-return since a conflicting page is non-indexable by definition. Only the two
  issue types seen in the export were built — Semrush's broader hreflang catalog
  (reciprocal return-links, x-default, duplicate-language entries) is unaddressed.
  Details: [crawler/known-issues.md](./crawler/known-issues.md).
- **2026-06-23 (crawler dashboard — issue list grouped by type, Semrush-style)** — User
  reported the issue-detail page (`/issues/{key}`) mixed every issue type in a category
  into one flat list with no separation. Added `CrawlReportService::typeBreakdown()` +
  reworked `SiteIssues.php`/its blade to default to a grouped-by-type card list (count +
  worst severity per type), drilling into the existing flat row list only once a type is
  picked or a search term is typed. Top-level Priority Action Queue (category-level)
  unchanged. Details: [crawler/known-issues.md](./crawler/known-issues.md).
- **2026-06-23 (crawler — added robots.txt audit, first one ever; deliberately GSC-optional)**
  — Catalog sweep gap: nothing checked whether Disallow rules accidentally block a real
  page. Added `RobotsTxtParser` (pure, no I/O) + `SiteIssueDetector::detectRobotsBlocked()`
  — one fetch per crawl run via the existing `CrawlFetcher`. First version gated on GSC
  clicks like `noindex_important`; user flagged that many subscribers never connect GSC, so
  re-did it to use sitemap-listed/internally-linked as the crawl-only "this page is real"
  signal instead, with GSC clicks only bumping severity when present. **Standing principle
  going forward: prefer crawl-only signals over GSC-gating wherever possible** — GSC
  presence can't be assumed. New `robots_blocked_important` finding under
  `CATEGORY_CRAWLABILITY`. Details: [crawler/known-issues.md](./crawler/known-issues.md).
- **2026-06-23 (crawler — added mixed-content detection)** — Catalog sweep gap: nothing
  scanned for plain-http resources on https pages. Added `HtmlAuditor::mixedContentUrls()`
  (img/script/iframe/source/video/audio/stylesheet, literal `http://` only) → `seo_signals`
  → new `mixed_content` finding, first real use of `CrawlFinding::CATEGORY_SECURITY`.
  Details: [crawler/known-issues.md](./crawler/known-issues.md).
- **2026-06-23 (crawler — sitemap quality, reverse direction; catalog sweep complete)** —
  Only `indexed_not_in_sitemap` existed (page missing from sitemap). Added the reverse:
  `sitemap_broken_url`/`sitemap_redirect_url`/`sitemap_noindex_url` for URLs that ARE in the
  sitemap despite being 4xx/5xx, redirecting, or non-indexable — click-independent, all
  under `CATEGORY_SITEMAP`. This closes out the full Semrush-catalog gap sweep started
  earlier today (hreflang → UI grouping → TTFB/redirect-chain/schema/twitter → robots.txt →
  duplicate content → mixed content → sitemap quality). Details:
  [crawler/known-issues.md](./crawler/known-issues.md).
- **2026-06-23 (crawler — full Semrush-catalog gap sweep; 4 "captured but discarded" fixes)**
  — User asked to cover all gaps Semrush's audit catches, not just export-driven ones. Found
  `ttfb_ms`/`redirect_chain` (computed in `CrawlFetcher` since this feature was built, never
  read), JSON-LD block validity (`HtmlAuditor::schema()` already flags malformed blocks,
  `seoSignals()` discarded it), and `twitter_tag_count` (captured, never checked) — all wired
  through to 4 new `SiteIssueDetector` checks. First real use of the long-dormant
  `CrawlFinding::CATEGORY_PERFORMANCE` constant. Remaining catalog gaps need new crawler
  instrumentation (robots.txt audit, cross-page exact-duplicate-content, mixed content,
  sitemap-quality checks) — tracked as separate follow-up work, not yet built.
  Details: [crawler/known-issues.md](./crawler/known-issues.md).
- **2026-06-23 (crawler — no duplicate-title detection existed; added one)** — User caught
  via a Semrush export that we missed 24 duplicate-title issues on soulfamburger.com (8
  titles × 3 i18n variants). Confirmed by grep: zero duplicate-title logic anywhere in the
  codebase, only missing/too-long/too-short checks. Added
  `SiteIssueDetector::detectDuplicateTitles()`. Hit (and fixed) a partial-hydration bug along
  the way — the group-by query's `select()` omitted `crawl_site_id`, causing a typed-null
  error visible only in the **worker box's** log, not the web box's — re-confirms the
  two-box deploy discipline from earlier today. Verified: 24/24 findings now match Semrush
  exactly. Details: [crawler/known-issues.md](./crawler/known-issues.md).
- **2026-06-23 (crawler — one host redirect cascaded into a redirecting_url finding on
  every page)** — User flagged two soulfamburger.com URLs both "redirecting"; turned out
  all 28/28 of the site's pages were flagged (apex→www). Root cause:
  `PageCrawlProcessor::process()` resolved relative internal links using the pre-redirect
  `$page->url` instead of the post-redirect effective URL, so every link discovered on the
  (actually www-hosted) page got re-anchored back to the apex host — propagating the same
  redirect to every subsequently discovered page. Fixed by using `$res['redirect_target']`
  as the analysis base URL when `$res['redirected']`. Did **not** mass-resolve the 28
  existing findings — unlike the other two crawler fixes today, each is individually a real,
  true redirect; only the multiplication bug was fixed, going forward. Also noted:
  `CrawlSite::homepageUrl()` always seeds the apex (www-stripped) host, so any
  www-canonical site will always get one legitimate homepage-level redirect finding — not a
  bug. Details: [crawler/known-issues.md](./crawler/known-issues.md).
- **2026-06-23 (crawler — wa.me click-to-chat links flagged as external_redirect)** —
  User asked to verify a soulfamburger.com finding (`wa.me/.../Soul Beef Meal`). Checked
  `detail.final_url`: wa.me 302s to `api.whatsapp.com/send/?...` — wa.me's own documented
  behavior, not fixable by the site owner. `SiteIssueDetector.php` had no allowlist for
  known 1-hop redirector services. Fixed: `KNOWN_REDIRECTOR_HOSTS` (wa.me, api.whatsapp.com,
  t.me, m.me, bit.ly) + `isKnownRedirector()` gate before raising `external_redirect`
  (the separate real-4xx/5xx `broken_external` check is untouched). Resolved 18 matching
  open findings across 3 sites. Details: [crawler/known-issues.md](./crawler/known-issues.md).
- **2026-06-23 (crawler — image assets ran through on-page SEO checks)** — User-reported
  false positives on childdaycaretracy.com's crawl report (`missing_title` on a `.jpeg`).
  Root cause: image sitemap entries / `<a href="...jpg">` targets became ordinary
  `website_pages` rows with no content-type gate, so `PageCrawlProcessor::process()` ran
  `PageAnalyzer::analyze()` on raw JPEG bytes — `website_pages` has no `content_type`
  column even though `CrawlFetcher` already captures the header. Fixed by checking
  `$res['content_type']` before `analyze()` and marking non-HTML responses
  `is_indexable=false` (which `SiteIssueDetector` already skips). Swept all sites by URL
  extension: only 22 pages (all on this one site) were affected; their 33 open false
  findings were resolved directly. Separately investigated and ruled out: a wa.me/
  soulfamburger.com finding the user saw "in the dashboard" does not exist anywhere in
  this site's `crawl_findings` (confirmed via direct DB query AND calling
  `CrawlReportService::categoryFindings()` exactly as the controller does) — it belongs to
  soulfamburger.com's own crawl_site. Likely a client-side stale-render artifact (same
  bleed class as the known `LinkStructurePanel` cap leak), not a data bug — user to confirm
  with a hard refresh. Details: [crawler/known-issues.md](./crawler/known-issues.md).
- **2026-06-23 (proxy pool — admin "Retest all" false-positive deletes)** — Single "Test"
  button passed a proxy; "Retest all" deleted every proxy in the pool. Root cause: the
  4 active proxies share one provider account/credential (rotating-IP "backconnect"
  gateway — same auth token + port, IP differs), capped server-side to a small number of
  concurrent connections. The old `concurrency: 5` Alpine sweep in
  `proxy-manager.blade.php` opened 5 simultaneous CONNECT tunnels on the same account; the
  provider 403'd the excess, and `deleteOnFail` nuked otherwise-healthy proxies. Fixed by
  dropping retest concurrency to 1 (sequential), matching single-Test semantics. Details +
  generalization (group-by-credential if pool grows multi-account) in
  [crawler/known-issues.md](./crawler/known-issues.md).
- **2026-06-23 (GSC sync — high-volume account stale since 2026-04-16, real root cause)** —
  `namesforfreefire.com`'s ~38k-row GSC account (the single biggest in `search_console_data`)
  never synced; `SyncSearchConsoleData` failed nightly with no logged exception. First pass
  raised `timeout` 600→3600 (+`redis-long`, `backoff=120`, per-window watermark/logging — still
  good changes) but live re-testing showed the job still hung on window 1 for over an hour with
  zero CPU progress. Real causes, confirmed live: (1) **`Google\Client`'s default HTTP client has
  no read timeout** — a stalled response can block `curl_exec()` forever, and the job's own
  pcntl-based `$timeout` doesn't reliably interrupt a blocking libcurl read, so raising it alone
  never fixed anything; fixed with an explicit Guzzle `connect_timeout=10`/`timeout=120` in
  `GoogleClientFactory::make` (benefits every Google API caller, not just this job). (2) **no
  overlap guard** — a run outliving `redis-long`'s `retry_after` (3900s) got a duplicate
  dispatched on top of it; confirmed two live Redis reservations for the same website fighting
  over the same upserts. Fixed with `WithoutOverlapping('sync-search-console:'.$websiteId)`
  (same pattern as `AnalyzeSiteJob`). Docs: [data-sources/sync-jobs.md](./data-sources/sync-jobs.md)
  §Gotchas, [data-sources/google-oauth.md](./data-sources/google-oauth.md).
- **2026-06-20 (proxy pool — final shape: import OFF by default, prune always ON)** —
  Settled after several iterations same day (see the two entries directly below for the
  history). Current state, fully detailed in
  [crawler/known-issues.md](./crawler/known-issues.md): `ebq:proxy-list-refresh`
  (import-only, new candidates from free lists, scheduled but gated OFF by
  `CRAWLER_PROXY_AUTO_IMPORT`, manual override via artisan or the admin "Import now"
  button → `RunProxyListRefreshJob`) is now fully separate from `ebq:proxy-pool-prune`
  (health-check-only, deletes any tracked proxy that fails a fresh test, always
  scheduled every 15min regardless of the import flag). Both share
  `ProxyPool::testBatch()` (cert-verified concurrent HTTPS test). Admin "Retest all" and
  real-usage `markFailure()` are two more, independent deletion paths — four total,
  intentionally not unified, see known-issues.md for which is which.
- **2026-06-20 (admin proxy screen — "Retest all" with delete-on-fail, superseded detail
  below by the pruner)** — Added an Alpine-driven "Retest all" sweep to `/admin/proxies`
  (concurrency 5, live per-row spinner + progress bar). Deliberately distinct from the
  synthetic auto-refresh job: this is a manual admin sweep, so
  `ProxyManager::test($id, deleteOnFail: true)` removes a proxy on the spot the moment it
  fails — the single-row "Test" button keeps the old non-destructive behavior. Confirm
  dialog before starting (irreversible per row).
- **2026-06-20 (proxy pool auto-refresh from a free public list — superseded, see entry
  above)** — Added `ebq:proxy-list-refresh` (scheduled `everyThirtyMinutes`,
  `routes/console.php`), which pulls `iplocate/free-proxy-list`'s `all-proxies.txt`,
  live-tests a random sample (real HTTPS GET, cert verification ON) before trusting any
  of it, and writes only the passing ones into `proxies` (feeds the same pool used by the
  broken-link checker fix above and the crawler's anti-block retries). Verified before
  building: manually curl-tested a sample first — ~45% of HTTP candidates worked, SOCKS5
  mostly dead, and one SOCKS5 node
  was actively MITM-ing HTTPS (self-signed cert swap) — confirms untested import of a free
  list is not safe; cert-verify-on testing is the load-bearing safety check, not optional.
  Docs: [crawler/known-issues.md](./crawler/known-issues.md).
- **2026-06-20 (page-audit broken-link false positive — 429 + proxy retry)** — User-reported
  false "broken link" (`apps.apple.com` rate-limiting the audit's HEAD check with 429). Root
  cause: `PageAuditService::checkLinks()`/`getFallback()` only fell back to GET on
  403/405/501 — 429 went straight to "broken" with zero retry. Fixed in both the single-page
  audit checker (`PageAuditService.php:1150,1239`) and its near-duplicate in the crawler
  pipeline (`Crawler/LinkChecker.php`): added 429 to the fallback-trigger list, and if the
  plain GET retry *still* looks dead, one more GET attempt through the crawler's `ProxyPool`
  (`crawler.proxy.*`, already live in prod with 4 active proxies) before trusting the result.
  Docs: `infra/audits/page-audit.md` §Gotchas + pipeline step 5.
- **2026-06-20 (AI Writer 504s — outer timeout layers shorter than the inner one)** — Blog-post
  generation intermittently 504'd. Root cause: the writer is fully synchronous, `set_time_limit(360)`
  + Mistral calls up to 300s (chained Serper+LLM up to ~5min, see `ai/writer.md`), but the two layers
  *outside* PHP were shorter — Apache's proxy_fcgi backend wait used the global `Timeout 60`
  (`ebq-hardening.conf`) and FPM's `request_terminate_timeout` was **120**. Whichever hit first killed
  the request mid-LLM-call → 504, regardless of the generous PHP-level limit. Fixed by raising FPM
  `request_terminate_timeout` 120→**400** and adding vhost-level `ProxyTimeout 400` to
  `ebq.io-le-ssl.conf` (mod_proxy_fcgi has no per-`<Location>` timeout, so this is vhost-wide for the
  PHP backend only — the client-facing `Timeout 60` is untouched). See `server-deployment.md` and
  `ai/writer.md` §Gotchas.
- **2026-06-18 (finalize timeout for extreme sites — code-based, no env edit)** — A ~168k-page/~1.5M-edge
  site (xplate) finalized past the 1200s timeout even after the graph + memory fixes (it's just slow:
  graph → value_rank → `detect` → suggester → scores, all chunked/bounded so no OOM, but minutes of work).
  Raised `AnalyzeSiteJob` timeout to **3600s** and moved it onto a dedicated **`redis-long`** queue
  connection (`config/queue.php`, retry_after **3900** as a *code default*, not the `REDIS_QUEUE_RETRY_AFTER`
  env) so the ceiling travels with the deploy — no per-box `.env` change. `$heavyPool` repinned to
  `redis-long` (timeout 3600, maxTime 4200, memory 1536). Permanence is the theme: all crawler fixes live in
  code, so `bootstrap()` + the snapshot build bake them onto every (incl. autoscaled) box automatically —
  see [crawler/autoscaling.md](./crawler/autoscaling.md) §"How fixes reach new boxes & snapshots".
- **2026-06-18 (worker memory ceiling — Horizon 128M regression)** — Horizon workers inherit PHP's
  CLI-default `memory_limit=128M`; the pre-Horizon raw workers ran `-d memory_limit=2048M` (lost in the
  migration), so `HtmlAuditor` (large pages) and the link-graph finalize OOM'd at 128M. Fix: the heavy
  jobs `ini_set` their own ceiling — `CrawlPageBatchJob` (`crawler.batch_memory_limit`, 512M) +
  `AnalyzeSiteJob` (`crawler.analyze_memory_limit`, 1024M) — so it travels with the code to **every box
  incl. autoscaled ephemeral** (via `bootstrap()`'s full-app rsync), no snapshot/php.ini dependency.
  Docs: server-deployment.md, crawler/autoscaling.md.
- **2026-06-18 (autoscaler — snapshot-existence preflight)** — Before provisioning a crawl box
  the autoscaler now verifies the configured worker **snapshot still exists in Hetzner**
  (`HetznerClient::imageExists`, tri-state; `FleetAutoscale::snapshotExists` gate +
  `WorkerFleetService::provision` defense-in-depth) — complementing the existing git-HEAD-drift
  gate. A deleted snapshot otherwise made `createServer` 422 every tick and the autoscaler
  **looped provision→reap** a dead node (observed after a snapshot was deleted during unrelated
  Hetzner cleanup). Confirmed-missing → rebuild (if `auto_snapshot`) or skip with an actionable
  error. 2 new tests. Doc: [crawler/autoscaling.md](./crawler/autoscaling.md).
- **2026-06-18 (crawl finalize — large-site 1205 lock-wait + finalize loop)** — Fixed two
  compounding `AnalyzeSiteJob` failure modes that stranded large-site finalizations (39k &
  168k pages): (1) `SiteGraphAnalyzer` did **whole-site UPDATEs** of `inbound_link_count` /
  `click_depth` that tripped `innodb_lock_wait_timeout` (1205) while contending with live
  crawl writes → now computes in PHP and writes in **bounded id-keyset chunks**
  (`resetColumnChunked`/`writeGroupedChunked`); (2) the supervisor re-dispatched finalize on
  slow-but-alive runs → **overlapping finalizes** fighting for locks → added
  `WithoutOverlapping` + `tries=2`/`backoff` to `AnalyzeSiteJob`. New test
  `tests/Feature/SiteGraphAnalyzerTest.php`. Corrected stale Horizon worker-pool table in
  `server-deployment.md`. Detail: [crawler/known-issues.md](./crawler/known-issues.md).
- **2026-06-17 (full-ULID + multi-node sharding — on branch `feature/db-sharding-ulid`)** — Re-keyed the
  whole schema to **ULID** (`char(26)`; framework/Sanctum/pivot surrogate ids stay bigint) and built
  **two-dimensional sharding**: tenant-by-owner + crawl-by-domain, behind one routing layer
  (`DbNode`/`db_nodes`, `ShardManager`, `ShardContext`, tier model traits, `ResolveShardContext`
  middleware + `WebsiteApiAuth` + job wiring). Cross-tier FKs dropped (MySQL-only migration; integrity
  app-enforced via `ShardCleanup` + `ShardTables`). Admin-managed DB-node fleet (`DbFleetService` reusing
  `HetznerClient`, `ebq:db-node`, `/admin/db-fleet`) + tenant/crawl **mover** (`ShardMover`, `ebq:shard`).
  Validated: full suite 0 new failures vs baseline (sqlite), schema + FK-drop + an end-to-end tenant move
  on a throwaway docker MariaDB. NOT merged/deployed: prod re-derive cutover + Hetzner node provisioning +
  Phase 0 backups are operator-gated. New doc [sharding/](./sharding/README.md); full plan
  `SHARDING_PLAN.md`.

- **2026-06-17 (fleet autoscaling — live-tested)** — Completed the Hetzner setup (token, network
  `12332718`, ssh key, firewall, worker **snapshot**, `.env.worker`) and ran a full live
  `provision → bootstrap → drain → destroy` cycle successfully (Redis `CLIENT LIST` confirmed the
  new box's workers polling the crawl queue). Fixes from the test: server type `cx23` (not AMD
  `cpx*`), wait-for-SSH on bootstrap, ephemeral boxes forced crawl-only, and **the web box `ufw`
  must allow the private subnet `10.0.0.0/24` to Redis 6379 + MariaDB 3306** (added) — otherwise
  ephemeral workers crash-loop. Autoscaler remains **off** pending an operator `enable`. See
  [crawler/autoscaling.md](./crawler/autoscaling.md).
- **2026-06-16 (fleet autoscaling P1–P4)** — Built elastic crawl-worker scaling on Hetzner
  ([crawler/autoscaling.md](./crawler/autoscaling.md)): `worker_nodes` fleet model +
  `HetznerClient`/`WorkerFleetService`, the `ebq:fleet-worker` manual command, the
  `ebq:fleet-autoscale` control loop (queue-depth driven, hysteresis) + `ebq:check-worker-nodes`
  health loop, a `/admin/fleet` panel (live status, cost, editable settings), the
  **`crawl-finalize` queue split** (long `AnalyzeSiteJob` on the pinned box only, so scale-down
  can't kill a finalize), and a **distributed per-domain rate limiter** (`DomainRateLimiter`).
  Autoscaler ships **disabled** — gated on the operator's Hetzner setup (token/snapshot/network).
  9 tests pass. Key property: the queue is central Redis, so new boxes just pull — no rebalance.
- **2026-06-16 (dashboard + crawl fixes)** — Fixed & deployed: IDOR gate on the Competitive
  components (Livewire actions skip route middleware), `summary()` stale-health (use last
  *completed* run), `KpiCards`/`TrafficChart` cache-version, and the `CrawlBanner` poll
  (10s/30s) + display. **Crawl fairness** (`crawler.pages_per_pass`) so a big site can't
  starve the shared queue, and a **`ebq:crawl-supervisor`** watchdog (every 5 min,
  `stall_minutes` 10) that recovers wedged multi-pass chains. Admin `/admin/crawler` now shows
  the client per crawl + a legend, and progress as crawled / total-discovered. Banner + admin
  progress are inventory-based (not the per-pass counter). 8 tests pass. Docs updated across
  `crawler/{pipeline,known-issues,operations,read-path}`, `reference/{jobs-and-scheduler,
  configuration}`, and `deployment-and-queues` (⛔ never `rsync --delete` to the worker — it
  wiped the worker-only compose/Dockerfile this session; recovered from image history).
- **2026-06-16 (wp plugin source)** — Documented the **client-side WordPress plugin** (the
  EBQ SEO plugin, a separate git repo at `/var/www/ebq/ebq-wordpress-plugin/`) in
  `wordpress-plugin/plugin-source.md` + `plugin-features.md`. Added `/ebq-wordpress-plugin`
  to the main repo's `.gitignore` (it's a 581M nested repo — must never be committed here;
  its old folder name `ebq-seo-wp` was already ignored but the rename left it exposed).
- **2026-06-16 (cross-cutting sweep)** — Added the **horizontal reference layer**
  (`infra/reference/`): database (83 tables / 49 models), routing (endpoint map),
  http-and-auth (middleware/guards/authz), jobs-and-scheduler (25 jobs + 17 commands +
  destructive-command safety), configuration (16 config files + env), mail-and-wiring, and
  testing (the sqlite-guard / safe-test-running). Plus **`infra/frontend/`** (Livewire/Alpine/
  Tailwind/Vite UI architecture). **55 docs total.** Surfaced latent bugs (dangling
  `research.rollout` alias, fail-open `EnsureFeatureAccess`, CI-on-`master`-not-`main`, orphaned
  `content_briefs`) — logged under "Where the docs are still thin".
- **2026-06-16 (later)** — **Full-application documentation sweep.** Documented every
  subsystem under `infra/<area>/` (data-sources, keywords, competitive, reports, ai, audits,
  wordpress-plugin, guest-tools, billing, accounts, admin) and added
  **[server-deployment.md](./server-deployment.md)** — a live, read-only inventory of both
  production boxes (Apache+FPM web box `host.ebq.io`/`10.0.0.2`; Docker worker box
  `10.0.0.3`; **MariaDB** not MySQL; Mistral LLM; co-located Postal/Jitsi). 46 docs total.
  Flagged prod risks: `APP_ENV=local` + `APP_DEBUG=true`, stale `MAIL_HOST`, un-versioned
  worker compose file.
- **2026-06-16** — Created this knowledge entry point (`infra/main.md`) + the maintenance
  protocol. Renamed `arch/` → `infra/`. Crawler subsystem fully documented under
  `infra/crawler/` (8 docs) following the shared single-crawl-store re-architecture; added
  `infra/deployment-and-queues.md`. Shared-crawl + tooling shipped to `main` (commit `3a041b5`).
