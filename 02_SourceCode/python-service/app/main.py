"""Ứng dụng FastAPI nội bộ của DiSanSo (Laravel gọi qua AnalyticsClient)."""
from __future__ import annotations

import asyncio
import logging
import os
import secrets
import time
from contextlib import asynccontextmanager, suppress
from dataclasses import dataclass
from typing import Literal

from fastapi import Depends, FastAPI, Header, HTTPException, status
from pydantic import BaseModel, Field

from . import db
from .quality import build_report
from .search import SearchIndex
from .stats import compute_overview
from .tags import TagSuggester

logger = logging.getLogger("disanso.analytics")
logging.basicConfig(level=os.getenv("LOG_LEVEL", "INFO").upper())

REINDEX_INTERVAL_SECONDS = int(os.getenv("REINDEX_INTERVAL_SECONDS", "600"))
# Khi chỉ mục còn rỗng (DB chưa seed xong lúc khởi động) thử lại nhanh hơn
EMPTY_RETRY_SECONDS = int(os.getenv("EMPTY_RETRY_SECONDS", "30"))
Locale = Literal["vi", "en"]
SubjectType = Literal["site", "artifact", "figure"]


@dataclass(frozen=True)
class EngineState:
    index: SearchIndex
    suggester: TagSuggester


def empty_state() -> EngineState:
    index = SearchIndex.empty()
    return EngineState(index=index, suggester=TagSuggester(index=index))


# Trạng thái được thay nguyên khối khi reindex để request đang chạy không thấy chỉ mục dở dang.
STATE: dict[str, EngineState] = {"current": empty_state()}
_reindex_lock = asyncio.Lock()


def build_state() -> EngineState:
    """Đọc DB và dựng chỉ mục mới (chạy trong thread vì tốn CPU/IO)."""
    index = SearchIndex.build(db.load_documents())
    return EngineState(index=index, suggester=TagSuggester.build(index, db.load_tags()))


async def refresh_index() -> EngineState:
    async with _reindex_lock:
        state = await asyncio.to_thread(build_state)
        STATE["current"] = state
        logger.info("Đã đánh chỉ mục %d tài liệu", state.index.size)
        return state


async def _safe_refresh() -> None:
    try:
        await refresh_index()
    except Exception:  # DB chưa sẵn sàng: giữ chỉ mục cũ/rỗng, không làm sập dịch vụ
        logger.exception("Không dựng được chỉ mục, giữ chỉ mục hiện tại")


async def _periodic_reindex() -> None:
    while True:
        interval = REINDEX_INTERVAL_SECONDS if STATE["current"].index.size > 0 else EMPTY_RETRY_SECONDS
        await asyncio.sleep(interval)
        await _safe_refresh()


async def _ensure_index() -> EngineState:
    """Chỉ mục rỗng (ví dụ seed xong sau khi dịch vụ khởi động) thì dựng lại ngay trước khi phục vụ."""
    if STATE["current"].index.size == 0:
        await _safe_refresh()
    return STATE["current"]


@asynccontextmanager
async def lifespan(_: FastAPI):
    if not os.getenv("ANALYTICS_TOKEN"):
        logger.warning("ANALYTICS_TOKEN chưa đặt: mọi endpoint có bảo vệ sẽ trả 401")
    await _safe_refresh()
    task = asyncio.create_task(_periodic_reindex())
    try:
        yield
    finally:
        task.cancel()
        with suppress(asyncio.CancelledError):
            await task


# Tắt trang tài liệu tương tác khi chạy production (chỉ bật ở môi trường phát triển)
_IS_PROD = os.getenv("APP_ENV", "production") == "production"
app = FastAPI(
    title="DiSanSo Analytics",
    version="1.0.0",
    lifespan=lifespan,
    docs_url=None if _IS_PROD else "/docs",
    redoc_url=None if _IS_PROD else "/redoc",
    openapi_url=None if _IS_PROD else "/openapi.json",
)


def require_token(x_analytics_token: str | None = Header(default=None)) -> None:
    """So sánh hằng thời gian; thiếu cấu hình token thì từ chối tất cả (fail closed)."""
    expected = os.getenv("ANALYTICS_TOKEN", "")
    if not expected or not x_analytics_token or not secrets.compare_digest(
        x_analytics_token.encode(), expected.encode()
    ):
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid analytics token")


class SearchRequest(BaseModel):
    q: str = Field(min_length=1, max_length=500)
    locale: Locale = "vi"
    limit: int = Field(default=20, ge=1, le=100)
    types: list[SubjectType] | None = None


class SuggestRequest(BaseModel):
    text: str = Field(min_length=1, max_length=20000)
    locale: Locale = "vi"
    limit: int = Field(default=5, ge=1, le=20)


def _ms(start: float) -> int:
    return int((time.perf_counter() - start) * 1000)


def _db_unavailable(exc: Exception) -> HTTPException:
    logger.error("Lỗi truy vấn DB: %s", exc)
    return HTTPException(status_code=status.HTTP_503_SERVICE_UNAVAILABLE, detail="Database unavailable")


@app.get("/healthz")
def healthz() -> dict:
    index = STATE["current"].index
    return {
        "status": "ok",
        "index_size": index.size,
        "indexed_at": index.indexed_at.isoformat() if index.indexed_at else None,
    }


@app.post("/search", dependencies=[Depends(require_token)])
async def search(body: SearchRequest) -> dict:
    start = time.perf_counter()
    state = await _ensure_index()
    results = state.index.search(body.q, body.locale, body.limit, body.types)
    return {"engine": "tfidf", "took_ms": _ms(start), "results": results}


@app.post("/suggest-tags", dependencies=[Depends(require_token)])
async def suggest_tags(body: SuggestRequest) -> dict:
    state = await _ensure_index()
    return {"suggestions": state.suggester.suggest_tags(body.text, body.locale, body.limit)}


@app.get("/quality-report", dependencies=[Depends(require_token)])
def quality_report() -> dict:
    try:
        tables = db.load_quality_tables()
    except Exception as exc:
        raise _db_unavailable(exc) from exc
    return build_report(tables)


@app.post("/reindex", dependencies=[Depends(require_token)])
async def reindex() -> dict:
    start = time.perf_counter()
    try:
        state = await refresh_index()
    except Exception as exc:
        raise _db_unavailable(exc) from exc
    return {"indexed": state.index.size, "took_ms": _ms(start)}


@app.get("/stats/overview", dependencies=[Depends(require_token)])
def stats_overview() -> dict:
    try:
        tables = db.load_stats_tables()
    except Exception as exc:
        raise _db_unavailable(exc) from exc
    return compute_overview(tables)
