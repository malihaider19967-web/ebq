"""
Per-page seed-keyword occurrence counter. Counts case-insensitive
word-boundary matches of each seed phrase across (title + headings + body).

Returns a `{seed: occurrences}` dict suitable for storing on
`competitor_pages.seed_keyword_coverage` JSON.
"""

from __future__ import annotations

import re
from typing import Iterable


def count_occurrences(seeds: Iterable[str], *, title: str | None, headings: list[dict] | None, body: str) -> dict[str, int]:
    cleaned = [s.strip().lower() for s in seeds if isinstance(s, str) and s.strip()]
    if not cleaned:
        return {}

    haystack = " ".join(filter(None, [
        (title or "").lower(),
        " ".join(h.get("text", "").lower() for h in (headings or [])),
        (body or "").lower(),
    ]))

    out: dict[str, int] = {}
    for seed in cleaned:
        # Word-boundary regex; escape so phrases with regex meta-chars are safe.
        pattern = re.compile(rf"\b{re.escape(seed)}\b")
        out[seed] = len(pattern.findall(haystack))
    return out


def coverage_density(coverage: dict[str, int], word_count: int) -> dict[str, float]:
    """Coverage divided by total words. Used to rank competitor pages per seed
    keyword (occurrences / word_count makes long pages compete fairly with short)."""
    if word_count <= 0:
        return {seed: 0.0 for seed in coverage}
    return {seed: round(count / word_count, 6) for seed, count in coverage.items()}
