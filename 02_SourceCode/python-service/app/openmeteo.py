"""Ứng dụng khách Open-Meteo Forecast API: gộp nhiều tọa độ trong một request, cache 10 phút.

Tài liệu: https://open-meteo.com/en/docs (CC BY 4.0, không cần khóa). Khi truyền nhiều
`latitude`/`longitude` phân tách dấu phẩy, API trả mảng JSON theo đúng thứ tự tọa độ.
"""
from __future__ import annotations

import time
from dataclasses import dataclass, field

import httpx

from . import config
from .timeutil import to_date, to_dt

CURRENT_VARS = ("temperature_2m", "apparent_temperature", "relative_humidity_2m", "wind_speed_10m",
                "wind_gusts_10m", "wind_direction_10m", "precipitation", "weather_code")
DAILY_VARS = ("temperature_2m_min", "temperature_2m_max", "precipitation_sum", "precipitation_probability_max",
              "wind_speed_10m_max", "wind_gusts_10m_max", "weather_code")
# Tên biến Open-Meteo → cột weather_observations / weather_forecasts
CURRENT_MAP = {"temperature_2m": "temp_c", "apparent_temperature": "apparent_c",
               "relative_humidity_2m": "humidity", "wind_speed_10m": "wind_kph", "wind_gusts_10m": "gust_kph",
               "wind_direction_10m": "wind_dir", "precipitation": "rain_mm", "weather_code": "weather_code"}
DAILY_MAP = {"temperature_2m_min": "tmin_c", "temperature_2m_max": "tmax_c", "precipitation_sum": "rain_mm",
             "precipitation_probability_max": "rain_prob", "wind_speed_10m_max": "wind_max_kph",
             "wind_gusts_10m_max": "gust_max_kph", "weather_code": "weather_code"}
ERROR_MAX_LEN = 300


@dataclass(frozen=True)
class Location:
    zone_id: int
    code: str
    lat: float
    lng: float


@dataclass(frozen=True)
class ZoneWeather:
    zone_id: int
    code: str
    current: dict | None
    daily: tuple[dict, ...]


@dataclass
class FetchResult:
    fresh: list[ZoneWeather] = field(default_factory=list)
    cached: list[ZoneWeather] = field(default_factory=list)
    errors: list[str] = field(default_factory=list)


class WeatherCache:
    """Cache theo mã vùng + tọa độ; hết hạn sau `ttl` giây (đồng hồ monotonic)."""

    def __init__(self, ttl: int = config.WEATHER_CACHE_TTL_S, clock=time.monotonic):
        self.ttl, self.clock = ttl, clock
        self._items: dict[tuple, tuple[float, ZoneWeather]] = {}

    @staticmethod
    def key(loc: Location) -> tuple:
        return loc.code, round(loc.lat, 4), round(loc.lng, 4)

    def get(self, loc: Location) -> ZoneWeather | None:
        hit = self._items.get(self.key(loc))
        if hit is None or hit[0] < self.clock():
            return None
        return hit[1]

    def put(self, loc: Location, value: ZoneWeather) -> None:
        self._items[self.key(loc)] = (self.clock() + self.ttl, value)

    def clear(self) -> None:
        self._items.clear()


def _num(value):
    return None if value is None else float(value)


def parse_current(item: dict) -> dict | None:
    cur = item.get("current")
    if not cur or not cur.get("time"):
        return None
    row = {col: _num(cur.get(var)) for var, col in CURRENT_MAP.items()}
    row["weather_code"] = None if row["weather_code"] is None else int(row["weather_code"])
    return {"observed_at": to_dt(cur["time"]), **row}


def parse_daily(item: dict) -> tuple[dict, ...]:
    daily = item.get("daily") or {}
    days = daily.get("time") or []
    rows = []
    for i, day in enumerate(days):
        row = {col: _num((daily.get(var) or [None] * len(days))[i]) for var, col in DAILY_MAP.items()}
        row["weather_code"] = None if row["weather_code"] is None else int(row["weather_code"])
        rows.append({"forecast_date": to_date(day), **row})
    return tuple(rows)


def build_params(locs: list[Location]) -> dict:
    return {
        "latitude": ",".join(f"{loc.lat:.4f}" for loc in locs),
        "longitude": ",".join(f"{loc.lng:.4f}" for loc in locs),
        "current": ",".join(CURRENT_VARS),
        "daily": ",".join(DAILY_VARS),
        "timezone": "Asia/Ho_Chi_Minh",
        "forecast_days": config.FORECAST_DAYS,
        "wind_speed_unit": "kmh",
    }


async def fetch_batch(client: httpx.AsyncClient, locs: list[Location]) -> list[ZoneWeather]:
    resp = await client.get(config.OPEN_METEO_URL, params=build_params(locs))
    resp.raise_for_status()
    data = resp.json()
    items = data if isinstance(data, list) else [data]
    if len(items) != len(locs):
        raise ValueError(f"Open-Meteo trả {len(items)} điểm, cần {len(locs)}")
    return [ZoneWeather(loc.zone_id, loc.code, parse_current(it), parse_daily(it)) for loc, it in zip(locs, items)]


def _describe(exc: Exception) -> str:
    return f"{type(exc).__name__}: {exc}"[:ERROR_MAX_LEN]


async def fetch_all(locs: list[Location], cache: WeatherCache, force: bool = False,
                    transport: httpx.AsyncBaseTransport | None = None) -> FetchResult:
    """Lấy thời tiết cho mọi vùng; lỗi mạng/định dạng được gom vào `errors`, không ném ra ngoài."""
    result = FetchResult()
    pending = []
    for loc in locs:
        hit = None if force else cache.get(loc)
        if hit is None:
            pending.append(loc)
        else:
            result.cached.append(hit)
    if not pending:
        return result
    async with httpx.AsyncClient(timeout=config.OPEN_METEO_TIMEOUT_S, transport=transport,
                                 headers={"User-Agent": "DuKhachAnToan/1.0 (CSE703073)"}) as client:
        for start in range(0, len(pending), config.OPEN_METEO_BATCH):
            batch = pending[start:start + config.OPEN_METEO_BATCH]
            try:
                rows = await fetch_batch(client, batch)
            except (httpx.HTTPError, ValueError, KeyError, TypeError, IndexError) as exc:
                result.errors.append(_describe(exc))
                continue
            for loc, zw in zip(batch, rows):
                cache.put(loc, zw)
            result.fresh.extend(rows)
    return result
