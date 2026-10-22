"""Fixture dùng chung: SQLite in-memory dựng từ app.schema, dữ liệu vùng mẫu, TestClient có token."""
from __future__ import annotations

import os
import sys
from datetime import datetime, timedelta
from pathlib import Path

import pytest

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))
os.environ["ANALYTICS_TOKEN"] = "test-token"
os.environ.setdefault("APP_ENV", "testing")

from fastapi.testclient import TestClient  # noqa: E402
from sqlalchemy import create_engine, insert  # noqa: E402
from sqlalchemy.pool import StaticPool  # noqa: E402

from app import db, schema, weather  # noqa: E402
from app.main import app  # noqa: E402
from app.timeutil import now_local  # noqa: E402

TOKEN = "test-token"


def square(lat_min: float, lat_max: float, lng_min: float, lng_max: float) -> dict:
    ring = [[lng_min, lat_min], [lng_max, lat_min], [lng_max, lat_max], [lng_min, lat_max], [lng_min, lat_min]]
    return {"type": "Polygon", "coordinates": [ring]}


class Seeder:
    """Chèn bản ghi nhanh, trả id; mặc định created_at/updated_at = now."""

    def __init__(self, engine):
        self.engine = engine
        self.now = now_local()

    def add(self, table, **values) -> int:
        cols = table.c.keys()
        if "created_at" in cols:
            values.setdefault("created_at", self.now)
            values.setdefault("updated_at", self.now)
        with self.engine.begin() as conn:
            res = conn.execute(insert(table).values(**values))
            return res.inserted_primary_key[0] if res.inserted_primary_key else None

    def base_geo(self) -> dict:
        """Hai tỉnh, vùng con, vùng tròn và điểm đến dùng cho đa số test."""
        ids = {
            "p_kh": self.add(schema.provinces, code="KHA", name="Khánh Hòa", name_en="Khanh Hoa", lat=12.25,
                             lng=109.19),
            "p_lc": self.add(schema.provinces, code="LCA", name="Lào Cai", name_en="Lao Cai", lat=22.48,
                             lng=103.97),
        }
        ids["z_kh"] = self.add(schema.zones, code="khanh-hoa", zone_type="province", province_id=ids["p_kh"],
                               name="Khánh Hòa", name_en="Khanh Hoa", geometry=square(11.8, 12.9, 108.6, 109.5),
                               centroid_lat=12.25, centroid_lng=109.05, is_active=True)
        ids["z_nt"] = self.add(schema.zones, code="nha-trang", zone_type="urban", province_id=ids["p_kh"],
                               parent_id=ids["z_kh"], name="Nha Trang", name_en="Nha Trang",
                               geometry=square(12.15, 12.32, 109.1, 109.25), centroid_lat=12.24,
                               centroid_lng=109.19, is_active=True)
        ids["z_lc"] = self.add(schema.zones, code="lao-cai", zone_type="province", province_id=ids["p_lc"],
                               name="Lào Cai", name_en="Lao Cai", geometry=square(21.8, 22.9, 103.5, 104.6),
                               centroid_lat=22.3, centroid_lng=104.0, is_active=True)
        ids["z_pq"] = self.add(schema.zones, code="phu-quoc", zone_type="island", name="Phú Quốc",
                               name_en="Phu Quoc", centroid_lat=10.22, centroid_lng=103.96, radius_km=30,
                               is_active=True)
        ids["d_vinpearl"] = self.add(schema.destinations, slug="dao-hon-tre", zone_id=ids["z_nt"],
                                     province_id=ids["p_kh"], dest_type="dao", lat=12.21, lng=109.24)
        return ids

    def trip(self, status: str = "active", code: str = "TR-0001") -> int:
        today = self.now.date()
        return self.add(schema.trips, code=code, user_id=1, title="Du lịch", start_date=today,
                        end_date=today + timedelta(days=5), status=status)

    def leg(self, trip_id: int, order: int, start: datetime, hours: int = 24, **kw) -> int:
        return self.add(schema.trip_legs, trip_id=trip_id, leg_order=order, starts_at=start,
                        ends_at=start + timedelta(hours=hours), title=f"Chặng {order}", **kw)

    def alert(self, code: str = "AL-0001", severity: int = 3, status: str = "active", zone_ids=(), **kw) -> int:
        kw.setdefault("starts_at", self.now - timedelta(hours=1))
        kw.setdefault("ends_at", self.now + timedelta(days=2))
        kw.setdefault("issued_at", self.now - timedelta(hours=1))
        kw.setdefault("source_name", "Trung tâm Dự báo KTTV Quốc gia")
        aid = self.add(schema.alerts, code=code, severity=severity, status=status, **kw)
        for zid in zone_ids:
            self.add(schema.alert_zones, alert_id=aid, zone_id=zid)
        return aid


@pytest.fixture
def engine():
    eng = create_engine("sqlite://", poolclass=StaticPool, connect_args={"check_same_thread": False})
    schema.metadata.create_all(eng)
    db.use_engine(eng)
    weather.CACHE.clear()
    weather.STATUS["last_fetch"] = None
    yield eng
    db.use_engine(None)
    weather.CACHE.clear()
    weather.STATUS["last_fetch"] = None


@pytest.fixture
def seed(engine) -> Seeder:
    return Seeder(engine)


@pytest.fixture
def client(engine):
    with TestClient(app, headers={"X-Analytics-Token": TOKEN}) as c:
        yield c


@pytest.fixture
def anon_client(engine):
    with TestClient(app) as c:
        yield c
