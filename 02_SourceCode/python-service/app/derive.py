"""Sinh cảnh báo nháp từ ngưỡng thời tiết (không ghi DB: Laravel lưu alerts status draft để cơ quan duyệt).

Ngưỡng: gió giật ≥ 60 km/h → mức 3 (≥ 90 → 4); mưa ≥ 100 mm/ngày → 3; nhiệt ≥ 38 °C → 2 (≥ 40 → 3);
weather_code ≥ 95 (dông) → 2. Mỗi (vùng, loại rủi ro) một nháp, gộp các ngày vượt ngưỡng trong cửa sổ.
"""
from __future__ import annotations

from dataclasses import dataclass
from datetime import date, datetime, time, timedelta

from sqlalchemy import select

from . import config, db
from .repo import ZoneRow, load_zones
from .schema import weather_forecasts, weather_observations, zones
from .timeutil import iso, now_local, to_date


@dataclass(frozen=True)
class Rule:
    hazard_code: str
    metric: str
    unit: str
    levels: tuple[tuple[float, int], ...]  # (ngưỡng, mức) giảm dần


RULES = (
    Rule("bao", "gust_kph", "km/h", ((90, 4), (60, 3))),
    Rule("mua_lon", "rain_mm", "mm", ((100, 3),)),
    Rule("nang_nong", "temp_c", "°C", ((40, 3), (38, 2))),
    Rule("dong_set", "weather_code", "WMO", ((95, 2),)),
)
TEXTS = {
    "bao": {
        "headline": ("Gió giật mạnh tới {v:.0f} km/h tại {zone}", "Strong wind gusts up to {v:.0f} km/h in {zone}"),
        "description": ("Dự báo Open-Meteo cho thấy gió giật tối đa {v:.0f} km/h ({days}). Nguy cơ cây đổ, "
                        "biển động, gián đoạn tàu thuyền và chuyến bay.",
                        "Open-Meteo forecasts maximum gusts of {v:.0f} km/h ({days}). Risk of falling trees, "
                        "rough seas and disrupted boat and flight services."),
        "instructions": ("Hạn chế ra biển, đi tàu, leo núi; tránh xa cây lớn, biển quảng cáo; theo dõi thông báo "
                         "của chính quyền địa phương.",
                         "Avoid sea trips, boat transfers and hiking; stay away from large trees and billboards; "
                         "follow local authority announcements."),
    },
    "mua_lon": {
        "headline": ("Mưa lớn {v:.0f} mm/ngày tại {zone}", "Heavy rain of {v:.0f} mm/day in {zone}"),
        "description": ("Lượng mưa dự báo {v:.0f} mm trong ngày ({days}). Nguy cơ ngập úng, lũ quét và sạt lở "
                        "đất ở vùng đồi núi.",
                        "Forecast rainfall of {v:.0f} mm in a day ({days}). Risk of flooding, flash floods and "
                        "landslides in hilly areas."),
        "instructions": ("Không đi qua ngầm, tràn, suối; tránh cung đường đèo dốc; chuẩn bị đèn pin, nước uống "
                         "và liên hệ đơn vị lữ hành để điều chỉnh lịch trình.",
                         "Do not cross flooded fords or streams; avoid mountain passes; keep a torch and water and "
                         "contact your tour operator to adjust the itinerary."),
    },
    "nang_nong": {
        "headline": ("Nắng nóng {v:.0f} °C tại {zone}", "Heat up to {v:.0f} °C in {zone}"),
        "description": ("Nhiệt độ cao nhất dự báo {v:.0f} °C ({days}). Nguy cơ say nắng, mất nước, đặc biệt "
                        "với người cao tuổi và trẻ nhỏ.",
                        "Maximum temperature forecast {v:.0f} °C ({days}). Risk of heatstroke and dehydration, "
                        "especially for the elderly and children."),
        "instructions": ("Tránh hoạt động ngoài trời 11–15 giờ, uống đủ nước, dùng mũ nón và kem chống nắng.",
                         "Avoid outdoor activities from 11:00 to 15:00, drink plenty of water, wear a hat and "
                         "sunscreen."),
    },
    "dong_set": {
        "headline": ("Dông, sét tại {zone}", "Thunderstorms and lightning in {zone}"),
        "description": ("Mã thời tiết {v:.0f} (dông) theo dự báo Open-Meteo ({days}). Có thể kèm lốc, mưa đá "
                        "và gió giật mạnh.",
                        "Weather code {v:.0f} (thunderstorm) forecast by Open-Meteo ({days}). Possible "
                        "whirlwinds, hail and strong gusts."),
        "instructions": ("Vào nhà hoặc xe kín khi có sấm sét; tránh bãi biển, đỉnh núi, cây đơn độc.",
                         "Go indoors or into a closed vehicle during lightning; avoid beaches, summits and "
                         "isolated trees."),
    },
}


def severity_for(rule: Rule, value: float | None) -> int:
    if value is None:
        return 0
    return next((sev for threshold, sev in rule.levels if value >= threshold), 0)


def day_values(forecasts: list[dict], obs: dict | None, today: date) -> dict[date, dict]:
    """Giá trị bất lợi nhất theo ngày; quan trắc hiện tại gộp vào hôm nay."""
    days: dict[date, dict] = {}
    for f in forecasts:
        d = to_date(f["forecast_date"])
        days[d] = {"gust_kph": f.get("gust_max_kph"), "rain_mm": f.get("rain_mm"), "temp_c": f.get("tmax_c"),
                   "weather_code": f.get("weather_code")}
    if obs:
        cur = days.setdefault(today, {})
        for key in ("gust_kph", "temp_c", "weather_code"):
            vals = [v for v in (cur.get(key), obs.get(key)) if v is not None]
            cur[key] = max(vals) if vals else None
    return days


def _span(day_hits: list[tuple[date, float, int]], now: datetime) -> tuple[datetime, datetime]:
    first, last = day_hits[0][0], day_hits[-1][0]
    return max(now, datetime.combine(first, time.min)), datetime.combine(last, time(23, 59, 59))


def _texts(hazard: str, value: float, zone: ZoneRow, names: dict, days: str) -> dict:
    out = {}
    for field, (vi, en) in TEXTS[hazard].items():
        out[field] = {"vi": vi.format(v=value, zone=names["vi"], days=days),
                      "en": en.format(v=value, zone=names["en"], days=days)}
    return out


def build_draft(rule: Rule, zone: ZoneRow, names: dict, hits: list[tuple[date, float, int]],
                now: datetime) -> dict:
    severity = max(h[2] for h in hits)
    peak = max(h[1] for h in hits)
    starts_at, ends_at = _span(hits, now)
    days = ", ".join(h[0].strftime("%d/%m") for h in hits)
    url = config.OPEN_METEO_SITE
    if zone.lat is not None:
        url = f"https://open-meteo.com/en/docs?latitude={zone.lat:.4f}&longitude={zone.lng:.4f}"
    return {
        "zone_code": zone.code, "hazard_code": rule.hazard_code, "severity": severity,
        **_texts(rule.hazard_code, peak, zone, names, days),
        "source": config.DERIVED_SOURCE, "source_name": config.DERIVED_SOURCE_NAME, "source_url": url,
        "issued_at": iso(now), "starts_at": iso(starts_at), "ends_at": iso(ends_at),
        "dedupe_key": f"{config.DERIVED_SOURCE}:{zone.code}:{rule.hazard_code}:{hits[0][0].isoformat()}",
        "raw": {"metric": rule.metric, "unit": rule.unit, "peak": peak,
                "thresholds": [{"value": t, "severity": s} for t, s in rule.levels],
                "days": [{"date": d.isoformat(), "value": v, "severity": s} for d, v, s in hits]},
    }


def derive_for_zone(zone: ZoneRow, names: dict, days: dict[date, dict], now: datetime) -> list[dict]:
    drafts = []
    for rule in RULES:
        hits = []
        for d in sorted(days):
            value = days[d].get(rule.metric)
            sev = severity_for(rule, None if value is None else float(value))
            if sev:
                hits.append((d, float(value), sev))
        if hits:
            drafts.append(build_draft(rule, zone, names, hits, now))
    return drafts


def _zone_names(conn) -> dict[int, dict]:
    rows = conn.execute(select(zones.c.id, zones.c.name, zones.c.name_en))
    return {int(r.id): {"vi": r.name or "", "en": r.name_en or r.name or ""} for r in rows}


def derive_all(codes: list[str] | None = None, horizon_days: int = 3, now: datetime | None = None) -> list[dict]:
    now = now or now_local()
    today = now.date()
    with db.get_engine().connect() as conn:
        target = load_zones(conn, codes)
        names = _zone_names(conn)
        fc_rows = conn.execute(select(weather_forecasts).where(
            weather_forecasts.c.forecast_date >= today,
            weather_forecasts.c.forecast_date < today + timedelta(days=horizon_days))).all()
        obs_rows = conn.execute(select(weather_observations).where(
            weather_observations.c.observed_at >= now - timedelta(hours=3)
        ).order_by(weather_observations.c.observed_at)).all()
    fcs: dict[int, list[dict]] = {}
    for r in fc_rows:
        fcs.setdefault(int(r.zone_id), []).append(r._asdict())
    obs = {int(r.zone_id): r._asdict() for r in obs_rows}
    drafts = []
    for z in target:
        days = day_values(fcs.get(z.id, []), obs.get(z.id), today)
        drafts.extend(derive_for_zone(z, names.get(z.id, {"vi": z.code, "en": z.code}), days, now))
    return sorted(drafts, key=lambda d: (-d["severity"], d["zone_code"], d["hazard_code"]))
