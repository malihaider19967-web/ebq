from competitor_scraper.extractors.topics import PageDoc, cluster_topics


def test_clusters_split_by_overlapping_keywords():
    docs = [
        PageDoc(page_id=1, keywords_text="running shoes cushioning trail running gear", representative_phrase="running shoes"),
        PageDoc(page_id=2, keywords_text="trail running shoes lugs grip outsole", representative_phrase="trail running"),
        PageDoc(page_id=3, keywords_text="best running shoes road runners", representative_phrase="best running shoes"),
        PageDoc(page_id=4, keywords_text="matcha latte recipe ceremonial grade tea", representative_phrase="matcha latte recipe"),
        PageDoc(page_id=5, keywords_text="matcha tea benefits caffeine antioxidant", representative_phrase="matcha tea benefits"),
        PageDoc(page_id=6, keywords_text="how to make matcha latte at home", representative_phrase="make matcha latte"),
    ]

    topics = cluster_topics(docs, min_topic_size=2, max_topics=10)

    assert len(topics) >= 2

    page_to_topic = {pid: t.name for t in topics for pid in t.page_ids}
    # Running pages should cluster together.
    assert page_to_topic.get(1) == page_to_topic.get(2) or page_to_topic.get(2) == page_to_topic.get(3)
    # Matcha pages should cluster together.
    assert page_to_topic.get(4) == page_to_topic.get(5) or page_to_topic.get(5) == page_to_topic.get(6)


def test_handles_too_few_docs():
    assert cluster_topics([], min_topic_size=3) == []
    assert cluster_topics([
        PageDoc(page_id=1, keywords_text="alone", representative_phrase="alone")
    ], min_topic_size=3) == []
