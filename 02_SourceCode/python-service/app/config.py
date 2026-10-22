"""Cấu hình đọc từ biến môi trường (không hard-code bí mật)."""
from __future__ import annotations

import os
from zoneinfo import ZoneInfo

APP_TZ = ZoneInfo(os.getenv("APP_TIMEZONE", "Asia/Ho_Chi_Minh"))
OPEN_METEO_URL = os.getenv("OPEN_METEO_URL", "https://api.open-meteo.com/v1/forecast")
OPEN_METEO_TIMEOUT_S = float(os.getenv("OPEN_METEO_TIMEOUT", "10"))
WEATHER_CACHE_TTL_S = int(os.getenv("WEATHER_CACHE_TTL", "600"))
# Open-Meteo nhận nhiều tọa độ phân tách dấu phẩy; giới hạn lô để URL không quá dài
OPEN_METEO_BATCH = int(os.getenv("OPEN_METEO_BATCH", "50"))
FORECAST_DAYS = 7
SOURCE_CODE = "openmeteo"
DERIVED_SOURCE = "openmeteo-derived"
DERIVED_SOURCE_NAME = "Open-Meteo (suy diễn tự động)"
OPEN_METEO_SITE = "https://open-meteo.com/"


def analytics_token() -> str:
    return os.getenv("ANALYTICS_TOKEN", "")
