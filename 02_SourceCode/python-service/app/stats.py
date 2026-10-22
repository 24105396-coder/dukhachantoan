"""GET /stats/overview: số liệu tổng hợp cho dashboard (không trả dữ liệu cá nhân)."""
from __future__ import annotations

from datetime import timedelta

from sqlalchemy import func, or_, select
from sqlalchemy.engine import Connection

from . import db
from .repo import ACTIVE_ALERT_STATUSES
from .schema import (alert_matches, alerts, notifications, risk_scores, safety_checkins, trips,
                     weather_observations, zones)
from .timeutil import iso, now_local

TOP_ZONES = 5
FINAL_NOTIFICATION_STATUSES = ("sent", "read", "failed")


def _group(conn: Connection, column, *where) -> dict:
    stmt = select(column, func.count()).group_by(column)
    for cond in where:
        stmt = stmt.where(cond)
    return {(str(k) if k is not None else "null"): int(v) for k, v in conn.execute(stmt)}


def latest_risk(conn: Connection, now) -> list[dict]:
    """Điểm mới nhất của mỗi vùng trong 24 giờ qua."""
    stmt = select(zones.c.code, risk_scores.c.score, risk_scores.c.level, risk_scores.c.computed_at,
                  risk_scores.c.zone_id).join(zones, zones.c.id == risk_scores.c.zone_id).where(
        risk_scores.c.computed_at >= now - timedelta(hours=24)).order_by(risk_scores.c.computed_at,
                                                                          risk_scores.c.id)
    latest = {r.zone_id: r for r in conn.execute(stmt)}
    return [{"zone_code": r.code, "score": int(r.score), "level": int(r.level), "computed_at": iso(r.computed_at)}
            for r in latest.values()]


def _alerts_block(conn: Connection, now) -> dict:
    live = (alerts.c.status.in_(ACTIVE_ALERT_STATUSES),)
    by_sev = _group(conn, alerts.c.severity, *live)
    return {
        "active": sum(by_sev.values()),
        "by_severity": {str(s): by_sev.get(str(s), 0) for s in range(1, 5)},
        "by_status": _group(conn, alerts.c.status),
        "expired_but_active": conn.execute(select(func.count()).select_from(alerts).where(
            *live, alerts.c.ends_at < now)).scalar_one(),
    }


def _notifications_block(conn: Connection) -> dict:
    by_status = _group(conn, notifications.c.status)
    final = sum(by_status.get(s, 0) for s in FINAL_NOTIFICATION_STATUSES)
    failed = by_status.get("failed", 0)
    return {"by_status": by_status, "failure_rate": round(failed / final, 4) if final else 0.0}


def overview() -> dict:
    now = now_local()
    with db.get_engine().connect() as conn:
        risk = latest_risk(conn, now)
        distribution = {str(lv): sum(1 for r in risk if r["level"] == lv) for lv in range(5)}
        return {
            "updated_at": iso(now),
            "zones": {"total": conn.execute(select(func.count()).select_from(zones)).scalar_one(),
                      "active": conn.execute(select(func.count()).select_from(zones)
                                             .where(zones.c.is_active.is_(True))).scalar_one()},
            "alerts": _alerts_block(conn, now),
            "trips": {"by_status": _group(conn, trips.c.status)},
            "matches": {"by_status": _group(conn, alert_matches.c.status),
                        "trips_affected": conn.execute(select(func.count(func.distinct(alert_matches.c.trip_id)))
                                                       .where(alert_matches.c.status.in_(("new", "notified")))
                                                       ).scalar_one()},
            "notifications": _notifications_block(conn),
            "checkins": {"by_status": _group(conn, safety_checkins.c.status),
                         "need_help_open": conn.execute(select(func.count()).select_from(safety_checkins).where(
                             safety_checkins.c.status == "need_help",
                             or_(safety_checkins.c.response_status.is_(None),
                                 safety_checkins.c.response_status != "closed"))).scalar_one()},
            "risk": {"distribution": distribution,
                     "top": sorted(risk, key=lambda r: (-r["score"], r["zone_code"]))[:TOP_ZONES]},
            "weather": {"last_fetch": iso(conn.execute(select(func.max(weather_observations.c.fetched_at)))
                                          .scalar())},
        }
