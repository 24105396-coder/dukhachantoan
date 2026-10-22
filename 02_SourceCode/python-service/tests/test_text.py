import unicodedata

from app.text import dual_form, fold, normalize, strip_accents, tokenize


def test_strip_accents_removes_all_vietnamese_marks_including_d():
    assert strip_accents("Hoàng thành Thăng Long") == "Hoang thanh Thang Long"
    assert strip_accents("Đền Đô, đình làng") == "Den Do, dinh lang"
    assert strip_accents("ỳýỷỹỵ ưừứửữự ơờớởỡợ") == "yyyyy uuuuuu oooooo"


def test_full_table_covers_every_vowel_with_tone():
    vowels = "àáảãạăằắẳẵặâầấẩẫậèéẻẽẹêềếểễệìíỉĩịòóỏõọôồốổỗộơờớởỡợùúủũụưừứửữựỳýỷỹỵđ"
    folded = strip_accents(vowels + vowels.upper())
    assert set(folded) <= set("aeiouydAEIOUYD")


def test_normalize_unifies_nfd_input_and_lowercases():
    nfd = unicodedata.normalize("NFD", "HOÀNG  Thành")
    assert normalize(nfd) == "hoàng thành"
    assert fold(nfd) == "hoang thanh"


def test_tokenizer_splits_on_punctuation_and_keeps_numbers():
    assert tokenize("Văn Miếu – Quốc Tử Giám (1070)!") == ["văn", "miếu", "quốc", "tử", "giám", "1070"]
    assert tokenize("snake_case") == ["snake", "case"]
    assert tokenize(None) == []


def test_dual_form_appends_folded_only_when_different():
    assert dual_form("Huế") == "huế hue"
    assert dual_form("Hanoi") == "hanoi"
