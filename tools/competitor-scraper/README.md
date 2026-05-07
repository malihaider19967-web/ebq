# competitor-scraper

EBQ competitor crawler. Admin triggers a scan from `/admin/research/competitor-scans`, Laravel dispatches `RunCompetitorScanJob`, that subprocess invokes this tool, the crawl writes progress + results back to the same MySQL database the Laravel app uses.

## Install

```powershell
cd tools/competitor-scraper
uv pip install -e ".[dev]"
# or:
pip install -e ".[dev]"
```

The DB connection is read from the **Laravel** `.env` two directories up (no separate Python `.env` for credentials). Override the location via `EBQ_LARAVEL_ROOT` if running from outside the repo.

Scraper-only knobs (caps, user-agent) live in `./.env` next to this README — copy `.env.example` and tune.

## Run

```powershell
# Triggered by Laravel (the canonical path):
python -m competitor_scraper run --scan-id 42

# Ad-hoc smoke test without going through the admin UI:
python -m competitor_scraper crawl https://example.com --max-pages 50 --output ./out/
```

## Test

```powershell
pytest
```

## Operational notes

- Always respects `robots.txt`. Polite by default — 1s delay, jittered.
- Cross-domain follow is bounded by per-external-domain page caps. The seed domain has no per-domain cap (only the global `max_total_pages`).
- Cancellation: the admin UI sets `competitor_scans.status='cancelling'`. The crawler polls that row at every heartbeat and exits gracefully.
- Crashes: a stale `last_heartbeat_at` (>60s old while `status='running'`) is the admin UI's signal that the worker died. Surfaced in the show page with a "mark failed" button.

## Architecture

See the project plan and `docs/research-section-plan.md` in the parent repo. Short version:

```
admin form → competitor_scans row → RunCompetitorScanJob → python -m competitor_scraper run
                ▲                                                        │
                └─ HeartbeatPipeline writes status/progress every 5s ────┘
```

## Layout

| Module | Responsibility |
|---|---|
| `cli.py` | typer entry. `run --scan-id` and `crawl <url>` |
| `runner.py` | Orchestrator: load scan row, run Scrapy, flush, mark done/failed |
| `crawler/spider.py` | `CompetitorSpider` — seed-domain + capped external follow |
| `crawler/middlewares.py` | Per-domain cap, seed-keyword frontier bias, cancellation poll |
| `crawler/pipelines.py` | Scratch-SQLite write + heartbeat |
| `extractors/content.py` | Title, meta, headings, body via readability |
| `extractors/keywords.py` | YAKE wrapper (short-tail + long-tail) |
| `extractors/seed_coverage.py` | Per-page seed-keyword occurrence counter |
| `extractors/topics.py` | TF-IDF + AgglomerativeClustering |
| `storage/laravel_env.py` | Locate + parse Laravel `.env` → SQLAlchemy URL |
| `storage/scratch.py` | Per-run SQLite (resumable, dedup) |
| `storage/reflect.py` | SQLAlchemy `automap_base` against MySQL |
| `storage/progress.py` | Heartbeat writer (status, current_url, page_count) |
| `storage/flush.py` | SQLite → MySQL upserts |
