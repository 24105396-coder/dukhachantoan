"""Open-Meteo: gộp nhiều điểm/request, phân tích, upsert, cache 10 phút, lỗi mạng không ném 500 (respx)."""
from datetime import timedelta

import httpx
import pytest
import respx
from sqlalchemy import func, select

from app import config, schema, weather
from app.openmeteo import Location, WeatherCache, build_params, parse_current, parse_daily


def om_item(today, gust=45.0, temp=31.2):
    days = [(today + timedelta(days=i)).isoformat() for i in range(7)]
    return {
        "latitude": 12.25, "longitude": 109.05, "timezone": "Asia/Ho_Chi_Minh",
        "current": {"time": f"{today.isoformat()}T14:15", "interval": 900, "temperature_2m": temp,
                    "apparent_temperature": 35.0, "relative_humidity_2m": 70, "wind_speed_10m": 20.5,
                    "wind_gusts_10m": gust, "wind_direction_10m": 90, "precipitation": 0.4, "weather_code": 3},
        "daily": {"time": days, "temperature_2m_min": [24.0] * 7, "temperature_2m_max": [32.0] * 7,
                  "precipitation_sum": [5.0] * 7, "precipitation_probability_max": [60] * 7,
                  "wind_speed_10m_max": [25.0] * 7, "wind_gusts_10m_max": [50.0] * 7, "weather_code": [61] * 7},
    }


def _count(engine, table):
    with engine.connect() as conn:
        return conn.execute(select(func.count()).select_from(table)).scalar_one()


def test_build_params_joins_coordinates_and_requests_all_variables():
    params = build_params([Location(1, "a", 12.25, 109.05), Location(2, "b", 22.3, 104.0)])
    assert params["latitude"] == "12.2500,22.3000" and params["longitude"] == "109.0500,104.0000"
    assert "wind_gusts_10m" in params["current"] and "precipitation_probability_max" in params["daily"]
    assert params["timezone"] == "Asia/Ho_Chi_Minh" and params["forecast_days"] == 7


def test_parse_current_and_daily(seed):
    item = om_item(seed.now.date())
    cur = parse_current(item)
    assert cur["gust_kph"] == 45.0 and cur["weather_code"] == 3 and cur["observed_at"].hour == 14
    daily = parse_daily(item)
    assert len(daily) == 7 and daily[0]["rain_prob"] == 60 and daily[0]["gust_max_kph"] == 50


async def test_refresh_batches_all_zones_in_one_request_and_upserts(seed):
    seed.base_geo()
    today = seed.now.date()
    with respx.mock(assert_all_called=True) as mock:
        route = mock.get(config.OPEN_METEO_URL).mock(return_value=httpx.Response(200, json=[om_item(today)] * 4))
        body = await weather.refresh()
        assert route.call_count == 1
        assert body["fetched"] == 4 and body["observations"] == 4 and body["forecasts"] == 28
        assert "error" not in body
        # gọi lại bắt buộc làm mới: upsert không nhân đôi bản ghi
        await weather.refresh(force=True)
    assert _count(seed.engine, schema.weather_observations) == 4
    assert _count(seed.engine, schema.weather_forecasts) == 28


async def test_refresh_uses_cache_within_ttl(seed):
    seed.base_geo()
    with respx.mock() as mock:
        route = mock.get(config.OPEN_METEO_URL).mock(
            return_value=httpx.Response(200, json=om_item(seed.now.date())))
        first = await weather.refresh(["lao-cai"])
        second = await weather.refresh(["lao-cai"])
    assert route.call_count == 1
    assert first["fetched"] == 1 and second["fetched"] == 0 and second["cached"] == 1


def test_cache_expires_after_ttl():
    clock = {"t": 0.0}
    cache = WeatherCache(ttl=600, clock=lambda: clock["t"])
    loc = Location(1, "a", 1.0, 2.0)
    cache.put(loc, "x")
    clock["t"] = 599
    assert cache.get(loc) == "x"
    clock["t"] = 601
    assert cache.get(loc) is None


@pytest.mark.parametrize("side_effect", [httpx.ConnectTimeout("timeout"), httpx.ConnectError("dns")])
def test_network_error_returns_fetched_zero_with_error_not_500(client, seed, side_effect):
    seed.base_geo()
    with respx.mock() as mock:
        mock.get(config.OPEN_METEO_URL).mock(side_effect=side_effect)
        res = client.post("/weather/refresh", json={"zone_codes": ["khanh-hoa"]})
    assert res.status_code == 200
    body = res.json()
    assert body["fetched"] == 0 and body["error"]
    assert _count(seed.engine, schema.weather_observations) == 0


def test_http_500_and_mismatched_payload_are_reported(client, seed):
    seed.base_geo()
    with respx.mock() as mock:
        mock.get(config.OPEN_METEO_URL).mock(return_value=httpx.Response(500, json={"error": True}))
        assert "HTTPStatusError" in client.post("/weather/refresh", json={}).json()["error"]
    with respx.mock() as mock:
        mock.get(config.OPEN_METEO_URL).mock(return_value=httpx.Response(200, json=[om_item(seed.now.date())]))
        body = client.post("/weather/refresh", json={"force": True}).json()
    assert body["fetched"] == 0 and "cần 4" in body["error"]


def test_refresh_marks_alert_source_status(client, seed):
    seed.base_geo()
    seed.add(schema.alert_sources, code="openmeteo", name="Open-Meteo", source_type="weather",
             provider="openmeteo", is_active=True)
    with respx.mock() as mock:
        mock.get(config.OPEN_METEO_URL).mock(side_effect=httpx.ConnectError("down"))
        client.post("/weather/refresh", json={})
    with seed.engine.connect() as conn:
        row = conn.execute(select(schema.alert_sources)).one()
    assert row.last_status == "error" and "ConnectError" in row.last_error and row.last_polled_at
