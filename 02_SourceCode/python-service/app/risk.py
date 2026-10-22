"""Điểm rủi ro 0–100 theo vùng (thuần, không I/O).

W (thời tiết) = max(thành phần) + 0,2 × thành phần lớn thứ hai, thành phần gồm gió giật, mưa ngày,
nhiệt độ cao, rét, mã thời tiết dông; mỗi thành phần nội suy tuyến tính theo các mốc dưới đây.
A (cảnh báo) = trọng số mức cao nhất + 0,1 × tổng trọng số các cảnh báo còn lại.
Điểm = floor(min(100, max(W, A) + 0,25 × min(W, A))); level = số ngưỡng (20, 40, 60, 80) mà điểm đạt.
Mốc được chọn để một mình mỗi ngưỡng của /weather/derive-alerts cho đúng mức tương ứng
(gió giật 60 km/h → 60 → mức 3; 90 → 80 → mức 4; mưa 100 mm → 60; 38 °C → 40; 40 °C → 60),
và một cảnh báo chính thức đứng riêng cho đúng mức của nó (1→20, 2→40, 3→60, 4→85).
"""
from __future__ import annotations

import math
from dataclasses import dataclass

WIND_CURVE = ((30, 0), (60, 60), (90, 80), (118, 100))
RAIN_CURVE = ((20, 0), (50, 40), (100, 60), (200, 80), (300, 100))
HEAT_CURVE = ((33, 0), (35, 20), (38, 40), (40, 60), (42, 80), (45, 100))
COLD_CURVE = ((0, 60), (4, 50), (10, 30), (13, 10), (15, 0))
STORM_CODE_SCORE = {95: 40, 96: 50, 99: 50, 65: 25, 82: 25}
SEVERITY_WEIGHT = {1: 20, 2: 40, 3: 60, 4: 85}
LEVEL_THRESHOLDS = (20, 40, 60, 80)
SECONDARY_WEIGHT = 0.2
ALERT_EXTRA_WEIGHT = 0.1
COMBINE_WEIGHT = 0.25
MAX_SCORE = 100
FORMULA = "score = floor(min(100, max(W, A) + 0.25*min(W, A)))"
LABELS = {
    "wind": ("Gió giật {v:.0f} km/h", "Wind gusts {v:.0f} km/h"),
    "rain": ("Mưa {v:.0f} mm/ngày", "Rain {v:.0f} mm/day"),
    "heat": ("Nhiệt độ {v:.0f} °C", "Temperature {v:.0f} °C"),
    "cold": ("Nhiệt độ thấp nhất {v:.0f} °C", "Minimum temperature {v:.0f} °C"),
    "storm": ("Mã thời tiết dông/mưa nặng {v:.0f}", "Thunderstorm/heavy rain code {v:.0f}"),
}


@dataclass(frozen=True)
class WeatherInput:
    gust_kph: float | None = None
    rain_mm: float | None = None
    temp_c: float | None = None
    tmin_c: float | None = None
    weather_code: int | None = None


def interp(x: float | None, points: tuple[tuple[float, float], ...]) -> float:
    """Nội suy tuyến tính từng đoạn, kẹp hai đầu; x None → 0."""
    if x is None:
        return 0.0
    if x <= points[0][0]:
        return float(points[0][1])
    for (x0, y0), (x1, y1) in zip(points, points[1:]):
        if x <= x1:
            return y0 + (y1 - y0) * (x - x0) / (x1 - x0)
    return float(points[-1][1])


def level_for(score: float) -> int:
    return sum(1 for t in LEVEL_THRESHOLDS if score >= t)


def weather_components(w: WeatherInput) -> dict[str, float]:
    return {
        "wind": round(interp(w.gust_kph, WIND_CURVE), 2),
        "rain": round(interp(w.rain_mm, RAIN_CURVE), 2),
        "heat": round(interp(w.temp_c, HEAT_CURVE), 2),
        "cold": round(interp(w.tmin_c, COLD_CURVE), 2) if w.tmin_c is not None else 0.0,
        "storm": float(STORM_CODE_SCORE.get(int(w.weather_code), 0)) if w.weather_code is not None else 0.0,
    }


def weather_score(w: WeatherInput | None) -> tuple[float, dict[str, float]]:
    if w is None:
        return 0.0, {}
    comps = weather_components(w)
    ranked = sorted(comps.values(), reverse=True)
    return min(MAX_SCORE, ranked[0] + SECONDARY_WEIGHT * ranked[1]), comps


def alert_score(severities: list[int]) -> float:
    weights = sorted((SEVERITY_WEIGHT.get(int(s), 0) for s in severities), reverse=True)
    if not weights:
        return 0.0
    return min(MAX_SCORE, weights[0] + ALERT_EXTRA_WEIGHT * sum(weights[1:]))


def combine(w_score: float, a_score: float) -> int:
    raw = max(w_score, a_score) + COMBINE_WEIGHT * min(w_score, a_score)
    return int(math.floor(min(MAX_SCORE, raw) + 1e-9))


def _value_of(name: str, w: WeatherInput) -> float | None:
    return {"wind": w.gust_kph, "rain": w.rain_mm, "heat": w.temp_c, "cold": w.tmin_c,
            "storm": w.weather_code}[name]


def explain(w: WeatherInput | None, comps: dict[str, float], alerts: list[dict]) -> dict[str, str]:
    """Câu giải thích ngắn hai ngôn ngữ cho giao diện (các yếu tố có điểm > 0)."""
    vi, en = [], []
    for name, value in sorted(comps.items(), key=lambda kv: -kv[1]):
        if value <= 0 or w is None:
            continue
        v = float(_value_of(name, w))
        vi.append(LABELS[name][0].format(v=v))
        en.append(LABELS[name][1].format(v=v))
    if alerts:
        top = max(int(a["severity"]) for a in alerts)
        vi.append(f"{len(alerts)} cảnh báo đang hiệu lực (cao nhất mức {top})")
        en.append(f"{len(alerts)} active alert(s) (highest level {top})")
    return {"vi": "; ".join(vi) or "Không có yếu tố rủi ro đáng kể",
            "en": "; ".join(en) or "No significant risk factors"}


def trend(score: int, previous: int | None) -> dict:
    if previous is None:
        return {"previous": None, "delta": None, "direction": "new"}
    delta = score - int(previous)
    return {"previous": int(previous), "delta": delta,
            "direction": "up" if delta > 0 else ("down" if delta < 0 else "flat")}


def compute(weather: WeatherInput | None, alerts: list[dict], previous: int | None = None,
            weather_meta: dict | None = None) -> dict:
    """alerts: [{code, severity, hazard_code?}] đang hiệu lực phủ vùng. Trả {score, level, factors}."""
    w_score, comps = weather_score(weather)
    a_score = alert_score([a["severity"] for a in alerts])
    score = combine(w_score, a_score)
    factors = {
        "weather": {"available": weather is not None, "score": round(w_score, 2), "components": comps,
                    "inputs": {**(weather.__dict__ if weather else {}), **(weather_meta or {})}},
        "alerts": {"score": round(a_score, 2), "count": len(alerts), "items": alerts},
        "trend": trend(score, previous),
        "formula": FORMULA,
        "explain": explain(weather, comps, alerts),
    }
    return {"score": score, "level": level_for(score), "factors": factors}
