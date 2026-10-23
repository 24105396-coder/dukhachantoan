"""Kết nối MySQL (SQLAlchemy + PyMySQL) qua env DB_*; cho phép thay engine khi kiểm thử."""
from __future__ import annotations

import json
import os
from collections.abc import Iterable, Sequence

from sqlalchemy import URL, Table, create_engine, text
from sqlalchemy.dialects import mysql, sqlite
from sqlalchemy.engine import Connection, Engine

_STATE: dict[str, Engine | None] = {"engine": None}


def database_url() -> URL | str:
    """DB_URL (vd. sqlite:///x.db) ưu tiên; mặc định MySQL theo DB_HOST/DB_PORT/..."""
    if os.getenv("DB_URL"):
        return os.environ["DB_URL"]
    return URL.create(
        "mysql+pymysql",
        username=os.getenv("DB_USERNAME", "dukhach"),
        password=os.getenv("DB_PASSWORD", ""),
        host=os.getenv("DB_HOST", "127.0.0.1"),
        port=int(os.getenv("DB_PORT", "3306")),
        database=os.getenv("DB_DATABASE", "dukhach"),
        query={"charset": "utf8mb4"},
    )


def mysql_connect_args() -> dict:
    """Tham số PyMySQL: timeout 3 s; TLS khi MySQL ngoài yêu cầu (DB_SSL_CA = PEM/đường dẫn, hoặc DB_SSL=true)."""
    args: dict = {"connect_timeout": 3}
    ca = os.getenv("DB_SSL_CA", "").strip()
    if ca.startswith("-----BEGIN"):
        path = os.path.join(os.getenv("TMPDIR", "/tmp"), "db-ca.pem")
        with open(path, "w", encoding="utf-8") as fh:
            fh.write(ca + "\n")
        ca = path
    if ca:
        args["ssl"] = {"ca": ca}
    elif os.getenv("DB_SSL", "").lower() in {"1", "true", "yes"}:
        args["ssl"] = {"check_hostname": False}  # mã hóa đường truyền, không kiểm chứng thư
    return args


def get_engine() -> Engine:
    engine = _STATE["engine"]
    if engine is None:
        url = database_url()
        is_mysql = str(url).startswith("mysql")
        engine = create_engine(
            url, pool_pre_ping=True, pool_recycle=1800,
            connect_args=mysql_connect_args() if is_mysql else {},
        )
        _STATE["engine"] = engine
    return engine


def use_engine(engine: Engine | None) -> None:
    """Thay engine (kiểm thử) hoặc đặt None để tạo lại từ env."""
    _STATE["engine"] = engine


def ping() -> bool:
    try:
        with get_engine().connect() as conn:
            conn.execute(text("SELECT 1"))
        return True
    except Exception:  # DB chưa lên: dịch vụ vẫn chạy, healthz báo db=down
        return False


def upsert(conn: Connection, table: Table, rows: Sequence[dict], keys: Iterable[str],
           update_cols: Iterable[str]) -> int:
    """INSERT … ON DUPLICATE KEY UPDATE (MySQL) / ON CONFLICT DO UPDATE (SQLite)."""
    if not rows:
        return 0
    keys, update_cols = list(keys), list(update_cols)
    dialect = conn.dialect.name
    if dialect == "mysql":
        stmt = mysql.insert(table).values(list(rows))
        stmt = stmt.on_duplicate_key_update({c: stmt.inserted[c] for c in update_cols})
    elif dialect == "sqlite":
        stmt = sqlite.insert(table).values(list(rows))
        stmt = stmt.on_conflict_do_update(index_elements=keys, set_={c: stmt.excluded[c] for c in update_cols})
    else:  # pragma: no cover - chỉ hỗ trợ hai hệ trên
        raise RuntimeError(f"Dialect {dialect} chưa hỗ trợ upsert")
    conn.execute(stmt)
    return len(rows)


def load_json(value):
    """Cột JSON có thể về dạng chuỗi (MySQL qua text(), dữ liệu mã hóa kép) hoặc dict."""
    for _ in range(2):
        if isinstance(value, (str, bytes)):
            try:
                value = json.loads(value)
            except ValueError:
                return None
    return value
