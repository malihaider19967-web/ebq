# 10 — Database Layer

> The Memory of the system. If this schema is wrong, every simulation is a guess.

Parent: [`00-master-plan.md`](./00-master-plan.md) · Siblings: [`20-researcher.md`](./20-researcher.md), [`30-simulation.md`](./30-simulation.md)

---

## 1. Design principles

1. **Relational, not NoSQL.** Keywords belong to pages, pages belong to sites, sites belong to users. Foreign keys enforce this; they are not optional.
2. **Cache-first economics.** Every paid-API call (Serper, DataForSEO, Lighthouse, OpenAI) is wrapped in a lookup-by-hash before the HTTP call. A cache miss logs `cache_miss` for cost attribution.
3. **Freshness is a field, not a feeling.** Every cacheable row has `computed_at TIMESTAMP` and a `fresh_for_days` constant on the model. A `scope fresh()` is all app code should ever use.
4. **JSON where shape is open, columns where shape is closed.** Entity lists, vectors, raw API payloads → JSON. Word counts, LCP ms, URL hashes → proper columns with indices.
5. **S3 for blobs, MySQL for pointers.** Raw HTML, screenshots, 768-d vectors never sit in MySQL row bodies. MySQL stores the S3 key.
6. **No destructive migrations past Phase 1.** Once a table goes to prod, changes are additive. The `->after()` trap already bit us once; see §7.

---

## 2. Entity-relationship overview

```
users ──< websites ──< custom_page_audits ──< page_audit_reports
                  │                         │
                  │                         └─< simulation_runs
                  │
                  ├─< page_indexing_statuses
                  ├─< page_vectors
                  └─< cannibalization_findings

keywords (global cache)  ◄── serp_snapshots  ◄── entity_analysis_metrics
                                                        │
                                                        └── market_benchmarks (aggregated per niche)
```

Everything keyed by `website_id` is per-tenant; `keywords / serp_snapshots / entity_analysis_metrics / market_benchmarks` are **shared caches** across tenants — they are the Oracle.

---

## 3. New tables (Phase 1–2)

### 3.1 `keywords`

Persistent keyword cache. Populated on demand; shared across all tenants.

| Column | Type | Notes |
|---|---|---|
| `id` | bigIncrements | |
| `phrase` | varchar(200) | raw query string, trimmed, case-preserved |
| `phrase_hash` | char(64) | `sha256(lower(phrase)\|gl\|hl)` — unique index |
| `gl` | char(2) | country (Serper `gl`) |
| `hl` | varchar(8) | language (Serper `hl`) |
| `search_volume` | int | monthly, from DFS |
| `cpc_usd` | decimal(8,2) | |
| `difficulty` | tinyint | 0–100 |
| `intent` | enum | `informational` / `commercial` / `transactional` / `navigational` |
| `raw_payload` | json | full provider response for replay |
| `computed_at` | timestamp | |
| `timestamps` | | |

Indexes: `(phrase_hash)` unique; `(gl, hl, search_volume)` for niche discovery.

**TTL:** 30 days. After that, re-fetch on next miss.

### 3.2 `serp_snapshots`

Top N organic URLs for a keyword at a point in time.

| Column | Type | Notes |
|---|---|---|
| `id` | bigIncrements | |
| `keyword_id` | FK → keywords | |
| `fetched_at` | timestamp | |
| `provider` | enum | `serper` / `dataforseo` |
| `results` | json | `[{position, url, title, snippet, domain, featured_snippet}]` |
| `total_results` | bigint | nullable |
| `computed_at` | timestamp | |

Indexes: `(keyword_id, fetched_at DESC)` for "latest snapshot for keyword X".

**TTL:** 30 days. The previous snapshot is kept for change-detection.

### 3.3 `entity_analysis_metrics`

The **DNA** of a single URL. One row per (url_hash).

| Column | Type | Notes |
|---|---|---|
| `id` | bigIncrements | |
| `url` | varchar(2048) | |
| `url_hash` | char(64) | `sha256(normalize(url))` — unique index |
| `website_id` | nullable FK | only set when the URL is the tenant's own page |
| `word_count` | int | |
| `header_tree` | json | `{h1: [..], h2: [..], h3: [..]}` |
| `readability_flesch` | decimal(5,2) | |
| `speed_lcp_ms` | int | nullable |
| `speed_ttfb_ms` | int | nullable |
| `speed_cls` | decimal(6,3) | nullable |
| `entities` | json | `[{text, label, count, in_headers}]` |
| `ngrams` | json | `[{phrase, count}]` — 2- and 3-grams |
| `vector_s3_key` | varchar(255) | pointer to `.npy` in S3 |
| `raw_html_s3_key` | varchar(255) | |
| `info_gain` | decimal(4,3) | 0.000–1.000 |
| `language` | varchar(8) | detected |
| `computed_at` | timestamp | |

Indexes: `(url_hash)` unique; `(website_id, computed_at DESC)`; fulltext on `entities->>'$.text'` if we later query by entity.

**TTL:** 30 days per URL. Re-crawl on miss.

### 3.4 `market_benchmarks`

Aggregated "what does the average winner look like in niche X?" Recomputed by a nightly job from `entity_analysis_metrics`.

| Column | Type | Notes |
|---|---|---|
| `id` | bigIncrements | |
| `niche_slug` | varchar(80) | e.g. `free-fire-names`, derived from seed keyword |
| `sample_size` | int | how many URLs went into the aggregate |
| `median_word_count` | int | |
| `median_lcp_ms` | int | |
| `median_ttfb_ms` | int | |
| `median_readability` | decimal(5,2) | |
| `top_entities` | json | `[{text, coverage_pct}]` — entities present on ≥50% of winners |
| `top_ngrams` | json | same shape |
| `has_faq_pct` | decimal(5,2) | % with `FAQPage` JSON-LD |
| `has_howto_pct` | decimal(5,2) | |
| `avg_outbound_citations` | decimal(5,2) | |
| `computed_at` | timestamp | |

Indexes: `(niche_slug)` unique.

**TTL:** 7 days. Bench updates feel stable enough for weekly refresh.

### 3.5 `page_vectors`

Normalized, queryable home for the 768-d vectors. Duplicates `vector_s3_key` from `entity_analysis_metrics` but keeps the vector column *in* MySQL so cosine queries stay inside one database round trip.

| Column | Type | Notes |
|---|---|---|
| `id` | bigIncrements | |
| `url_hash` | char(64) | unique |
| `website_id` | nullable FK | set when it's a tenant page |
| `vector` | blob | packed float32 little-endian, 768 × 4 = 3072 bytes |
| `model` | varchar(64) | e.g. `all-mpnet-base-v2@sbert-2.6` — bump when model changes |
| `computed_at` | timestamp | |

Indexes: `(url_hash)` unique; `(website_id)` for per-site scans.

> We do **not** use MySQL 8 vector-type yet (not in MariaDB; version skew). Packed BLOB + app-side cosine is fine at the volumes we'll see in 2026. Migrate to `VECTOR(768)` or pgvector if we outgrow it.

### 3.6 `cannibalization_findings`

Output of the nightly overlap scan. One row per `(website_id, page_a_hash, page_b_hash)` pair.

| Column | Type | Notes |
|---|---|---|
| `id` | bigIncrements | |
| `website_id` | FK | |
| `page_a_url` | varchar(2048) | |
| `page_a_hash` | char(64) | |
| `page_b_url` | varchar(2048) | |
| `page_b_hash` | char(64) | |
| `similarity` | decimal(5,4) | 0.0000–1.0000 |
| `winner_url` | varchar(2048) | chosen by GSC clicks over last 28d |
| `gsc_a_clicks_28d` | int | nullable |
| `gsc_b_clicks_28d` | int | nullable |
| `recommendation` | enum | `merge_redirect` / `re-angle` / `ignore` |
| `dismissed_at` | timestamp | user dismissal |
| `computed_at` | timestamp | |

Indexes: `(website_id, similarity DESC)`, unique `(website_id, page_a_hash, page_b_hash)`.

Always store the pair with `page_a_hash < page_b_hash` so we don't double-insert.

### 3.7 `simulation_runs`

Every "what-if" a user tried. Fuels future calibration of CFD weights.

| Column | Type | Notes |
|---|---|---|
| `id` | bigIncrements | |
| `user_id` | FK | |
| `custom_page_audit_id` | FK | |
| `inputs` | json | `{delta_words: +500, delta_lcp_ms: -300, added_entities: [...]}` |
| `baseline_cfd` | decimal(5,4) | pre-deltas |
| `simulated_cfd` | decimal(5,4) | post-deltas |
| `predicted_rank` | tinyint | 1–20 or null |
| `benchmark_id` | FK → market_benchmarks | the row used for math |
| `created_at` | timestamp | |

Indexes: `(custom_page_audit_id, created_at DESC)`.

No TTL — these are the training set.

---

## 4. Existing-table amendments

### 4.1 `websites`

Add:

- `niche_slug VARCHAR(80) NULL` — indexes into `market_benchmarks`.
- `default_serp_gl CHAR(2) NULL` / `default_serp_hl VARCHAR(8) NULL` — remembered across audits so the UI doesn't re-ask.

### 4.2 `custom_page_audits`

Already has `target_keyword`, `serp_sample_gl`. Add:

- `entity_analysis_metric_id BIGINT UNSIGNED NULL` — FK to the Researcher's row for this URL.
- `latest_simulation_run_id BIGINT UNSIGNED NULL` — last "what-if" the user tried.

### 4.3 `page_audit_reports`

No schema change — the `result` JSON is already the kitchen sink. New fields go inside:

- `result.researcher` — pointer block `{entity_analysis_metric_id, vector_s3_key, info_gain}`.
- `result.benchmark.market_benchmark_id` — which niche row powered the numbers.

---

## 5. Conventions & helpers

### 5.1 URL normalization

```
normalize(url) =
  lowercase(host)
  + strip_default_port
  + remove_trailing_slash_unless_root
  + strip_utm_*
  + sort_query_params
```

Implemented once in `App\Support\Url\Normalizer`. **All** `url_hash` columns compute from this function. If we change it, we rehash on migration, not on read.

### 5.2 Freshness

```php
// Every cache model:
public const FRESH_FOR_DAYS = 30;

public function scopeFresh(Builder $q): Builder
{
    return $q->where('computed_at', '>=', now()->subDays(static::FRESH_FOR_DAYS));
}
```

Callers always do `Keyword::fresh()->where(...)->first()` and never handwrite the `>=` date check.

### 5.3 Migration hygiene

- **Never** use `->after('col')` unless `col` was created in the *same* migration file or in a file with a **strictly earlier** timestamp. Column position is cosmetic in MySQL; it is not worth the ordering foot-gun.
- Every `add_*` migration checks `Schema::hasColumn()` before adding — re-runs survive re-deploys.
- Index names must be ≤ 64 chars. For composite indexes use an explicit short name: `$table->index([...], 'short_name_idx');`.

---

## 6. Data retention

| Table | Retention |
|---|---|
| `keywords` | 30 days rolling (re-fetch on miss) |
| `serp_snapshots` | 180 days, keep historical for trend graphs |
| `entity_analysis_metrics` | 30 days rolling per URL |
| `market_benchmarks` | 7 days rolling; old rows pruned |
| `page_vectors` | 30 days rolling |
| `cannibalization_findings` | 90 days, or until `dismissed_at + 30 days` |
| `simulation_runs` | **forever** — training data |
| `page_audit_reports` | **forever** — immutable user artifact |
| `custom_page_audits` | **forever** |

Retention is enforced by `app/Console/Commands/PruneStaleCache.php` running daily at 03:00 via the scheduler.

---

## 7. Ordering pitfalls we've already hit

These are scar-tissue rules, not opinions:

| Symptom | Root cause | Rule |
|---|---|---|
| `Identifier name is too long (>64)` | Auto-named composite index on long columns | Always pass an explicit short name |
| `Unknown column 'page_hash'` mid-migration | `->after('page_hash')` in a migration dated *before* the one that creates `page_hash` | Drop `after()` unless in-file, OR renumber timestamps |
| `Table already exists` on re-run | CREATE TABLE succeeded, later ALTER in same file failed, row not inserted into `migrations` | Split schema creation and index/alter into separate migration files |

---

## 8. Implementation order

1. `websites.niche_slug` + `websites.default_serp_*` (tiny alter, enables everything downstream).
2. `keywords` + `serp_snapshots` (needed before Researcher Phase 1 can cache anything).
3. `entity_analysis_metrics` + `page_vectors` (Researcher writes these).
4. `market_benchmarks` + nightly aggregator command.
5. `simulation_runs` (Phase 2 — once CFD math lands).
6. `cannibalization_findings` (Phase 3).

Each step gets its own PR. No "big-bang" migration drop.
