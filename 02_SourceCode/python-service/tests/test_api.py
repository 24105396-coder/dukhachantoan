import pytest
from fastapi.testclient import TestClient

from app import main

TOKEN = "test-token"
AUTH = {"X-Analytics-Token": TOKEN}


@pytest.fixture
def client(monkeypatch, index, suggester, clean_tables):
    monkeypatch.setenv("ANALYTICS_TOKEN", TOKEN)
    monkeypatch.setitem(main.STATE, "current", main.EngineState(index=index, suggester=suggester))
    monkeypatch.setattr(main.db, "load_quality_tables", lambda: clean_tables)
    monkeypatch.setattr(main.db, "load_stats_tables", lambda: clean_tables)
    # Không dùng "with" → lifespan không chạy, không cần MySQL.
    return TestClient(main.app)


def test_healthz_needs_no_token(client):
    res = client.get("/healthz")
    assert res.status_code == 200
    assert res.json()["status"] == "ok" and res.json()["index_size"] > 0


@pytest.mark.parametrize("method, url, body", [
    ("post", "/search", {"q": "hue", "locale": "vi"}),
    ("post", "/suggest-tags", {"text": "trống đồng", "locale": "vi"}),
    ("get", "/quality-report", None),
    ("post", "/reindex", None),
    ("get", "/stats/overview", None),
])
@pytest.mark.parametrize("headers", [{}, {"X-Analytics-Token": "wrong"}])
def test_protected_endpoints_reject_missing_or_wrong_token(client, method, url, body, headers):
    res = getattr(client, method)(url, json=body, headers=headers) if body else getattr(client, method)(url, headers=headers)
    assert res.status_code == 401


def test_unset_server_token_fails_closed(client, monkeypatch):
    monkeypatch.delenv("ANALYTICS_TOKEN")
    assert client.post("/search", json={"q": "hue"}, headers={"X-Analytics-Token": ""}).status_code == 401


def test_search_contract(client):
    res = client.post("/search", json={"q": "hoang thanh thang long", "locale": "vi", "limit": 5}, headers=AUTH)
    data = res.json()
    assert res.status_code == 200 and data["engine"] == "tfidf" and isinstance(data["took_ms"], int)
    top = data["results"][0]
    assert set(top) == {"subject_type", "subject_id", "slug", "score", "matched_terms"}
    assert top["slug"] == "hoang-thanh-thang-long"


def test_search_validation(client):
    assert client.post("/search", json={"q": "", "locale": "vi"}, headers=AUTH).status_code == 422
    assert client.post("/search", json={"q": "a", "locale": "fr"}, headers=AUTH).status_code == 422
    assert client.post("/search", json={"q": "a", "types": ["route"]}, headers=AUTH).status_code == 422


def test_suggest_quality_stats_contracts(client):
    sug = client.post("/suggest-tags", json={"text": "trống đồng Đông Sơn", "locale": "vi"}, headers=AUTH).json()
    assert sug["suggestions"][0]["slug"] == "do-dong"
    report = client.get("/quality-report", headers=AUTH).json()
    assert report["summary"]["errors"] == 0
    stats = client.get("/stats/overview", headers=AUTH).json()
    assert set(stats) == {"sites", "artifacts", "figures", "interpretations", "media", "by_province", "by_period"}


def test_reindex_returns_503_when_db_down(client, monkeypatch):
    def boom():
        raise ConnectionError("db down")
    monkeypatch.setattr(main, "build_state", boom)
    res = client.post("/reindex", headers=AUTH)
    assert res.status_code == 503
    assert client.get("/healthz").json()["index_size"] > 0  # chỉ mục cũ được giữ


def test_reindex_swaps_state(client, monkeypatch, index, suggester):
    monkeypatch.setattr(main, "build_state", lambda: main.EngineState(index=index, suggester=suggester))
    res = client.post("/reindex", headers=AUTH)
    assert res.status_code == 200 and res.json()["indexed"] == index.size


def test_lifespan_survives_db_failure(monkeypatch):
    def boom():
        raise ConnectionError("db down")
    monkeypatch.setattr(main, "build_state", boom)
    monkeypatch.setitem(main.STATE, "current", main.empty_state())
    with TestClient(main.app) as c:
        assert c.get("/healthz").json()["index_size"] == 0
