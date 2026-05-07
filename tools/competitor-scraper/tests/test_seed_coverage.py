from competitor_scraper.extractors.seed_coverage import count_occurrences, coverage_density


def test_counts_word_boundary_matches_case_insensitively():
    headings = [{"level": 1, "text": "Best Running Shoes"}, {"level": 2, "text": "Trail Running"}]
    body = "running shoes are great for running. The Running Tribe."
    out = count_occurrences(["running shoes", "marathon", "running"], title="Buy running shoes",
                            headings=headings, body=body)

    assert out["running shoes"] == 2  # heading + body
    assert out["marathon"] == 0
    assert out["running"] >= 4         # multiple occurrences


def test_density_divides_by_word_count():
    cov = {"running": 10, "shoes": 5}
    density = coverage_density(cov, word_count=1000)
    assert density["running"] == 0.01
    assert density["shoes"] == 0.005


def test_density_zero_when_word_count_is_zero():
    density = coverage_density({"running": 5}, word_count=0)
    assert density["running"] == 0.0


def test_handles_empty_seed_list():
    assert count_occurrences([], title="x", headings=[], body="x") == {}
