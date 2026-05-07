"""
Scrapy item pipelines.

  ScratchSQLitePipeline   writes every PageItem + outlinks into the
                          per-run SQLite file the runner created.

  HeartbeatPipeline       updates competitor_scans.{progress,page_count,
                          last_heartbeat_at} every N items so the admin
                          UI can show live progress and the cancellation
                          poll has something to read.
"""

from __future__ import annotations

import json
import sqlite3
from pathlib import Path
from time import time
from typing import Optional

from ..storage.scratch import insert_outlinks, insert_page, open_scratch
from ..extractors.content import ExtractedPage


class ScratchSQLitePipeline:
    """Writes pages + outlinks into the scratch SQLite the runner opened."""

    def __init__(self) -> None:
        self.conn: Optional[sqlite3.Connection] = None
        self.scratch_db: Optional[str] = None

    @classmethod
    def from_crawler(cls, crawler):
        instance = cls()
        instance.scratch_db = crawler.spider.scratch_db
        return instance

    def open_spider(self, spider) -> None:
        if not self.scratch_db:
            raise RuntimeError("ScratchSQLitePipeline: scratch_db not set on spider.")
        self.conn = open_scratch(Path(self.scratch_db))

    def close_spider(self, spider) -> None:
        if self.conn is not None:
            self.conn.close()

    def process_item(self, item, spider):
        if self.conn is None:
            return item

        page = ExtractedPage(
            url=item["url"],
            url_hash=item["url_hash"],
            domain=item["domain"],
            title=item.get("title"),
            meta_description=item.get("meta_description"),
            headings=item.get("headings") or [],
            body_text=item.get("body_text") or "",
            word_count=item.get("word_count") or 0,
            status_code=item.get("status_code"),
            depth=item.get("depth") or 0,
            is_external=item.get("is_external") or False,
        )
        headings_json = json.dumps(page.headings, ensure_ascii=False)
        seed_coverage_json = json.dumps(item.get("seed_keyword_coverage") or {}, ensure_ascii=False)

        page_id = insert_page(self.conn, page, headings_json=headings_json, seed_coverage_json=seed_coverage_json)
        if page_id is not None:
            insert_outlinks(self.conn, from_page_id=page_id, edges=item.get("outlinks") or [])

        return item


class HeartbeatPipeline:
    """Heartbeat writer. Stamps competitor_scans every N items or every
    `interval_seconds`, whichever happens first. Only active when the
    spider was launched with a `scan_id` (admin-triggered run, not the
    ad-hoc CLI debug crawl)."""

    def __init__(self, interval_seconds: float = 5.0, item_interval: int = 10) -> None:
        self.interval_seconds = interval_seconds
        self.item_interval = item_interval
        self._last_emit_at: float = 0.0
        self._items_since: int = 0
        self._scan_id: Optional[int] = None

    @classmethod
    def from_crawler(cls, crawler):
        instance = cls()
        instance._scan_id = crawler.spider.scan_id
        return instance

    def open_spider(self, spider) -> None:
        if self._scan_id is None:
            return
        # Lazy import: only the admin path needs MySQL connectivity.
        from ..storage.progress import HeartbeatWriter
        self._writer = HeartbeatWriter(self._scan_id)
        self._writer.mark_running()

    def close_spider(self, spider) -> None:
        if self._scan_id is None:
            return
        # Final heartbeat (the runner.py marks status=done after flush).
        try:
            self._writer.flush_progress(current_url=None, force=True)
        except Exception:
            pass

    def process_item(self, item, spider):
        if self._scan_id is None:
            return item

        self._items_since += 1
        now = time()
        if (now - self._last_emit_at) >= self.interval_seconds or self._items_since >= self.item_interval:
            self._writer.flush_progress(current_url=item.get("url"), force=False)
            if self._writer.is_cancelling():
                spider.crawler.engine.close_spider(spider, "cancelled by admin")
            self._last_emit_at = now
            self._items_since = 0

        return item
