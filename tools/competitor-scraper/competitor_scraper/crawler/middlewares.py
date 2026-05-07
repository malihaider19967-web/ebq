"""
Scrapy downloader middlewares.

  SeedDomainOnlyMiddleware         Drops every request that escapes the
                                   seed domain. Defense-in-depth — the
                                   spider already filters externals
                                   upstream, but a redirect can land us
                                   off-domain. Also enforces the global
                                   max_total_pages.

  SeedKeywordPriorityMiddleware    Bumps the priority of requests whose
                                   anchor text contains a seed token, so
                                   the crawl reaches seed-relevant pages
                                   first. Doesn't gate; just biases.

Note: cross-domain follow used to live here (per-domain cap, global cap).
Removed in favour of `AutoEnqueueOutlinksJob` queueing every external
to_domain as a separate research_target after the scan completes — each
domain becomes its own focused scan instead of grabbing a few random
pages mid-crawl.
"""

from __future__ import annotations

import logging
from collections import defaultdict
from typing import Optional
from urllib.parse import urlparse

import tldextract
from scrapy import signals
from scrapy.exceptions import IgnoreRequest


logger = logging.getLogger(__name__)


class SeedDomainOnlyMiddleware:
    """Stops the crawl from straying off the seed domain (e.g., via a
    redirect that lands on a different host). Also enforces the global
    max_total_pages cap."""

    def __init__(self) -> None:
        self.seed_domain: Optional[str] = None
        self.max_total_pages: int = 1000
        self._total: int = 0

    @classmethod
    def from_crawler(cls, crawler):
        instance = cls()
        crawler.signals.connect(instance.spider_opened, signal=signals.spider_opened)
        return instance

    def spider_opened(self, spider):
        self.seed_domain = getattr(spider, "seed_domain", None)
        self.max_total_pages = int(getattr(spider, "max_total_pages", 1000))

    def process_request(self, request, spider):
        if self._total >= self.max_total_pages:
            raise IgnoreRequest(f"global cap reached ({self.max_total_pages})")

        domain = self._registered_domain(request.url)
        if self.seed_domain and domain != self.seed_domain:
            raise IgnoreRequest(
                f"off-domain request to {domain} (only {self.seed_domain} crawled; "
                f"external domains queued as research_targets after scan)"
            )

        self._total += 1
        return None

    def _registered_domain(self, url: str) -> str:
        ext = tldextract.extract(url)
        if not ext.suffix:
            return ext.domain.lower()
        return f"{ext.domain}.{ext.suffix}".lower()


# Backwards-compatible alias so existing pipelines / settings strings
# pointing at the old name keep working until callers are updated.
PerDomainCapMiddleware = SeedDomainOnlyMiddleware


class SeedKeywordPriorityMiddleware:
    """Raise priority for requests whose anchor text or referrer page hints
    at containing a seed keyword. Falls back to default priority when no
    seed keywords are configured."""

    def __init__(self) -> None:
        self._seeds: list[str] = []

    @classmethod
    def from_crawler(cls, crawler):
        instance = cls()
        crawler.signals.connect(instance.spider_opened, signal=signals.spider_opened)
        return instance

    def spider_opened(self, spider):
        self._seeds = [s.lower() for s in getattr(spider, "seed_keywords", []) if s]

    def process_request(self, request, spider):
        if not self._seeds:
            return None

        anchor = (request.meta.get("anchor_text") or "").lower()
        if not anchor:
            return None

        for seed in self._seeds:
            if seed and seed in anchor:
                # Higher numbers => earlier scheduling in Scrapy.
                request.priority = max(request.priority, 100)
                break
        return None
