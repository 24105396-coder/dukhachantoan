"""Cảnh báo suy diễn: ngưỡng biên, nội dung song ngữ, gộp ngày, không ghi DB."""
from datetime import timedelta

import pytest
from sqlalchemy import func, select

from app import schema
from app.derive import RULES, severity_for


def _rule(code):
    return next(r for r in RULES if r.hazard_code == code)


@pytest.mark.parametrize("hazard,value,expected", [
    ("bao", 59.9, 0), ("bao", 60, 3), ("bao", 89.9, 3), ("bao", 90, 4),
    ("mua_lon", 99.9, 0), ("mua_lon", 100, 3),
    ("nang_nong", 37.9, 0), ("nang_nong", 38, 2), ("nang_nong", 39.9, 2), ("nang_nong", 40, 3),
    ("dong_set", 82, 0), ("dong_set", 95, 2), ("dong_set", 99, 2),
    ("bao", None, 0),
])
def test_threshold_boundaries(hazard, value, expected):
    assert severity_for(_rule(hazard), value) == expected


def _forecast(seed, zone_id, day_offset, **values):
    seed.add(schema.weather_forecasts, zone_id=zone_id, forecast_date=seed.now.date() + timedelta(days=day_offset),
             source="openmeteo", fetched_at=seed.now, **values)


def test_derive_endpoint_returns_bilingual_drafts_without_writing(client, seed):
    ids = seed.base_geo()
    _forecast(seed, ids["z_kh"], 0, gust_max_kph=95, rain_mm=120, tmax_c=31, weather_code=95)
    _forecast(seed, ids["z_kh"], 1, gust_max_kph=70, rain_mm=10, tmax_c=30, weather_code=3)
    _forecast(seed, ids["z_lc"], 0, gust_max_kph=20, rain_mm=0, tmax_c=39, weather_code=1)
    res = client.post("/weather/derive-alerts", json={})
    assert res.status_code == 200
    drafts = res.json()["drafts"]
    by_key = {(d["zone_code"], d["hazard_code"]): d for d in drafts}
    assert set(by_key) == {("khanh-hoa", "bao"), ("khanh-hoa", "mua_lon"), ("khanh-hoa", "dong_set"),
                           ("lao-cai", "nang_nong")}
    wind = by_key[("khanh-hoa", "bao")]
    assert wind["severity"] == 4 and len(wind["raw"]["days"]) == 2
    assert wind["source_name"] == "Open-Meteo (suy diễn tự động)" and wind["source"] == "openmeteo-derived"
    assert wind["headline"]["vi"].startswith("Gió giật") and "gusts" in wind["headline"]["en"]
    assert wind["instructions"]["en"] and wind["description"]["vi"]
    assert wind["ends_at"].endswith("23:59:59")
    assert by_key[("lao-cai", "nang_nong")]["severity"] == 2
    assert drafts[0]["severity"] == 4  # sắp xếp mức giảm dần
    with seed.engine.connect() as conn:
        assert conn.execute(select(func.count()).select_from(schema.alerts)).scalar_one() == 0


def test_derive_below_thresholds_returns_empty(client, seed):
    ids = seed.base_geo()
    _forecast(seed, ids["z_kh"], 0, gust_max_kph=59, rain_mm=99, tmax_c=37.9, weather_code=82)
    assert client.post("/weather/derive-alerts", json={"zone_codes": ["khanh-hoa"]}).json()["count"] == 0


def test_derive_uses_current_observation_for_today(client, seed):
    ids = seed.base_geo()
    seed.add(schema.weather_observations, zone_id=ids["z_pq"], observed_at=seed.now - timedelta(minutes=15),
             gust_kph=64, temp_c=30, weather_code=2, source="openmeteo", fetched_at=seed.now)
    drafts = client.post("/weather/derive-alerts", json={"zone_codes": ["phu-quoc"], "days": 1}).json()["drafts"]
    assert [(d["hazard_code"], d["severity"]) for d in drafts] == [("bao", 3)]
