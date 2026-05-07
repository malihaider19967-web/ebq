"""
Topic clustering across the crawled page set. TF-IDF over per-page
top-keyword strings + AgglomerativeClustering with cosine distance.

Why this and not LDA: page-level top keywords are short; LDA needs more
tokens per doc than we typically have. Agglomerative on TF-IDF is robust
on small corpora and keeps memory bounded for 1k-page scans.
"""

from __future__ import annotations

import math
from dataclasses import dataclass
from typing import Iterable

import numpy as np
from sklearn.cluster import AgglomerativeClustering
from sklearn.feature_extraction.text import TfidfVectorizer


@dataclass
class PageDoc:
    page_id: int                 # scratch SQLite row id
    keywords_text: str           # space-joined keyword phrases for this page
    representative_phrase: str   # used to seed cluster names


@dataclass
class Topic:
    name: str
    page_ids: list[int]
    top_phrases: list[str]


def cluster_topics(
    docs: Iterable[PageDoc],
    *,
    min_topic_size: int = 3,
    max_topics: int = 30,
) -> list[Topic]:
    docs = [d for d in docs if d.keywords_text.strip()]
    if len(docs) < min_topic_size:
        return []

    corpus = [d.keywords_text for d in docs]
    vec = TfidfVectorizer(min_df=1, max_df=0.9, ngram_range=(1, 2))
    X = vec.fit_transform(corpus)
    if X.shape[0] < 2:
        return []

    n_clusters = max(2, min(max_topics, len(docs) // min_topic_size))

    model = AgglomerativeClustering(
        n_clusters=n_clusters,
        metric="cosine",
        linkage="average",
    )
    labels = model.fit_predict(X.toarray())

    feature_names = vec.get_feature_names_out()
    topics: dict[int, dict] = {}
    for label, doc in zip(labels, docs):
        bucket = topics.setdefault(int(label), {"page_ids": [], "doc_ids": []})
        bucket["page_ids"].append(doc.page_id)
        bucket["doc_ids"].append(doc)

    out: list[Topic] = []
    for label, bucket in topics.items():
        if len(bucket["page_ids"]) < min_topic_size:
            continue

        # Top phrases per cluster: mean TF-IDF across the cluster's rows.
        rows = [docs.index(d) for d in bucket["doc_ids"]]
        cluster_matrix = X[rows].toarray()
        mean_scores = cluster_matrix.mean(axis=0)
        top_indices = np.argsort(mean_scores)[::-1][:5]
        top_phrases = [feature_names[i] for i in top_indices if mean_scores[i] > 0]

        if not top_phrases:
            continue

        out.append(
            Topic(
                name=top_phrases[0],
                page_ids=bucket["page_ids"],
                top_phrases=top_phrases,
            )
        )

    out.sort(key=lambda t: -len(t.page_ids))
    return out[:max_topics]
