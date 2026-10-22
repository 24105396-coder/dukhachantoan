"""API: xác thực token, healthz khi DB tắt, geo/contains, risk/compute ghi risk_scores, stats."""
from datetime import timedelta

import pytest
from fastapi.testclient import TestClient
from sqlalchemy import create_engine, select

from app import db, schema
from app.main import app
from tests.conftest import square

PROTECTED = [("post", "/weather/refresh"), ("post", "/risk/compute"), ("post", "/match"),
             ("post", "/geo/contains"), ("post", "/weather/derive-alerts"), ("get", "/stats/overview")]


@pytest.mark.parametrize("method,url", PROTECTED)
def test_protected_endpoints_require_token(anon_client, method, url):
    assert getattr(anon_client, method)(url).status_code == 401


def test_wrong_token_is_rejected(anon_client):
    assert anon_client.get("/stats/overview", headers={"X-Analytics-Token": "sai"}).status_code == 401


def test_unset_server_token_fails_closed(anon_client, monkeypatch):
    monkeypatch.setenv("ANALYTICS_TOKEN", "")
    assert anon_client.get("/stats/overview", headers={"X-Analytics-Token": ""}).status_code == 401


def test_healthz_without_token_reports_db_up(anon_client):
    body = anon_client.get("/healthz").json()
    assert body["status"] == "ok" and body["db"] == "up" and body["last_fetch"] is None and body["version"]


def test_healthz_and_endpoints_when_db_down():
    db.use_engine(create_engine("sqlite:////khong-ton-tai/thu-muc/x.db"))
    try:
        with TestClient(app, headers={"X-Analytics-Token": "test-token"}) as c:
            health = c.get("/healthz")
            assert health.status_code == 200 and health.json()["db"] == "down"
            assert c.get("/stats/overview").status_code == 503
            assert c.post("/risk/compute", json={}).status_code == 503
    finally:
        db.use_engine(None)


def test_geo_contains_polygon_and_invalid(client):
    body = client.post("/geo/contains", json={"geometry": square(10, 11, 106, 107), "points": [
        {"id": 1, "lat": 10.5, "lng": 106.5}, {"id": 2, "lat": 12, "lng": 106.5}]}).json()
    assert body["inside"] == [1] and body["distances_km"]["1"] == 0
    bad = client.post("/geo/contains", json={"geometry": {"type": "Point", "coordinates": [1, 2]}, "points": []})
    assert bad.status_code == 422


def test_geo_contains_validates_coordinates(client):
    res = client.post("/geo/contains", json={"geometry": square(10, 11, 106, 107),
                                             "points": [{"id": 1, "lat": 95, "lng": 106}]})
    assert res.status_code == 422


def test_risk_compute_writes_scores_with_factors_and_trend(client, seed):
    ids = seed.base_geo()
    seed.add(schema.weather_forecasts, zone_id=ids["z_kh"], forecast_date=seed.now.date(), gust_max_kph=95,
             rain_mm=40, tmax_c=31, tmin_c=24, weather_code=63, source="openmeteo", fetched_at=seed.now)
    seed.add(schema.weather_observations, zone_id=ids["z_lc"], observed_at=seed.now - timedelta(hours=1),
             temp_c=39, gust_kph=10, weather_code=0, source="openmeteo", fetched_at=seed.now)
    seed.alert(severity=3, zone_ids=[ids["z_kh"]])  # phủ cả nha-trang (vùng con)
    first = {r["zone_code"]: r for r in client.post("/risk/compute", json={}).json()}
    assert first["khanh-hoa"]["level"] == 4
    assert first["nha-trang"]["level"] == 3 and first["nha-trang"]["score"] == 60
    assert first["lao-cai"]["level"] == 2
    assert first["phu-quoc"] == {"zone_code": "phu-quoc", "score": 0, "level": 0, "delta": None}
    with seed.engine.connect() as conn:
        row = conn.execute(select(schema.risk_scores).where(schema.risk_scores.c.zone_id == ids["z_kh"])).one()
    assert row.factors["alerts"]["items"][0]["code"] == "AL-0001"
    assert row.factors["weather"]["components"]["wind"] > 80
    second = {r["zone_code"]: r for r in client.post("/risk/compute", json={"zone_codes": ["lao-cai"]}).json()}
    assert list(second) == ["lao-cai"] and second["lao-cai"]["delta"] == 0


def test_risk_compute_counts_geometry_alert_covering_centroid(client, seed):
    ids = seed.base_geo()
    seed.alert(severity=4, geometry={"type": "Circle", "center": {"lat": 10.22, "lng": 103.96}, "radius_km": 5})
    scores = {r["zone_code"]: r["level"] for r in client.post("/risk/compute", json={}).json()}
    assert scores == {"khanh-hoa": 0, "nha-trang": 0, "lao-cai": 0, "phu-quoc": 4}
    assert ids


def test_expired_alert_is_not_counted(client, seed):
    ids = seed.base_geo()
    seed.alert(severity=4, zone_ids=[ids["z_lc"]], ends_at=seed.now - timedelta(minutes=1))
    levels = {r["zone_code"]: r["level"] for r in client.post("/risk/compute", json={}).json()}
    assert levels["lao-cai"] == 0


def test_stats_overview(client, seed):
    ids = seed.base_geo()
    seed.alert(severity=3, zone_ids=[ids["z_kh"]])
    seed.alert(code="AL-0002", severity=4, status="draft")
    trip = seed.trip()
    for status in ("sent", "sent", "failed", "queued"):
        seed.add(schema.notifications, user_id=1, channel="sms", level=3, status=status)
    seed.add(schema.safety_checkins, trip_id=trip, user_id=1, status="need_help", response_status="open",
             checked_at=seed.now)
    client.post("/risk/compute", json={})
    body = client.get("/stats/overview").json()
    assert body["alerts"]["active"] == 1 and body["alerts"]["by_severity"]["3"] == 1
    assert body["notifications"]["failure_rate"] == pytest.approx(1 / 3, abs=1e-3)
    assert body["checkins"]["need_help_open"] == 1
    assert body["zones"]["active"] == 4 and sum(body["risk"]["distribution"].values()) == 4
    assert body["risk"]["top"][0]["level"] == 3 and body["updated_at"]
