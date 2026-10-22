"""Chỉ mục tìm kiếm TF-IDF đa ngữ (một bộ vector cho mỗi locale)."""
from __future__ import annotations

from dataclasses import dataclass, field
from datetime import datetime, timezone

import numpy as np
import pandas as pd

from .text import STOPWORDS, dual_form, fold, folded_tokens, normalize, tokenize
from .vectorize import HybridMatrix, HybridVectorizer, similarity

DOCUMENT_COLUMNS = [
    "subject_type", "subject_id", "slug", "locale", "depth",
    "title", "summary", "body", "tags_text",
]
SUBJECT_TYPES = ("site", "artifact", "figure")
TITLE_WEIGHT = 3
# Ngưỡng loại nhiễu: char n-gram cho điểm dương với gần như mọi tài liệu, nên tài liệu
# không chung từ nào với truy vấn chỉ được giữ khi điểm char đủ cao so với tài liệu tốt nhất.
MIN_CHAR_SCORE = 0.15
RELATIVE_CHAR_RATIO = 0.6
MIN_SCORE = 0.03
MAX_LIMIT = 100


def document_text(row: pd.Series) -> str:
    """Văn bản đánh chỉ mục: tiêu đề ×3 + tóm tắt + nội dung + tên thẻ, có dấu + không dấu."""
    title = str(row.get("title") or "")
    parts = [title] * TITLE_WEIGHT + [
        str(row.get("summary") or ""), str(row.get("body") or ""), str(row.get("tags_text") or ""),
    ]
    return dual_form(" ".join(p for p in parts if p))


def _empty_documents() -> pd.DataFrame:
    return pd.DataFrame(columns=DOCUMENT_COLUMNS + ["tag_ids"])


@dataclass
class LocaleIndex:
    docs: pd.DataFrame
    vectorizer: HybridVectorizer
    matrix: HybridMatrix
    token_sets: list[frozenset[str]]


@dataclass
class SearchIndex:
    """Chỉ mục bất biến sau khi build; reindex tạo đối tượng mới rồi hoán đổi."""

    locales: dict[str, LocaleIndex] = field(default_factory=dict)
    indexed_at: datetime | None = None

    @classmethod
    def empty(cls) -> "SearchIndex":
        return cls()

    @classmethod
    def build(cls, documents: pd.DataFrame) -> "SearchIndex":
        docs = documents if documents is not None else _empty_documents()
        if "tag_ids" not in docs.columns:
            docs = docs.assign(tag_ids=[[] for _ in range(len(docs))])
        locales: dict[str, LocaleIndex] = {}
        for locale, group in docs.groupby("locale", sort=True):
            group = group.reset_index(drop=True)
            texts = [document_text(row) for _, row in group.iterrows()]
            if not any(t.strip() for t in texts):
                continue
            vectorizer = HybridVectorizer()
            matrix = vectorizer.fit_transform(texts)
            token_sets = [frozenset(folded_tokens(t)) for t in texts]
            locales[str(locale)] = LocaleIndex(group, vectorizer, matrix, token_sets)
        return cls(locales=locales, indexed_at=datetime.now(timezone.utc))

    @property
    def size(self) -> int:
        return sum(len(li.docs) for li in self.locales.values())

    def score_documents(self, text: str, locale: str) -> tuple[np.ndarray, np.ndarray, np.ndarray] | None:
        """Điểm của mọi tài liệu thuộc locale đối với một văn bản truy vấn."""
        li = self.locales.get(locale)
        if li is None or not normalize(text):
            return None
        query = li.vectorizer.transform([dual_form(text)])
        combined, word, char = similarity(query, li.matrix)
        return combined[0], word[0], char[0]

    def top_documents(self, text: str, locale: str, k: int = 5) -> list[tuple[int, float]]:
        """Chỉ số hàng + điểm của k tài liệu giống nhất (dùng cho bỏ phiếu thẻ)."""
        scored = self.score_documents(text, locale)
        if scored is None:
            return []
        combined, word, char = scored
        keep = relevance_mask(word, char)
        order = [int(i) for i in np.argsort(-combined) if keep[i] and combined[i] >= MIN_SCORE]
        return [(i, float(combined[i])) for i in order[:k]]

    def search(self, q: str, locale: str, limit: int = 20, types: list[str] | None = None) -> list[dict]:
        scored = self.score_documents(q, locale)
        if scored is None:
            return []
        li = self.locales[locale]
        combined, word, char = scored
        allowed = set(types) if types else set(SUBJECT_TYPES)
        q_tokens = _query_terms(q)
        keep = relevance_mask(word, char)
        best: dict[tuple[str, int], dict] = {}
        for i in np.argsort(-combined):
            score = float(combined[i])
            if score < MIN_SCORE:
                break
            if not keep[i]:
                continue
            row = li.docs.iloc[int(i)]
            if row["subject_type"] not in allowed:
                continue
            key = (str(row["subject_type"]), int(row["subject_id"]))
            matched = [orig for orig, folded in q_tokens if folded in li.token_sets[int(i)]]
            _merge_hit(best, key, str(row["slug"]), score, matched)
        ranked = sorted(best.values(), key=lambda r: (-r["score"], r["subject_type"], r["subject_id"]))
        return ranked[: max(1, min(int(limit), MAX_LIMIT))]


def relevance_mask(word: np.ndarray, char: np.ndarray) -> np.ndarray:
    """Giữ tài liệu có chung ít nhất một từ, hoặc khớp char n-gram mạnh (gõ sai/dính chữ)."""
    if char.size == 0:
        return np.zeros(0, dtype=bool)
    char_floor = max(MIN_CHAR_SCORE, RELATIVE_CHAR_RATIO * float(char.max()))
    return (word > 0) | (char >= char_floor)


def _query_terms(q: str) -> list[tuple[str, str]]:
    """Token truy vấn (dạng gốc, dạng không dấu), bỏ hư từ và trùng lặp."""
    seen: set[str] = set()
    terms: list[tuple[str, str]] = []
    for token in tokenize(q):
        folded = fold(token)
        if token in STOPWORDS or folded in seen:
            continue
        seen.add(folded)
        terms.append((token, folded))
    return terms


def _merge_hit(best: dict, key: tuple[str, int], slug: str, score: float, matched: list[str]) -> None:
    """Gộp nhiều depth của cùng một thực thể: lấy điểm cao nhất, hợp các từ khớp."""
    current = best.get(key)
    if current is None:
        best[key] = {
            "subject_type": key[0], "subject_id": key[1], "slug": slug,
            "score": round(score, 4), "matched_terms": matched,
        }
        return
    merged_terms = current["matched_terms"] + [t for t in matched if t not in current["matched_terms"]]
    best[key] = {**current, "score": max(current["score"], round(score, 4)), "matched_terms": merged_terms}
