"""
Orchestrator. Two entry shapes:

  run_scan(scan_id)        loads competitor_scans row, runs the crawl,
                           extracts keywords + topics + seed coverage,
                           flushes to MySQL, marks status=done/failed.

  run_crawl_only(...)      ad-hoc debug. No MySQL. Returns the path of
                           the scratch SQLite the operator can inspect.
"""

from __future__ import annotations

import logging
import time
from pathlib import Path
from typing import Optional

logger = logging.getLogger(__name__)


def run_scan(scan_id: int) -> int:
    """Canonical run path.

    Returns 0 on success, non-zero on failure. Failure modes:
      1   could not load the scan row (id missing, DB unreachable)
      2   crawl crashed
      3   flush crashed
    The job wrapper reads stderr and stamps competitor_scans.error.
    """
    from .storage.progress import HeartbeatWriter
    from .storage.flush import flush_scan_results
    from .crawler.spider import CompetitorSpider

    try:
        from .storage.reflect import load_scan_record
        scan = load_scan_record(scan_id)
    except Exception as e:
        logger.exception("Could not load scan #%s", scan_id)
        return 1

    if scan is None:
        logger.error("Scan #%s not found in competitor_scans", scan_id)
        return 1

    writer = HeartbeatWriter(scan_id)
    writer.mark_running()

    output_dir = Path("./out")
    output_dir.mkdir(parents=True, exist_ok=True)
    scratch_db = output_dir / f"scratch-{scan_id}-{int(time.time())}.db"

    try:
        from scrapy.crawler import CrawlerProcess
        from scrapy.utils.project import get_project_settings

        process = CrawlerProcess(_scrapy_settings(scan, scratch_db))
        process.crawl(
            CompetitorSpider,
            seed_url=scan["seed_url"],
            max_total_pages=scan["caps"]["max_total_pages"],
            max_pages_per_external_domain=scan["caps"]["max_pages_per_external_domain"],
            max_depth=scan["caps"]["max_depth"],
            seed_keywords=scan["seed_keywords"],
            scan_id=scan_id,
            scratch_db=str(scratch_db),
        )
        process.start()
    except SystemExit:
        # Scrapy raises SystemExit on graceful shutdowns; treat as success.
        pass
    except Exception:
        logger.exception("Crawl failed for scan #%s", scan_id)
        writer.mark_failed("crawl raised; see worker logs")
        return 2

    try:
        flush_scan_results(scan_id=scan_id, scratch_db=scratch_db, seed_keywords=scan["seed_keywords"])
    except Exception:
        logger.exception("Flush failed for scan #%s", scan_id)
        writer.mark_failed("flush raised; see worker logs")
        return 3

    writer.mark_done()
    return 0


def run_crawl_only(seed: str, max_pages: int, output_dir: Path) -> Path:
    from scrapy.crawler import CrawlerProcess
    from .crawler.spider import CompetitorSpider
    from .settings import settings

    output_dir.mkdir(parents=True, exist_ok=True)
    scratch_db = output_dir / f"scratch-debug-{int(time.time())}.db"

    process = CrawlerProcess(_scrapy_settings_debug(scratch_db))
    process.crawl(
        CompetitorSpider,
        seed_url=seed,
        max_total_pages=max_pages,
        max_pages_per_external_domain=settings.max_pages_per_external_domain,
        max_depth=settings.max_depth,
        seed_keywords=[],
        scan_id=None,
        scratch_db=str(scratch_db),
    )
    process.start()
    return scratch_db


def _scrapy_settings(scan: dict, scratch_db: Path) -> dict:
    from .settings import settings
    return {
        "USER_AGENT": settings.user_agent,
        "ROBOTSTXT_OBEY": True,
        "ITEM_PIPELINES": {
            "competitor_scraper.crawler.pipelines.ScratchSQLitePipeline": 300,
            "competitor_scraper.crawler.pipelines.HeartbeatPipeline": 400,
        },
        "DOWNLOADER_MIDDLEWARES": {
            "competitor_scraper.crawler.middlewares.PerDomainCapMiddleware": 543,
            "competitor_scraper.crawler.middlewares.SeedKeywordPriorityMiddleware": 544,
        },
        "LOG_LEVEL": "INFO",
    }


def _scrapy_settings_debug(scratch_db: Path) -> dict:
    from .settings import settings
    return {
        "USER_AGENT": settings.user_agent,
        "ROBOTSTXT_OBEY": True,
        "ITEM_PIPELINES": {
            "competitor_scraper.crawler.pipelines.ScratchSQLitePipeline": 300,
        },
        "LOG_LEVEL": "INFO",
    }
