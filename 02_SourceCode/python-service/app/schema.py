"""Định nghĩa SQLAlchemy Core cho các bảng dịch vụ đọc/ghi (theo data-model.md).

Chỉ khai báo cột dịch vụ dùng; bảng thật do migration Laravel tạo (có thể nhiều cột hơn).
Kiểm thử dùng `metadata.create_all()` trên SQLite in-memory.
"""
from __future__ import annotations

from sqlalchemy import (JSON, BigInteger, Boolean, Column, Date, DateTime, Float, ForeignKey, Integer,
                        MetaData, SmallInteger, String, Table, Text, UniqueConstraint)
from sqlalchemy.dialects import sqlite

metadata = MetaData()
# SQLite chỉ tự tăng với INTEGER PRIMARY KEY; MySQL dùng BIGINT UNSIGNED như Laravel
Id = BigInteger().with_variant(sqlite.INTEGER(), "sqlite")


def _pk() -> Column:
    return Column("id", Id, primary_key=True, autoincrement=True)


def _timestamps() -> tuple[Column, Column]:
    return Column("created_at", DateTime), Column("updated_at", DateTime)


provinces = Table(
    "provinces", metadata, _pk(),
    Column("code", String(20)), Column("name", String(120)), Column("name_en", String(120)),
    Column("region", String(40)), Column("lat", Float), Column("lng", Float), *_timestamps(),
)

zones = Table(
    "zones", metadata, _pk(),
    Column("code", String(60), unique=True), Column("zone_type", String(20)),
    Column("province_id", Id, ForeignKey("provinces.id")), Column("parent_id", Id),
    Column("name", String(160)), Column("name_en", String(160)), Column("geometry", JSON),
    Column("centroid_lat", Float), Column("centroid_lng", Float), Column("radius_km", Float),
    Column("source_name", String(160)), Column("source_url", String(255)),
    Column("is_active", Boolean, default=True), *_timestamps(),
)

destinations = Table(
    "destinations", metadata, _pk(),
    Column("slug", String(160), unique=True), Column("zone_id", Id), Column("province_id", Id),
    Column("dest_type", String(30)), Column("lat", Float), Column("lng", Float),
    Column("is_popular", Boolean, default=False), *_timestamps(),
)

trips = Table(
    "trips", metadata, _pk(),
    Column("code", String(20), unique=True), Column("user_id", Id), Column("partner_id", Id),
    Column("title", String(200)), Column("start_date", Date), Column("end_date", Date),
    Column("status", String(20)), Column("risk_level_cached", SmallInteger, default=0), *_timestamps(),
)

trip_legs = Table(
    "trip_legs", metadata, _pk(),
    Column("trip_id", Id, ForeignKey("trips.id")), Column("leg_order", Integer),
    Column("zone_id", Id), Column("destination_id", Id), Column("title", String(200)),
    Column("starts_at", DateTime), Column("ends_at", DateTime),
    Column("lat", Float), Column("lng", Float), *_timestamps(),
)

alert_sources = Table(
    "alert_sources", metadata, _pk(),
    Column("code", String(40), unique=True), Column("name", String(160)), Column("source_type", String(20)),
    Column("provider", String(20)), Column("last_polled_at", DateTime), Column("last_status", String(10)),
    Column("last_error", Text), Column("is_active", Boolean, default=True), *_timestamps(),
)

hazard_types = Table(
    "hazard_types", metadata, _pk(),
    Column("code", String(30), unique=True), Column("default_severity", SmallInteger), *_timestamps(),
)

alerts = Table(
    "alerts", metadata, _pk(),
    Column("code", String(20), unique=True), Column("source_id", Id), Column("hazard_type_id", Id),
    Column("severity", SmallInteger), Column("status", String(20)), Column("geometry", JSON),
    Column("starts_at", DateTime), Column("ends_at", DateTime), Column("issued_at", DateTime),
    Column("source_name", String(160)), Column("source_url", String(255)),
    Column("is_simulated", Boolean, default=False), *_timestamps(),
)

alert_zones = Table(
    "alert_zones", metadata,
    Column("alert_id", Id, primary_key=True), Column("zone_id", Id, primary_key=True),
)

weather_observations = Table(
    "weather_observations", metadata, _pk(),
    Column("zone_id", Id, nullable=False), Column("observed_at", DateTime, nullable=False),
    Column("temp_c", Float), Column("apparent_c", Float), Column("humidity", Float),
    Column("wind_kph", Float), Column("gust_kph", Float), Column("wind_dir", Float),
    Column("rain_mm", Float), Column("weather_code", SmallInteger), Column("source", String(40)),
    Column("fetched_at", DateTime), *_timestamps(),
    UniqueConstraint("zone_id", "observed_at"),
)

weather_forecasts = Table(
    "weather_forecasts", metadata, _pk(),
    Column("zone_id", Id, nullable=False), Column("forecast_date", Date, nullable=False),
    Column("tmin_c", Float), Column("tmax_c", Float), Column("rain_mm", Float), Column("rain_prob", Float),
    Column("wind_max_kph", Float), Column("gust_max_kph", Float), Column("weather_code", SmallInteger),
    Column("source", String(40)), Column("fetched_at", DateTime), *_timestamps(),
    UniqueConstraint("zone_id", "forecast_date"),
)

risk_scores = Table(
    "risk_scores", metadata, _pk(),
    Column("zone_id", Id, nullable=False), Column("computed_at", DateTime), Column("score", Integer),
    Column("level", SmallInteger), Column("factors", JSON), Column("source", String(40)), *_timestamps(),
)

alert_matches = Table(
    "alert_matches", metadata, _pk(),
    Column("alert_id", Id, nullable=False), Column("trip_id", Id, nullable=False),
    Column("leg_id", Id, nullable=False), Column("match_type", String(20)), Column("distance_km", Float),
    Column("overlap_from", DateTime), Column("overlap_to", DateTime),
    Column("status", String(20), default="new"), Column("acknowledged_at", DateTime), *_timestamps(),
    UniqueConstraint("alert_id", "leg_id"),
)

notifications = Table(
    "notifications", metadata, _pk(),
    Column("user_id", Id), Column("trip_id", Id), Column("alert_id", Id), Column("channel", String(10)),
    Column("level", SmallInteger), Column("status", String(20)), Column("attempts", Integer, default=0),
    *_timestamps(),
)

safety_checkins = Table(
    "safety_checkins", metadata, _pk(),
    Column("trip_id", Id), Column("user_id", Id), Column("alert_id", Id), Column("status", String(20)),
    Column("response_status", String(20)), Column("checked_at", DateTime), *_timestamps(),
)

emergency_contacts = Table(
    "emergency_contacts", metadata, _pk(),
    Column("province_id", Id), Column("zone_id", Id), Column("contact_type", String(30)),
    Column("phone", String(40)), Column("lat", Float), Column("lng", Float),
    Column("is_verified", Boolean, default=False), *_timestamps(),
)

response_guides = Table(
    "response_guides", metadata, _pk(),
    Column("slug", String(160), unique=True), Column("hazard_type_id", Id), Column("status", String(20)),
    *_timestamps(),
)

response_guide_translations = Table(
    "response_guide_translations", metadata, _pk(),
    Column("guide_id", Id), Column("locale", String(5)), Column("title", String(255)),
    Column("summary", Text), *_timestamps(),
)
