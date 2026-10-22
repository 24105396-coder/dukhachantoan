#!/usr/bin/env python3
"""Kiểm định chất lượng dữ liệu DiSanSo (dùng chung logic với GET /quality-report).

Cách chạy (từ thư mục 02_SourceCode, hệ thống đang chạy bằng docker compose):
    docker compose exec analytics python kiem_dinh_du_lieu.py
    docker compose exec analytics python kiem_dinh_du_lieu.py --json   # xuất JSON
Chạy cục bộ (cần MySQL ở localhost:3307):
    cd 02_SourceCode/python-service
    DB_HOST=127.0.0.1 DB_PORT=3307 DB_PASSWORD=disanso_secret python kiem_dinh_du_lieu.py

Mã thoát: 0 = không có lỗi (có thể có cảnh báo), 1 = có lỗi mức error, 2 = không kết nối được DB.
Bản sao của tệp này nằm ở 03_Database/kiem_dinh_du_lieu.py (giữ nội dung giống hệt).
"""
from __future__ import annotations

import argparse
import json
import os
import sys

from app.db import load_quality_tables
from app.quality import build_report

COLORS = {"error": "\033[31m", "warning": "\033[33m", "ok": "\033[32m", "bold": "\033[1m", "reset": "\033[0m"}


def paint(text: str, color: str, enabled: bool) -> str:
    return f"{COLORS[color]}{text}{COLORS['reset']}" if enabled else text


def render(report: dict, color: bool) -> str:
    lines = [paint(f"{'MÃ':<32} {'MỨC':<8} {'SỐ':>5}  NỘI DUNG", "bold", color), "-" * 100]
    for check in report["checks"]:
        level = check["severity"] if check["count"] else "ok"
        lines.append(f"{check['code']:<32} {paint(f'{level:<8}', level, color)} {check['count']:>5}  {check['title']}")
        lines.extend(f"{'':<47}- {sample}" for sample in check["samples"])
    s = report["summary"]
    lines.append("-" * 100)
    status = "error" if s["errors"] else ("warning" if s["warnings"] else "ok")
    lines.append(paint(f"Tổng: {s['checks']} phép kiểm, {s['errors']} lỗi, {s['warnings']} cảnh báo", status, color))
    return "\n".join(lines)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Kiểm định chất lượng dữ liệu DiSanSo")
    parser.add_argument("--json", action="store_true", help="in báo cáo dạng JSON")
    parser.add_argument("--no-color", action="store_true", help="tắt màu ANSI")
    args = parser.parse_args(argv)
    try:
        report = build_report(load_quality_tables())
    except Exception as exc:  # thông báo ngắn gọn, không in chuỗi kết nối/mật khẩu
        print(f"Không kết nối được cơ sở dữ liệu: {type(exc).__name__}", file=sys.stderr)
        return 2
    if args.json:
        print(json.dumps(report, ensure_ascii=False, indent=2, default=str))
    else:
        color = sys.stdout.isatty() and not args.no_color and not os.getenv("NO_COLOR")
        print(render(report, color))
    return 1 if report["summary"]["errors"] else 0


if __name__ == "__main__":
    sys.exit(main())
