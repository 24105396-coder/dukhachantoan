from app.search import SearchIndex


def test_unaccented_query_finds_hoang_thanh_first(index):
    results = index.search("hoang thanh thang long", "vi", 10)
    assert results[0]["slug"] == "hoang-thanh-thang-long"
    assert results[0]["subject_type"] == "site"
    assert {"hoang", "thanh", "thang", "long"} <= set(results[0]["matched_terms"])


def test_accented_query_gives_same_top_result(index):
    assert index.search("Hoàng thành Thăng Long", "vi")[0]["slug"] == "hoang-thanh-thang-long"


def test_multiple_depths_are_merged_into_one_subject(index):
    results = index.search("thang long", "vi", 20)
    keys = [(r["subject_type"], r["subject_id"]) for r in results]
    assert len(keys) == len(set(keys))
    assert ("site", 1) in keys


def test_types_filter_and_locale_separation(index):
    results = index.search("thang long", "vi", 20, ["figure"])
    assert [r["slug"] for r in results] == ["ly-thai-to"]
    en = index.search("temple literature", "en")
    assert en[0]["slug"] == "van-mieu-quoc-tu-giam"


def test_tag_names_are_indexed(index):
    # "khảo cổ học" chỉ xuất hiện trong tên thẻ của Hoàng thành
    assert index.search("khao co hoc", "vi")[0]["slug"] == "hoang-thanh-thang-long"


def test_unrelated_query_and_empty_index_return_nothing(index):
    assert index.search("zzzzqqqq", "vi") == []
    assert index.search("thang long", "fr") == []
    assert SearchIndex.empty().search("thang long", "vi") == []
    assert SearchIndex.empty().size == 0


def test_limit_is_respected(index):
    assert len(index.search("di tich viet nam", "vi", 2)) <= 2


def test_typo_and_glued_queries_use_char_ngrams(index):
    assert index.search("hoang thnah thang lonh", "vi")[0]["slug"] == "hoang-thanh-thang-long"
    assert [r["slug"] for r in index.search("trongdong", "vi")] == ["trong-dong-ngoc-lu"]


def test_char_noise_is_filtered(index):
    slugs = [r["slug"] for r in index.search("thang long", "vi", 20)]
    assert slugs == ["hoang-thanh-thang-long", "ly-thai-to"]


def test_accentless_ambiguous_words_are_not_dropped_as_stopwords(index):
    assert "trong" in index.search("trong dong", "vi")[0]["matched_terms"]
