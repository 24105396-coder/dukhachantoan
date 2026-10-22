"""Truy vấn dùng chung (SQLAlchemy Core, tham số hóa) cho rủi ro, đối sánh và suy diễn cảnh báo."""
from __future__ import annotations

from collections import defaultdict
from dataclasses import dataclass
from datetime import datetime, timedelta

from sqlalchemy import or_, select
from sqlalchemy.engine import Connection

from .db import load_json
from .schema import alert_zones, alerts, hazard_types, zones

ACTIVE_ALERT_STATUSES = ("active", "updated")


@dataclass(frozen=True)
class ZoneRow:
    id: int
    code: str
    parent_id: int | None
    province_id: int | None
    lat: float | None
    lng: float | None
    radius_km: float | None
    geometry: dict | None


def load_zones(conn: Connection, codes: list[str] | None = None, active_only: bool = True) -> list[ZoneRow]:
    stmt = select(zones.c.id, zones.c.code, zones.c.parent_id, zones.c.province_id, zones.c.centroid_lat,
                  zones.c.centroid_lng, zones.c.radius_km, zones.c.geometry)
    if active_only:
        stmt = stmt.where(zones.c.is_active.is_(True))
    if codes:
        stmt = stmt.where(zones.c.code.in_(codes))
    return [ZoneRow(r.id, r.code, r.parent_id, r.province_id,
                    None if r.centroid_lat is None else float(r.centroid_lat),
                    None if r.centroid_lng is None else float(r.centroid_lng),
                    None if r.radius_km is None else float(r.radius_km), load_json(r.geometry))
            for r in conn.execute(stmt.order_by(zones.c.id))]


def with_descendants(zone_ids: set[int], all_zones: list[ZoneRow]) -> set[int]:
    """Cảnh báo cho vùng cha (tỉnh) phủ cả vùng con (Sa Pa thuộc Lào Cai)."""
    children: dict[int, list[int]] = defaultdict(list)
    for z in all_zones:
        if z.parent_id is not None:
            children[int(z.parent_id)].append(z.id)
    result, stack = set(zone_ids), list(zone_ids)
    while stack:
        for child in children.get(stack.pop(), []):
            if child not in result:
                result.add(child)
                stack.append(child)
    return result


def alert_zone_ids(conn: Connection, alert_ids: list[int]) -> dict[int, set[int]]:
    out: dict[int, set[int]] = defaultdict(set)
    if not alert_ids:
        return out
    stmt = select(alert_zones.c.alert_id, alert_zones.c.zone_id).where(alert_zones.c.alert_id.in_(alert_ids))
    for r in conn.execute(stmt):
        out[int(r.alert_id)].add(int(r.zone_id))
    return out


def _alert_select():
    return select(alerts.c.id, alerts.c.code, alerts.c.severity, alerts.c.status, alerts.c.geometry,
                  alerts.c.starts_at, alerts.c.ends_at, alerts.c.issued_at, hazard_types.c.code.label("hazard_code")
                  ).select_from(alerts.outerjoin(hazard_types, hazard_types.c.id == alerts.c.hazard_type_id))


def effective_alerts(conn: Connection, now: datetime, horizon_hours: int = 24) -> list[dict]:
    """Cảnh báo active/updated chưa hết hạn và bắt đầu trong `horizon_hours` tới."""
    stmt = _alert_select().where(
        alerts.c.status.in_(ACTIVE_ALERT_STATUSES),
        or_(alerts.c.ends_at.is_(None), alerts.c.ends_at > now),
        or_(alerts.c.starts_at.is_(None), alerts.c.starts_at <= now + timedelta(hours=horizon_hours)),
    )
    return [{**r._asdict(), "geometry": load_json(r.geometry)} for r in conn.execute(stmt.order_by(alerts.c.id))]


def get_alert(conn: Connection, alert_id: int) -> dict | None:
    row = conn.execute(_alert_select().where(alerts.c.id == alert_id)).first()
    return None if row is None else {**row._asdict(), "geometry": load_json(row.geometry)}
