from app.search import SearchIndex
from app.tags import TagSuggester


def test_suggests_bronze_tag_for_bronze_drum_text(suggester):
    out = suggester.suggest_tags("Chiếc trống đồng cổ được đúc thời Đông Sơn", "vi", 3)
    assert out[0]["slug"] == "do-dong"
    assert all({"tag_id", "slug", "score"} <= set(s) for s in out)


def test_suggests_capital_tag_for_capital_story_without_accents(suggester):
    slugs = [s["slug"] for s in suggester.suggest_tags("kinh do trieu nguyen o hue, cung dien lang tam", "vi", 3)]
    assert "kinh-do-co" in slugs
    assert "kien-truc-cung-dinh" in slugs


def test_votes_from_similar_documents_contribute(suggester):
    # Văn bản giống Văn Miếu → thẻ Nho giáo được bầu từ tài liệu láng giềng
    slugs = [s["slug"] for s in suggester.suggest_tags("bia tiến sĩ khoa thi thờ Khổng Tử", "vi", 3)]
    assert "nho-giao" in slugs


def test_limit_and_empty_inputs(suggester):
    assert len(suggester.suggest_tags("di sản", "vi", 1)) <= 1
    assert suggester.suggest_tags("   ", "vi") == []
    assert TagSuggester(index=SearchIndex.empty()).suggest_tags("trống đồng", "vi") == []
