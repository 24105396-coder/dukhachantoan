"""Làm mới thời tiết: đọc tâm vùng, gọi Open-Meteo, upsert weather_observations / weather_forecasts."""
from __future__ import annotations

import asyncio
import time
from datetime import datetime

from sqlalchemy import func, select, update
from sqlalchemy.engine import Connection

from . import config, db
from .openmeteo import FetchResult, Location, WeatherCache, ZoneWeather, fetch_all
from .schema import alert_sources, weather_forecasts, weather_observations, zones
from .timeutil import iso, now_local

CACHE = WeatherCache()
STATUS: dict[str, datetime | None] = {"last_fetch": None}
OBS_COLS = ("temp_c", "apparent_c", "humidity", "wind_kph", "gust_kph", "wind_dir", "rain_mm", "weather_code")
FC_COLS = ("tmin_c", "tmax_c", "rain_mm", "rain_prob", "wind_max_kph", "gust_max_kph", "weather_code")


def load_locations(conn: Connection, codes: list[str] | None) -> list[Location]:
    stmt = select(zones.c.id, zones.c.code, zones.c.centroid_lat, zones.c.centroid_lng).where(
        zones.c.is_active.is_(True), zones.c.centroid_lat.is_not(None), zones.c.centroid_lng.is_not(None))
    if codes:
        stmt = stmt.where(zones.c.code.in_(codes))
    rows = conn.execute(stmt.order_by(zones.c.id)).all()
    return [Location(r.id, r.code, float(r.centroid_lat), float(r.centroid_lng)) for r in rows]


def observation_rows(items: list[ZoneWeather], fetched_at: datetime) -> list[dict]:
    meta = {"source": config.SOURCE_CODE, "fetched_at": fetched_at, "created_at": fetched_at,
            "updated_at": fetched_at}
    return [{"zone_id": zw.zone_id, **zw.current, **meta} for zw in items if zw.current]


def forecast_rows(items: list[ZoneWeather], fetched_at: datetime) -> list[dict]:
    meta = {"source": config.SOURCE_CODE, "fetched_at": fetched_at, "created_at": fetched_at,
            "updated_at": fetched_at}
    return [{"zone_id": zw.zone_id, **day, **meta} for zw in items for day in zw.daily]


def save(conn: Connection, items: list[ZoneWeather], fetched_at: datetime) -> tuple[int, int]:
    obs = observation_rows(items, fetched_at)
    fcs = forecast_rows(items, fetched_at)
    upd = ("source", "fetched_at", "updated_at")
    db.upsert(conn, weather_observations, obs, ("zone_id", "observed_at"), OBS_COLS + upd)
    db.upsert(conn, weather_forecasts, fcs, ("zone_id", "forecast_date"), FC_COLS + upd)
    return len(obs), len(fcs)


def mark_source(conn: Connection, now: datetime, errors: list[str]) -> None:
    """Ghi trạng thái lần lấy vào alert_sources (provider openmeteo) nếu bản ghi đã tồn tại."""
    conn.execute(update(alert_sources).where(alert_sources.c.provider == config.SOURCE_CODE).values(
        last_polled_at=now, last_status="error" if errors else "ok",
        last_error="; ".join(errors)[:1000] if errors else None, updated_at=now))


def last_fetch_from_db() -> datetime | None:
    with db.get_engine().connect() as conn:
        return conn.execute(select(func.max(weather_observations.c.fetched_at))).scalar()


def _persist(result: FetchResult) -> tuple[int, int]:
    now = now_local()
    with db.get_engine().begin() as conn:
        counts = save(conn, result.fresh, now)
        mark_source(conn, now, result.errors)
    if result.fresh:
        STATUS["last_fetch"] = now
    return counts


def _load(codes: list[str] | None) -> list[Location]:
    with db.get_engine().connect() as conn:
        return load_locations(conn, codes)


async def refresh(codes: list[str] | None = None, force: bool = False, transport=None) -> dict:
    """Quy trình POST /weather/refresh. Lỗi mạng → fetched=0 + error; lỗi DB để tầng API trả 503."""
    start = time.perf_counter()
    locs = await asyncio.to_thread(_load, codes)
    result = await fetch_all(locs, CACHE, force=force, transport=transport)
    obs, fcs = await asyncio.to_thread(_persist, result)
    body = {
        "fetched": len(result.fresh), "cached": len(result.cached), "zones": len(locs),
        "observations": obs, "forecasts": fcs, "took_ms": int((time.perf_counter() - start) * 1000),
        "last_fetch": iso(STATUS["last_fetch"]),
    }
    if result.errors:
        body["error"] = "; ".join(result.errors)
    return body
