"""Chuẩn hóa văn bản tiếng Việt: NFC, chữ thường, bỏ dấu, tách token."""
from __future__ import annotations

import re
import unicodedata

# Bảng ánh xạ đầy đủ các nguyên âm có dấu tiếng Việt (kể cả đ) về chữ Latin cơ bản.
# Viết tường minh thay vì chỉ dựa vào NFD vì 'đ' không phân rã được bằng Unicode.
_VIET_GROUPS = {
    "a": "àáảãạăằắẳẵặâầấẩẫậ",
    "e": "èéẻẽẹêềếểễệ",
    "i": "ìíỉĩị",
    "o": "òóỏõọôồốổỗộơờớởỡợ",
    "u": "ùúủũụưừứửữự",
    "y": "ỳýỷỹỵ",
    "d": "đ",
}


def _build_table() -> dict[int, str]:
    table: dict[int, str] = {}
    for base, chars in _VIET_GROUPS.items():
        for ch in chars:
            table[ord(ch)] = base
            table[ord(ch.upper())] = base.upper()
    return table


VIET_ACCENT_TABLE = _build_table()

_TOKEN_RE = re.compile(r"[^\W_]+", re.UNICODE)
_SPACE_RE = re.compile(r"\s+")

# Hư từ chỉ dùng để lọc matched_terms (không lọc khi đánh chỉ mục để giữ cụm từ).
# So khớp trên dạng CÓ dấu: "trong"/"co" không dấu có thể là "trống"/"cổ" nên không bị lọc.
STOPWORDS = frozenset(
    {
        "và", "của", "là", "ở", "các", "những", "có", "cho", "với", "một", "được",
        "the", "of", "and", "a", "an", "in", "on", "to", "for", "is", "at", "by",
    }
)


def nfc(text: str | None) -> str:
    """Chuẩn hóa Unicode NFC (gộp ký tự tổ hợp do bàn phím/hệ điều hành khác nhau)."""
    if not text:
        return ""
    return unicodedata.normalize("NFC", str(text))


def normalize(text: str | None) -> str:
    """NFC + chữ thường + gộp khoảng trắng, giữ nguyên dấu."""
    return _SPACE_RE.sub(" ", nfc(text).lower()).strip()


def strip_accents(text: str | None) -> str:
    """Bỏ dấu tiếng Việt (đ→d) rồi bỏ nốt dấu tổ hợp còn sót của ngôn ngữ khác."""
    mapped = nfc(text).translate(VIET_ACCENT_TABLE)
    decomposed = unicodedata.normalize("NFD", mapped)
    stripped = "".join(ch for ch in decomposed if not unicodedata.combining(ch))
    return unicodedata.normalize("NFC", stripped)


def fold(text: str | None) -> str:
    """Dạng so khớp: chuẩn hóa + không dấu."""
    return strip_accents(normalize(text))


def tokenize(text: str | None) -> list[str]:
    """Tách token chữ/số trên văn bản đã chuẩn hóa (giữ dấu)."""
    return _TOKEN_RE.findall(normalize(text))


def folded_tokens(text: str | None) -> list[str]:
    """Token không dấu."""
    return _TOKEN_RE.findall(fold(text))


def dual_form(text: str | None) -> str:
    """Ghép bản có dấu và bản không dấu để TF-IDF chịu được truy vấn gõ không dấu."""
    accented = normalize(text)
    folded = strip_accents(accented)
    return accented if folded == accented else f"{accented} {folded}"
