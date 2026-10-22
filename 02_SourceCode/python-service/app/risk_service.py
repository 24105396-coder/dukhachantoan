"""POST /risk/compute: gom thời tiết + cảnh báo theo vùng, tính điểm, ghi risk_scores."""
from __future__ import annotations

from datetime import date, datetime, timedelta

from sqlalchemy import insert, select
from sqlalchemy.engine import Connection

from . import db, risk
from .geo import GeometryError, parse_geometry
from .repo import ZoneRow, alert_zone_ids, effective_alerts, load_zones, with_descendants
from .schema import risk_scores, weather_forecasts, weather_observations
from .timeutil import iso, now_local, to_date

OBS_MAX_AGE_HOURS = 6
HORIZON_DAYS = 2  # hôm nay + ngày mai
SOURCE = "python-risk-v1"


def latest_observations(conn: Connection, now: datetime) -> dict[int, dict]:
    stmt = select(weather_observations).where(
        weather_observations.c.observed_at >= now - timedelta(hours=OBS_MAX_AGE_HOURS)
    ).order_by(weather_observations.c.observed_at)
    return {int(r.zone_id): r._asdict() for r in conn.execute(stmt)}


def horizon_forecasts(conn: Connection, today: date) -> dict[int, list[dict]]:
    stmt = select(weather_forecasts).where(
        weather_forecasts.c.forecast_date >= today,
        weather_forecasts.c.forecast_date < today + timedelta(days=HORIZON_DAYS))
    out: dict[int, list[dict]] = {}
    for r in conn.execute(stmt):
        out.setdefault(int(r.zone_id), []).append(r._asdict())
    return out


def previous_scores(conn: Connection, now: datetime) -> dict[int, int]:
    stmt = select(risk_scores.c.zone_id, risk_scores.c.score).where(
        risk_scores.c.computed_at >= now - timedelta(days=2)).order_by(risk_scores.c.computed_at, risk_scores.c.id)
    return {int(r.zone_id): int(r.score) for r in conn.execute(stmt)}


def _max(values) -> float | None:
    vals = [float(v) for v in values if v is not None]
    return max(vals) if vals else None


def weather_input(obs: dict | None, fcs: list[dict]) -> tuple[risk.WeatherInput | None, dict]:
    """Gộp quan trắc gần nhất và dự báo 48 giờ: lấy giá trị bất lợi nhất."""
    if obs is None and not fcs:
        return None, {}
    obs = obs or {}
    tmins = [float(f["tmin_c"]) for f in fcs if f.get("tmin_c") is not None]
    code = _max([obs.get("weather_code")] + [f.get("weather_code") for f in fcs])
    w = risk.WeatherInput(
        gust_kph=_max([obs.get("gust_kph")] + [f.get("gust_max_kph") for f in fcs]),
        rain_mm=_max(f.get("rain_mm") for f in fcs),
        temp_c=_max([obs.get("temp_c")] + [f.get("tmax_c") for f in fcs]),
        tmin_c=min(tmins) if tmins else None,
        weather_code=None if code is None else int(code),
    )
    meta = {"observed_at": iso(obs.get("observed_at")), "apparent_c": obs.get("apparent_c"),
            "forecast_dates": sorted(str(to_date(f["forecast_date"])) for f in fcs)}
    return w, meta


def zone_alerts(zone: ZoneRow, alerts: list[dict], covered: dict[int, set[int]]) -> list[dict]:
    """Cảnh báo phủ vùng: qua pivot (gồm vùng con) hoặc hình học chứa tâm vùng."""
    out = []
    for a in alerts:
        hit = zone.id in covered.get(a["id"], set())
        if not hit and a["geometry"] and zone.lat is not None:
            try:
                hit = parse_geometry(a["geometry"]).contains(zone.lat, zone.lng)
            except GeometryError:
                hit = False
        if hit:
            out.append({"code": a["code"], "severity": int(a["severity"]), "hazard_code": a["hazard_code"]})
    return out


def compute_all(codes: list[str] | None = None, now: datetime | None = None) -> list[dict]:
    now = now or now_local()
    with db.get_engine().begin() as conn:
        all_zones = load_zones(conn)
        targets = [z for z in all_zones if not codes or z.code in codes]
        obs, fcs = latest_observations(conn, now), horizon_forecasts(conn, now.date())
        prev = previous_scores(conn, now)
        alerts = effective_alerts(conn, now)
        pivot = alert_zone_ids(conn, [a["id"] for a in alerts])
        covered = {aid: with_descendants(zids, all_zones) for aid, zids in pivot.items()}
        results, rows = [], []
        for z in targets:
            w, meta = weather_input(obs.get(z.id), fcs.get(z.id, []))
            res = risk.compute(w, zone_alerts(z, alerts, covered), prev.get(z.id), meta)
            rows.append({"zone_id": z.id, "computed_at": now, "score": res["score"], "level": res["level"],
                         "factors": res["factors"], "source": SOURCE, "created_at": now, "updated_at": now})
            results.append({"zone_code": z.code, "score": res["score"], "level": res["level"],
                            "delta": res["factors"]["trend"]["delta"]})
        if rows:
            conn.execute(insert(risk_scores), rows)
    return results

