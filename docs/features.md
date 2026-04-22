# EBQ Feature Guide

A complete reference for everything you see inside EBQ. Each feature explains **what it does, every column or metric, how the number is calculated, and what action to take when you see it**.

This guide is deliberately long — use the table of contents to jump to the feature you care about.

---

## Table of contents

1. [Data sources](#data-sources) — where the numbers come from
2. [Dashboard](#dashboard)
   - [KPI cards](#kpi-cards)
   - [PPC-equivalent banner](#ppc-equivalent-banner)
   - [Action insights (insight cards)](#action-insights-insight-cards)
   - [Traffic chart](#traffic-chart)
   - [Top countries](#top-countries)
   - [Seasonal peaks ahead](#seasonal-peaks-ahead)
   - [Quick wins card](#quick-wins-card)
   - [Country filter](#country-filter)
3. [Reports → Insights](#reports--insights)
   - [Cannibalizations tab](#cannibalizations-tab)
   - [Striking distance tab](#striking-distance-tab)
   - [Index fails with traffic tab](#index-fails-with-traffic-tab)
   - [Content decay tab](#content-decay-tab)
   - [Quick wins tab](#quick-wins-tab)
   - [Audit vs traffic tab](#audit-vs-traffic-tab)
   - [Backlink impact tab](#backlink-impact-tab)
4. [Reports → Growth report builder](#reports--growth-report-builder)
5. [Pages](#pages) — per-page Search Console table
6. [Keywords](#keywords) — per-query Search Console table
7. [Rank tracking](#rank-tracking)
   - [Rank tracker table](#rank-tracker-table)
   - [Keyword detail](#keyword-detail)
   - [SERP feature risk](#serp-feature-risk)
8. [Page audits](#page-audits)
   - [Custom audit tool](#custom-audit-tool)
   - [Audit detail view](#audit-detail-view)
   - [Every section of the audit report](#every-section-of-the-audit-report)
   - [Downloading and emailing a report](#downloading-and-emailing-a-report)
9. [Keyword metrics (Keywords Everywhere layer)](#keyword-metrics-keywords-everywhere-layer)
10. [Country filtering across the app](#country-filtering-across-the-app)
11. [Glossary](#glossary)

---

## Data sources

EBQ blends **five live data sources**. Every number in the app traces back to one of these:

| Source | Provides | Refreshed |
|---|---|---|
| **Google Search Console** (GSC) | Queries, pages, clicks, impressions, CTR, position, country, device | Nightly sync (configurable window) |
| **Google Analytics 4** (GA4) | Users, sessions, source, bounce rate | Nightly sync |
| **Google URL Inspection API** | Per-URL index verdict, coverage state, last crawl date | On demand + nightly poll |
| **Serper.dev** (SERP API) | Organic rankings for tracked keywords, SERP features, competitor URLs | Per-keyword cadence you set (12h default) |
| **Keywords Everywhere** | Monthly search volume, CPC, competition score, 12-month trend | Every 30 days per keyword (cached) |

Lighthouse-style Core Web Vitals come from EBQ's own audit runner that fetches the page and records LCP, CLS, performance score (mobile + desktop).

No number in the app is made up or estimated — every cell tells you which source it came from on hover.

---

## Dashboard

The dashboard is the "30-second glance" view. Every card is live against yesterday's data unless noted.

### KPI cards

The top row. Each card compares **last 30 days vs. the previous 30 days** for one metric.

| Card | What it measures | Source |
|---|---|---|
| **Users (30d)** | Unique visitors to the site | GA4 |
| **Sessions (30d)** | Total visit sessions (user can have multiple) | GA4 |
| **Clicks (30d)** | Clicks from Google search | GSC |
| **Impressions (30d)** | Times your pages appeared in Google search results | GSC |

**The ∆ badge** on each card is the percent change vs. previous period. Green means up, red means down. "was 1,234" shows the previous-period number for quick reasoning.

**What to do**: If clicks drop but impressions rise, you're ranking for more queries but converting fewer — check `CTR` and `Avg position` in the Search Performance section of a custom report. If both drop together, check indexing status or run an audit.

### PPC-equivalent banner

Indigo strip above the insight cards:

> Your organic traffic is worth **$4,230/month** in PPC equivalent · based on 187 priced queries

**Formula**: For every GSC query in the last 30 days where we have Keywords Everywhere data, we compute `(impressions at this position × CTR for that position) × CPC`, then sum.

**Why it's useful**: Turns organic SEO into a number a finance team cares about — "if we turned this off, we'd need this much Google Ads budget to replace it."

**Hidden when**: Fewer than 10 queries have priced data (sample too small to be meaningful).

### Action insights (insight cards)

Five cards that each count one type of issue. Click any card to jump to the matching Reports → Insights tab.

| Card | Number shown | What it means |
|---|---|---|
| **Cannibalizations** | Queries where 2+ of your pages compete | You're splitting click-share. Pick one page to own the query. |
| **Striking distance** | Queries at positions 5–20 with traffic | One push gets them on page 1. |
| **Index fails w/ traffic** | URLs Google says are "not indexed" but still get impressions | Urgent — Google is showing them but won't rank them properly. |
| **Content decay** | Pages with sustained ≥15% click drop | Either rankings slipped or the keyword demand is fading. |
| **Quick wins** | Low-competition keywords you don't rank top-10 for | New content opportunities, scored by dollar upside. |

### Traffic chart

30-day clicks trend overlaid with impressions. The red band marks any day where the anomaly detector flagged a drop. Hover any point for the exact value.

### Top countries

Horizontal-bar list of the top 10 countries driving clicks, last 30 days vs. previous 30 days. Each row:

| Column | Meaning |
|---|---|
| Flag + country name | Country name (from ISO alpha-3 code GSC returns) |
| Bar | Visual share of clicks — the top country fills the bar, others scale proportionally |
| Number | Absolute clicks in the last 30 days |
| +12% / −5% | Percent change vs. the prior 30 days |

**What to do**: Spot where your traffic is concentrated. If 90% comes from one country, consider if content, hreflang, and schema are localized correctly.

### Seasonal peaks ahead

Amber card that only renders when Keywords Everywhere data flags keywords as **seasonal** AND their historical peak month is within the next 60 days.

| Column | Meaning |
|---|---|
| Keyword | The query |
| Peaks in *Month* | The month the keyword historically sees maximum search volume |
| /mo at peak | Peak-month monthly search volume |
| "this month" / "N mo away" badge | How many months until the peak |

**What to do**: Refresh or re-publish content for these keywords *before* the season lands — Google needs time to crawl and re-rank.

### Quick wins card

Emerald card with the top 5 quick-wins (see [Quick wins tab](#quick-wins-tab) for the full list). Each row links directly to a pre-filled custom audit for that keyword. Hidden when the radar has nothing to show.

### Country filter

The "Country" dropdown next to the insight heading. Affects:

- The four insight-count cards
- The Reports → Insights panel
- The Pages and Keywords tables
- The rank-tracker's GSC-join column

Choosing a country scopes every downstream number to just that country's GSC impressions. Clearing the filter restores the aggregate view.

Only countries where the site has recorded impressions appear in the dropdown.

---

## Reports → Insights

The "Insights" sub-tab on `/dashboard/reports` is the full drill-down for each action card. Seven tabs, one table per tab.

All tabs respect the **country filter** at the top of the panel (except audit-performance and backlink-impact, which are aggregate-only today).

### Cannibalizations tab

A query is "cannibalizing" when **two or more of your pages** rank for it and neither dominates the clicks.

**Filters we apply**:
- Last 28 days
- ≥ 100 total impressions across all competing pages
- Primary page captures < 90% of clicks (otherwise it's effectively resolved)

**Columns**:

| Column | Meaning |
|---|---|
| **Query** | The query Google is confused about |
| **Primary page** | The page currently getting the most clicks |
| **Pages** | How many of your pages compete on this query |
| **Clicks** | Total clicks the query attracted across all competing pages (28d) |
| **Impr.** | Total impressions across all competing pages (28d) |
| **At stake** | Full-market dollar value at position 1 (volume × top-of-SERP CTR × CPC). What your weakest pages are bleeding away. Dash if Keywords Everywhere has no data yet. |
| **Competing pages (share %)** | All competing URLs with the click share each one captures. Amber % label = they should consolidate. |

**What to do**: Pick the primary page. Redirect the others to it, or heavily de-optimize them for the query. Update internal links to point at the primary.

### Striking distance tab

Queries ranking **positions 5–20** with high impressions and below-curve CTR — the fastest SEO wins.

**Filters we apply**:
- Last 28 days
- ≥ 200 impressions
- Avg position between 5 and 20 (inclusive)

**Columns**:

| Column | Meaning |
|---|---|
| **Query** | The query |
| **Position** | Average position over the period (rounded to 1 decimal) |
| **Impressions** | Total impressions (28d) |
| **Clicks** | Total clicks (28d) |
| **CTR** | Click-through rate as a percentage |
| **Upside/mo** | Projected **extra monthly dollars** if this query reached position 3. Uses volume × CTR-curve-delta × CPC. Rows with Keywords Everywhere data sort first; rows without fall back to the legacy impression-based score. |

**How the sort works**: rows where we know the dollar upside come first (largest upside at the top). Rows still pending Keywords Everywhere data fall to the bottom, sorted by a heuristic score (impressions ÷ 100 + distance-to-top-3 − CTR penalty).

**What to do**: One push (title, meta, H1, intent fix, one strong internal link, one backlink) moves page-2 results onto page 1. These are your highest-ROI SEO bets.

### Index fails with traffic tab

Pages Google's URL Inspection API flags as **not PASS** (e.g., *Crawled — currently not indexed*, *Discovered — currently not indexed*, etc.) but which still got impressions in the last 14 days.

**Columns**:

| Column | Meaning |
|---|---|
| **Page** | The URL |
| **Verdict** | Google's own word — `PASS`, `FAIL`, or similar |
| **Coverage** | The detailed reason — `"Crawled - currently not indexed"`, `"Discovered"`, etc. |
| **Clicks (14d)** | Clicks earned despite the indexing issue |
| **Impr. (14d)** | Impressions earned despite the indexing issue |
| **Last crawl** | When Google last visited this URL |

**What to do**: If impressions are present, Google *knows* the page exists. The block is trust/quality, not discoverability. Common fixes: boost internal links, improve content quality, request re-indexing after changes.

### Content decay tab

Pages showing **sustained click decline** over the last 28 days vs. the prior 28 days.

**Filters we apply**:
- ≥ 100 current impressions (filters out noise)
- ≥ 15% drop in clicks 28d-over-28d

**Columns**:

| Column | Meaning |
|---|---|
| **Page** | The URL. `market decline` pill appears when the decay is driven by falling keyword demand, not your page losing rank (see note below). |
| **Clicks (28d)** | Current-period clicks |
| **Prev 28d** | Previous-period clicks (visible on md+ screens) |
| **∆ 28d** | Percent change — the bigger the red number, the worse |
| **YoY** | Same 28 days vs. a year ago. Green if you're still up YoY despite recent slip. Dash if we don't yet have 13 months of history. |
| **Verdict** | Google indexing verdict. A non-PASS here means the decay is being masked by de-indexing and the problem is more urgent. |

**The `market decline` pill**: When ≥2 of the page's top-3 queries are themselves trending down in Keywords Everywhere's 12-month data, the decay is not your fault — the topic is fading. Don't waste hours rewriting; monitor and plan next-quarter content instead. Pages without the pill are **recoverable** and worth rewriting.

### Quick wins tab

Low-competition keywords with real search volume where your site either doesn't rank or ranks outside the top 10. **Scored by the dollar upside of reaching position 3.**

**Filters we apply**:
- Keywords Everywhere volume ≥ 500/month
- Competition score ≤ 0.4 (where 1.0 = max)
- Site either has no GSC match OR best position > 10 in the last 90 days

**Columns**:

| Column | Meaning |
|---|---|
| **Keyword** | The query to target |
| **Volume/mo** | Keywords Everywhere monthly search volume (global) |
| **Comp.** | Competition score as a percentage (0–100). Lower = easier. |
| **Current pos** | Your best observed position for this query in the last 90 days, or "unranked" if we've never shown up |
| **Upside/mo** | Projected dollar value if this keyword reached position 3 |
| **Action** | Either *"Audit current page"* (we know which page ranks) or *"Start new audit"* (unranked) — both deep-link to the custom-audit form with the keyword pre-filled. |

**What to do**: These are the clearest greenfield opportunities. Start a custom audit from the action link — it'll tell you exactly what to add to the page (or what to put in a new one).

### Audit vs traffic tab

Pages with **weak Core Web Vitals but high GSC impressions** — technical debt measurably costing traffic.

**Filters we apply**:
- Audit has been run (`page_audit_reports` row exists)
- Worst-of-mobile/desktop performance score < 70
- ≥ 100 GSC impressions in the last 28 days

**Columns**:

| Column | Meaning |
|---|---|
| **Page** | The URL |
| **Mobile score** | Performance score 0–100 (red < 50, amber 50–89, green ≥ 90) |
| **Desktop score** | Performance score 0–100 |
| **LCP (ms)** | Largest Contentful Paint on mobile |
| **CLS** | Cumulative Layout Shift on mobile |
| **Impressions** | GSC impressions attracted while performing this badly (28d) |
| **Clicks** | GSC clicks earned (28d) |
| **Avg pos** | Average position |
| **Audited at** | When we last audited the page |

**What to do**: These pages earn traffic *despite* being slow. Fix LCP/CLS and clicks almost always go up without any content change.

### Backlink impact tab

Shows click change **before and after** each backlink was discovered. Uses whatever 3rd-party backlink data you've connected.

**Columns**:

| Column | Meaning |
|---|---|
| **Target page** | The internal page receiving the link |
| **Referring domain** | The domain linking to you |
| **Discovered** | When the backlink appeared |
| **Clicks before** | Page's average daily clicks in the 14 days before the link |
| **Clicks after** | Page's average daily clicks in the 14 days after |
| **∆** | Percent change — green positive, red negative |

**What to do**: Use this to evaluate link building campaigns — did the link actually move the needle? Zero/negative ∆ means the target page needs on-page work before more links will pay off.

---

## Reports → Growth report builder

`/dashboard/reports` main view. Build a shareable HTML/email report for any date range.

**Controls**:

| Control | Purpose |
|---|---|
| **Report type** | Daily / Weekly / Monthly / Custom. Picks the date range and sensible default comparisons. |
| **Start / End** | Editable only in Custom mode. |
| **Country filter** | Scopes Search Console data to one country for this report. |
| **Preview** | Renders inline without sending. |
| **Email** | Sends the report to every person on the website's recipient list (Settings → Recipients). Rate-limited to 5 sends per hour per user. |

**Report contents (top to bottom)**:

1. **Google Analytics section**
   - Users, Sessions, Bounce rate — current vs. previous
   - Top traffic sources (organic, direct, referral, etc.) with user change per source
   - Top gainers / losers — which sources grew or fell the most
   - Sessions-per-user ratio + top-3 source concentration (how reliant you are on a single channel)

2. **Google Search Console section**
   - Clicks, Impressions, Avg Position, Avg CTR — current vs. previous
   - **PPC-equivalent line** — the same dollar estimate as the dashboard banner
   - Top queries with clicks, impressions, position, CTR, and change
   - Top pages with the same breakdown
   - Devices pie (desktop / mobile / tablet click share, with change)
   - Countries top 10 (clicks + impressions + change)
   - Top query gainers / losers
   - Top page gainers / losers
   - Position buckets — how many queries are in top-3 / top-10 / 11–20 / 21+
   - Opportunities — striking-distance list

3. **Backlinks section**
   - New, lost, and total referring domains in this period

4. **Indexing section**
   - Indexed / not-indexed counts + list of any pages Google flagged

All sections except Analytics and Backlinks respect the country filter.

---

## Pages

`/pages` — one row per unique URL with Search Console aggregates.

**Filters / controls**:

| Control | Purpose |
|---|---|
| **Search** | Substring match against the URL. |
| **Only failing w/ traffic** | Checkbox — filters to pages where Google's verdict ≠ PASS **and** impressions > 0. Shortcut to the "Index fails with traffic" cohort. |
| **Country filter** | Scopes to impressions from one country. |

**Columns**:

| Column | Meaning |
|---|---|
| **Page** | URL (clickable → per-page detail) |
| **Market** | Detected locale of the page (hreflang + content-language + page-content heuristic) |
| **Clicks** | Sum of GSC clicks in the window configured under Settings → Reports (default 30d) |
| **Impressions** | Sum of GSC impressions in the same window |
| **Avg CTR** | Ratio |
| **Avg Position** | Average across all queries this URL ranked for |
| **Google Indexing Status** | PASS / FAIL / Pending + coverage reason on hover |

Sort any column by clicking its header. Bottom panel paginates 20 rows at a time.

---

## Keywords

`/keywords` — one row per unique query.

**Controls**:

| Control | Purpose |
|---|---|
| **Search** | Substring match against the query. |
| **Device filter** | All / Desktop / Mobile / Tablet. |
| **From / To** | Exact date range. |
| **Country filter** | Scope to one country. |
| **Aggregated / By Date** | Switch between total-per-query and per-query-per-day. |
| **Cannibalized / Tracked pills** | Pre-flagged queries — cannibalized are split across pages, tracked are in your rank-tracker. |

**Columns**:

| Column | Meaning |
|---|---|
| **Date** (by-date view only) | The date |
| **Keyword** | The query |
| **Clicks** | Clicks in the window |
| **Impressions** | Impressions in the window |
| **CTR** | Click-through rate |
| **Position** | Average position, color-coded: green ≤ 3, blue 4–10, amber 11–20, grey 21+ |
| **Volume** | Monthly search volume from Keywords Everywhere. Trend arrow next to the number: ↑ rising, ↓ falling, ◐ seasonal. Hover for last-updated date + CPC. |
| **Value/mo** | Projected monthly dollar value at your current average position. `(volume × CTR for that position × CPC)` |

**What to do**: Sort by Value/mo descending to find your biggest commercial keywords. Click a cannibalized pill to jump to the fix list.

---

## Rank tracking

Dedicated rank-tracker for keywords you want to watch at a specific cadence (vs. GSC which only shows what Google happens to surface).

### Rank tracker table

`/rank-tracking` — one row per keyword you track.

**Add-keyword form fields**:

| Field | Required | Notes |
|---|---|---|
| Keyword | ✓ | Up to 500 chars |
| Target domain | ✓ | Defaults to your site's domain |
| Target URL | — | Use this to track a *specific* page rather than "whichever page ranks" |
| Search engine / Search type | ✓ | Google + organic/news/images/videos/shopping/maps/scholar |
| Country, Language, Location | ✓ | ISO codes + optional free-form location |
| Device | ✓ | desktop / mobile |
| Depth | ✓ | How many SERP results to scan (10–100) |
| Tbs, Autocorrect, Safe search | — | Power-user SERP flags |
| Competitors | — | CSV of domains to track alongside you |
| Tags, Notes | — | Free text |
| Interval | ✓ | Hours between re-checks (1–168). 12h = twice daily. |

**Filters above the table**:

| Filter | Purpose |
|---|---|
| Search | Keyword substring |
| Device / Country / Search type / Status | Reduce the list |
| Clear filters | Reset |

**Stats row** (above the table):

| Stat | Meaning |
|---|---|
| Total | Keywords you track |
| Active | Not paused |
| Top 3 / Top 10 / Top 100 | Distinct counts at each depth |
| Unranked | Keywords with no position (outside the search depth or filtered by SERP features) |
| Avg position | Across all ranked keywords |

**Columns** (left to right):

| Column | Meaning |
|---|---|
| **Keyword** | Click → keyword detail view. Badges underneath: `search_type`, `Paused`, `Failed` (last check errored), `SERP risk`, `lost feature`, plus your own tags. |
| **Target** | The domain you track. Optional target URL shown under it in green if set — the row also shows the URL Google actually ranked in that slot. |
| **Market** | Country badge + language + device + location (if set). |
| **Position** | Current rank. Pill colors: green ≤ 3, blue 4–10, amber 11–20, grey 21+. Dash = unranked. |
| **∆** (change) | Δ vs. last check. ▲ green for improvement, ▼ red for decline. |
| **Best** | Best-ever position since you started tracking. |
| **GSC (30d)** | Side-by-side with your Serper-measured position, this is what Google Search Console recorded for the same keyword in the last 30 days. Big number = clicks. Subtle second line = avg position and impressions. Differences between the two columns tell you about personalization, locale, or CTR-under-curve. |
| **Volume** | Keywords Everywhere monthly search volume, plus trend arrow (↑ ↓ ◐). Underneath: CPC ({currency} X.XX) and competition percentage. Dash while the first background fetch is pending. |
| **Value/mo** | Projected monthly dollar value at current position (volume × CTR for this position × CPC). Hover for the formula breakdown. |
| **Last check** | How long ago we last checked + when the next check is due. "Pending first check" if queued but not yet run. |
| **Actions** | `View` (detail), **Re-check** (force immediate check), Pause/Resume, Delete (with confirmation). |

### Keyword detail

Per-keyword drill-down accessed from the rank-tracker "View" action. Shows:

- Current position, best-ever, position vs. a historical view
- Full history chart (position over time)
- Latest SERP snapshot — the actual 10–100 URLs Google returned, including features (People Also Ask, Featured Snippets, Videos, Knowledge Panel, etc.)
- Competitor rows — position of each competitor domain you listed when setting up the keyword
- People Also Ask + Related searches extracted from the SERP
- Audit button — one-click to open a custom audit pre-filled with this keyword + your current ranking URL

### SERP feature risk

Automatic flag on each rank-tracker row:

- **SERP risk (amber)** — Google is showing a SERP feature (AI overview, featured snippet, video carousel, etc.) for this keyword and you don't own the top result. These features pull clicks away from organic results.
- **Lost feature (red)** — You used to own a SERP feature (e.g., a featured snippet) and Google removed it.

Hover the pill for the specific feature list.

**What to do**: SERP risk = optimize aggressively or accept a traffic ceiling. Lost feature = something changed recently — content update, Google algorithm, competitor push — investigate in the last 7–14 days of page changes.

---

## Page audits

### Custom audit tool

`/custom-audit` — runs a deep on-page audit for a specific URL + target keyword.

**Fields**:

| Field | Required | Notes |
|---|---|---|
| Page URL | ✓ | Must be on your website (domain or subdomain) |
| Target keyword | ✓ | Used for the SERP benchmark comparison |

**Flow**:

1. Click **Run audit**. EBQ fetches the page's `<head>` to detect its language/locale and *suggests* the best Google SERP country to benchmark against.
2. Confirm the SERP country in the dropdown (or override).
3. Click **Run audit** again — the audit is queued.
4. The "Recent custom audits" list below polls itself and updates the row status (Queued → Running → Completed). No page refresh needed.
5. When complete, click the row to open the full audit detail view.

**Dedupe**: If there's already an active audit for the same URL, EBQ won't let you queue a second one — saves credits.

**Rate limit**: 8 audits per user per 2 minutes.

### Audit detail view

`/page-audits/{id}` — the full audit report. Opens directly from:
- The custom audit list
- The "Audit vs traffic" Reports tab
- The Quick-wins action buttons
- The Rank-tracker "Audit" button
- The monthly growth email

At the top: Page URL, detected market, audit timestamp, a "Back to Pages" breadcrumb, and two actions:

- **Page context** — opens the standard Pages drill-down (GSC-only view)
- **Open URL** — opens the live page in a new tab

### Every section of the audit report

The audit report is a single long card with numbered and named sections. **Every section is collapsible**. The section index at the top is a clickable nav.

#### 0. Page Audit Report (summary)

The opening block has:

- **Score donut** (0–100) — 100 minus severity-weighted penalties (`critical × 15 + warning × 6 + serp_gap × 5 + info × 2`).
- **Score label** — Healthy (≥ 85) / Needs attention (65–84) / Critical (< 65)
- **Severity pills** — counts of each recommendation type (critical / warning / SERP gap / info / good)

A red "Failed" banner takes over if the audit errored (rare — usually network or anti-bot blocking).

#### 1. Recommendations

The core value of the audit. Each item is one concrete action, tagged by severity:

| Severity | Meaning |
|---|---|
| **Critical** (rose) | Blocks indexing, breaks the page, or leaks serious ranking signal. Fix immediately. |
| **Warning** (amber) | Standard SEO issue — title too long, duplicate H1, etc. Fix this week. |
| **SERP gap** (violet) | Competitors rank for sub-topics you don't cover. Write content. |
| **Info** (sky) | Heads-up — not broken, could be better. |
| **Good** (emerald) | Something you're doing well — keep it. |

Each row:
- Title (what to fix)
- Section tag (which part of the audit this came from)
- Why (one-line explanation)
- **Fix** (exact action to take)

#### 2. Core Web Vitals

Side-by-side mobile + desktop. For each device:

| Metric | Meaning | Good / Needs work / Poor |
|---|---|---|
| **Performance score** | 0–100 Lighthouse score | ≥ 90 / 50–89 / < 50 |
| **LCP (ms)** | Largest Contentful Paint — when the main visual element loads | ≤ 2500 / ≤ 4000 / > 4000 |
| **CLS** | Cumulative Layout Shift — how much stuff jumps around | ≤ 0.1 / ≤ 0.25 / > 0.25 |
| **FID/INP** | Input delay | See tooltips |
| **TBT** | Total Blocking Time | See tooltips |

#### 3. Technical

| Check | What it looks for |
|---|---|
| HTTP status | 200 vs. redirect chain vs. 4xx/5xx |
| robots.txt | Whether the page is allowed |
| Meta robots | noindex, nofollow, etc. |
| Canonical | Present + points to this page (or a sensible alternate) |
| XML sitemap | Page is listed |
| hreflang | Present and reciprocal (for international sites) |
| SSL | Valid cert, no mixed content |
| Page size | Total transferred bytes |
| Response time | Server's TTFB |

#### 4. SERP readability benchmark

Runs your target keyword through Google (via Serper.dev), fetches the top 5 organic results, extracts their readability + structure metrics, and compares yours to the average.

**Your SERP position hero**:
- Rank (1–100) + first-page badge (green) / page-2+ badge (amber)
- If not found in the top-N: "Not ranking in the sample" note
- SERP country/location used (e.g. *gl=us, hl=en*) — shown when locale differs from page-detected market

**Competitor readability table**:
- Flesch reading ease score per competitor (yours shown first with bold emphasis)
- Word count, image count, heading count (H2-H6), internal/external link count

**Gap table** (per metric):
- Your number
- Mean across competitor pages (with outliers removed — competitor pages outside 10–95 Flesch range are excluded)
- Delta — how many words/headings/links you need to add to match the mean

#### 5. Keyword Strategy

Powered by GSC + Keywords Everywhere. Answers "Are you targeting the right keywords on this page?"

- **Primary keyword** — either pulled from GSC (highest-click query) or overridden by the "Target keyword" you used when queuing the custom audit. Shows clicks, impressions, avg position.
- **Power placement** — checks if the primary keyword appears in the Title, H1, and Meta description. Each shown as a pill with the value (or "missing").
- **Coverage score** (0–100) — how many of your top GSC queries are present in the page body. Color-coded.
- **Intent mix** — commercial / informational / navigational blend of your target queries. Helps you spot a page that targets confused intents.
- **Accidental authority** — queries you rank for but never explicitly targeted. Opportunities for an H2 or FAQ addition.
- **Missing queries** — queries you rank for but whose text doesn't appear in the page body.
- **Target keywords from Search Console** — table with: Keyword, Clicks, Impressions, Position, **Vol** (Keywords Everywhere volume), In-body YES/NO pill. Sort by Vol to prioritize; rewrite pages where high-volume keywords are YES in GSC but absent from the body.

#### 6. Traffic by country (GSC)

Collapsible panel (closed by default) on the audit detail view.

Summary line when closed:
> 3 markets · 12,450 clicks · top: 🇺🇸 United States (42%)

Expanded rows (1..10):
- Rank
- Flag + country name + ISO-3 code
- Share-of-clicks bar (top market darker indigo, others lighter)
- Share percent
- Clicks (hover for impressions + avg position)

**Why it's here**: A CWV audit on a page that gets 70% of its traffic from USA on mobile tells a very different story than the same audit on a page with evenly-distributed traffic. This panel contextualizes the severity.

#### 7. Metadata

| Field | Rule |
|---|---|
| Title | 50–60 chars ideal, ≤ 65 hard cap |
| Meta description | 120–158 chars ideal |
| Canonical URL | Present and self-referential unless intentionally alternate |
| OG title / OG description / OG image | Present and correctly sized (1200×630 recommended) |
| Twitter card | `summary_large_image` for most content |
| Schema.org types present | List of detected JSON-LD types |
| hreflang alternates | Listed with target language/country |

Problems here become `critical` or `warning` recommendations in section 1.

#### 8. Content & Structure

| Check | What it looks for |
|---|---|
| H1 count | Exactly 1 |
| H2, H3, H4+ counts | ≥ 2 H2s recommended for long-form |
| Word count | Displayed for context |
| Flesch reading ease | 0–100 |
| Paragraph count | Used for readability scoring |
| Internal vs external links | Ratio |

#### 9. Image & Link Analysis

**Images section**:
- Total images on the page
- Images missing alt attributes (list)
- Oversized images (file size > 500KB)
- Next-gen format adoption (WebP / AVIF share)

**Links section**:
- Internal link count
- External link count
- **Broken links** — URLs that returned 4xx/5xx when we probed them (subsection auto-opens when present, red-tinted)
- **All links outline** — full crawl of every link on the page (collapsible, alphabetized)

#### 10. Technical Performance

Raw Lighthouse output with top opportunities (render-blocking resources, unused JS, etc.) — the items Core Web Vitals section summarized.

#### 11. Advanced Data

Everything else the crawl captured — HTTP headers, structured data validation errors, canonical chains, redirect chains, robots.txt excerpt. Power-user reference.

### Downloading and emailing a report

At the bottom of the audit page:

- **Download** — renders the audit to a standalone HTML file you can save, print, or convert to PDF. Same data as on-screen, with a print-optimized stylesheet.
- **Email report** — send the HTML to any email address. 5 sends per user per 5 minutes.

Both versions include all sections (Traffic by country, SERP benchmark, Keyword strategy, everything) with a static layout — no JavaScript, email-safe.

---

## Keyword metrics (Keywords Everywhere layer)

Every "Volume", "Value/mo", "CPC", "Competition", and trend arrow in the app reads from a cached Keywords Everywhere lookup.

### What we fetch

| Metric | Meaning |
|---|---|
| **Search volume** | Monthly Google searches, global aggregate |
| **CPC** | Average cost-per-click advertisers pay for this keyword on Google Ads |
| **Competition** | 0.0–1.0 score of bid competition. *This is advertiser competition, not SEO difficulty — but the two correlate.* |
| **Trend (12-month)** | Array of last 12 months' search volume. Feeds the trend arrow + seasonality detection. |

### Trend classifications

Every keyword is tagged as one of:

| Class | Icon | Meaning |
|---|---|---|
| Rising | ↑ (green) | Log-slope of last 6 months > +0.08 |
| Falling | ↓ (red) | Log-slope of last 6 months < −0.08 |
| Seasonal | ◐ (amber) | Coefficient of variation > 0.6 across 12 months — recurring peaks |
| Stable | (no icon) | We have trend data and it's flat |
| Unknown | (no icon) | Not enough trend data yet |

### Value calculations (CTR curve)

Projected-value formulas use a **Sistrix-style SERP click-through curve**:

| Position | CTR |
|---|---|
| 1 | 28% |
| 2 | 15% |
| 3 | 11% |
| 4 | 8% |
| 5 | 7% |
| 6 | 5% |
| 7 | 4% |
| 8 | 3% |
| 9 | 2.5% |
| 10 | 2% |
| 11–20 | 1% |
| 21+ | 0.5% |

Formulas:

- `projected_monthly_clicks = volume × CTR(position)`
- `projected_monthly_value = projected_monthly_clicks × CPC`
- `upside_value = projected_value_at_target_3 − projected_value_at_current_position`

### How data gets cached

- **On nightly GSC sync**: queries with ≥ 100 impressions in the sync window get auto-queued for Keywords Everywhere lookup (skipping anything already fresh).
- **On new tracked keyword**: single global fetch when you add a rank-tracker keyword.
- **On-demand**: `php artisan ebq:fetch-keyword-metrics` (ops-facing).
- **Stale-while-revalidate**: when any page renders and finds a stale row (older than 30 days), it re-queues a fetch in the background while showing what we already have.

Each row stays fresh for 30 days. No re-billing on cached data.

### Why rows show "—"

A dash (—) in any Volume/Value column means Keywords Everywhere hasn't been queried for that specific keyword yet. Auto-fetch will pick it up within ~24 hours on the next sync, or you can force it via the command.

---

## Country filtering across the app

A single dropdown on the dashboard and Reports panel scopes every downstream number to one country. The filter is URL-persisted (shareable).

**What it affects**:

- Dashboard insight cards (counts)
- Dashboard PPC-equivalent banner
- Reports → Insights tabs: Cannibalizations, Striking distance, Index fails, Content decay, Quick wins (with a note — audit-performance and backlink-impact stay aggregate)
- Reports → Growth report builder (Search Console section)
- Pages + Keywords tables
- Rank-tracker's GSC-join column (so your tracked-keyword rows compare position vs. country-scoped GSC data)

**What it does NOT affect**:

- Analytics (GA4 data isn't dimensioned by country in our current schema)
- Backlinks (third-party backlink data)
- The audit report itself (the audit is per-URL and country-agnostic — though the Traffic-by-country panel will still show the unfiltered country breakdown)

Only countries where your site has recorded GSC impressions appear in the dropdown.

---

## Glossary

| Term | Definition |
|---|---|
| **Addressable value** | The full dollar value of a keyword at position 1 — `volume × top-CTR × CPC`. Used in cannibalization to show the ceiling you're bleeding. |
| **Cannibalization** | When ≥ 2 of your pages compete for the same query, splitting click share instead of one winning decisively. |
| **Content decay** | Sustained ≥ 15% click drop on a page 28 days over the prior 28 days. |
| **Coverage score** | % of your top GSC queries that appear in the page body text. |
| **CPC** | Cost-per-click — what advertisers bid to show up for this query. We use it as a proxy for commercial intent. |
| **CTR** | Click-through rate — clicks ÷ impressions. |
| **CTR curve** | Industry-accepted CTR-by-position table. We use Sistrix rounded numbers. |
| **Custom audit** | A user-triggered audit of a specific URL + target keyword, with SERP benchmark. Queues in the background; updates itself. |
| **Decay reason** | `recoverable` (your page lost rank) vs. `market_decline` (the query itself is falling in KE trend data). |
| **Depth** (rank tracker) | How many SERP positions to scan. 100 = you'll find the ranking even if it's on page 10. |
| **Impressions** | Times your page appeared in a search result anyone saw. |
| **Index verdict** | Google's own word on whether your page is indexable — `PASS`, `FAIL`, or specific reasons like `"Crawled - currently not indexed"`. |
| **LCP** | Largest Contentful Paint. Time to the main visual element. Core Web Vitals metric. |
| **PPC equivalent** | The dollar amount you'd need to spend on Google Ads to replicate your organic traffic at current CPC rates. |
| **Primary keyword** | The query we consider most representative of a page — either the highest-click GSC query or the "target keyword" you set when queuing a custom audit. |
| **Projected value** | Monthly dollar value at the current rank — `volume × CTR(current_pos) × CPC`. |
| **Quick win** | A keyword with real volume, low competition, and where your site doesn't rank top-10 yet. |
| **Recommendation severity** | critical / warning / SERP gap / info / good — the pill color in the audit report. |
| **SERP feature** | Anything on a Google results page that isn't a plain organic link — AI overview, featured snippet, People Also Ask, Knowledge Panel, video carousel, maps pack, etc. |
| **Striking distance** | A query ranking position 5–20 with real impressions — one push and it's on page 1. |
| **Target keyword** | The keyword you specify when queuing a custom audit. Used for SERP benchmark + primary-keyword override. |
| **Trend class** | rising / falling / seasonal / stable / unknown — classification of a keyword's 12-month search-volume pattern. |
| **Upside value** | Dollars you'd gain if a keyword reached position 3 — `projected_value(3) − projected_value(current)`. |
| **Volume** | Monthly global search volume from Keywords Everywhere. |
| **YoY** | Year-over-year. "Same 28-day window vs. the equivalent window a year ago." |

---

*Last updated: Apr 2026. For questions about a specific number, hover the cell — every metric has a tooltip that traces back to the source.*
