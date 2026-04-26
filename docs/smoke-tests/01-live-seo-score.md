# 01 — Live SEO score (rich factors)

**MOAT lever:** computation lock-in + data gravity. Score is composed
server-side from GSC + audit + KE backlinks + indexing data — none of
which the WP plugin can reproduce offline.

## Surface

| Layer | Path |
|---|---|
| Service | `app/Services/LiveSeoScoreService.php` |
| Controller | `app/Http/Controllers/Api/V1/PluginInsightsController::seoScore()` |
| Route | `POST /api/v1/posts/{externalPostId}/seo-score` (also accepts GET for back-compat) |
| WP proxy | `ebq-seo-wp/includes/class-ebq-rest-proxy.php::seo_score()` |
| WP route | `POST /wp-json/ebq/v1/seo-score/{id}` |
| React | `ebq-seo-wp/src/sidebar/components/LiveSeoScore.jsx` |

**Factor mix (weight totals 100):** rank 18 · ctr 6 · coverage 6 ·
cannibalization 4 · indexing 12 · backlinks 4 · core_web_vitals 10 ·
page_performance 6 · on_page_seo 6 · technical_health 6 · content_quality 5 ·
keyword_alignment 7 · recommendations 8 · tracked 0.

## Pre-conditions

- `<WEBSITE_ID>` exists in `websites`, has a Sanctum token, and at least
  one row in `search_console_data` (otherwise the endpoint returns
  `unavailable: no_gsc_data_for_url` correctly but you can't smoke-test
  the real factor mix).
- A `PageAuditReport` with `status='completed'` exists for the test URL
  (otherwise audit factors are pending — that's the audit-queue test, not
  this one).

## Scenario 1 — Happy path: ready audit + GSC data

```bash
curl -s -X POST "https://ebq.io/api/v1/posts/<POST_ID>/seo-score?url=<URL>&focus_keyword=test%20kw" \
  -H "Authorization: Bearer <TOKEN>" | jq '.live | {score, label, available, audit:.audit.status, factors:[.factors[] | {key, score, weight}]}'
```

**Pass when:**
- `available: true`
- `audit.status: "ready"`
- `factors[]` array has 14 entries (13 scoring + 1 zero-weight `tracked`)
- Each `score` is 0–100, no nulls, no `pending: true` rows

## Scenario 2 — No audit yet: pending placeholders + auto-queue

Run the same curl on a URL that's never been audited. Expect:

- `audit.status: "queued"` or `"running"`
- 5 factors carry `pending: true`: `core_web_vitals`, `page_performance`,
  `on_page_seo`, `technical_health`, `content_quality`, `keyword_alignment`,
  `recommendations`. These are excluded from the weighted average.
- A new row appears in `custom_page_audits` with `source='live_score'`:
  ```sql
  SELECT id, status, source, queued_at FROM custom_page_audits
  WHERE page_url_hash = SHA2(?, 256) ORDER BY queued_at DESC LIMIT 1;
  ```

## Scenario 3 — SERP-features rank discount

After the audit completes (`status='ready'`), inspect a `rank` factor row
on a query where `result.benchmark.your_serp.organic_sample_size < 8`:

```bash
curl -s -X POST "https://ebq.io/api/v1/posts/<POST_ID>/seo-score?..." \
  -H "Authorization: Bearer <TOKEN>" | jq '.live.factors[] | select(.key=="rank") | {score, detail, recommendation}'
```

**Pass when:** `detail` mentions `features visible: N% penalty` and `recommendation`
opens with the SERP-features-dominate sentence.

## Acceptance summary

| Check | Pass condition |
|---|---|
| Endpoint reachable | HTTP 200, `available: true` (or `false` with explicit `reason`) |
| Factor count | 14 with full audit, 14 with audit pending (5 marked `pending`) |
| Weight integrity | Sum of non-pending weights == 100 |
| Auto-queue | New `custom_page_audits` row when no completed audit exists |

## Common failures

| Symptom | Diagnosis | Fix |
|---|---|---|
| HTTP 500 + `Argument #4 must be of type ?Illuminate\Support\Carbon` | Old controller calls `\Carbon\Carbon::parse()` against the new service signature | Deploy + reload PHP-FPM; service hint is `?\DateTimeInterface` since 2026-04-26 |
| Always returns `available: false, reason: no_gsc_data_for_url` | URL doesn't match any `search_console_data.page` variant | Check the diagnostics block (`debug.tried_variants`) vs the URLs in GSC |
| Score is stuck on the same value despite content edits | WP transient cache returning stale | See `04-cache-hardening.md`. Ensure `seo-score` is in the skip-cache list in `class-ebq-api-client.php` |
