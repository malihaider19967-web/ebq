# 12 — Topical authority map

**MOAT lever:** computation + data gravity. Clusters GSC queries on EBQ
using token co-occurrence + page sharing — pragmatic alternative to a
full embedding pipeline. Output schema is forward-compatible: a future
`text-embedding-3-small`-based clusterer drops in without changing
consumers.

## Surface

| Layer | Path |
|---|---|
| Service | `app/Services/TopicalAuthorityService.php` |
| Controller | `PluginHqController::topicalAuthority()` |
| Route | `GET /api/v1/hq/topical-authority` |
| WP proxy | `class-ebq-rest-proxy.php::hq_topical_authority()` |
| HQ tab | `ebq-seo-wp/src/hq/tabs/TopicalAuthorityTab.jsx` |
| Cache | `ebq_topical_authority:<wid>` (24h TTL) |
| Underlying data | `search_console_data` (90-day rolling window) |

## Pre-conditions

- Test website has ≥100 query×page rows in `search_console_data` over
  the last 90 days (otherwise clusters won't form — the service requires
  `min impressions ≥ 5` per query and at least 2 queries per cluster).

## Scenario 1 — No GSC data

Brand-new site:

```bash
curl -s "https://ebq.io/api/v1/hq/topical-authority" \
  -H "Authorization: Bearer <TOKEN>" | jq
```

**Pass when** `ok: false, reason: "no_gsc_data"`.

## Scenario 2 — Populated map

```bash
curl -s "https://ebq.io/api/v1/hq/topical-authority" \
  -H "Authorization: Bearer <TOKEN>" | jq '{ok, gap_count: (.gaps|length), cluster_count: (.clusters|length), top_cluster: .clusters[0]}'
```

**Pass when:**
- `ok: true`
- `clusters[]` has 1+ entries; sorted by `authority_score` desc
- Each cluster has: `id`, `label` (top 3 tokens), `queries[]` (up to 10
  examples), `pages[]`, `authority_score` (0–100), `avg_position`,
  `total_clicks`, `total_impressions`
- `gaps[]` lists low-authority high-impression clusters as content
  opportunities (each with `label` + `suggested_action`)

## Scenario 3 — Authority score sanity

For a known high-traffic cluster (e.g., your top brand keyword):

```php
// In Tinker
$map = app(\App\Services\TopicalAuthorityService::class)->map($website);
// Find your strongest cluster:
collect($map['clusters'])->first(fn($c) => str_contains($c['label'], 'brand'));
```

**Pass when** the cluster you'd intuitively expect to be the strongest
is in the top 3 by `authority_score`. Score formula:
- 40% position quality (1=100, 100=0, log decay)
- 25% click traffic (saturates at 1000/90d)
- 20% impression breadth (saturates at 5000/90d)
- 10% page coverage (1 page = 30, 5+ pages = 100)
- 5% query breadth (5+ queries = 100)

## Scenario 4 — Gap detection

Look at the `gaps` array. **Pass when** every gap has:
- `authority_score < 40` AND
- `total_impressions ≥ 200`

These are the actionable "you get traffic but rank poorly — write a
better page" prompts.

## Scenario 5 — HQ tab interactivity

Open EBQ HQ → Topical Authority.

**Pass when:**
- Yellow "Content opportunities" callout box at top (if gaps exist).
- Cluster table with authority badge color-coded (green ≥65, amber
  ≥40, red <40).
- Click a cluster row → expands to show queries (chips) + ranking
  pages (links).

## Acceptance summary

| Check | Pass condition |
|---|---|
| Empty site returns clean reason | `no_gsc_data` |
| Clustering produces meaningful labels | Top cluster's label looks like a real topic, not noise |
| Authority score ranks clusters sensibly | Hand-picked strong cluster appears in top 3 |
| Gaps surface real opportunities | Each gap is low-authority + high-volume |
| Cache 24h | Second call within 24h returns instantly |

## Common failures

| Symptom | Diagnosis | Fix |
|---|---|---|
| `clusters: []` despite having GSC data | Token co-occurrence + page-sharing filter too strict; check `MIN_QUERY_IMPRESSIONS` (default 5) | Lower threshold for testing or wait for more impressions |
| Cluster labels are noise ("the and for") | Token frequency dominated by stopwords; check `STOPWORDS` constant | Add the noisy term to `STOPWORDS` if it's a true stopword |
| One giant mega-cluster | Token-overlap join too aggressive; ultra-frequent tokens not pruned | The `if (count($queries) > 200) continue;` skip is the safety valve — verify it's hitting |
| Same map every day | 24h cache; flush via `Cache::forget('ebq_topical_authority:<wid>')` to test changes |
