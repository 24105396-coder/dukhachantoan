"""Thống kê tổng quan cho trang báo cáo quản trị."""
from __future__ import annotations

import pandas as pd

Tables = dict[str, pd.DataFrame]


def _table(tables: Tables, name: str) -> pd.DataFrame:
    df = tables.get(name)
    return df if df is not None else pd.DataFrame()


def _published(df: pd.DataFrame) -> pd.DataFrame:
    return df[df["status"] == "published"] if not df.empty else df


def _count_by(df: pd.DataFrame, column: str) -> dict[int, int]:
    if df.empty:
        return {}
    ids = pd.to_numeric(df[column], errors="coerce").dropna().astype(int)
    return {int(k): int(v) for k, v in ids.value_counts().items()}


def _by_province(tables: Tables, sites: pd.DataFrame) -> list[dict]:
    provinces = _table(tables, "provinces")
    counts = _count_by(sites, "province_id")
    rows = [
        {"province_id": int(p.id), "code": p.code, "name": p.name, "name_en": p.name_en,
         "region": p.region, "sites": counts.get(int(p.id), 0)}
        for p in provinces.itertuples()
        if counts.get(int(p.id), 0) > 0
    ]
    return sorted(rows, key=lambda r: (-r["sites"], r["name"]))


def _period_names(tables: Tables) -> dict[tuple[int, str], str]:
    tr = _table(tables, "period_translations")
    return {(int(r.period_id), str(r.locale)): str(r.name) for r in tr.itertuples()} if not tr.empty else {}


def _by_period(tables: Tables, sites: pd.DataFrame, artifacts: pd.DataFrame, figures: pd.DataFrame) -> list[dict]:
    periods = _table(tables, "periods")
    if periods.empty:
        return []
    names = _period_names(tables)
    counts = {k: _count_by(df, "period_id") for k, df in
              (("sites", sites), ("artifacts", artifacts), ("figures", figures))}
    rows = []
    for p in periods.sort_values(["sort_order", "start_year"]).itertuples():
        pid = int(p.id)
        rows.append({
            "period_id": pid, "code": p.code, "name": names.get((pid, "vi"), p.code),
            "name_en": names.get((pid, "en"), p.code), "start_year": int(p.start_year),
            **{k: c.get(pid, 0) for k, c in counts.items()},
        })
    return rows


def compute_overview(tables: Tables) -> dict:
    sites = _published(_table(tables, "heritage_sites"))
    artifacts = _published(_table(tables, "artifacts"))
    figures = _published(_table(tables, "historical_figures"))
    return {
        "sites": int(len(sites)),
        "artifacts": int(len(artifacts)),
        "figures": int(len(figures)),
        "interpretations": int(len(_table(tables, "interpretations"))),
        "media": int(len(_table(tables, "media_assets"))),
        "by_province": _by_province(tables, sites),
        "by_period": _by_period(tables, sites, artifacts, figures),
    }
