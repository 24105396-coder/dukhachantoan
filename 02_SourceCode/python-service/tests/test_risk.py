"""Điểm rủi ro: nội suy, biên ngưỡng, tổ hợp thời tiết + cảnh báo, giải thích factors."""
import pytest

from app import risk
from app.risk import WeatherInput, compute, interp, level_for


def test_interp_clamps_and_interpolates():
    pts = ((0, 0), (10, 100))
    assert interp(-5, pts) == 0
    assert interp(5, pts) == 50
    assert interp(50, pts) == 100
    assert interp(None, pts) == 0


@pytest.mark.parametrize("score,level", [(0, 0), (19, 0), (20, 1), (39, 1), (40, 2), (59, 2), (60, 3),
                                         (79, 3), (80, 4), (100, 4)])
def test_level_thresholds(score, level):
    assert level_for(score) == level


def test_no_data_gives_zero_and_unavailable():
    res = compute(None, [])
    assert res["score"] == 0 and res["level"] == 0
    assert res["factors"]["weather"]["available"] is False
    assert res["factors"]["trend"]["direction"] == "new"


@pytest.mark.parametrize("gust,level", [(59, 2), (60, 3), (89, 3), (90, 4), (150, 4)])
def test_wind_gust_boundaries_match_derive_thresholds(gust, level):
    assert compute(WeatherInput(gust_kph=gust), [])["level"] == level


@pytest.mark.parametrize("weather,level", [
    (WeatherInput(rain_mm=99), 2), (WeatherInput(rain_mm=100), 3),
    (WeatherInput(temp_c=37.9), 1), (WeatherInput(temp_c=38), 2), (WeatherInput(temp_c=40), 3),
    (WeatherInput(weather_code=95), 2), (WeatherInput(tmin_c=2), 2),
])
def test_rain_heat_storm_cold_levels(weather, level):
    assert compute(weather, [])["level"] == level


@pytest.mark.parametrize("severity", [1, 2, 3, 4])
def test_single_official_alert_maps_to_its_own_level(severity):
    res = compute(None, [{"code": "AL-1", "severity": severity, "hazard_code": "bao"}])
    assert res["level"] == severity


def test_combination_is_capped_at_100():
    w = WeatherInput(gust_kph=140, rain_mm=400, temp_c=46, weather_code=99)
    alerts = [{"code": f"AL-{i}", "severity": 4, "hazard_code": "bao"} for i in range(5)]
    res = compute(w, alerts)
    assert res["score"] == 100 and res["level"] == 4


def test_secondary_factor_adds_twenty_percent():
    # gió 60 → 60; mưa 50 mm → 40 → 60 + 0.2*40 = 68
    assert compute(WeatherInput(gust_kph=60, rain_mm=50), [])["score"] == 68


def test_weather_and_alert_combine_with_quarter_weight():
    res = compute(WeatherInput(gust_kph=60), [{"code": "AL-1", "severity": 3, "hazard_code": "bao"}])
    assert res["score"] == 75  # 60 + 0.25*60


def test_factors_explain_and_trend():
    res = compute(WeatherInput(gust_kph=75), [{"code": "AL-9", "severity": 2, "hazard_code": "bao"}], previous=30)
    f = res["factors"]
    assert f["weather"]["components"]["wind"] == 70
    assert f["alerts"]["count"] == 1
    assert f["trend"] == {"previous": 30, "delta": res["score"] - 30, "direction": "up"}
    assert "Gió giật 75 km/h" in f["explain"]["vi"] and "Wind gusts 75 km/h" in f["explain"]["en"]
    assert f["formula"] == risk.FORMULA
