"""
SQLAlchemy reflection. Laravel migrations are the canonical schema —
Python never declares its own table classes.

`get_engine()` is cached; same engine is reused across the heartbeat
writer + flush pass.
"""

from __future__ import annotations

import json
from functools import lru_cache
from typing import Any, Optional

from sqlalchemy import Engine, MetaData, Table, create_engine
from sqlalchemy.orm import Session

from .laravel_env import build_sqlalchemy_url


_TABLES = (
    "competitor_scans",
    "competitor_pages",
    "competitor_outlinks",
    "competitor_topics",
    "competitor_topic_pages",
    "competitor_scan_keywords",
    "keywords",
)


@lru_cache(maxsize=1)
def get_engine() -> Engine:
    url = build_sqlalchemy_url()
    return create_engine(url, pool_pre_ping=True, future=True)


@lru_cache(maxsize=1)
def get_metadata() -> MetaData:
    md = MetaData()
    md.reflect(bind=get_engine(), only=_TABLES)
    return md


def table(name: str) -> Table:
    return get_metadata().tables[name]


def load_scan_record(scan_id: int) -> Optional[dict[str, Any]]:
    """Read the competitor_scans row needed to launch the crawl. Returns
    a plain dict so the runner has no SQLAlchemy dependency leaking into
    its arguments."""
    scans = table("competitor_scans")
    with get_engine().connect() as conn:
        row = conn.execute(scans.select().where(scans.c.id == scan_id)).mappings().first()

    if row is None:
        return None

    return {
        "id": row["id"],
        "seed_url": row["seed_url"],
        "seed_domain": row["seed_domain"],
        "seed_keywords": _decode_json(row.get("seed_keywords")) or [],
        "caps": _decode_json(row.get("caps")) or {
            "max_total_pages": 1000,
            "max_pages_per_external_domain": 5,
            "max_depth": 4,
        },
        "status": row["status"],
    }


def _decode_json(raw: Any) -> Any:
    if raw is None:
        return None
    if isinstance(raw, (dict, list)):
        return raw
    if isinstance(raw, (bytes, bytearray)):
        raw = raw.decode("utf-8", errors="replace")
    try:
        return json.loads(raw)
    except Exception:
        return None
