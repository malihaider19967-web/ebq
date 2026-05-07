"""
YAKE-based keyword extraction. One pass produces both short-tail and
long-tail by reading at different n-gram sizes. YAKE is statistical
(no model download) and language-aware via stoplists.
"""

from __future__ import annotations

from dataclasses import dataclass
from typing import Iterable

import yake


SHORT_TAIL_MIN_N = 2
SHORT_TAIL_MAX_N = 3
LONG_TAIL_MIN_N = 4
LONG_TAIL_MAX_N = 7
TOP_PER_BUCKET = 25


@dataclass(frozen=True)
class ScoredPhrase:
    phrase: str
    score: float  # YAKE: lower is better; we invert when ranking outward.


@dataclass(frozen=True)
class KeywordResult:
    short_tail: list[ScoredPhrase]
    long_tail: list[ScoredPhrase]

    def all_phrases(self) -> list[str]:
        return [p.phrase for p in self.short_tail] + [p.phrase for p in self.long_tail]


def extract_keywords(text: str, *, language: str = "en") -> KeywordResult:
    """Single YAKE pass at n=LONG_TAIL_MAX_N, then partition the results
    by phrase length. Single-word phrases are dropped — they're too
    generic to be useful for SEO research and crowd out genuine
    multi-word terms.

    YAKE's de-duplication threshold is loose so near-duplicate phrases
    survive (e.g. both "running shoes" and "best running shoes" stay) —
    they convey different intent."""
    if not text or len(text.split()) < 5:
        return KeywordResult(short_tail=[], long_tail=[])

    raw = yake.KeywordExtractor(
        lan=language,
        n=LONG_TAIL_MAX_N,
        dedupLim=0.7,
        top=TOP_PER_BUCKET * 4,  # over-fetch then partition
    ).extract_keywords(text)

    short: list[ScoredPhrase] = []
    long: list[ScoredPhrase] = []

    for phrase, score in raw:
        words = len(phrase.split())
        if SHORT_TAIL_MIN_N <= words <= SHORT_TAIL_MAX_N:
            short.append(ScoredPhrase(phrase=phrase, score=float(score)))
        elif LONG_TAIL_MIN_N <= words <= LONG_TAIL_MAX_N:
            long.append(ScoredPhrase(phrase=phrase, score=float(score)))
        # 1-word phrases and >7-word phrases discarded.

    return KeywordResult(
        short_tail=short[:TOP_PER_BUCKET],
        long_tail=long[:TOP_PER_BUCKET],
    )
