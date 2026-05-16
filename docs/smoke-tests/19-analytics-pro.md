# 19 — Analytics Pro tabs (AI traffic, algorithm overlay, winners/losers)

## What the feature does

Surfaces four new analytical views in EBQ HQ:

- AI traffic split (Google, Discover, Perplexity, ChatGPT-User, …).
- Algorithm-update overlay on the SEO Performance chart.
- Top 5 winning / top 5 losing queries + posts over a window.
- Page-level keyword drilldown.

## Files

- [`app/Http/Controllers/Api/V1/AnalyticsProController.php`](../../app/Http/Controllers/Api/V1/AnalyticsProController.php)
- Plan flag: `plan_features.analytics_pro`

## Pre-conditions

- Plan has `analytics_pro` on (Startup+).
- Site has ≥7 days of analytics data so winners/losers has signal.

## Scenarios

### 1. AI-traffic split

```bash
curl -H "Authorization: Bearer <TOKEN>" \
  https://ebq.io/api/v1/analytics/ai-traffic-split?days=30
```

✅ Returns `{ ok: true, sources: [...] }` with at least 6 source
buckets. Sessions may be 0 on a fresh site.

### 2. Algorithm-update registry

```bash
curl -H "Authorization: Bearer <TOKEN>" \
  https://ebq.io/api/v1/analytics/algorithm-updates
```

✅ Returns the curated update list with `{ date, name, url }` triples.

### 3. Winners / losers

```bash
curl -H "Authorization: Bearer <TOKEN>" \
  https://ebq.io/api/v1/analytics/winners-losers?days=28
```

✅ Returns four arrays (`winners_queries`, `losers_queries`,
`winners_pages`, `losers_pages`).

### 4. Page drilldown

```bash
curl -H "Authorization: Bearer <TOKEN>" \
  https://ebq.io/api/v1/analytics/page/<POST_ID>
```

✅ Returns `{ totals: {...}, series: [...], top_queries: [...] }`.

### 5. Plan gate (Pro)

Switch the plan to Pro and re-run any of the above.

✅ HTTP 402 + `{ error: "tier_required", required_tier: "startup" }`.
