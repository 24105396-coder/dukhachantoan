"""Kiểm định chất lượng dữ liệu đa ngữ trên các DataFrame (không phụ thuộc MySQL)."""
from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, timezone
from typing import Callable

import pandas as pd

from .text import fold

# Khung tọa độ lãnh thổ đất liền Việt Nam theo yêu cầu đề tài.
VN_BBOX = {"lat_min": 8.0, "lat_max": 23.5, "lng_min": 102.0, "lng_max": 110.0}
MAX_SAMPLES = 10
REQUIRED_LOCALES = ("vi", "en")
ENTITY_TABLES = {"site": "heritage_sites", "artifact": "artifacts", "figure": "historical_figures"}

Tables = dict[str, pd.DataFrame]


@dataclass(frozen=True)
class CheckResult:
    code: str
    severity: str
    title: str
    count: int
    samples: list[str]

    def to_dict(self) -> dict:
        return {"code": self.code, "severity": self.severity, "title": self.title,
                "count": self.count, "samples": self.samples}


@dataclass(frozen=True)
class CheckSpec:
    code: str
    severity: str
    title: str
    run: Callable[[Tables], list[str]]


def _table(tables: Tables, name: str) -> pd.DataFrame:
    df = tables.get(name)
    return df if df is not None else pd.DataFrame()


def _num(series: pd.Series) -> pd.Series:
    return pd.to_numeric(series, errors="coerce")


def _blank(series: pd.Series) -> pd.Series:
    return series.isna() | (series.astype(str).str.strip() == "")


def _published_entities(tables: Tables) -> pd.DataFrame:
    """Gộp các thực thể đã công bố thành (subject_type, subject_id, slug)."""
    frames = []
    for alias, name in ENTITY_TABLES.items():
        df = _table(tables, name)
        if df.empty:
            continue
        pub = df[df["status"] == "published"]
        frames.append(pd.DataFrame({"subject_type": alias, "subject_id": _num(pub["id"]).astype("Int64"),
                                    "slug": pub["slug"].astype(str)}))
    if not frames:
        return pd.DataFrame(columns=["subject_type", "subject_id", "slug"])
    return pd.concat(frames, ignore_index=True)


def _missing_depth1(locale: str) -> Callable[[Tables], list[str]]:
    def run(tables: Tables) -> list[str]:
        entities = _published_entities(tables)
        interp = _table(tables, "interpretations")
        have: set[tuple[str, int]] = set()
        if not interp.empty:
            ok = interp[(interp["locale"] == locale) & (_num(interp["depth_level"]) == 1)]
            have = {(str(t), int(i)) for t, i in zip(ok["subject_type"], _num(ok["subject_id"]))}
        return [f"{r.subject_type}:{r.slug}" for r in entities.itertuples()
                if (r.subject_type, int(r.subject_id)) not in have]
    return run


def check_bbox(tables: Tables) -> list[str]:
    sites = _table(tables, "heritage_sites")
    if sites.empty:
        return []
    lat, lng = _num(sites["lat"]), _num(sites["lng"])
    bad = (lat.isna() | lng.isna() | (lat < VN_BBOX["lat_min"]) | (lat > VN_BBOX["lat_max"])
           | (lng < VN_BBOX["lng_min"]) | (lng > VN_BBOX["lng_max"]))
    return [f"site:{s} ({la}, {ln})" for s, la, ln in zip(sites["slug"][bad], lat[bad], lng[bad])]


def _all_slugs(tables: Tables) -> pd.DataFrame:
    frames = []
    for alias, name in {**ENTITY_TABLES, "route": "tour_routes"}.items():
        df = _table(tables, name)
        if not df.empty:
            frames.append(pd.DataFrame({"type": alias, "slug": df["slug"].astype(str)}))
    if not frames:
        return pd.DataFrame(columns=["type", "slug", "key"])
    out = pd.concat(frames, ignore_index=True)
    # Chuẩn hóa: bỏ dấu, chữ thường, bỏ khoảng trắng thừa → bắt cả trùng "gần đúng".
    return out.assign(key=out["slug"].map(lambda s: fold(s).strip()))


def check_duplicate_slug(tables: Tables) -> list[str]:
    slugs = _all_slugs(tables)
    dup = slugs[slugs.duplicated(["type", "key"], keep=False)]
    return [f"{t}:{', '.join(sorted(g['slug']))}" for (t, _), g in dup.groupby(["type", "key"])]


def check_duplicate_slug_cross_type(tables: Tables) -> list[str]:
    slugs = _all_slugs(tables).drop_duplicates(["type", "key"])
    dup = slugs[slugs.duplicated("key", keep=False)]
    return [f"{k}: {', '.join(sorted(g['type']))}" for k, g in dup.groupby("key")]


def _media_label(media: pd.DataFrame) -> list[str]:
    return [f"media#{i} ({t}:{s})" for i, t, s in zip(media["id"], media["subject_type"], media["subject_id"])]


def check_media_license(tables: Tables) -> list[str]:
    media = _table(tables, "media_assets")
    return [] if media.empty else _media_label(media[_blank(media["license"])])


def check_media_source(tables: Tables) -> list[str]:
    media = _table(tables, "media_assets")
    if media.empty:
        return []
    return _media_label(media[_blank(media["source_name"]) & _blank(media["source_url"])])


def check_event_period(tables: Tables) -> list[str]:
    events, periods = _table(tables, "timeline_events"), _table(tables, "periods")
    if events.empty or periods.empty:
        return []
    p = periods[["id", "code", "start_year", "end_year"]].rename(
        columns={"id": "period_id", "start_year": "p_start", "end_year": "p_end"})
    ev = events.assign(period_id=_num(events["period_id"])).dropna(subset=["period_id"])
    merged = ev.merge(p.assign(period_id=_num(p["period_id"])), on="period_id", how="inner")
    year, start, end = _num(merged["event_year"]), _num(merged["p_start"]), _num(merged["p_end"])
    bad = (year < start) | (end.notna() & (year > end))
    rows = merged[bad]
    return [f"event#{i}: năm {y} ngoài {c} [{s}..{'' if pd.isna(e) else int(e)}]"
            for i, y, c, s, e in zip(rows["id"], rows["event_year"], rows["code"], rows["p_start"], rows["p_end"])]


def check_stop_unpublished_site(tables: Tables) -> list[str]:
    stops, routes = _table(tables, "tour_route_stops"), _table(tables, "tour_routes")
    sites = _table(tables, "heritage_sites")
    if stops.empty or routes.empty:
        return []
    live = routes[routes["status"] == "published"][["id", "slug"]].rename(columns={"id": "route_id", "slug": "route"})
    published_ids = set(_num(sites[sites["status"] == "published"]["id"])) if not sites.empty else set()
    merged = stops.merge(live, on="route_id", how="inner")
    bad = merged[~_num(merged["site_id"]).isin(published_ids)]
    return [f"route:{r} #{o} → site#{s}" for r, o, s in zip(bad["route"], bad["stop_order"], bad["site_id"])]


def check_slot_overbooked(tables: Tables) -> list[str]:
    slots = _table(tables, "tour_slots")
    if slots.empty:
        return []
    bad = slots[_num(slots["booked_count"]) > _num(slots["capacity"])]
    return [f"slot#{i} (route#{r}, {s}): {b}/{c}" for i, r, s, b, c in
            zip(bad["id"], bad["route_id"], bad["starts_at"], bad["booked_count"], bad["capacity"])]


def check_machine_translation(tables: Tables) -> list[str]:
    interp = _table(tables, "interpretations")
    if interp.empty:
        return []
    mt = _num(interp["is_machine_translated"]).fillna(0).astype(int) == 1
    bad = interp[mt & interp["reviewed_by"].isna()]
    return [f"{t}#{s} {loc} depth {d}" for t, s, loc, d in
            zip(bad["subject_type"], bad["subject_id"], bad["locale"], bad["depth_level"])]


CHECKS: tuple[CheckSpec, ...] = (
    CheckSpec("Q01_MISSING_DEPTH1_VI", "error", "Thực thể công bố thiếu diễn giải depth 1 tiếng Việt", _missing_depth1("vi")),
    CheckSpec("Q02_MISSING_DEPTH1_EN", "error", "Thực thể công bố thiếu diễn giải depth 1 tiếng Anh", _missing_depth1("en")),
    CheckSpec("Q03_COORD_OUT_OF_BBOX", "error", "Tọa độ di sản ngoài khung Việt Nam (8.0–23.5, 102.0–110.0)", check_bbox),
    CheckSpec("Q04_DUPLICATE_SLUG", "error", "Slug trùng trong cùng loại thực thể (sau chuẩn hóa)", check_duplicate_slug),
    CheckSpec("Q05_DUPLICATE_SLUG_CROSS_TYPE", "warning", "Slug trùng giữa các loại thực thể khác nhau", check_duplicate_slug_cross_type),
    CheckSpec("Q06_MEDIA_MISSING_LICENSE", "error", "Media thiếu giấy phép (license)", check_media_license),
    CheckSpec("Q07_MEDIA_MISSING_SOURCE", "warning", "Media thiếu nguồn (source_name và source_url)", check_media_source),
    CheckSpec("Q08_EVENT_OUTSIDE_PERIOD", "warning", "Sự kiện có năm nằm ngoài thời kỳ gắn với nó", check_event_period),
    CheckSpec("Q09_STOP_UNPUBLISHED_SITE", "error", "Tuyến công bố có điểm dừng trỏ di sản chưa công bố", check_stop_unpublished_site),
    CheckSpec("Q10_SLOT_OVERBOOKED", "error", "Khung giờ có booked_count > capacity", check_slot_overbooked),
    CheckSpec("Q11_MT_UNREVIEWED", "warning", "Bản dịch máy chưa được duyệt", check_machine_translation),
)


def run_checks(tables: Tables) -> list[CheckResult]:
    results = []
    for spec in CHECKS:
        items = spec.run(tables)
        results.append(CheckResult(spec.code, spec.severity, spec.title, len(items), items[:MAX_SAMPLES]))
    return results


def build_report(tables: Tables) -> dict:
    """Báo cáo đúng hợp đồng: errors/warnings = tổng số bản ghi vi phạm theo mức độ."""
    results = run_checks(tables)
    return {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "summary": {
            "errors": sum(r.count for r in results if r.severity == "error"),
            "warnings": sum(r.count for r in results if r.severity == "warning"),
            "checks": len(results),
        },
        "checks": [r.to_dict() for r in results],
    }
