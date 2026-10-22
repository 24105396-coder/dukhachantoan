"""Thời gian: DB lưu datetime không múi giờ theo giờ Việt Nam (APP_TIMEZONE của Laravel)."""
from __future__ import annotations

from datetime import date, datetime

from .config import APP_TZ


def now_local() -> datetime:
    """Giờ hiện tại theo APP_TZ, bỏ tzinfo, làm tròn giây (khớp cột DATETIME)."""
    return datetime.now(APP_TZ).replace(tzinfo=None, microsecond=0)


def to_dt(value) -> datetime | None:
    """Chuẩn hóa datetime/date/chuỗi ISO (SQLite trả chuỗi) về datetime không múi giờ."""
    if value is None or value == "":
        return None
    if isinstance(value, datetime):
        return value.astimezone(APP_TZ).replace(tzinfo=None) if value.tzinfo else value
    if isinstance(value, date):
        return datetime(value.year, value.month, value.day)
    text = str(value).strip().replace("Z", "+00:00")
    parsed = datetime.fromisoformat(text)
    return parsed.astimezone(APP_TZ).replace(tzinfo=None) if parsed.tzinfo else parsed


def to_date(value) -> date | None:
    dt = to_dt(value)
    return dt.date() if dt else None


def iso(value) -> str | None:
    dt = to_dt(value)
    return dt.isoformat() if dt else None
