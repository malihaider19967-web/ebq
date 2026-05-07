"""
CompetitorSpider. Walks a seed domain, follows internal links by default,
and (when external follow is enabled by middleware caps in Phase C)
yields requests to external domains. Each fetched page produces a
`PageItem` consumed by the scratch-SQLite pipeline.
"""

from __future__ import annotations

import json
from typing import Any, Iterable, Optional
from urllib.parse import urljoin, urlparse

import scrapy
import tldextract

from ..extractors.content import ExtractedPage, extract, hash_url


class PageItem(scrapy.Item):
    url = scrapy.Field()
    url_hash = scrapy.Field()
    domain = scrapy.Field()
    title = scrapy.Field()
    meta_description = scrapy.Field()
    headings = scrapy.Field()  # list of {"level": int, "text": str}
    word_count = scrapy.Field()
    body_text = scrapy.Field()
    status_code = scrapy.Field()
    depth = scrapy.Field()
    is_external = scrapy.Field()
    outlinks = scrapy.Field()  # list of (to_url, to_url_hash, to_domain, anchor_text, is_external)
    seed_keyword_coverage = scrapy.Field()  # set by SeedCoveragePipeline


class CompetitorSpider(scrapy.Spider):
    name = "competitor"

    custom_settings: dict[str, Any] = {
        "ROBOTSTXT_OBEY": True,
        "RETRY_TIMES": 1,
        "DOWNLOAD_TIMEOUT": 30,
        "REDIRECT_MAX_TIMES": 5,
        "AUTOTHROTTLE_ENABLED": True,
        "AUTOTHROTTLE_START_DELAY": 1.0,
        "AUTOTHROTTLE_MAX_DELAY": 10.0,
        "AUTOTHROTTLE_TARGET_CONCURRENCY": 2.0,
    }

    def __init__(
        self,
        seed_url: str,
        max_total_pages: int = 1000,
        max_pages_per_external_domain: int = 5,
        max_depth: int = 4,
        seed_keywords: Optional[list[str]] = None,
        scan_id: Optional[int] = None,
        scratch_db: Optional[str] = None,
        request_delay_seconds: float = 1.0,
        user_agent: str = "EBQ-CompetitorScraper/1.0 (+https://ebq.io/scraper-info)",
        **kwargs: Any,
    ) -> None:
        super().__init__(**kwargs)
        self.seed_url = seed_url
        self.seed_domain = self._registered_domain(seed_url)
        self.max_total_pages = int(max_total_pages)
        self.max_pages_per_external_domain = int(max_pages_per_external_domain)
        self.max_depth = int(max_depth)
        self.seed_keywords = list(seed_keywords or [])
        self.scan_id = scan_id
        self.scratch_db = scratch_db

        # Scrapy reads these off the spider after instantiation.
        self.custom_settings = dict(self.custom_settings)
        self.custom_settings["DEPTH_LIMIT"] = self.max_depth
        self.custom_settings["CLOSESPIDER_PAGECOUNT"] = self.max_total_pages
        self.custom_settings["DOWNLOAD_DELAY"] = float(request_delay_seconds)
        self.custom_settings["USER_AGENT"] = user_agent

    def start_requests(self) -> Iterable[scrapy.Request]:
        # Always seed the domain root in addition to the user-supplied
        # URL. If the operator pasted a deep product / article URL,
        # starting only there often dead-ends — robots.txt may forbid
        # it, the page may be JS-rendered with empty static HTML, or it
        # may simply have few internal links. The root usually has nav
        # / sitemap-style links that fan out across the site.
        parsed = urlparse(self.seed_url)
        root = f"{parsed.scheme or 'https'}://{parsed.netloc}/"
        seen: set[str] = set()

        if root and root not in seen:
            seen.add(root)
            yield scrapy.Request(
                root,
                callback=self.parse,
                cb_kwargs={"depth": 0},
                priority=110,
                dont_filter=False,
            )

        if self.seed_url not in seen:
            yield scrapy.Request(
                self.seed_url,
                callback=self.parse,
                cb_kwargs={"depth": 0},
                priority=100,
                dont_filter=False,
            )

    def closed(self, reason: str) -> None:
        """Final crawl stats — captured here and pushed to MySQL so the
        admin UI can diagnose 0-page scans (robots blocks, HTTP errors,
        retry storms) without reading worker logs."""
        if self.scan_id is None:
            return
        try:
            stats = self.crawler.stats.get_stats() if hasattr(self, "crawler") else {}
            from ..storage.progress import HeartbeatWriter
            HeartbeatWriter(self.scan_id).write_final_stats(stats, reason)
        except Exception:
            pass

    def parse(self, response: scrapy.http.Response, depth: int = 0):
        body = response.text or ""
        is_external = self._registered_domain(response.url) != self.seed_domain
        extracted = extract(
            response.url,
            body,
            depth=depth,
            is_external=is_external,
            status_code=response.status,
        )

        outlinks: list[tuple[str, str, str, str, bool]] = []
        try:
            from lxml import html as lxml_html
            tree = lxml_html.fromstring(body)
            for a in tree.iter("a"):
                href = a.get("href")
                if not href:
                    continue
                absolute = urljoin(response.url, href.strip())
                if not absolute.startswith(("http://", "https://")):
                    continue
                anchor_text = " ".join(t.strip() for t in a.itertext() if t.strip())[:512]
                target_external = self._registered_domain(absolute) != self.seed_domain
                outlinks.append((
                    absolute,
                    hash_url(absolute),
                    self._domain_of(absolute),
                    anchor_text,
                    target_external,
                ))
        except Exception:
            pass

        item = PageItem()
        item["url"] = extracted.url
        item["url_hash"] = extracted.url_hash
        item["domain"] = extracted.domain
        item["title"] = extracted.title
        item["meta_description"] = extracted.meta_description
        item["headings"] = extracted.headings
        item["word_count"] = extracted.word_count
        item["body_text"] = extracted.body_text
        item["status_code"] = extracted.status_code
        item["depth"] = extracted.depth
        item["is_external"] = extracted.is_external
        item["outlinks"] = outlinks
        yield item

        if depth >= self.max_depth:
            return

        for to_url, _hash, _to_domain, anchor, target_external in outlinks:
            request = scrapy.Request(
                to_url,
                callback=self.parse,
                cb_kwargs={"depth": depth + 1},
                meta={
                    "anchor_text": anchor,
                    "is_external_target": target_external,
                    "seed_keywords": self.seed_keywords,
                },
            )
            yield request

    def _registered_domain(self, url: str) -> str:
        ext = tldextract.extract(url)
        if not ext.suffix:
            return ext.domain.lower()
        return f"{ext.domain}.{ext.suffix}".lower()

    def _domain_of(self, url: str) -> str:
        host = urlparse(url).netloc.lower()
        if host.startswith("www."):
            host = host[4:]
        return host[:255]
