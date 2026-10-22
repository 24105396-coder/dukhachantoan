#!/usr/bin/env python3
"""Kiểm định chất lượng dữ liệu DuKhachAnToan (Q01–Q10), tệp độc lập chỉ cần SQLAlchemy + PyMySQL.

Cách chạy:
    # trong container dịch vụ Python (đã có thư viện):
    docker compose exec analytics python -I kiem_dinh_du_lieu.py
    # từ máy chủ, MySQL của compose mở ở 127.0.0.1:3308:
    DB_HOST=127.0.0.1 DB_PORT=3308 DB_DATABASE=dukhach DB_USERNAME=dukhach DB_PASSWORD=dukhach_secret \
        python3 -I 03_Database/kiem_dinh_du_lieu.py [--json] [--no-color]
    # hoặc một URL SQLAlchemy bất kỳ: DB_URL=sqlite:///dukhach.db python3 -I kiem_dinh_du_lieu.py

Mã thoát: 0 = không có lỗi (có thể có cảnh báo), 1 = có kiểm định mức error phát hiện vi phạm,
2 = không kết nối được cơ sở dữ liệu hoặc thiếu thư viện.
Hai bản 02_SourceCode/python-service/kiem_dinh_du_lieu.py và 03_Database/kiem_dinh_du_lieu.py giống hệt nhau.
"""
from __future__ import annotations

import argparse
import json
import math
import os
import sys
from dataclasses import asdict, dataclass, field
from datetime import date, datetime, timedelta
from zoneinfo import ZoneInfo

APP_TZ = ZoneInfo(os.getenv("APP_TIMEZONE", "Asia/Ho_Chi_Minh"))
VN_MAINLAND = (8.0, 23.5, 102.0, 110.0)  # (lat_min, lat_max, lng_min, lng_max)
ARCHIPELAGOS = {"Hoàng Sa": (15.7, 17.2, 111.0, 113.0), "Trường Sa": (6.0, 12.0, 111.5, 117.5)}
NOTIFICATION_FAIL_LIMIT = 0.05
RISK_MAX_AGE = timedelta(hours=1)
SAMPLE_LIMIT = 5
LIVE_ALERT = "('active', 'updated')"
COLORS = {"error": "\033[31m", "warning": "\033[33m", "ok": "\033[32m", "skip": "\033[36m",
          "bold": "\033[1m", "reset": "\033[0m"}


@dataclass
class Check:
    code: str
    title: str
    severity: str  # error | warning
    count: int = 0
    samples: list[str] = field(default_factory=list)
    note: str = ""
    skipped: bool = False


# ---------------------------------------------------------------- tiện ích
def now_local() -> datetime:
    return datetime.now(APP_TZ).replace(tzinfo=None, microsecond=0)


def to_dt(value) -> datetime | None:
    if value is None or value == "":
        return None
    if isinstance(value, datetime):
        return value.astimezone(APP_TZ).replace(tzinfo=None) if value.tzinfo else value
    if isinstance(value, date):
        return datetime(value.year, value.month, value.day)
    parsed = datetime.fromisoformat(str(value).replace("Z", "+00:00"))
    return parsed.astimezone(APP_TZ).replace(tzinfo=None) if parsed.tzinfo else parsed


def load_json(value):
    for _ in range(2):
        if isinstance(value, (str, bytes)):
            try:
                value = json.loads(value)
            except ValueError:
                return None
    return value


def rows(conn, sql: str, **params) -> list[dict]:
    from sqlalchemy import text
    return [dict(r._mapping) for r in conn.execute(text(sql), params)]


def in_vietnam(lat: float, lng: float) -> bool:
    boxes = [VN_MAINLAND, *ARCHIPELAGOS.values()]
    return any(a <= lat <= b and c <= lng <= d for a, b, c, d in boxes)


def haversine_km(lat1: float, lng1: float, lat2: float, lng2: float) -> float:
    p1, p2 = math.radians(lat1), math.radians(lat2)
    a = (math.sin((p2 - p1) / 2) ** 2
         + math.cos(p1) * math.cos(p2) * math.sin(math.radians(lng2 - lng1) / 2) ** 2)
    return 2 * 6371.0088 * math.asin(min(1.0, math.sqrt(a)))


def _in_ring(lng: float, lat: float, ring: list) -> bool:
    inside, j = False, len(ring) - 1
    for i in range(len(ring)):
        xi, yi = float(ring[i][0]), float(ring[i][1])
        xj, yj = float(ring[j][0]), float(ring[j][1])
        if (yi > lat) != (yj > lat) and lng < (xj - xi) * (lat - yi) / (yj - yi) + xi:
            inside = not inside
        j = i
    return inside


def polygon_contains(geometry: dict, lat: float, lng: float) -> bool | None:
    """Ray casting thuần Python cho Polygon/MultiPolygon (kể cả lỗ); None nếu không phải đa giác."""
    kind = geometry.get("type")
    if kind == "Feature":
        return polygon_contains(geometry.get("geometry") or {}, lat, lng)
    polys = {"Polygon": [geometry.get("coordinates")], "MultiPolygon": geometry.get("coordinates")}.get(kind)
    if not polys:
        return None
    for poly in polys:
        if poly and _in_ring(lng, lat, poly[0]) and not any(_in_ring(lng, lat, h) for h in poly[1:]):
            return True
    return False


def _add(check: Check, sample: str) -> None:
    check.count += 1
    if len(check.samples) < SAMPLE_LIMIT:
        check.samples.append(sample)


# ---------------------------------------------------------------- 10 kiểm định
def q01_alert_source(conn, now: datetime, c: Check) -> Check:
    for r in rows(conn, f"SELECT code, source_name, issued_at FROM alerts WHERE status IN {LIVE_ALERT}"):
        missing = [k for k in ("source_name", "issued_at") if not r[k] or not str(r[k]).strip()]
        if missing:
            _add(c, f"{r['code']}: thiếu {', '.join(missing)}")
    return c


def q02_zone_shape(conn, now: datetime, c: Check) -> Check:
    for r in rows(conn, "SELECT code, geometry, radius_km FROM zones WHERE is_active = 1"):
        geom = load_json(r["geometry"])
        has_geom = isinstance(geom, dict) and geom.get("type")
        if not has_geom and not (r["radius_km"] and float(r["radius_km"]) > 0):
            _add(c, f"{r['code']}: geometry rỗng/hỏng, radius_km={r['radius_km']}")
    return c


COORD_SOURCES = (
    ("zones", "code", "centroid_lat", "centroid_lng"),
    ("destinations", "slug", "lat", "lng"),
    ("emergency_contacts", "id", "lat", "lng"),
    ("trip_legs", "id", "lat", "lng"),
)


def q03_coordinates(conn, now: datetime, c: Check) -> Check:
    for table, key, lat_col, lng_col in COORD_SOURCES:
        sql = (f"SELECT {key} AS k, {lat_col} AS lat, {lng_col} AS lng FROM {table} "
               f"WHERE {lat_col} IS NOT NULL AND {lng_col} IS NOT NULL")
        for r in rows(conn, sql):
            if not in_vietnam(float(r["lat"]), float(r["lng"])):
                _add(c, f"{table} {r['k']}: ({r['lat']}, {r['lng']})")
    return c


def q04_contacts(conn, now: datetime, c: Check) -> Check:
    sql = ("SELECT COALESCE(p.name, '(không tỉnh)') AS province, COUNT(*) AS total, "
           "SUM(CASE WHEN ec.is_verified = 1 THEN 0 ELSE 1 END) AS unverified "
           "FROM emergency_contacts ec LEFT JOIN provinces p ON p.id = ec.province_id "
           "GROUP BY p.name ORDER BY unverified DESC, p.name")
    for r in rows(conn, sql):
        unverified = int(r["unverified"] or 0)
        if unverified:
            c.count += unverified
            if len(c.samples) < SAMPLE_LIMIT:
                c.samples.append(f"{r['province']}: {unverified}/{r['total']} chưa xác minh")
    return c


def q05_guides_en(conn, now: datetime, c: Check) -> Check:
    sql = ("SELECT g.slug FROM response_guides g WHERE g.status = 'published' AND NOT EXISTS ("
           "SELECT 1 FROM response_guide_translations t WHERE t.guide_id = g.id AND t.locale = 'en' "
           "AND t.title IS NOT NULL AND t.title <> '') ORDER BY g.slug")
    for r in rows(conn, sql):
        _add(c, r["slug"])
    return c


def q06_leg_overlap(conn, now: datetime, c: Check) -> Check:
    sql = ("SELECT t.code, l.id, l.leg_order, l.starts_at, l.ends_at FROM trip_legs l "
           "JOIN trips t ON t.id = l.trip_id WHERE t.status <> 'cancelled' AND l.starts_at IS NOT NULL "
           "AND l.ends_at IS NOT NULL ORDER BY t.code, l.starts_at, l.leg_order")
    prev: dict[str, tuple] = {}
    for r in rows(conn, sql):
        start, end = to_dt(r["starts_at"]), to_dt(r["ends_at"])
        if end < start:
            _add(c, f"{r['code']} chặng {r['leg_order']}: ends_at < starts_at")
        last = prev.get(r["code"])
        if last and start < last[1]:
            _add(c, f"{r['code']} chặng {last[0]} và {r['leg_order']} chồng giờ")
        if not last or end > last[1]:
            prev[r["code"]] = (r["leg_order"], end)
    return c


def q07_notification_failures(conn, now: datetime, c: Check) -> Check:
    counts = {r["status"]: int(r["n"]) for r in rows(
        conn, "SELECT status, COUNT(*) AS n FROM notifications GROUP BY status")}
    final = sum(counts.get(s, 0) for s in ("sent", "read", "failed"))
    failed = counts.get("failed", 0)
    ratio = failed / final if final else 0.0
    c.note = f"failed {failed}/{final} = {ratio:.1%}"
    if ratio > NOTIFICATION_FAIL_LIMIT:
        c.count = failed
        c.samples.append(c.note)
    return c


def q08_stale_risk(conn, now: datetime, c: Check) -> Check:
    latest = {r["zone_id"]: to_dt(r["last"]) for r in rows(
        conn, "SELECT zone_id, MAX(computed_at) AS last FROM risk_scores GROUP BY zone_id")}
    for r in rows(conn, "SELECT id, code FROM zones WHERE is_active = 1 ORDER BY code"):
        last = latest.get(r["id"])
        if last is None or now - last > RISK_MAX_AGE:
            _add(c, f"{r['code']}: {last.isoformat(sep=' ') if last else 'chưa tính'}")
    return c


def q09_expired_active(conn, now: datetime, c: Check) -> Check:
    sql = f"SELECT code, ends_at FROM alerts WHERE status IN {LIVE_ALERT} AND ends_at IS NOT NULL"
    for r in rows(conn, sql):
        if to_dt(r["ends_at"]) < now:
            _add(c, f"{r['code']}: hết hạn {to_dt(r['ends_at']).isoformat(sep=' ')}")
    return c


def _zone_contains(zone: dict, lat: float, lng: float) -> bool | None:
    geom = load_json(zone["geometry"])
    if isinstance(geom, dict):
        inside = polygon_contains(geom, lat, lng)
        if inside is not None:
            return inside
    if zone["radius_km"] and zone["clat"] is not None:
        return haversine_km(float(zone["clat"]), float(zone["clng"]), lat, lng) <= float(zone["radius_km"])
    return None


def q10_destination_zone(conn, now: datetime, c: Check) -> Check:
    zones = {r["id"]: r for r in rows(
        conn, "SELECT id, code, geometry, radius_km, centroid_lat AS clat, centroid_lng AS clng FROM zones")}
    for r in rows(conn, "SELECT slug, zone_id, lat, lng FROM destinations ORDER BY slug"):
        zone = zones.get(r["zone_id"])
        if zone is None:
            _add(c, f"{r['slug']}: zone_id={r['zone_id']} không tồn tại")
        elif r["lat"] is not None and _zone_contains(zone, float(r["lat"]), float(r["lng"])) is False:
            _add(c, f"{r['slug']}: ({r['lat']}, {r['lng']}) ngoài vùng {zone['code']}")
    return c


CHECKS = (
    ("Q01", "Cảnh báo đang hiệu lực thiếu nguồn (source_name) hoặc issued_at", "error", q01_alert_source),
    ("Q02", "Vùng không có geometry lẫn radius_km", "error", q02_zone_shape),
    ("Q03", "Tọa độ ngoài lãnh thổ Việt Nam (đất liền 8–23,5°B, 102–110°Đ; Hoàng Sa, Trường Sa)", "error", q03_coordinates),
    ("Q04", "Danh bạ khẩn cấp chưa xác minh (theo tỉnh)", "warning", q04_contacts),
    ("Q05", "Hướng dẫn ứng phó đã xuất bản thiếu bản tiếng Anh", "error", q05_guides_en),
    ("Q06", "Chặng hành trình chồng giờ hoặc kết thúc trước khi bắt đầu", "error", q06_leg_overlap),
    ("Q07", f"Tỉ lệ thông báo thất bại vượt {NOTIFICATION_FAIL_LIMIT:.0%}", "error", q07_notification_failures),
    ("Q08", "Điểm rủi ro cũ hơn 1 giờ (hoặc chưa có) ở vùng đang hoạt động", "warning", q08_stale_risk),
    ("Q09", "Cảnh báo đã quá ends_at nhưng vẫn active/updated", "error", q09_expired_active),
    ("Q10", "Điểm đến không thuộc vùng (thiếu zone_id hoặc nằm ngoài hình học vùng)", "warning", q10_destination_zone),
)


def _run_one(code: str, title: str, severity: str, func, conn, now: datetime) -> Check:
    check = Check(code, title, severity)
    try:
        with conn.begin_nested():  # lỗi một kiểm định (thiếu bảng) không làm hỏng giao dịch chung
            return func(conn, now, check)
    except Exception as exc:  # bảng chưa migrate: ghi nhận, không dừng các kiểm định khác
        return Check(code, title, severity, note=f"Bỏ qua: {type(exc).__name__}", skipped=True)


def run_checks(conn, now: datetime | None = None) -> dict:
    now = now or now_local()
    checks = [_run_one(*meta, conn, now) for meta in CHECKS]
    errors = sum(1 for ch in checks if ch.severity == "error" and ch.count)
    warnings = sum(1 for ch in checks if ch.severity == "warning" and ch.count)
    return {"generated_at": now.isoformat(sep=" "), "checks": [asdict(ch) for ch in checks],
            "summary": {"checks": len(checks), "errors": errors, "warnings": warnings,
                        "skipped": sum(1 for ch in checks if ch.skipped)}}


# ---------------------------------------------------------------- CLI
def database_url():
    if os.getenv("DB_URL"):
        return os.environ["DB_URL"]
    from sqlalchemy import URL
    return URL.create("mysql+pymysql", username=os.getenv("DB_USERNAME", "dukhach"),
                      password=os.getenv("DB_PASSWORD", ""), host=os.getenv("DB_HOST", "127.0.0.1"),
                      port=int(os.getenv("DB_PORT", "3306")), database=os.getenv("DB_DATABASE", "dukhach"),
                      query={"charset": "utf8mb4"})


def paint(text: str, color: str, enabled: bool) -> str:
    return f"{COLORS[color]}{text}{COLORS['reset']}" if enabled else text


def render(report: dict, color: bool = False) -> str:
    lines = [paint(f"{'MÃ':<5} {'MỨC':<8} {'SỐ':>6}  NỘI DUNG", "bold", color), "-" * 100]
    for ch in report["checks"]:
        level = "skip" if ch["skipped"] else (ch["severity"] if ch["count"] else "ok")
        lines.append(f"{ch['code']:<5} {paint(f'{level:<8}', level, color)} {ch['count']:>6}  {ch['title']}")
        if ch["note"]:
            lines.append(f"{'':<23}({ch['note']})")
        lines.extend(f"{'':<23}- {s}" for s in ch["samples"])
    s = report["summary"]
    status = "error" if s["errors"] else ("warning" if s["warnings"] else "ok")
    lines += ["-" * 100, paint(f"Tổng: {s['checks']} kiểm định, {s['errors']} lỗi, {s['warnings']} cảnh báo, "
                               f"{s['skipped']} bỏ qua · {report['generated_at']}", status, color)]
    return "\n".join(lines)


def main(argv: list[str] | None = None, engine=None) -> int:
    parser = argparse.ArgumentParser(description="Kiểm định chất lượng dữ liệu DuKhachAnToan (Q01–Q10)")
    parser.add_argument("--json", action="store_true", help="in báo cáo dạng JSON")
    parser.add_argument("--no-color", action="store_true", help="tắt màu ANSI")
    args = parser.parse_args(argv)
    try:
        if engine is None:
            from sqlalchemy import create_engine
            engine = create_engine(database_url(), pool_pre_ping=True)
        with engine.connect() as conn:
            report = run_checks(conn)
    except Exception as exc:  # không in chuỗi kết nối/mật khẩu
        print(f"Không kết nối được cơ sở dữ liệu: {type(exc).__name__}", file=sys.stderr)
        return 2
    if args.json:
        print(json.dumps(report, ensure_ascii=False, indent=2))
    else:
        color = sys.stdout.isatty() and not args.no_color and not os.getenv("NO_COLOR")
        print(render(report, color))
    return 1 if report["summary"]["errors"] else 0


if __name__ == "__main__":
    sys.exit(main())
