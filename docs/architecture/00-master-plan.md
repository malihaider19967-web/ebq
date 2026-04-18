# EBQ Search Intelligence Platform — Master Plan

> "Laravel is the Manager, Python is the Researcher, the Database is the Memory."

This document is the single source of truth for what EBQ is becoming. Every PR, ticket, and roadmap item should trace back to a section below.

---

## 0. North Star

Build a **Digital Brain for SEO** that reverse-engineers a niche at server speed:

1. **Read** the top-ranking pages for any keyword (Researcher).
2. **Understand** them semantically — entities, topics, depth, speed (NLP).
3. **Predict** what it would take to outrank them (Simulation Math).
4. **Act** by recommending concrete content, cannibalization fixes, and AEO-ready snippets.

Success is measured by one question: *"Can a non-SEO user launch an audit and get a rank prediction they can trust?"*

---

## 1. High-Level Architecture

```
                   ┌──────────────────────────────────────────────┐
                   │                   USER / CLIENT              │
                   └───────────────┬──────────────────────────────┘
                                   │  HTTPS
                 ┌─────────────────▼──────────────────┐
                 │   LARAVEL  (The Manager)           │
                 │   - Livewire/Volt dashboard        │
                 │   - Auth, billing, projects        │
                 │   - Job dispatcher                 │
                 │   - Simulation math (CFD, UX)      │
                 │   - Cannibalization detector       │
                 └──────┬───────────────────┬─────────┘
                        │                   │
                        │ Redis queue       │ MySQL (source of truth)
                        ▼                   ▼
            ┌──────────────────────┐  ┌──────────────────────┐
            │ PYTHON  (Researcher) │  │   KEYWORD REPOSITORY │
            │ - Serper / DFS       │◄─┤   keywords           │
            │ - Playwright crawler │  │   entity_analysis_*  │
            │ - spaCy NLP          │  │   market_benchmarks  │
            │ - Vector builder     │  │   competitor_cache   │
            └──────────┬───────────┘  └──────────────────────┘
                       │
                       ▼
               Hetzner S3 (raw HTML, screenshots, vectors)
```

**Why split the brain from the muscles:**

- Laravel is excellent at forms, auth, queues, and SQL. It is *bad* at text-heavy NLP.
- Python owns spaCy, Playwright, scikit-learn, numpy. Do not bolt these into PHP.
- The two talk through **Redis (jobs) + MySQL (persisted results) + S3 (blobs)**. Nothing else.

---

## 2. Database — The Memory

Relational MySQL. Every value saved buys us a future audit we *don't* have to pay for.

### 2.1 Core tables

| Table | Purpose | Freshness TTL |
|---|---|---|
| `keywords` | Persistent keyword cache — search volume, difficulty, CPC, SERP intent | 30 days |
| `serp_snapshots` | Top 10–20 URLs per (keyword × gl × hl) at a point in time | 30 days |
| `entity_analysis_metrics` | The DNA of every scraped page: word count, LCP/TTFB, headers, entity JSON | 30 days, per URL |
| `market_benchmarks` | Aggregated niche averages (word count, H2 count, readability, entity coverage) | 7 days, per niche slug |
| `page_vectors` | 768-dim semantic vectors per URL (for cannibalization + info-gain) | 30 days |
| `cannibalization_findings` | Cached cosine-similarity hits per site | 14 days |
| `simulation_runs` | Every "what-if" the user tried (inputs + predicted rank) | keep indefinitely |

### 2.2 Tables we already have

| Table | Role in the new plan |
|---|---|
| `custom_page_audits` | Remains the entry point for a user-initiated audit; gains a `simulation_id` FK |
| `page_audit_reports` | Stays as the immutable report artifact; new fields wrap into `result` JSON |
| `page_indexing_statuses` | Already covers Google indexing — keep as-is |
| `websites` | Add `niche_slug` + `default_serp_gl/hl` so benchmarks are precomputable |

### 2.3 Conventions

- All TTLs enforced by a `computed_at` timestamp + a `is_fresh(TTL)` scope.
- Before any paid API call: **check cache first**. Log `cache_hit` / `cache_miss` for cost attribution.
- JSON blobs (entities, vectors) live in MySQL for queryability + a copy in S3 for replay.

---

## 3. The Researcher — Python Service

Separate repo/container. Single responsibility: *turn a URL into structured knowledge.*

### 3.1 Stack

| Layer | Tool | Why |
|---|---|---|
| Orchestration | FastAPI + Celery (or RQ) | Matches Laravel's Redis queue natively |
| Browser | Playwright (headless Chromium) | Vite/React sites need JS execution |
| Discovery | Serper API → DataForSEO (fallback) | Lowest cost-per-SERP; same shape |
| NLP | spaCy (`en_core_web_trf`) | Best balance of accuracy + speed on CPU |
| Vectors | sentence-transformers (`all-mpnet-base-v2`) | 768-d, good for cosine similarity |
| Metrics | Lighthouse-CI (subprocess) | LCP / TTFB / CLS without paying PageSpeed |

### 3.2 Pipeline (one job = one URL)

```
Job in Redis
   │
   ▼
1. Cache check         → entity_analysis_metrics hit? return immediately
2. Fetch               → Playwright navigate, wait for networkidle
3. Extract             → readable text, header tree, canonical, meta
4. NLP                 → spaCy entities, POS-tagged n-grams (2–3 grams)
5. Speed               → Lighthouse LCP/TTFB/CLS
6. Vector              → sentence-transformers embedding
7. Info-Gain score     → cosine to competitor vectors; <0.15 novelty = low score
8. Persist             → MySQL rows + S3 blobs
9. Publish             → Redis pub/sub "research.done" with audit_id
```

### 3.3 Contract with Laravel (JSON payload)

```json
{
  "audit_id": 123,
  "url": "https://example.com/free-fire-names",
  "status": "ok",
  "fetched_at": "2026-04-19T12:00:00Z",
  "metrics": {
    "word_count": 1847,
    "headers": { "h1": 1, "h2": 7, "h3": 12 },
    "readability": { "flesch_kincaid": 62.4 },
    "speed": { "lcp_ms": 2120, "ttfb_ms": 340, "cls": 0.02 }
  },
  "entities": [
    { "text": "V-Badge", "label": "MISC", "count": 11, "in_headers": true },
    { "text": "Unicode", "label": "ORG",  "count": 4,  "in_headers": false }
  ],
  "ngrams":   [ { "phrase": "copy and paste", "count": 9 } ],
  "vector":   "s3://ebq-research/vectors/2026/04/123.npy",
  "raw_html": "s3://ebq-research/html/2026/04/123.html.gz",
  "info_gain": 0.34
}
```

Laravel never calls Playwright/spaCy directly. Every read is `DB` or `Redis`.

---

## 4. Simulation Math — The Predictor

The differentiator: **"Move a slider → see a rank prediction."**

### 4.1 Content-First Difficulty (CFD)

Per competitor URL, compute a score in `[0, 1]`. Your page's score minus the median competitor = your ranking distance.

```
CFD = 0.40 · intent_match
    + 0.35 · topical_depth
    + 0.25 · ux_strength

intent_match   = matches_serp_intent ∈ {0, 0.5, 1} (informational/commercial/transactional)
topical_depth  = |entities_covered ∩ benchmark_entities| / |benchmark_entities|
ux_strength   = 1 − clamp((your_lcp − median_lcp) / median_lcp, -1, 1)
```

### 4.2 What-if simulation

1. Snapshot the current page's metrics (word count, entity set, LCP).
2. Apply user deltas (`+500 words`, `+entity "V-Badge"`, `−200ms LCP`).
3. Recompute CFD against the cached `market_benchmarks` row.
4. Find the first competitor whose CFD ≤ new score. That's the **Predicted Rank**.
5. Persist the run into `simulation_runs` — fuel for future ML.

### 4.3 Why this is honest

- Every input comes from cached facts, not vibes.
- The formula is deterministic and auditable.
- Over time, compare `simulation_runs.predicted_rank` vs. actual GSC position — calibrate the weights.

---

## 5. Cannibalization Safety Net

Two of your own pages chasing the same intent = Google picks neither.

### 5.1 Detection

1. Nightly job loads every page vector for the site.
2. Pairwise cosine similarity. `> 0.85` ⇒ flag.
3. Join GSC impressions/clicks per URL.
4. Write to `cannibalization_findings` with `{page_a, page_b, similarity, winner_url}`.

### 5.2 Recommendation

- **Winner** = the page with higher clicks/impressions over the last 28 days.
- **Loser** = prompt user to either merge (301 to winner) or re-angle to an adjacent intent.
- The UI shows a merge/re-angle wizard, not just a red dot.

---

## 6. 2026 Upgrades — AEO & Information Gain

Classic "10 blue links" SEO is flatlining. We optimize for **answer engines** (ChatGPT, Perplexity, Google AI Overviews).

### 6.1 Information Gain score

- Compute cosine similarity of our vector to each top-10 competitor vector.
- Info-Gain = `1 − mean(cosine)`. Below `0.15` = we are a clone.
- Surface this as a **"Originality: 34%"** badge on the audit.

### 6.2 AEO checklist (automatic)

For each audit we check:

- Is there a **≤40-word definition** in the first 200 words? (answer-ready snippet)
- Is there a `FAQPage` or `HowTo` JSON-LD block?
- Are H2s phrased as questions?
- Is there a clear **source citation** (outbound link with author/date)?

These are binary flags that feed the overall score and produce recommendations in the existing `RecommendationEngine`.

---

## 7. Deployment & Scale

### 7.1 Today's VPS layout (already provisioned)

| Role | Where | Notes |
|---|---|---|
| Apache 2.4 + PHP-FPM 8.3 | Ubuntu VPS | `scripts/ubuntu-vps-install.sh` |
| MySQL | same host | utf8mb4, `ebq` db |
| Redis | same host (optional) | `ENABLE_REDIS=1` in `deploy.env` |
| Laravel queue worker | supervisor (`ebq-queue`) | see `docs/ops/supervisor.md` (todo) |
| Laravel scheduler | supervisor (`ebq-schedule`) | |
| TLS | Let's Encrypt via certbot | auto-renew via `certbot.timer` |

### 7.2 Target layout (next)

| Role | Where |
|---|---|
| Laravel web | current VPS |
| MySQL (primary) | current VPS |
| Redis | current VPS |
| **Python Researcher** | separate Hetzner CX box (higher RAM, Playwright-friendly) |
| **Raw HTML / vectors / screenshots** | Hetzner Object Storage (S3-compatible) |
| Backups | nightly mysqldump → S3 |

### 7.3 Scaling rules of thumb

- **Never crawl from a user click.** Always: Livewire action → dispatch job → Redis → Researcher.
- **Never persist HTML on the web VPS disk.** Gzip → S3.
- **Never hit a paid API without a cache check.** Every external call is wrapped in a `Cached<T>` helper.
- **Always stamp `computed_at`** on cache rows so we can prove freshness in support tickets.

---

## 8. Roadmap — Phased Delivery

### Phase 0 — Ops hardening (done / in-flight)
- Production installer, TLS, supervisor, UFW, unattended-upgrades
- Migration-ordering hygiene (keep `->after()` off columns created by later migrations)

### Phase 1 — Researcher MVP (weeks 1–3)
- Python service skeleton (FastAPI + Celery + Redis listener)
- Playwright fetcher → raw HTML to S3
- spaCy entity extraction → JSON contract in §3.3
- MySQL persistence of `entity_analysis_metrics`
- Laravel side: `ResearchJob`, `ResearchResult` model, Livewire "processing…" state

### Phase 2 — Benchmarks & predictions (weeks 4–6)
- `market_benchmarks` aggregator (nightly cron)
- CFD math implementation in `app/Support/Audit/CFD.php`
- What-if slider UI in the audit report
- `simulation_runs` persistence

### Phase 3 — Cannibalization + AEO (weeks 7–9)
- `page_vectors` table + nightly vectorization job
- Cannibalization detector + merge wizard
- AEO checklist rules in `RecommendationEngine`
- Info-Gain badge on the report

### Phase 4 — Oracle mode (weeks 10+)
- Enough cached competitor data that fresh niches instantly have benchmarks
- GSC-calibrated re-weighting of CFD formula
- Predictive alerting ("Rank-3 competitor just added 1200 words — review now")

---

## 9. Non-Goals (on purpose)

- ❌ No multi-tenant white-labeling in Phase 1.
- ❌ No real-time (streaming) crawl — batch is cheaper and sufficient.
- ❌ No in-house search index — we rely on Serper/DFS.
- ❌ No GPU-hosted LLMs — everything runs on CPU boxes.

---

## 10. Glossary

| Term | Meaning |
|---|---|
| **Researcher** | The Python service that crawls, parses, and scores pages |
| **Manager** | The Laravel app that users interact with |
| **Entity** | A proper-noun concept in the text (character, product, standard) |
| **Vector** | 768-dim embedding of a page's meaning |
| **CFD** | Content-First Difficulty — our ranking-distance score |
| **AEO** | Answer Engine Optimization — ranking inside AI answers, not just links |
| **Info-Gain** | How much *new* signal a page adds vs. the top-10 |
| **Oracle Effect** | Having so much cached data that audits need no fresh crawl |

---

*Keep this file short. Details belong in sibling docs under `docs/architecture/` (e.g. `10-database.md`, `20-researcher.md`, `30-simulation.md`). This file is the index.*
