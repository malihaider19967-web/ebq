"""Pure-function tests for the content extractor."""

from __future__ import annotations

from competitor_scraper.extractors.content import extract, hash_url


HTML_FIXTURE = """
<!doctype html>
<html><head>
  <title>  Best Running Shoes  </title>
  <meta name="DESCRIPTION" content="A guide to the best running shoes for trail and road running in 2026.">
</head>
<body>
  <header><nav>Home About Contact Privacy</nav></header>
  <main>
    <h1>Best Running Shoes</h1>
    <h2>Trail running shoes</h2>
    <h2>Road running shoes</h2>
    <p>The best trail running shoes balance grip, cushioning and durability.
       For road runners, prioritise lightweight cushioning and a smooth heel-to-toe transition.</p>
  </main>
  <footer>Copyright</footer>
</body></html>
"""


def test_hash_url_is_deterministic_and_normalised():
    assert hash_url("https://Example.com/path") == hash_url("https://example.com/PATH/")


def test_extract_pulls_title_meta_headings_body():
    page = extract("https://example.com/running-shoes", HTML_FIXTURE, depth=1, is_external=False, status_code=200)

    assert page.title == "Best Running Shoes"
    assert page.meta_description.startswith("A guide to the best running shoes")
    assert page.domain == "example.com"
    assert page.depth == 1
    assert page.is_external is False
    assert page.status_code == 200

    levels = [h["level"] for h in page.headings]
    assert 1 in levels and 2 in levels
    assert any("Trail running" in h["text"] for h in page.headings)

    # Body should NOT include "Copyright" / nav boilerplate after readability strip.
    assert "trail running shoes balance grip" in page.body_text.lower()
    assert page.word_count > 5


def test_extract_handles_empty_input():
    page = extract("https://empty.test", "", depth=0)
    assert page.title is None
    assert page.body_text == ""
    assert page.word_count == 0
