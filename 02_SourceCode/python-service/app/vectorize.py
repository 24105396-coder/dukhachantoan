"""Bộ vector lai word n-gram + char n-gram dùng chung cho tìm kiếm và gợi ý thẻ."""
from __future__ import annotations

from dataclasses import dataclass

import numpy as np
from scipy.sparse import csr_matrix
from sklearn.feature_extraction.text import TfidfVectorizer

from .text import tokenize

# Trọng số kết hợp hai không gian; word bắt khớp đúng từ, char chịu lỗi gõ/thiếu dấu.
WORD_WEIGHT = 0.6
CHAR_WEIGHT = 0.4


@dataclass(frozen=True)
class HybridMatrix:
    word: csr_matrix
    char: csr_matrix

    @property
    def n_rows(self) -> int:
        return self.word.shape[0]


class HybridVectorizer:
    """TF-IDF word (1,2)-gram + char_wb (2,4)-gram; văn bản đầu vào đã ở dạng dual_form."""

    def __init__(self) -> None:
        self.word = TfidfVectorizer(
            tokenizer=tokenize, token_pattern=None, lowercase=False,
            ngram_range=(1, 2), sublinear_tf=True,
        )
        self.char = TfidfVectorizer(
            analyzer="char_wb", ngram_range=(2, 4), lowercase=False, sublinear_tf=True,
        )

    def fit_transform(self, texts: list[str]) -> HybridMatrix:
        return HybridMatrix(
            word=csr_matrix(self.word.fit_transform(texts)),
            char=csr_matrix(self.char.fit_transform(texts)),
        )

    def transform(self, texts: list[str]) -> HybridMatrix:
        return HybridMatrix(
            word=csr_matrix(self.word.transform(texts)),
            char=csr_matrix(self.char.transform(texts)),
        )


def similarity(query: HybridMatrix, docs: HybridMatrix) -> tuple[np.ndarray, np.ndarray, np.ndarray]:
    """Cosine (các hàng đã chuẩn hóa L2) → (điểm kết hợp, điểm word, điểm char), shape (q, d)."""
    word = (query.word @ docs.word.T).toarray()
    char = (query.char @ docs.char.T).toarray()
    return WORD_WEIGHT * word + CHAR_WEIGHT * char, word, char
