"""Ứng dụng FastAPI nội bộ của DuKhachAnToan (Laravel gọi qua AnalyticsClient, header X-Analytics-Token)."""
from __future__ import annotations

import asyncio
import logging
import os
import secrets
from contextlib import asynccontextmanager
from typing import Annotated

from fastapi import Depends, FastAPI, Header, HTTPException, Request, status
from fastapi.responses import JSONResponse
from pydantic import BaseModel, Field, StringConstraints
from sqlalchemy.exc import SQLAlchemyError

from . import __version__, config, db, derive, matching, risk_service, stats, weather
from .geo import GeometryError, contains_points
from .timeutil import iso, now_local

logger = logging.getLogger("dukhach.analytics")
logging.basicConfig(level=os.getenv("LOG_LEVEL", "INFO").upper())

ZoneCode = Annotated[str, StringConstraints(min_length=1, max_length=60)]
MAX_ZONES = 500
MAX_POINTS = 5000


@asynccontextmanager
async def lifespan(_: FastAPI):
    if not config.analytics_token():
        logger.warning("ANALYTICS_TOKEN chưa đặt: mọi endpoint có bảo vệ sẽ trả 401")
    yield


_IS_PROD = os.getenv("APP_ENV", "production") == "production"
app = FastAPI(
    title="DuKhachAnToan Analytics", version=__version__, lifespan=lifespan,
    docs_url=None if _IS_PROD else "/docs", redoc_url=None if _IS_PROD else "/redoc",
    openapi_url=None if _IS_PROD else "/openapi.json",
)


def require_token(x_analytics_token: str | None = Header(default=None)) -> None:
    """So sánh hằng thời gian; thiếu cấu hình token thì từ chối tất cả (fail closed)."""
    expected = config.analytics_token()
    if not expected or not x_analytics_token or not secrets.compare_digest(
        x_analytics_token.encode(), expected.encode()
    ):
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid analytics token")


@app.exception_handler(SQLAlchemyError)
async def db_error_handler(_: Request, exc: SQLAlchemyError) -> JSONResponse:
    logger.error("Lỗi cơ sở dữ liệu: %s", type(exc).__name__)
    return JSONResponse(status_code=503, content={"detail": "Database unavailable"})


class ZonesRequest(BaseModel):
    zone_codes: list[ZoneCode] | None = Field(default=None, max_length=MAX_ZONES)


class RefreshRequest(ZonesRequest):
    force: bool = False


class DeriveRequest(ZonesRequest):
    days: int = Field(default=3, ge=1, le=config.FORECAST_DAYS)


class MatchRequest(BaseModel):
    alert_id: int = Field(gt=0)
    trip_id: int | None = Field(default=None, gt=0)


class GeoPoint(BaseModel):
    id: int | str
    lat: float = Field(ge=-90, le=90)
    lng: float = Field(ge=-180, le=180)


class ContainsRequest(BaseModel):
    geometry: dict
    points: list[GeoPoint] = Field(max_length=MAX_POINTS)


Protected = [Depends(require_token)]


@app.get("/healthz")
async def healthz() -> dict:
    up = await asyncio.to_thread(db.ping)
    last = weather.STATUS["last_fetch"]
    if last is None and up:
        try:
            last = await asyncio.to_thread(weather.last_fetch_from_db)
        except SQLAlchemyError:
            last = None
    return {"status": "ok", "db": "up" if up else "down", "last_fetch": iso(last),
            "version": __version__, "updated_at": iso(now_local())}


@app.post("/weather/refresh", dependencies=Protected)
async def weather_refresh(body: RefreshRequest | None = None) -> dict:
    body = body or RefreshRequest()
    return await weather.refresh(body.zone_codes, body.force)


@app.post("/risk/compute", dependencies=Protected)
async def risk_compute(body: ZonesRequest | None = None) -> list[dict]:
    return await asyncio.to_thread(risk_service.compute_all, (body or ZonesRequest()).zone_codes)


@app.post("/match", dependencies=Protected)
async def match(body: MatchRequest) -> dict:
    try:
        return await asyncio.to_thread(matching.match_alert, body.alert_id, body.trip_id)
    except matching.AlertNotFound as exc:
        raise HTTPException(status_code=404, detail="Alert not found") from exc


@app.post("/geo/contains", dependencies=Protected)
def geo_contains(body: ContainsRequest) -> dict:
    try:
        inside, distances = contains_points(body.geometry, [p.model_dump() for p in body.points])
    except GeometryError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
    return {"inside": inside, "count": len(inside), "distances_km": distances}


@app.post("/weather/derive-alerts", dependencies=Protected)
async def derive_alerts(body: DeriveRequest | None = None) -> dict:
    body = body or DeriveRequest()
    drafts = await asyncio.to_thread(derive.derive_all, body.zone_codes, body.days)
    return {"drafts": drafts, "count": len(drafts), "generated_at": iso(now_local())}


@app.get("/stats/overview", dependencies=Protected)
async def stats_overview() -> dict:
    return await asyncio.to_thread(stats.overview)
