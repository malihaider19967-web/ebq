"""
Per-run SQLite scratch DB. Holds the entire raw crawl (pages + outlinks)
so a long crawl is resumable, dedup-safe, and isolated from MySQL write
pressure. Flushed to MySQL by `storage/flush.py` after the crawl
finishes.
"""

from __future__ import annotations

import sqlite3
import time
from contextlib import contextmanager
from dataclasses import asdict
from pathlib import Path
from typing import Iterable, Optional

from ..extractors.content import ExtractedPage


SCHEMA = """
CREATE TABLE IF NOT EXISTS scan_meta (
    key TEXT PRIMARY KEY,
    value TEXT
);

CREATE TABLE IF NOT EXISTS pages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    url TEXT NOT NULL,
    url_hash TEXT NOT NULL UNIQUE,
    domain TEXT NOT NULL,
    title TEXT,
    meta_description TEXT,
    headings_json TEXT,
    word_count INTEGER DEFAULT 0,
    body_text TEXT,
    status_code INTEGER,
    depth INTEGER DEFAULT 0,
    is_external INTEGER NOT NULL DEFAULT 0,
    seed_keyword_coverage_json TEXT,
    fetched_at REAL
);

CREATE INDEX IF NOT EXISTS idx_pages_domain ON pages(domain);
CREATE INDEX IF NOT EXISTS idx_pages_external ON pages(is_external);

CREATE TABLE IF NOT EXISTS outlinks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    from_page_id INTEGER NOT NULL,
    to_url TEXT NOT NULL,
    to_url_hash TEXT NOT NULL,
    to_domain TEXT NOT NULL,
    anchor_text TEXT,
    is_external INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (from_page_id) REFERENCES pages(id)
);

CREATE INDEX IF NOT EXISTS idx_outlinks_from ON outlinks(from_page_id);
CREATE INDEX IF NOT EXISTS idx_outlinks_to_domain ON outlinks(to_domain);
"""


def open_scratch(db_path: Path) -> sqlite3.Connection:
    db_path.parent.mkdir(parents=True, exist_ok=True)
    conn = sqlite3.connect(str(db_path), isolation_level=None, check_same_thread=False)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA journal_mode=WAL")
    conn.execute("PRAGMA synchronous=NORMAL")
    conn.executescript(SCHEMA)
    return conn


def set_meta(conn: sqlite3.Connection, key: str, value: str) -> None:
    conn.execute(
        "INSERT INTO scan_meta(key, value) VALUES(?, ?) ON CONFLICT(key) DO UPDATE SET value=excluded.value",
        (key, value),
    )


def get_meta(conn: sqlite3.Connection, key: str, default: Optional[str] = None) -> Optional[str]:
    row = conn.execute("SELECT value FROM scan_meta WHERE key=?", (key,)).fetchone()
    return row["value"] if row else default


def insert_page(conn: sqlite3.Connection, page: ExtractedPage, *, headings_json: str, seed_coverage_json: str = "") -> Optional[int]:
    """Insert if new (by url_hash). Returns new row id, or None on duplicate."""
    try:
        cur = conn.execute(
            """
            INSERT INTO pages
              (url, url_hash, domain, title, meta_description, headings_json,
               word_count, body_text, status_code, depth, is_external,
               seed_keyword_coverage_json, fetched_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """,
            (
                page.url,
                page.url_hash,
                page.domain,
                page.title,
                page.meta_description,
                headings_json,
                page.word_count,
                page.body_text,
                page.status_code,
                page.depth,
                1 if page.is_external else 0,
                seed_coverage_json,
                time.time(),
            ),
        )
        return cur.lastrowid
    except sqlite3.IntegrityError:
        return None


def insert_outlinks(
    conn: sqlite3.Connection,
    *,
    from_page_id: int,
    edges: Iterable[tuple[str, str, str, str, bool]],
) -> int:
    """edges: (to_url, to_url_hash, to_domain, anchor_text, is_external)."""
    rows = list(edges)
    if not rows:
        return 0
    conn.executemany(
        """
        INSERT INTO outlinks (from_page_id, to_url, to_url_hash, to_domain, anchor_text, is_external)
        VALUES (?, ?, ?, ?, ?, ?)
        """,
        [(from_page_id, u, h, d, a or "", 1 if e else 0) for (u, h, d, a, e) in rows],
    )
    return len(rows)


def page_count(conn: sqlite3.Connection, *, external: Optional[bool] = None) -> int:
    if external is None:
        return conn.execute("SELECT COUNT(*) AS c FROM pages").fetchone()["c"]
    return conn.execute(
        "SELECT COUNT(*) AS c FROM pages WHERE is_external = ?",
        (1 if external else 0,),
    ).fetchone()["c"]


@contextmanager
def transaction(conn: sqlite3.Connection):
    conn.execute("BEGIN")
    try:
        yield conn
        conn.execute("COMMIT")
    except Exception:
        conn.execute("ROLLBACK")
        raise
