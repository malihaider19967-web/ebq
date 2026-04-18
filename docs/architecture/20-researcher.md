# 20 — The Researcher (Python Service)

> Laravel is the Manager. This service is the Researcher — the Python/NLP layer that actually "reads" the internet, understands it, and extracts the secrets of competitors.

Parent: [`00-master-plan.md`](./00-master-plan.md) · Siblings: [`10-database.md`](./10-database.md), [`30-simulation.md`](./30-simulation.md)

---

## 1. Responsibilities — one sentence each

1. Given a URL and a locale, return structured knowledge about that page.
2. Given a keyword and a locale, return the current SERP top-N.
3. Never be called synchronously. Always work off a queue.
4. Never leak provider-specific errors upstream; always return a normalized envelope.
5. Be boring and replayable. A bug report is "here's the URL and the job_id" — we should re-run it from raw HTML on S3.

If this service does anything else (user-facing routing, billing checks, auth), that is a bug.

---

## 2. Why a separate service

| Constraint | Why PHP loses | Why Python wins |
|---|---|---|
| Need a real headless browser | Playwright-PHP exists but is a second-class citizen | `playwright` is the reference impl |
| Need modern NLP (entities, vectors) | No equivalent of spaCy / sentence-transformers | Two `pip install`s |
| Need pandas-style aggregation for benchmarks | Painful in PHP | Native |
| Need to isolate heavy RAM (Chromium, transformer models) | Shared with PHP-FPM = OOM risk | Separate VM, easy to scale vertically |
| Need to pin scientific library versions | Composer + native exts = rebuild Apache | `pyproject.toml` + venv |

The cost is one extra service to operate. Worth it.

---

## 3. Stack

| Layer | Choice | Reason |
|---|---|---|
| Python | 3.12 | matches Debian 12 / Ubuntu 24.04 default |
| API | FastAPI | async-native, OpenAPI for free |
| Workers | Celery + Redis | same Redis as Laravel; same idioms |
| Browser | Playwright (Chromium) | handles Vite/React/SSR-hydrated sites |
| NLP | spaCy `en_core_web_trf` | strongest CPU-friendly entity model |
| Embeddings | `sentence-transformers` `all-mpnet-base-v2` | 768-d, open, no API cost |
| Metrics | `lighthouse` CLI (subprocess) | avoids paying for PageSpeed API |
| Cloud | Hetzner CX VPS (dedicated), Hetzner Object Storage (S3-compatible) | same billing as web VPS |
| Process manager | systemd units | consistent with Laravel side |
| Observability | OpenTelemetry → Grafana Cloud free tier | trace per job |

Nothing in this stack requires a GPU. If we ever adopt a bigger embedding model, it belongs behind a feature flag, not as the default.

---

## 4. Service layout

```
researcher/
├── pyproject.toml
├── README.md
├── researcher/
│   ├── __init__.py
│   ├── app.py                 # FastAPI — health, debug-only replay
│   ├── tasks/                 # Celery tasks (one per job type)
│   │   ├── __init__.py
│   │   ├── research_url.py    # main pipeline
│   │   ├── fetch_serp.py
│   │   └── vectorize_site.py
│   ├── pipeline/              # pure functions, no side effects
│   │   ├── fetch.py           # playwright wrapper
│   │   ├── extract.py         # html → text/headers/canonical
│   │   ├── nlp.py             # spaCy entity + ngram
│   │   ├── embed.py           # sentence-transformers
│   │   ├── speed.py           # lighthouse runner
│   │   └── infogain.py        # cosine math
│   ├── storage/
│   │   ├── s3.py              # put/get raw html, vectors
│   │   └── db.py              # SQLAlchemy models mirroring Laravel schema
│   ├── providers/
│   │   ├── serper.py
│   │   └── dataforseo.py
│   ├── contracts/
│   │   └── payload.py         # Pydantic models matching §7 JSON contract
│   └── settings.py            # pydantic-settings, reads .env
└── tests/
    ├── fixtures/              # saved HTML/SERP payloads
    └── test_pipeline.py
```

Rule of thumb: **`pipeline/*.py` is pure** — takes inputs, returns outputs, no I/O. Tasks and storage handle I/O. This makes unit tests trivial.

---

## 5. Job pipeline — step by step

Input: `{ job_id, audit_id, url, gl, hl, force_refresh }`

```
 1. normalize(url)                       # see docs/architecture/10-database.md §5.1
 2. cache_hit = EntityAnalysisMetric.fresh(url_hash)
    if cache_hit and not force_refresh:
        publish("research.done", cache_hit.id)
        return
 3. raw_html, final_url = fetch_playwright(url, gl, hl)
 4. s3_html_key = s3.put_gzipped_html(raw_html)
 5. extracted  = extract(raw_html)       # text, headers, canonical, lang
 6. metrics    = speed.run_lighthouse(url)
 7. nlp_out    = nlp.analyze(extracted.text)
 8. vector     = embed.encode(extracted.text)
    s3_vec_key = s3.put_vector(vector)
 9. info_gain  = infogain.compute_against_niche(vector, niche_slug)
10. row = db.upsert_entity_analysis_metrics(
          url_hash, extracted, metrics, nlp_out,
          s3_html_key, s3_vec_key, info_gain)
11. publish("research.done", { job_id, audit_id, entity_analysis_metric_id: row.id })
```

Failure handling:

- Step 3 fails (network, 403, JS error) → row inserted with `status='fetch_failed'`, published with `status` so Laravel can show a friendly error. **No retries here** — retrying a permanent 403 wastes money. Laravel decides whether to retry.
- Step 6 fails (Lighthouse flaky) → metrics left null, **rest of pipeline continues**. Speed is nice-to-have, not a blocker.
- Steps 7 / 8 / 9 fail → fail the whole job; these are the core value.

---

## 6. Discovery — the SERP call

Separate task, not part of per-URL research.

```
fetch_serp(keyword, gl, hl):
  hit = Keyword.fresh(phrase_hash=sha256(keyword|gl|hl))
  if hit: return hit
  payload = serper.search(keyword, gl, hl, num=20)
  kw = db.upsert_keyword(payload)
  db.insert_serp_snapshot(kw.id, payload.results)
  return kw
```

Provider selection is a single switch:

```python
provider = settings.serp_provider  # "serper" | "dataforseo"
```

Both implement the same `SerpProvider` interface so swapping is one config change. DataForSEO is the fallback because it's slower but has better market/historical data.

---

## 7. JSON contract with Laravel

### 7.1 Job dispatch (Laravel → Python)

Redis list key: `researcher:queue:default`. Payload:

```json
{
  "type": "research_url",
  "job_id": "01HWXY...",
  "audit_id": 123,
  "website_id": 45,
  "url": "https://example.com/free-fire-names",
  "gl": "pk",
  "hl": "en",
  "niche_slug": "free-fire-names",
  "force_refresh": false,
  "dispatched_at": "2026-04-19T12:00:00Z"
}
```

### 7.2 Job completion (Python → Laravel)

Redis pub/sub channel: `researcher:events`. Laravel subscribes via a long-running `php artisan researcher:listen` worker (supervised).

```json
{
  "type": "research.done",
  "job_id": "01HWXY...",
  "audit_id": 123,
  "status": "ok",
  "entity_analysis_metric_id": 8891,
  "finished_at": "2026-04-19T12:00:04Z"
}
```

On failure:

```json
{
  "type": "research.failed",
  "job_id": "01HWXY...",
  "audit_id": 123,
  "status": "fetch_failed",
  "reason_code": "http_403",
  "reason_message": "Cloudflare challenge — need residential proxy",
  "finished_at": "2026-04-19T12:00:02Z"
}
```

Laravel reads the MySQL row directly afterwards — the pub/sub message is just a wake-up signal, not the payload.

---

## 8. Configuration

Single `.env` for the Researcher:

```dotenv
# Providers
SERPER_API_KEY=...
DATAFORSEO_LOGIN=...
DATAFORSEO_PASSWORD=...
SERP_PROVIDER=serper

# Redis — same instance Laravel uses
REDIS_URL=redis://10.0.0.5:6379/0
REDIS_QUEUE=researcher:queue:default
REDIS_EVENTS_CHANNEL=researcher:events

# MySQL — same instance Laravel uses
DATABASE_URL=mysql+pymysql://researcher:***@10.0.0.5/ebq

# S3 (Hetzner Object Storage)
S3_ENDPOINT=https://fsn1.your-objectstorage.com
S3_BUCKET=ebq-research
S3_ACCESS_KEY=...
S3_SECRET_KEY=...

# NLP
SPACY_MODEL=en_core_web_trf
EMBED_MODEL=sentence-transformers/all-mpnet-base-v2

# Runtime
WORKER_CONCURRENCY=4
PLAYWRIGHT_TIMEOUT_MS=30000
```

The Researcher has a **dedicated** MySQL user (`researcher`) with write access only to the caches (`keywords`, `serp_snapshots`, `entity_analysis_metrics`, `page_vectors`). Zero access to tenant tables like `users`, `websites`, `custom_page_audits`. Laravel is the only writer to tenant tables.

---

## 9. Deployment

Separate Hetzner VPS (CX22 or bigger — Playwright wants RAM).

### 9.1 systemd units

```
/etc/systemd/system/researcher-api.service      # FastAPI (health + replay endpoints)
/etc/systemd/system/researcher-worker@.service  # templated Celery worker (instance-per-core)
/etc/systemd/system/researcher-worker.target    # enables N workers at once
```

`researcher-worker@1.service`, `@2.service`, … up to `WORKER_CONCURRENCY`.

### 9.2 Bootstrap script

Follow the pattern of `scripts/ubuntu-vps-install.sh`:

- `scripts/researcher-install.sh` on the Researcher host.
- Installs Python 3.12, Playwright + browsers, creates a `researcher` Linux user, clones the repo, installs requirements, writes systemd units, enables them.
- Idempotent re-runs pick up code changes.

### 9.3 Observability

- One OpenTelemetry span per job, tagged with `audit_id`, `url_hash`, `cache_hit` boolean.
- Logs to `journalctl` (structured JSON), shipped to Grafana Loki.
- A "replay-this-job" FastAPI endpoint that takes a `job_id` and re-runs from S3 raw HTML — zero new crawls.

---

## 10. Performance budget

Not hard SLAs; these are the targets we monitor.

| Stage | p50 | p95 | p99 |
|---|---|---|---|
| Cache hit (return only) | 15 ms | 40 ms | 80 ms |
| Full pipeline (fresh URL) | 8 s | 18 s | 40 s |
| SERP fetch (Serper) | 700 ms | 2 s | 5 s |
| Vector cosine scan (per site, 5k URLs) | 90 ms | 300 ms | 800 ms |

If p95 on the full pipeline crosses 30 s sustained, add a second worker VPS before optimizing — Playwright parallelism is cheap.

---

## 11. What the Researcher does NOT do

- ❌ It does not call OpenAI / Claude / any LLM. That belongs in a separate `llm-summarizer` service if we ever add one.
- ❌ It does not render screenshots in the main pipeline. Screenshotting is a separate task triggered on demand.
- ❌ It does not decide rankings, weights, or recommendations. It only produces facts; the Manager interprets them.
- ❌ It does not read or write `users`, `websites`, `custom_page_audits`, or `page_audit_reports`. Those belong to Laravel.
- ❌ It does not expose any auth'd user-facing HTTP endpoint. Only Laravel + internal ops talk to it.

---

## 12. Phase 1 ship list

Week-sized chunks, in order:

1. Repo scaffold + `pyproject.toml` + Dockerfile + CI (pytest on PR).
2. `fetch.py` + Playwright wrapper + S3 upload; round-trip test using a saved fixture site.
3. `extract.py` + `nlp.py` with spaCy; golden-test fixtures.
4. `embed.py` + `page_vectors` write path.
5. Celery worker wired to Redis; Laravel dispatches via the same Redis connection.
6. `providers/serper.py` + `keywords` / `serp_snapshots` upsert.
7. Replay endpoint + ops runbook.
8. Systemd units + install script.

Phase 1 is done when Laravel can click "Audit" and see a filled `entity_analysis_metrics` row appear for any public URL within 30 seconds.
