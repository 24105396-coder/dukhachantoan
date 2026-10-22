"""Kiểm định Q01–Q10 trên SQLite in-memory; CLI trả mã thoát; bản 03_Database giống hệt."""
from datetime import timedelta
from pathlib import Path

import pytest

import kiem_dinh_du_lieu as kd
from app import schema


def _report(seed):
    with seed.engine.connect() as conn:
        report = kd.run_checks(conn, seed.now)
    return report, {c["code"]: c for c in report["checks"]}


def test_clean_dataset_has_no_errors(seed):
    seed.base_geo()
    report, checks = _report(seed)
    assert report["summary"]["errors"] == 0 and report["summary"]["skipped"] == 0
    assert checks["Q08"]["count"] == 4  # chưa có risk_scores → cảnh báo (warning)


def test_alert_checks_q01_q09(seed):
    ids = seed.base_geo()
    seed.alert(code="AL-NOSRC", source_name="", zone_ids=[ids["z_kh"]])
    seed.alert(code="AL-OLD", ends_at=seed.now - timedelta(hours=2), starts_at=seed.now - timedelta(days=1))
    seed.alert(code="AL-DRAFT", status="draft", source_name=None, issued_at=None)
    _, checks = _report(seed)
    assert checks["Q01"]["count"] == 1 and "AL-NOSRC" in checks["Q01"]["samples"][0]
    assert checks["Q09"]["count"] == 1 and "AL-OLD" in checks["Q09"]["samples"][0]


def test_zone_shape_and_coordinates_q02_q03(seed):
    seed.base_geo()
    seed.add(schema.zones, code="rong", name="Rỗng", centroid_lat=16.0, centroid_lng=108.0, is_active=True)
    seed.add(schema.zones, code="lech", name="Lệch", centroid_lat=35.6, centroid_lng=139.7, radius_km=10,
             is_active=True)
    seed.add(schema.zones, code="truong-sa", name="Trường Sa", centroid_lat=8.64, centroid_lng=111.92,
             radius_km=50, is_active=True)
    _, checks = _report(seed)
    assert checks["Q02"]["count"] == 1 and checks["Q02"]["samples"][0].startswith("rong")
    assert checks["Q03"]["count"] == 1 and "lech" in checks["Q03"]["samples"][0]


def test_contacts_and_guides_q04_q05(seed):
    ids = seed.base_geo()
    seed.add(schema.emergency_contacts, province_id=ids["p_kh"], contact_type="police", phone="113",
             is_verified=False)
    seed.add(schema.emergency_contacts, province_id=ids["p_kh"], contact_type="fire", phone="114", is_verified=True)
    g1 = seed.add(schema.response_guides, slug="bao", status="published")
    seed.add(schema.response_guide_translations, guide_id=g1, locale="vi", title="Bão")
    g2 = seed.add(schema.response_guides, slug="lu", status="published")
    seed.add(schema.response_guide_translations, guide_id=g2, locale="en", title="Floods")
    _, checks = _report(seed)
    assert checks["Q04"]["count"] == 1 and checks["Q04"]["samples"] == ["Khánh Hòa: 1/2 chưa xác minh"]
    assert checks["Q05"]["samples"] == ["bao"]


def test_overlapping_legs_q06(seed):
    ids = seed.base_geo()
    trip = seed.trip()
    seed.leg(trip, 1, seed.now, hours=10, zone_id=ids["z_nt"])
    seed.leg(trip, 2, seed.now + timedelta(hours=8), hours=5, zone_id=ids["z_nt"])
    seed.leg(seed.trip(code="TR-OK"), 1, seed.now, hours=10, zone_id=ids["z_nt"])
    _, checks = _report(seed)
    assert checks["Q06"]["count"] == 1 and "TR-0001" in checks["Q06"]["samples"][0]


@pytest.mark.parametrize("failed,expect", [(1, 0), (2, 2)])
def test_notification_failure_rate_q07(seed, failed, expect):
    seed.base_geo()
    for _ in range(19):
        seed.add(schema.notifications, user_id=1, channel="email", level=2, status="sent")
    for _ in range(failed):
        seed.add(schema.notifications, user_id=1, channel="sms", level=2, status="failed")
    _, checks = _report(seed)
    assert checks["Q07"]["count"] == expect  # 1/20 = 5% chưa vượt; 2/21 ≈ 9.5% vượt


def test_stale_risk_q08_and_destination_zone_q10(seed):
    ids = seed.base_geo()
    for key, age in (("z_kh", 10), ("z_nt", 10), ("z_lc", 90)):
        seed.add(schema.risk_scores, zone_id=ids[key], computed_at=seed.now - timedelta(minutes=age), score=10,
                 level=0, source="t")
    seed.add(schema.destinations, slug="sai-vung", zone_id=ids["z_lc"], lat=12.24, lng=109.19)
    seed.add(schema.destinations, slug="mat-vung", zone_id=9999, lat=12.24, lng=109.19)
    seed.add(schema.destinations, slug="bai-sao", zone_id=ids["z_pq"], lat=10.05, lng=104.03)
    _, checks = _report(seed)
    assert {s.split(":")[0] for s in checks["Q08"]["samples"]} == {"lao-cai", "phu-quoc"}
    assert sorted(s.split(":")[0] for s in checks["Q10"]["samples"]) == ["mat-vung", "sai-vung"]


def test_missing_table_is_skipped_not_crashing(seed):
    seed.base_geo()
    schema.notifications.drop(seed.engine)
    report, checks = _report(seed)
    assert checks["Q07"]["skipped"] is True and report["summary"]["skipped"] == 1


def test_cli_exit_codes_and_render(seed, capsys):
    ids = seed.base_geo()
    assert kd.main(["--no-color"], engine=seed.engine) == 0
    seed.alert(code="AL-X", source_name=None, zone_ids=[ids["z_kh"]])
    assert kd.main(["--no-color"], engine=seed.engine) == 1
    out = capsys.readouterr().out
    assert "Q01" in out and "Q10" in out and "Tổng: 10 kiểm định" in out
    assert kd.main(["--json"], engine=seed.engine) == 1


def test_cli_returns_2_when_db_unreachable():
    from sqlalchemy import create_engine
    assert kd.main([], engine=create_engine("sqlite:////khong-ton-tai/x.db")) == 2


def test_database_folder_copy_is_identical():
    root = Path(kd.__file__).resolve().parent
    copy = root.parents[1] / "03_Database" / "kiem_dinh_du_lieu.py" if len(root.parents) >= 2 else None
    if copy is None or not copy.exists():
        pytest.skip("Không có 03_Database (đang chạy trong container)")
    assert copy.read_bytes() == Path(kd.__file__).read_bytes()
