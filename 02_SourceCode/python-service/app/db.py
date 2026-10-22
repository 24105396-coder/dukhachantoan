"""Kết nối MySQL (SQLAlchemy + PyMySQL) và nạp dữ liệu thành DataFrame."""
from __future__ import annotations

import os
from functools import lru_cache

import pandas as pd
from sqlalchemy import URL, create_engine, text
from sqlalchemy.engine import Engine

from .search import DOCUMENT_COLUMNS

# Dữ liệu cũ có thể lưu tên lớp thay vì alias morph; quy về alias để thống nhất.
MORPH_ALIASES = {
    "App\\Models\\HeritageSite": "site",
    "App\\Models\\Artifact": "artifact",
    "App\\Models\\HistoricalFigure": "figure",
    "App\\Models\\TourRoute": "route",
}
SOFT_DELETE_TABLES = frozenset({"heritage_sites", "artifacts", "historical_figures", "tour_routes"})

# Danh sách trắng bảng/cột: tên định danh không bao giờ lấy từ đầu vào người dùng.
TABLE_COLUMNS: dict[str, tuple[str, ...]] = {
    "heritage_sites": ("id", "slug", "status", "lat", "lng", "period_id", "province_id"),
    "artifacts": ("id", "slug", "status", "period_id", "site_id"),
    "historical_figures": ("id", "slug", "status", "period_id", "province_id"),
    "interpretations": ("id", "subject_type", "subject_id", "locale", "depth_level", "title",
                        "is_machine_translated", "reviewed_by"),
    "media_assets": ("id", "subject_type", "subject_id", "media_type", "license", "source_name", "source_url"),
    "timeline_events": ("id", "site_id", "figure_id", "period_id", "event_year", "end_year"),
    "periods": ("id", "code", "start_year", "end_year", "sort_order"),
    "period_translations": ("period_id", "locale", "name"),
    "provinces": ("id", "code", "name", "name_en", "region"),
    "tour_routes": ("id", "slug", "status"),
    "tour_route_stops": ("id", "route_id", "stop_order", "site_id"),
    "tour_slots": ("id", "route_id", "starts_at", "capacity", "booked_count"),
}
QUALITY_TABLES = (
    "heritage_sites", "artifacts", "historical_figures", "interpretations", "media_assets",
    "timeline_events", "periods", "tour_routes", "tour_route_stops", "tour_slots",
)
STATS_TABLES = (
    "heritage_sites", "artifacts", "historical_figures", "interpretations", "media_assets",
    "provinces", "periods", "period_translations",
)

PUBLISHED_ENTITIES_SQL = """
SELECT 'site' AS subject_type, id AS subject_id, slug FROM heritage_sites
 WHERE status = 'published' AND deleted_at IS NULL
UNION ALL
SELECT 'artifact', id, slug FROM artifacts WHERE status = 'published' AND deleted_at IS NULL
UNION ALL
SELECT 'figure', id, slug FROM historical_figures WHERE status = 'published' AND deleted_at IS NULL
"""
INTERPRETATIONS_SQL = """
SELECT subject_type, subject_id, locale, depth_level AS depth, title, summary, body
  FROM interpretations
"""
TAGS_SQL = """
SELECT t.id AS tag_id, t.slug, tt.locale, tt.name, tt.description
  FROM tags t JOIN tag_translations tt ON tt.tag_id = t.id
 ORDER BY t.id, tt.locale
"""
TAGGABLES_SQL = "SELECT tag_id, taggable_type, taggable_id FROM taggables"


def database_url() -> URL:
    return URL.create(
        "mysql+pymysql",
        username=os.getenv("DB_USERNAME", "disanso"),
        password=os.getenv("DB_PASSWORD", ""),
        host=os.getenv("DB_HOST", "127.0.0.1"),
        port=int(os.getenv("DB_PORT", "3306")),
        database=os.getenv("DB_DATABASE", "disanso"),
        query={"charset": "utf8mb4"},
    )


@lru_cache(maxsize=1)
def get_engine() -> Engine:
    return create_engine(
        database_url(), pool_pre_ping=True, pool_recycle=1800,
        connect_args={"connect_timeout": 5},
    )


def _read(engine: Engine, sql: str) -> pd.DataFrame:
    with engine.connect() as conn:
        return pd.read_sql(text(sql), conn)


def normalize_morph(series: pd.Series) -> pd.Series:
    return series.astype(str).map(lambda v: MORPH_ALIASES.get(v, v))


def build_documents(entities: pd.DataFrame, interpretations: pd.DataFrame,
                    taggables: pd.DataFrame, tags: pd.DataFrame) -> pd.DataFrame:
    """Ghép diễn giải của thực thể đã công bố với tên thẻ theo cùng locale (thuần pandas)."""
    if entities.empty or interpretations.empty:
        return pd.DataFrame(columns=DOCUMENT_COLUMNS + ["tag_ids"])
    interp = interpretations.assign(subject_type=normalize_morph(interpretations["subject_type"]))
    docs = interp.merge(entities, on=["subject_type", "subject_id"], how="inner")
    tag_rows = _tag_rows(taggables, tags)
    docs = docs.merge(tag_rows, on=["subject_type", "subject_id", "locale"], how="left")
    docs = docs.assign(
        tags_text=docs["tags_text"].fillna(""),
        tag_ids=docs["tag_ids"].map(lambda v: v if isinstance(v, list) else []),
        summary=docs["summary"].fillna(""),
        body=docs["body"].fillna(""),
    )
    return docs[DOCUMENT_COLUMNS + ["tag_ids"]].reset_index(drop=True)


def _tag_rows(taggables: pd.DataFrame, tags: pd.DataFrame) -> pd.DataFrame:
    """Mỗi (subject, locale) → chuỗi tên thẻ + danh sách tag_id."""
    cols = ["subject_type", "subject_id", "locale", "tags_text", "tag_ids"]
    if taggables.empty or tags.empty:
        return pd.DataFrame(columns=cols)
    tg = taggables.assign(subject_type=normalize_morph(taggables["taggable_type"]),
                          subject_id=taggables["taggable_id"])
    joined = tg.merge(tags[["tag_id", "locale", "name"]], on="tag_id", how="inner")
    grouped = joined.groupby(["subject_type", "subject_id", "locale"])
    return grouped.agg(tags_text=("name", " ".join), tag_ids=("tag_id", lambda s: sorted(int(x) for x in s))
                       ).reset_index()[cols]


def load_tags(engine: Engine | None = None) -> pd.DataFrame:
    return _read(engine or get_engine(), TAGS_SQL)


def load_documents(engine: Engine | None = None) -> pd.DataFrame:
    """Tài liệu tìm kiếm: (subject_type, subject_id, slug, locale, depth, title, summary, body, tags_text)."""
    eng = engine or get_engine()
    return build_documents(
        _read(eng, PUBLISHED_ENTITIES_SQL), _read(eng, INTERPRETATIONS_SQL),
        _read(eng, TAGGABLES_SQL), load_tags(eng),
    )


def load_tables(names: tuple[str, ...], engine: Engine | None = None) -> dict[str, pd.DataFrame]:
    """Nạp các bảng trong danh sách trắng, bỏ bản ghi đã xóa mềm."""
    eng = engine or get_engine()
    out: dict[str, pd.DataFrame] = {}
    for name in names:
        columns = TABLE_COLUMNS[name]
        where = " WHERE deleted_at IS NULL" if name in SOFT_DELETE_TABLES else ""
        df = _read(eng, f"SELECT {', '.join(columns)} FROM {name}{where}")
        if "subject_type" in df.columns:
            df = df.assign(subject_type=normalize_morph(df["subject_type"]))
        out[name] = df
    return out


def load_quality_tables(engine: Engine | None = None) -> dict[str, pd.DataFrame]:
    return load_tables(QUALITY_TABLES, engine)


def load_stats_tables(engine: Engine | None = None) -> dict[str, pd.DataFrame]:
    return load_tables(STATS_TABLES, engine)
