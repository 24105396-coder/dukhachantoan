import pandas as pd
import pytest

from app.quality import CHECKS, build_report, run_checks


def _counts(tables):
    return {r.code: r.count for r in run_checks(tables)}


def _with(tables, name, extra_rows):
    return {**tables, name: pd.concat([tables[name], pd.DataFrame(extra_rows)], ignore_index=True)}


def test_clean_dataset_passes_every_check(clean_tables):
    report = build_report(clean_tables)
    assert report["summary"] == {"errors": 0, "warnings": 0, "checks": len(CHECKS)}
    assert all(c["count"] == 0 for c in report["checks"])


def test_report_shape_matches_contract(clean_tables):
    report = build_report(clean_tables)
    assert set(report) == {"generated_at", "summary", "checks"}
    for check in report["checks"]:
        assert set(check) == {"code", "severity", "title", "count", "samples"}
        assert check["severity"] in ("error", "warning")


@pytest.mark.parametrize("locale, code", [("en", "Q02_MISSING_DEPTH1_EN"), ("vi", "Q01_MISSING_DEPTH1_VI")])
def test_missing_depth1_per_locale(clean_tables, locale, code):
    interp = clean_tables["interpretations"]
    drop = (interp["subject_type"] == "figure") & (interp["locale"] == locale)
    tables = {**clean_tables, "interpretations": interp[~drop]}
    result = next(r for r in run_checks(tables) if r.code == code)
    assert result.count == 1 and result.samples == ["figure:ly-thai-to"]


def test_depth2_only_does_not_satisfy_depth1(clean_tables):
    interp = clean_tables["interpretations"]
    hit = (interp["subject_type"] == "site") & (interp["subject_id"] == 2) & (interp["locale"] == "en")
    tables = {**clean_tables, "interpretations": interp.assign(depth_level=interp["depth_level"].mask(hit, 2))}
    assert _counts(tables)["Q02_MISSING_DEPTH1_EN"] == 1


def test_draft_entities_are_not_required_to_have_depth1(clean_tables):
    tables = _with(clean_tables, "heritage_sites", [
        {"id": 9, "slug": "nhap", "status": "draft", "lat": 21.0, "lng": 105.0, "period_id": None, "province_id": 1}])
    assert _counts(tables)["Q01_MISSING_DEPTH1_VI"] == 0


def test_coordinates_outside_vietnam_bbox(clean_tables):
    tables = _with(clean_tables, "heritage_sites", [
        {"id": 5, "slug": "dao-ngoai", "status": "published", "lat": 25.0, "lng": 105.0, "period_id": None, "province_id": 1},
        {"id": 6, "slug": "dao-dong", "status": "draft", "lat": 16.0, "lng": 111.5, "period_id": None, "province_id": 2},
        {"id": 7, "slug": "thieu", "status": "draft", "lat": None, "lng": 105.0, "period_id": None, "province_id": 2},
    ])
    assert _counts(tables)["Q03_COORD_OUT_OF_BBOX"] == 3


def test_duplicate_slug_same_type_after_normalization(clean_tables):
    tables = _with(clean_tables, "artifacts", [
        {"id": 11, "slug": "Trống-Đồng-Ngọc-Lũ", "status": "draft", "period_id": 1, "site_id": 1}])
    counts = _counts(tables)
    assert counts["Q04_DUPLICATE_SLUG"] == 1


def test_duplicate_slug_across_types_is_warning(clean_tables):
    tables = _with(clean_tables, "historical_figures", [
        {"id": 21, "slug": "pho-co-hoi-an", "status": "draft", "period_id": None, "province_id": None}])
    report = build_report(tables)
    check = next(c for c in report["checks"] if c["code"] == "Q05_DUPLICATE_SLUG_CROSS_TYPE")
    assert check["count"] == 1 and check["severity"] == "warning"
    assert report["summary"]["errors"] == 0


def test_media_missing_license_and_source(clean_tables):
    tables = _with(clean_tables, "media_assets", [
        {"id": 2, "subject_type": "site", "subject_id": 1, "media_type": "image", "license": None,
         "source_name": "Bảo tàng", "source_url": None},
        {"id": 3, "subject_type": "site", "subject_id": 2, "media_type": "audio", "license": "simulated",
         "source_name": "", "source_url": None},
    ])
    counts = _counts(tables)
    assert counts["Q06_MEDIA_MISSING_LICENSE"] == 1
    assert counts["Q07_MEDIA_MISSING_SOURCE"] == 1


def test_event_year_outside_period(clean_tables):
    tables = _with(clean_tables, "timeline_events", [
        {"id": 3, "site_id": 1, "figure_id": None, "period_id": 3, "event_year": 1300, "end_year": None},
        {"id": 4, "site_id": 1, "figure_id": None, "period_id": 1, "event_year": -800, "end_year": None},
        {"id": 5, "site_id": 2, "figure_id": None, "period_id": 4, "event_year": 2020, "end_year": None},
    ])
    result = next(r for r in run_checks(tables) if r.code == "Q08_EVENT_OUTSIDE_PERIOD")
    assert result.count == 2  # thời kỳ end_year NULL là "đến nay" → 2020 hợp lệ


def test_stop_points_to_unpublished_site(clean_tables):
    tables = _with(clean_tables, "heritage_sites", [
        {"id": 8, "slug": "nhap", "status": "draft", "lat": 21.0, "lng": 105.0, "period_id": None, "province_id": 1}])
    tables = _with(tables, "tour_route_stops", [
        {"id": 2, "route_id": 1, "stop_order": 2, "site_id": 8},
        {"id": 3, "route_id": 1, "stop_order": 3, "site_id": 99},  # di sản đã xóa mềm
    ])
    tables = _with(tables, "tour_routes", [{"id": 2, "slug": "nhap", "status": "draft"}])
    tables = _with(tables, "tour_route_stops", [{"id": 4, "route_id": 2, "stop_order": 1, "site_id": 8}])
    assert _counts(tables)["Q09_STOP_UNPUBLISHED_SITE"] == 2


def test_slot_overbooked(clean_tables):
    tables = _with(clean_tables, "tour_slots", [
        {"id": 2, "route_id": 1, "starts_at": "2026-11-02 08:00:00", "capacity": 10, "booked_count": 11}])
    result = next(r for r in run_checks(tables) if r.code == "Q10_SLOT_OVERBOOKED")
    assert result.count == 1 and "11/10" in result.samples[0]


def test_machine_translation_not_reviewed(clean_tables):
    tables = _with(clean_tables, "interpretations", [
        {"id": 100, "subject_type": "site", "subject_id": 1, "locale": "en", "depth_level": 2, "title": "mt",
         "is_machine_translated": 1, "reviewed_by": None},
        {"id": 101, "subject_type": "site", "subject_id": 2, "locale": "en", "depth_level": 2, "title": "mt",
         "is_machine_translated": 1, "reviewed_by": 2},
    ])
    report = build_report(tables)
    assert report["summary"] == {"errors": 0, "warnings": 1, "checks": len(CHECKS)}


def test_samples_are_capped(clean_tables):
    rows = [{"id": 100 + i, "route_id": 1, "starts_at": f"2026-12-{i + 1:02d} 08:00", "capacity": 1,
             "booked_count": 5} for i in range(15)]
    result = next(r for r in run_checks(_with(clean_tables, "tour_slots", rows)) if r.code == "Q10_SLOT_OVERBOOKED")
    assert result.count == 15 and len(result.samples) == 10


def test_missing_tables_do_not_crash():
    report = build_report({})
    assert report["summary"]["errors"] == 0
