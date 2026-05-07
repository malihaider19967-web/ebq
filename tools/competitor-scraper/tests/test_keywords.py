"""YAKE wrapper tests. We assert structure, not exact YAKE output (which
varies with the YAKE version)."""

from competitor_scraper.extractors.keywords import (
    LONG_TAIL_MAX_N,
    LONG_TAIL_MIN_N,
    SHORT_TAIL_MAX_N,
    extract_keywords,
)


SAMPLE_TEXT = """
The best running shoes balance cushioning, grip, and weight. Trail
running shoes prioritise lugged outsoles for muddy terrain, while
road running shoes favour smooth midsoles for repeatable footstrikes.
A long-distance trail runner often picks shoes with a rock plate and
gusseted tongue. Marathon runners prefer carbon-plated racing shoes.
""" * 3  # YAKE prefers more text to settle


def test_extract_returns_short_and_long_tail_buckets():
    result = extract_keywords(SAMPLE_TEXT)

    for p in result.short_tail:
        assert 1 <= len(p.phrase.split()) <= SHORT_TAIL_MAX_N

    for p in result.long_tail:
        assert LONG_TAIL_MIN_N <= len(p.phrase.split()) <= LONG_TAIL_MAX_N

    # Both buckets should contain something for a non-trivial corpus.
    assert len(result.short_tail) > 0
    assert len(result.all_phrases()) >= len(result.short_tail)


def test_extract_returns_empty_for_thin_text():
    result = extract_keywords("hi")
    assert result.short_tail == []
    assert result.long_tail == []
