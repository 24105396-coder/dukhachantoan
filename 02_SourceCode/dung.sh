#!/usr/bin/env sh
# DuKhachAnToan – dừng toàn bộ container, giữ nguyên dữ liệu. Xóa sạch: docker compose down -v
set -eu
cd "$(dirname "$0")"
docker compose down
echo "Đã dừng. Dữ liệu vẫn được giữ; chạy ./chay.sh để khởi động lại."
