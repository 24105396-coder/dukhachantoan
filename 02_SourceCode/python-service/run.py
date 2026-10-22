"""Khởi chạy uvicorn nghe cả IPv4 lẫn IPv6 (dual-stack).

Lý do: asyncio bật IPV6_V6ONLY khi bind "::", nên một socket không phục vụ được cả hai họ địa chỉ.
Railway nối dịch vụ qua mạng riêng IPv6 nhưng kiểm tra sức khỏe qua IPv4; Docker Compose cục bộ dùng IPv4.
Biến môi trường: PORT (mặc định 8001), UVICORN_HOST = dual | 0.0.0.0 | :: (mặc định dual).
"""
from __future__ import annotations

import os
import socket

import uvicorn

PORT = int(os.getenv("PORT", "8001"))
HOST = os.getenv("UVICORN_HOST", "dual").strip() or "dual"


def _listen(family: int, address: str) -> socket.socket:
    sock = socket.socket(family, socket.SOCK_STREAM)
    sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    if family == socket.AF_INET6:
        sock.setsockopt(socket.IPPROTO_IPV6, socket.IPV6_V6ONLY, 1)
    sock.bind((address, PORT))
    sock.listen(2048)
    sock.set_inheritable(True)
    return sock


def main() -> None:
    config = uvicorn.Config("app.main:app", host="0.0.0.0", port=PORT, log_level="info", proxy_headers=True)
    server = uvicorn.Server(config)
    if HOST != "dual":
        config.host = HOST
        server.run()
        return

    sockets: list[socket.socket] = []
    for family, address in ((socket.AF_INET6, "::"), (socket.AF_INET, "0.0.0.0")):
        try:
            sockets.append(_listen(family, address))
        except OSError as exc:  # container không có IPv6 → chỉ nghe IPv4
            print(f"[run] bỏ qua {address}:{PORT}: {exc}")
    if not sockets:
        raise SystemExit("[run] không bind được cổng nào")
    print(f"[run] uvicorn nghe {[s.getsockname()[:2] for s in sockets]}")
    server.run(sockets=sockets)


if __name__ == "__main__":
    main()
