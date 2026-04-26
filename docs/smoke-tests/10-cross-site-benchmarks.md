# 10 — Cross-site benchmarks

**MOAT lever:** network effect. Single-site competitor tools physically
can't match this — they only have data for one site each. Only EBQ has
the network-wide GSC corpus to compute "your percentile vs the field".

## Surface

| Layer | Path |
|---|---|
| Service | `app/Services/CrossSiteBenchmarkService.php` |
| Controller | `PluginHqController::crossSiteBenchmarks()` |
| Route | `GET /api/v1/hq/benchmarks?country=us` |
| WP proxy | `class-ebq-rest-proxy.php::hq_benchmarks()` |
| HQ tab | `ebq-seo-wp/src/hq/tabs/BenchmarksTab.jsx` |
| Underlying data | `search_console_data` aggregated cross-website |
| Privacy floor | `MIN_COHORT_SIZE = 5` (constant in service) |

## Pre-conditions

- ≥5 distinct websites in the EBQ instance with `search_console_data`
  rows in the last 30 days, each with ≥100 data points (otherwise the
  global cohort is "too small" and you'll get the privacy-floor message).
- Test website also has ≥1 row in `search_console_data` so its `your`
  payload populates.

## Scenario 1 — Cohort too small

Fresh / dev EBQ instance with <5 sites:

```bash
curl -s "https://ebq.io/api/v1/hq/benchmarks" -H "Authorization: Bearer <TOKEN>" | jq
```

**Pass when** response has `ok: false` + `reason: "cohort_too_small"`,
plus the user's own `your` block populated.

## Scenario 2 — Production cohort, global only

```bash
curl -s "https://ebq.io/api/v1/hq/benchmarks" -H "Authorization: Bearer <TOKEN>" | jq
```

**Pass when:**
- `ok: true`
- `your` block: `avg_position`, `ctr_pct`, `queries_30d`, `clicks_30d`
- `global` block: `avg_position`, `ctr_pct`, `p50_position`, `p90_position`,
  `sample_size` (≥5)
- `percentile`: 0–100, where higher = better than more peers

## Scenario 3 — Country cohort

```bash
curl -s "https://ebq.io/api/v1/hq/benchmarks?country=us" \
  -H "Authorization: Bearer <TOKEN>" | jq '.country'
```

**Pass when** the `country` block exists with `country: "us"` + same
shape as `global` (avg_position, ctr_pct, sample_size). If <5 US-cohort
sites, the `country` key will be absent — but the global block stays.

## Scenario 4 — HQ tab renders

WP admin → EBQ HQ → Benchmarks.

**Pass when:**
- Country dropdown (Global / US / GB / CA / AU / IN / DE / FR / PK).
- Big "Nth percentile" callout box with explanation.
- Three stat cards: You / Network avg / [Country cohort if selected].

## Scenario 5 — Privacy floor enforced

In Tinker, simulate 4 sites' worth of data, then call:

```php
app(\App\Services\CrossSiteBenchmarkService::class)->forWebsite($website);
```

**Pass when** `ok: false, reason: "cohort_too_small"`. Even though the
endpoint succeeded, no per-site stats were exposed. The floor exists so
no individual site can be backed out of aggregate numbers.

## Acceptance summary

| Check | Pass condition |
|---|---|
| Empty cohort returns explicit reason | `cohort_too_small` |
| Global stats include p50 + p90 | Both percentiles present |
| Percentile direction correct | Higher = better (more peers worse than you) |
| Country cohort honored | Separate `country` block when ≥5 country-eligible sites |
| Cache 24h | Second call within 24h returns instantly (no big aggregate query) |

## Common failures

| Symptom | Diagnosis | Fix |
|---|---|---|
| Stats look wrong / unrealistic | Cohort filter `havingRaw('COUNT(*) >= 100')` excludes thin sites; check it's not excluding ALL | `SELECT website_id, COUNT(*) FROM search_console_data WHERE date >= ... GROUP BY website_id` |
| Same percentile every day | 24h cache; flush via `Cache::forget('ebq_xsite_benchmark:<wid>:<country>')` to test changes |
| Always cohort-too-small in production | Check `search_console_data` recent counts per site | Backfill GSC sync if data is stale |
