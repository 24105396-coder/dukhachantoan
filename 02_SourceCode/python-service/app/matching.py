"""POST /match: đối sánh một cảnh báo với các chặng hành trình đang hoạt động, ghi alert_matches.

Quy tắc: cảnh báo có `geometry` (Polygon/MultiPolygon/Circle) → chỉ xét hình học với điểm chặng
(match_type=geometry); không có geometry → xét vùng qua pivot alert_zones gồm cả vùng con: chặng thuộc
vùng (zone) hoặc điểm đến của chặng thuộc vùng (destination). Chặng phải giao thời gian với cảnh báo.
Điểm chặng = lat/lng của chặng, nếu thiếu thì của điểm đến, cuối cùng là tâm vùng.
"""
from __future__ import annotations

import logging
from dataclasses import dataclass
from datetime import datetime, time

from sqlalchemy import insert, select, update
from sqlalchemy.engine import Connection

from . import db
from .geo import Circle, GeometryError, Shape, parse_geometry
from .repo import ACTIVE_ALERT_STATUSES, alert_zone_ids, get_alert, load_zones, with_descendants
from .schema import alert_matches, destinations, trip_legs, trips, zones
from .timeutil import iso, now_local, to_date, to_dt

logger = logging.getLogger("dukhach.analytics.match")
FAR_PAST, FAR_FUTURE = datetime(1970, 1, 1), datetime(9999, 12, 31)


class AlertNotFound(LookupError):
    pass


@dataclass(frozen=True)
class Leg:
    id: int
    trip_id: int
    zone_id: int | None
    dest_zone_id: int | None
    starts_at: datetime
    ends_at: datetime
    lat: float | None
    lng: float | None
    point_source: str | None


def _point(r) -> tuple[float | None, float | None, str | None]:
    for lat, lng, src in ((r.lat, r.lng, "leg"), (r.dest_lat, r.dest_lng, "destination"),
                          (r.zone_lat, r.zone_lng, "zone_centroid")):
        if lat is not None and lng is not None:
            return float(lat), float(lng), src
    return None, None, None


def _window(r) -> tuple[datetime, datetime]:
    start = to_dt(r.starts_at)
    end = to_dt(r.ends_at)
    if start is None:
        d = to_date(r.start_date)
        start = datetime.combine(d, time.min) if d else FAR_PAST
    if end is None:
        d = to_date(r.end_date)
        end = datetime.combine(d, time(23, 59, 59)) if d else FAR_FUTURE
    return start, end


def load_legs(conn: Connection, trip_id: int | None = None) -> list[Leg]:
    leg_zone = zones.alias("leg_zone")
    stmt = select(
        trip_legs.c.id, trip_legs.c.trip_id, trip_legs.c.zone_id, trip_legs.c.starts_at, trip_legs.c.ends_at,
        trip_legs.c.lat, trip_legs.c.lng, trips.c.start_date, trips.c.end_date,
        destinations.c.zone_id.label("dest_zone_id"), destinations.c.lat.label("dest_lat"),
        destinations.c.lng.label("dest_lng"), leg_zone.c.centroid_lat.label("zone_lat"),
        leg_zone.c.centroid_lng.label("zone_lng"),
    ).select_from(
        trip_legs.join(trips, trips.c.id == trip_legs.c.trip_id)
        .outerjoin(destinations, destinations.c.id == trip_legs.c.destination_id)
        .outerjoin(leg_zone, leg_zone.c.id == trip_legs.c.zone_id)
    ).where(trips.c.status == "active")
    if trip_id is not None:
        stmt = stmt.where(trip_legs.c.trip_id == trip_id)
    legs = []
    for r in conn.execute(stmt.order_by(trip_legs.c.trip_id, trip_legs.c.leg_order, trip_legs.c.id)):
        start, end = _window(r)
        lat, lng, src = _point(r)
        legs.append(Leg(int(r.id), int(r.trip_id), r.zone_id, r.dest_zone_id, start, end, lat, lng, src))
    return legs


def overlap(leg: Leg, a_start: datetime, a_end: datetime) -> tuple[datetime, datetime] | None:
    start, end = max(leg.starts_at, a_start), min(leg.ends_at, a_end)
    return (start, end) if start < end else None


def decide(leg: Leg, shape: Shape | None, zone_set: set[int]) -> tuple[str, float | None] | None:
    if shape is not None:
        if leg.lat is None or not shape.contains(leg.lat, leg.lng):
            return None
        dist = shape.distance_km(leg.lat, leg.lng) if isinstance(shape, Circle) else 0.0
        return "geometry", round(dist, 3)
    if leg.zone_id is not None and int(leg.zone_id) in zone_set:
        return "zone", None
    if leg.dest_zone_id is not None and int(leg.dest_zone_id) in zone_set:
        return "destination", None
    return None


def _alert_shape(alert: dict) -> Shape | None:
    if not alert["geometry"]:
        return None
    try:
        return parse_geometry(alert["geometry"])
    except GeometryError as exc:  # hình học hỏng: rơi về đối sánh theo vùng
        logger.warning("Cảnh báo %s có geometry lỗi (%s), dùng zones", alert["code"], exc)
        return None


def find_matches(conn: Connection, alert: dict, trip_id: int | None = None) -> list[dict]:
    shape = _alert_shape(alert)
    zone_set: set[int] = set()
    if shape is None:
        pivot = alert_zone_ids(conn, [alert["id"]]).get(alert["id"], set())
        zone_set = with_descendants(pivot, load_zones(conn, active_only=False))
    a_start = to_dt(alert["starts_at"]) or to_dt(alert["issued_at"]) or FAR_PAST
    a_end = to_dt(alert["ends_at"]) or FAR_FUTURE
    out = []
    for leg in load_legs(conn, trip_id):
        window = overlap(leg, a_start, a_end)
        verdict = decide(leg, shape, zone_set) if window else None
        if verdict:
            out.append({"trip_id": leg.trip_id, "leg_id": leg.id, "match_type": verdict[0],
                        "distance_km": verdict[1], "overlap_from": window[0], "overlap_to": window[1],
                        "point_source": leg.point_source})
    return out


def save_matches(conn: Connection, alert_id: int, found: list[dict], now: datetime) -> list[dict]:
    """Idempotent theo unique(alert_id, leg_id): cập nhật thông tin hình học, giữ nguyên status."""
    existing = {int(r.leg_id): r.id for r in conn.execute(
        select(alert_matches.c.id, alert_matches.c.leg_id).where(alert_matches.c.alert_id == alert_id))}
    result = []
    for m in found:
        values = {k: m[k] for k in ("trip_id", "match_type", "distance_km", "overlap_from", "overlap_to")}
        if m["leg_id"] in existing:
            conn.execute(update(alert_matches).where(alert_matches.c.id == existing[m["leg_id"]])
                         .values(**values, updated_at=now))
        else:
            conn.execute(insert(alert_matches).values(alert_id=alert_id, leg_id=m["leg_id"], status="new",
                                                      created_at=now, updated_at=now, **values))
        result.append({**m, "overlap_from": iso(m["overlap_from"]), "overlap_to": iso(m["overlap_to"]),
                       "is_new": m["leg_id"] not in existing})
    return result


def match_alert(alert_id: int, trip_id: int | None = None) -> dict:
    now = now_local()
    with db.get_engine().begin() as conn:
        alert = get_alert(conn, alert_id)
        if alert is None:
            raise AlertNotFound(alert_id)
        body = {"alert_id": alert_id, "alert_code": alert["code"], "status": alert["status"]}
        if alert["status"] not in ACTIVE_ALERT_STATUSES:
            return {**body, "matches": [], "total": 0, "created": 0,
                    "skipped": f"Cảnh báo ở trạng thái {alert['status']}, chỉ đối sánh active/updated"}
        saved = save_matches(conn, alert_id, find_matches(conn, alert, trip_id), now)
    return {**body, "matches": saved, "total": len(saved), "created": sum(m["is_new"] for m in saved)}
