"""
Page content extraction. Pulls title, meta description, headings, body
text + word count from a raw HTML document.

Body text passes through `readability-lxml` to strip nav/footer/sidebar
boilerplate before tokenization, so YAKE / TF-IDF aren't dominated by
"home about contact privacy" repeated on every page.
"""

from __future__ import annotations

import hashlib
import re
from dataclasses import dataclass, field
from typing import Optional

from lxml import html as lxml_html
from readability import Document


_WS_RE = re.compile(r"\s+", re.UNICODE)
_WORD_RE = re.compile(r"\w+", re.UNICODE)


@dataclass
class ExtractedPage:
    url: str
    url_hash: str
    domain: str
    title: Optional[str]
    meta_description: Optional[str]
    headings: list[dict] = field(default_factory=list)
    body_text: str = ""
    word_count: int = 0
    status_code: Optional[int] = None
    depth: int = 0
    is_external: bool = False


def hash_url(url: str) -> str:
    """Match Laravel's WebsitePage::hashUrl semantics — lowercase + rtrim('/')
    + sha256. Same hash means the same row across stores."""
    normalized = url.strip().lower().rstrip("/")
    return hashlib.sha256(normalized.encode("utf-8")).hexdigest()


def extract(url: str, raw_html: str, *, depth: int = 0, is_external: bool = False, status_code: Optional[int] = None) -> ExtractedPage:
    if not raw_html:
        return ExtractedPage(
            url=url,
            url_hash=hash_url(url),
            domain=_domain_of(url),
            title=None,
            meta_description=None,
            depth=depth,
            is_external=is_external,
            status_code=status_code,
        )

    title, meta_description = _meta(raw_html)
    headings = _headings(raw_html)
    body_text = _body(raw_html)
    word_count = len(_WORD_RE.findall(body_text))

    return ExtractedPage(
        url=url,
        url_hash=hash_url(url),
        domain=_domain_of(url),
        title=title,
        meta_description=meta_description,
        headings=headings,
        body_text=body_text,
        word_count=word_count,
        status_code=status_code,
        depth=depth,
        is_external=is_external,
    )


def _meta(raw_html: str) -> tuple[Optional[str], Optional[str]]:
    try:
        tree = lxml_html.fromstring(raw_html)
    except Exception:
        return None, None

    title = None
    title_el = tree.find(".//title")
    if title_el is not None and title_el.text:
        title = _WS_RE.sub(" ", title_el.text).strip()[:512] or None

    meta_description = None
    for el in tree.xpath('//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="description"]'):
        content = el.get("content")
        if content:
            meta_description = _WS_RE.sub(" ", content).strip()[:1024] or None
            break

    return title, meta_description


def _headings(raw_html: str) -> list[dict]:
    try:
        tree = lxml_html.fromstring(raw_html)
    except Exception:
        return []

    out: list[dict] = []
    for level in (1, 2, 3):
        for el in tree.iter(f"h{level}"):
            text = " ".join(t.strip() for t in el.itertext() if t.strip())
            text = _WS_RE.sub(" ", text)[:512]
            if text:
                out.append({"level": level, "text": text})
    return out


def _body(raw_html: str) -> str:
    """readability-lxml extracts the main article block, stripping nav etc."""
    try:
        doc = Document(raw_html)
        summary_html = doc.summary(html_partial=True)
        tree = lxml_html.fromstring(summary_html)
        text = " ".join(t.strip() for t in tree.itertext() if t.strip())
        return _WS_RE.sub(" ", text).strip()
    except Exception:
        # Readability throws on tiny / malformed pages. Fall back to plain text.
        try:
            tree = lxml_html.fromstring(raw_html)
            text = " ".join(t.strip() for t in tree.itertext() if t.strip())
            return _WS_RE.sub(" ", text).strip()
        except Exception:
            return ""


def _domain_of(url: str) -> str:
    """Lowercase host with leading 'www.' stripped. Matches existing Laravel
    SerpResult.domain handling."""
    from urllib.parse import urlparse
    host = urlparse(url).netloc.lower()
    if host.startswith("www."):
        host = host[4:]
    return host[:255]
