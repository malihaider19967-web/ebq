"""
YAKE-based keyword extraction. One pass produces both short-tail and
long-tail by reading at different n-gram sizes. YAKE is statistical
(no model download) and language-aware via stoplists.
"""

from __future__ import annotations

from dataclasses import dataclass
from typing import Iterable

import yake


SHORT_TAIL_MAX_N = 2
LONG_TAIL_MIN_N = 3
LONG_TAIL_MAX_N = 7
TOP_PER_BUCKET = 20


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
    """Run YAKE twice — once for short n-grams, once for long. YAKE's
    de-duplication threshold is set so near-duplicate phrases don't crowd
    out the top-N (e.g. "running shoes" + "best running shoes" both stay)."""
    if not text or len(text.split()) < 5:
        return KeywordResult(short_tail=[], long_tail=[])

    short = yake.KeywordExtractor(
        lan=language,
        n=SHORT_TAIL_MAX_N,
        dedupLim=0.7,
        top=TOP_PER_BUCKET,
    ).extract_keywords(text)

    long = yake.KeywordExtractor(
        lan=language,
        n=LONG_TAIL_MAX_N,
        dedupLim=0.7,
        top=TOP_PER_BUCKET,
    ).extract_keywords(text)

    short_phrases = [
        ScoredPhrase(phrase=phrase, score=float(score))
        for phrase, score in short
        if 1 <= len(phrase.split()) <= SHORT_TAIL_MAX_N
    ]
    long_phrases = [
        ScoredPhrase(phrase=phrase, score=float(score))
        for phrase, score in long
        if LONG_TAIL_MIN_N <= len(phrase.split()) <= LONG_TAIL_MAX_N
    ]

    return KeywordResult(short_tail=short_phrases[:TOP_PER_BUCKET], long_tail=long_phrases[:TOP_PER_BUCKET])
