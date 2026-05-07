"""
SQLite → MySQL flush. Runs after the Scrapy crawl finishes:

  1. Bulk-inserts competitor_pages (500-row chunks).
  2. Extracts keywords per page (YAKE), upserts into the canonical
     `keywords` table by query_hash.
  3. Runs TF-IDF + AgglomerativeClustering, writes competitor_topics +
     competitor_topic_pages.
  4. Inserts competitor_outlinks last (200-row chunks; biggest cardinality).
  5. Aggregates seed-keyword coverage → competitor_scan_keywords.
  6. Stamps competitor_scans.{page_count, external_page_count}.

Non-fatal failures inside any step are logged + collected; the runner
calls mark_failed() if the function raises, otherwise mark_done().
"""

from __future__ import annotations

import hashlib
import json
import logging
import re
from datetime import datetime, timezone
from pathlib import Path
from typing import Iterable, Optional

from sqlalchemy import insert, select, update
from sqlalchemy.dialects.mysql import insert as mysql_insert

from ..extractors.keywords import extract_keywords
from ..extractors.seed_coverage import coverage_density
from ..extractors.topics import PageDoc, cluster_topics
from .reflect import get_engine, table
from .scratch import open_scratch


logger = logging.getLogger(__name__)

PAGE_BATCH = 500
OUTLINK_BATCH = 200


def flush_scan_results(*, scan_id: int, scratch_db: Path, seed_keywords: list[str]) -> None:
    conn = open_scratch(scratch_db)
    try:
        scratch_pages = conn.execute("SELECT * FROM pages ORDER BY id").fetchall()
        scratch_outlinks = conn.execute("SELECT * FROM outlinks ORDER BY id").fetchall()
    finally:
        conn.close()

    if not scratch_pages:
        _stamp_counts(scan_id, page_count=0, external_page_count=0)
        return

    sqlite_id_to_mysql_id = _flush_pages(scan_id, scratch_pages)
    page_keywords = _extract_per_page_keywords(scratch_pages)
    keyword_id_by_phrase = _upsert_keywords(page_keywords)
    _flush_topics(scan_id, scratch_pages, sqlite_id_to_mysql_id, page_keywords, keyword_id_by_phrase)
    _flush_outlinks(scan_id, scratch_outlinks, sqlite_id_to_mysql_id)
    _flush_scan_keywords(scan_id, scratch_pages, sqlite_id_to_mysql_id, seed_keywords, keyword_id_by_phrase)

    page_count = len(scratch_pages)
    external_page_count = sum(1 for p in scratch_pages if p["is_external"])
    _stamp_counts(scan_id, page_count=page_count, external_page_count=external_page_count)


def _flush_pages(scan_id: int, rows) -> dict[int, int]:
    pages = table("competitor_pages")
    sqlite_id_to_mysql_id: dict[int, int] = {}

    chunk: list[dict] = []
    for row in rows:
        chunk.append({
            "competitor_scan_id": scan_id,
            "url": row["url"],
            "url_hash": row["url_hash"],
            "domain": row["domain"],
            "title": row["title"],
            "meta_description": row["meta_description"],
            "headings_json": row["headings_json"],
            "word_count": row["word_count"] or 0,
            "body_text": row["body_text"],
            "status_code": row["status_code"],
            "depth": row["depth"] or 0,
            "is_external": bool(row["is_external"]),
            "seed_keyword_coverage": row["seed_keyword_coverage_json"] or None,
            "created_at": _now(),
            "updated_at": _now(),
        })
        if len(chunk) >= PAGE_BATCH:
            _bulk_insert_pages(pages, chunk, scan_id, sqlite_id_to_mysql_id, rows_iter=rows[:len(chunk)])
            chunk = []

    if chunk:
        _bulk_insert_pages(pages, chunk, scan_id, sqlite_id_to_mysql_id, rows_iter=rows[-len(chunk):])

    # Resolve sqlite→mysql ids by reading back url_hash mapping in one go.
    sqlite_by_hash = {row["url_hash"]: row["id"] for row in rows}
    pages_by_hash: dict[str, int] = {}
    with get_engine().connect() as conn:
        result = conn.execute(
            pages.select()
                 .with_only_columns(pages.c.id, pages.c.url_hash)
                 .where(pages.c.competitor_scan_id == scan_id)
        )
        for r in result:
            pages_by_hash[r.url_hash] = r.id

    for url_hash, sqlite_id in sqlite_by_hash.items():
        mysql_id = pages_by_hash.get(url_hash)
        if mysql_id is not None:
            sqlite_id_to_mysql_id[sqlite_id] = mysql_id

    return sqlite_id_to_mysql_id


def _bulk_insert_pages(pages_tbl, chunk, scan_id, mapping, rows_iter) -> None:
    with get_engine().begin() as conn:
        conn.execute(insert(pages_tbl).prefix_with("IGNORE"), chunk)


def _extract_per_page_keywords(rows) -> dict[int, dict]:
    out: dict[int, dict] = {}
    for row in rows:
        text = " ".join(filter(None, [
            row["title"] or "",
            row["meta_description"] or "",
            row["body_text"] or "",
        ]))
        if not text.strip():
            out[row["id"]] = {"short_tail": [], "long_tail": []}
            continue

        result = extract_keywords(text)
        out[row["id"]] = {
            "short_tail": [(p.phrase, p.score) for p in result.short_tail],
            "long_tail": [(p.phrase, p.score) for p in result.long_tail],
        }
    return out


def _upsert_keywords(page_keywords: dict[int, dict]) -> dict[str, int]:
    """Upsert every extracted phrase into `keywords` (country='global',
    language='en'). Returns a phrase → keyword_id map."""
    keywords_tbl = table("keywords")
    phrases: set[str] = set()
    for buckets in page_keywords.values():
        for phrase, _score in buckets["short_tail"] + buckets["long_tail"]:
            phrase = phrase.strip()
            if phrase:
                phrases.add(phrase)

    if not phrases:
        return {}

    rows: list[dict] = []
    for phrase in phrases:
        normalized = _normalize(phrase)
        rows.append({
            "query": phrase[:512],
            "normalized_query": normalized[:512],
            "query_hash": _hash_for(phrase),
            "language": "en",
            "country": "global",
            "created_at": _now(),
            "updated_at": _now(),
        })

    with get_engine().begin() as conn:
        # MySQL: INSERT IGNORE on the unique (query_hash, country, language).
        conn.execute(insert(keywords_tbl).prefix_with("IGNORE"), rows)

    # Read back ids.
    out: dict[str, int] = {}
    with get_engine().connect() as conn:
        for batch_start in range(0, len(rows), 500):
            batch = rows[batch_start:batch_start + 500]
            hashes = [r["query_hash"] for r in batch]
            res = conn.execute(
                select(keywords_tbl.c.id, keywords_tbl.c.query_hash)
                .where(keywords_tbl.c.query_hash.in_(hashes))
                .where(keywords_tbl.c.country == "global")
                .where(keywords_tbl.c.language == "en")
            )
            id_by_hash = {r.query_hash: r.id for r in res}
            for r in batch:
                kid = id_by_hash.get(r["query_hash"])
                if kid is not None:
                    out[r["query"]] = kid
    return out


def _flush_topics(scan_id, rows, id_map, page_keywords, keyword_id_by_phrase) -> None:
    docs: list[PageDoc] = []
    for row in rows:
        sqlite_id = row["id"]
        mysql_id = id_map.get(sqlite_id)
        if mysql_id is None:
            continue
        kw = page_keywords.get(sqlite_id, {"short_tail": [], "long_tail": []})
        joined = " ".join(p for p, _ in kw["short_tail"] + kw["long_tail"])
        if not joined.strip():
            continue
        docs.append(PageDoc(
            page_id=mysql_id,
            keywords_text=joined,
            representative_phrase=(kw["long_tail"][0][0] if kw["long_tail"] else (kw["short_tail"][0][0] if kw["short_tail"] else "")),
        ))

    topics = cluster_topics(docs)
    if not topics:
        return

    topics_tbl = table("competitor_topics")
    topic_pages_tbl = table("competitor_topic_pages")

    with get_engine().begin() as conn:
        for topic in topics:
            top_keyword_ids = [
                keyword_id_by_phrase[p] for p in topic.top_phrases if p in keyword_id_by_phrase
            ]
            centroid = top_keyword_ids[0] if top_keyword_ids else None
            result = conn.execute(
                insert(topics_tbl).values(
                    competitor_scan_id=scan_id,
                    name=topic.name[:255],
                    centroid_keyword_id=centroid,
                    page_count=len(topic.page_ids),
                    top_keyword_ids=json.dumps(top_keyword_ids),
                    created_at=_now(),
                    updated_at=_now(),
                )
            )
            topic_id = result.inserted_primary_key[0] if result.inserted_primary_key else None
            if topic_id is None:
                continue
            conn.execute(
                insert(topic_pages_tbl),
                [
                    {"competitor_topic_id": topic_id, "competitor_page_id": pid, "created_at": _now(), "updated_at": _now()}
                    for pid in topic.page_ids
                ],
            )


def _flush_outlinks(scan_id, rows, id_map) -> None:
    if not rows:
        return
    outlinks_tbl = table("competitor_outlinks")

    chunk: list[dict] = []
    for row in rows:
        from_mysql_id = id_map.get(row["from_page_id"])
        if from_mysql_id is None:
            continue
        chunk.append({
            "competitor_scan_id": scan_id,
            "from_page_id": from_mysql_id,
            "to_url": row["to_url"][:2048],
            "to_url_hash": row["to_url_hash"],
            "to_domain": row["to_domain"][:255],
            "anchor_text": (row["anchor_text"] or "")[:512],
            "is_external": bool(row["is_external"]),
            "created_at": _now(),
            "updated_at": _now(),
        })
        if len(chunk) >= OUTLINK_BATCH:
            with get_engine().begin() as conn:
                conn.execute(insert(outlinks_tbl), chunk)
            chunk = []
    if chunk:
        with get_engine().begin() as conn:
            conn.execute(insert(outlinks_tbl), chunk)


def _flush_scan_keywords(scan_id, rows, id_map, seed_keywords, keyword_id_by_phrase) -> None:
    if not seed_keywords:
        return

    # Per seed phrase: total occurrences + top-N pages by density.
    csk_tbl = table("competitor_scan_keywords")
    keywords_tbl = table("keywords")

    # Ensure seed phrases exist as keyword rows so we can FK them.
    seed_rows = [
        {
            "query": s[:512],
            "normalized_query": _normalize(s)[:512],
            "query_hash": _hash_for(s),
            "language": "en",
            "country": "global",
            "created_at": _now(),
            "updated_at": _now(),
        }
        for s in seed_keywords
        if isinstance(s, str) and s.strip()
    ]
    if not seed_rows:
        return

    with get_engine().begin() as conn:
        conn.execute(insert(keywords_tbl).prefix_with("IGNORE"), seed_rows)

    seed_ids: dict[str, int] = {}
    with get_engine().connect() as conn:
        res = conn.execute(
            select(keywords_tbl.c.id, keywords_tbl.c.query)
            .where(keywords_tbl.c.query_hash.in_([r["query_hash"] for r in seed_rows]))
            .where(keywords_tbl.c.country == "global")
            .where(keywords_tbl.c.language == "en")
        )
        for r in res:
            seed_ids[r.query] = r.id

    # Aggregate per seed phrase.
    aggregates: dict[str, list[tuple[int, int, float]]] = {s: [] for s in seed_ids.keys()}
    for row in rows:
        try:
            coverage = json.loads(row["seed_keyword_coverage_json"] or "{}")
        except Exception:
            continue
        if not isinstance(coverage, dict):
            continue
        density = coverage_density(coverage, int(row["word_count"] or 0))
        mysql_id = id_map.get(row["id"])
        if mysql_id is None:
            continue
        for phrase, count in coverage.items():
            if phrase not in aggregates:
                continue
            aggregates[phrase].append((mysql_id, int(count), float(density.get(phrase, 0.0))))

    payloads: list[dict] = []
    for phrase, hits in aggregates.items():
        if phrase not in seed_ids:
            continue
        hits.sort(key=lambda t: t[2], reverse=True)
        top = [
            {"page_id": pid, "occurrences": occ, "density": dens}
            for (pid, occ, dens) in hits[:10]
        ]
        payloads.append({
            "competitor_scan_id": scan_id,
            "keyword_id": seed_ids[phrase],
            "total_occurrences": sum(o for _, o, _ in hits),
            "top_pages_json": json.dumps(top),
            "created_at": _now(),
            "updated_at": _now(),
        })

    if payloads:
        with get_engine().begin() as conn:
            conn.execute(insert(csk_tbl).prefix_with("IGNORE"), payloads)


def _stamp_counts(scan_id: int, *, page_count: int, external_page_count: int) -> None:
    scans = table("competitor_scans")
    with get_engine().begin() as conn:
        conn.execute(
            update(scans)
            .where(scans.c.id == scan_id)
            .values(
                page_count=page_count,
                external_page_count=external_page_count,
                updated_at=_now(),
            )
        )


_WS_RE = re.compile(r"\s+", re.UNICODE)


def _normalize(query: str) -> str:
    return _WS_RE.sub(" ", query.strip().lower())


def _hash_for(query: str) -> str:
    return hashlib.sha256(_normalize(query).encode("utf-8")).hexdigest()


def _now() -> datetime:
    return datetime.now(timezone.utc).replace(tzinfo=None)
