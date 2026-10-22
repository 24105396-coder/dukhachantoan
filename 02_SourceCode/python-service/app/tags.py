"""Gợi ý thẻ: cosine với mô tả thẻ + bỏ phiếu thẻ của các tài liệu giống nhất."""
from __future__ import annotations

from collections import defaultdict
from dataclasses import dataclass, field

import numpy as np
import pandas as pd

from .search import SearchIndex
from .text import dual_form, normalize
from .vectorize import HybridMatrix, HybridVectorizer, similarity

COSINE_WEIGHT = 0.5
VOTE_WEIGHT = 0.5
TOP_DOCS = 5
MAX_LIMIT = 20
MIN_SCORE = 0.02
TAG_COLUMNS = ["tag_id", "slug", "locale", "name", "description"]


def tag_text(row: pd.Series) -> str:
    """Mô tả thẻ: tên ×2 + slug (không dấu) + mô tả."""
    name = str(row.get("name") or "")
    slug = str(row.get("slug") or "").replace("-", " ")
    return dual_form(" ".join([name, name, slug, str(row.get("description") or "")]))


@dataclass
class LocaleTags:
    tags: pd.DataFrame
    vectorizer: HybridVectorizer
    matrix: HybridMatrix


@dataclass
class TagSuggester:
    index: SearchIndex
    locales: dict[str, LocaleTags] = field(default_factory=dict)
    slugs: dict[int, str] = field(default_factory=dict)

    @classmethod
    def build(cls, index: SearchIndex, tags: pd.DataFrame | None) -> "TagSuggester":
        if tags is None or tags.empty:
            return cls(index=index)
        slugs = {int(r.tag_id): str(r.slug) for r in tags.itertuples()}
        locales: dict[str, LocaleTags] = {}
        for locale, group in tags.groupby("locale", sort=True):
            group = group.reset_index(drop=True)
            texts = [tag_text(row) for _, row in group.iterrows()]
            doc_index = index.locales.get(str(locale))
            if doc_index is not None:
                # Dùng không gian vector của tài liệu cùng locale để IDF phản ánh kho nội dung.
                vectorizer = doc_index.vectorizer
                matrix = vectorizer.transform(texts)
            else:
                vectorizer = HybridVectorizer()
                matrix = vectorizer.fit_transform(texts)
            locales[str(locale)] = LocaleTags(group, vectorizer, matrix)
        return cls(index=index, locales=locales, slugs=slugs)

    def suggest_tags(self, text: str, locale: str, limit: int = 5) -> list[dict]:
        if not normalize(text):
            return []
        scores: dict[int, float] = defaultdict(float)
        for tag_id, value in self._cosine_scores(text, locale).items():
            scores[tag_id] += COSINE_WEIGHT * value
        for tag_id, value in self._vote_scores(text, locale).items():
            scores[tag_id] += VOTE_WEIGHT * value
        ranked = sorted(
            ((tid, s) for tid, s in scores.items() if s >= MIN_SCORE and tid in self.slugs),
            key=lambda item: (-item[1], item[0]),
        )
        size = max(1, min(int(limit), MAX_LIMIT))
        return [{"tag_id": tid, "slug": self.slugs[tid], "score": round(s, 4)} for tid, s in ranked[:size]]

    def _cosine_scores(self, text: str, locale: str) -> dict[int, float]:
        lt = self.locales.get(locale)
        if lt is None:
            return {}
        query = lt.vectorizer.transform([dual_form(text)])
        combined, _, _ = similarity(query, lt.matrix)
        row = combined[0]
        return {int(lt.tags.iloc[i]["tag_id"]): float(row[i]) for i in np.flatnonzero(row > 0)}

    def _vote_scores(self, text: str, locale: str) -> dict[int, float]:
        """Mỗi tài liệu trong top-5 bỏ phiếu cho thẻ của nó với trọng số = độ giống."""
        top = self.index.top_documents(text, locale, TOP_DOCS)
        total = sum(score for _, score in top)
        li = self.index.locales.get(locale)
        if not top or total <= 0 or li is None:
            return {}
        votes: dict[int, float] = defaultdict(float)
        for row_idx, score in top:
            for tag_id in li.docs.iloc[row_idx].get("tag_ids") or []:
                votes[int(tag_id)] += score / total
        return dict(votes)
