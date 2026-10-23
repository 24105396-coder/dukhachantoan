#!/usr/bin/env sh
# DuKhachAnToan (CSE703073 ĐT-15) – xóa sạch dữ liệu và nạp lại dữ liệu mẫu từ đầu (macOS/Linux).
# CẢNH BÁO: mất toàn bộ dữ liệu đã nhập, ảnh đã tải lên và tài khoản đã đăng ký. Dữ liệu mẫu được tạo lại tự động.
set -eu
cd "$(dirname "$0")"
docker info >/dev/null 2>&1 || { echo "[LỖI] Docker chưa chạy."; exit 1; }
printf 'Thao tác này XÓA SẠCH cơ sở dữ liệu và ảnh đã tải lên, rồi nạp lại dữ liệu mẫu. Tiếp tục? [y/N] '
read -r ans
case "$ans" in y|Y) ;; *) exit 0 ;; esac
docker compose down -v
echo "Đã xóa. Đang khởi động lại và nạp dữ liệu mẫu (khoảng 2-3 phút)..."
exec ./chay.sh
