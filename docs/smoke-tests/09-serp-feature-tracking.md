# 09 — Live SERP-feature tracking

**MOAT lever:** data gravity. Timeline depth compounds with site age —
a site with 90 days of snapshots can show feature volatility a fresh
competitor tool can't reproduce.

## Surface

| Layer | Path |
|---|---|
| Service | `app/Services/SerpFeatureTrackerService.php` |
| Controller | `PluginHqController::serpFeatures()` |
| Route | `GET /api/v1/hq/serp-features?days=30` |
| WP proxy | `class-ebq-rest-proxy.php::hq_serp_features()` |
| HQ tab | `ebq-seo-wp/src/hq/tabs/SerpFeaturesTab.jsx` |
| Underlying data | `rank_tracking_keywords` + `rank_tracking_snapshots.serp_features` (already populated by daily rank cron) |

## Pre-conditions

- At least one active `RankTrackingKeyword` for the test website.
- ≥1 `RankTrackingSnapshot` row with non-null `serp_features` JSON.
  (Backfill if needed via the existing rank-tracking cron.)

## Scenario 1 — Empty website

```bash
curl -s "https://ebq.io/api/v1/hq/serp-features?days=30" \
  -H "Authorization: Bearer <TOKEN>" | jq
```

**Pass when** for a website with no tracked keywords:

```json
{ "keywords": [], "summary": { "total": 0, "with_answer_box": 0, "with_paa": 0, "with_image_pack": 0, "with_any_feature": 0 } }
```

## Scenario 2 — Populated timeline

For a website with ≥1 active keyword + recent snapshots:

```bash
curl -s "https://ebq.io/api/v1/hq/serp-features?days=30" \
  -H "Authorization: Bearer <TOKEN>" | jq '.keywords[0]'
```

**Pass when** the entry has:
- `id`, `keyword`, `country`
- `features_today`: list of feature keys present in the latest snapshot
  (subset of: `answer_box`, `people_also_ask`, `image_pack`, `sitelinks`,
  `video`, `top_stories`, `knowledge_panel`, `shopping`)
- `features_owned`: list of features where the user's domain appears
  inside the feature block (e.g. their URL in the answer box quote)
- `timeline`: per-day feature presence over the requested window

## Scenario 3 — "Owned features" detection

Pick a tracked keyword you KNOW shows your domain in a sitelinks block
(or any feature). Verify `features_owned` includes that feature's key.

To debug if it doesn't:

```php
// In Tinker — inspect the latest snapshot for that keyword
$snap = \App\Models\RankTrackingSnapshot::where('rank_tracking_keyword_id', <KW_ID>)
    ->latest('checked_at')->first();
dump($snap->serp_features);
// Walk the structure manually — the `blockContainsDomain()` method does
// a recursive iterator over all string values looking for URLs whose
// host matches the website's domain. If URLs aren't there, the
// detection can't fire.
```

## Scenario 4 — HQ tab renders

Open WP admin → EBQ HQ → SERP Features.

**Pass when:**
- Day-range pills (7 / 30 / 90) at the top.
- Summary stat tiles show `% of tracked keywords with answer box / PAA /
  image pack / any feature`.
- Per-keyword table renders with feature pills (purple = feature
  present, green-bordered "owned" pills when you own one).

## Acceptance summary

| Check | Pass condition |
|---|---|
| Endpoint reachable | HTTP 200 with `keywords` + `summary` shape |
| Feature detection from snapshot JSON | `features_today` reflects actual snapshot contents |
| Owned-feature detection | `features_owned` populated when domain appears in feature block |
| Day-range filter | `?days=7` returns shorter timelines than `?days=90` |
| HQ tab visible | "SERP Features" appears in the HQ tab nav |

## Common failures

| Symptom | Diagnosis | Fix |
|---|---|---|
| All `features_today` empty even with snapshots | `serp_features` JSON shape doesn't match `FEATURE_KEYS` constants | `dump($snap->serp_features)` — verify keys; update `SerpFeatureTrackerService::FEATURE_KEYS` if the rank tracker now uses different names |
| `features_owned` always empty | Feature blocks store URLs in a non-string field type, OR domain mismatch | Service walks ALL strings recursively — verify by `dump($snap->serp_features)` and grep for the website's domain |
| HQ tab missing | `App.jsx` not updated | `grep SerpFeaturesTab ebq-seo-wp/src/hq/App.jsx` |
