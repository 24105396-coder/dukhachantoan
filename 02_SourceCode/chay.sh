#!/usr/bin/env sh
# DuKhachAnToan (CSE703073 ĐT-15) – khởi động hệ thống bằng một lệnh trên macOS/Linux.
# Yêu cầu: Docker Engine + Compose v2 đang chạy. Dùng: ./chay.sh
set -eu
cd "$(dirname "$0")"

command -v docker >/dev/null 2>&1 || { echo "[LỖI] Không tìm thấy docker. Cài Docker Desktop hoặc Docker Engine."; exit 1; }
docker info >/dev/null 2>&1 || { echo "[LỖI] Docker chưa chạy. Mở Docker Desktop (hoặc systemctl start docker) rồi chạy lại."; exit 1; }
docker compose version >/dev/null 2>&1 || { echo "[LỖI] Cần Docker Compose v2."; exit 1; }

if [ ! -f .env ]; then
  cp .env.example .env
  echo "[OK] Đã tạo .env từ .env.example (giá trị mô phỏng cho môi trường học tập)."
fi
PORT=$(grep -E '^APP_FORWARD_PORT=' .env | tail -n1 | cut -d= -f2)
PORT=${PORT:-8081}
URL="http://localhost:$PORT"

echo "[1/3] Đang dựng image và khởi động 3 dịch vụ (db, analytics, app). Lần đầu khoảng 5–10 phút..."
docker compose up -d --build

echo "[2/3] Đang chờ ứng dụng sẵn sàng (lần đầu nạp dữ liệu mẫu mất khoảng 2 phút)..."
i=0
until [ "$(curl -s -o /dev/null -w '%{http_code}' "$URL/healthz" 2>/dev/null || echo 0)" = "200" ]; do
  i=$((i+1))
  if [ "$i" -ge 90 ]; then
    echo; echo "[LỖI] Sau 7 phút ứng dụng vẫn chưa phản hồi. Xem nhật ký: docker compose logs -f app"; exit 1
  fi
  printf '.'; sleep 5
done
echo
cat <<TXT
[3/3] Hệ thống đã sẵn sàng.

  Trang web     : $URL/vi   (tiếng Anh: $URL/en)
  Quản trị      : $URL/vi/quan-tri
  Kiểm tra sống : $URL/healthz
  API mở        : $URL/api/v1/openapi.json

  Tài khoản mẫu (mật khẩu: DuKhach@2026)
    admin@dukhach.test     Quản trị hệ thống
    agency@dukhach.test    Cơ quan hỗ trợ du khách (Khánh Hòa)
    partner@dukhach.test   Đơn vị lữ hành
    traveler@dukhach.test  Du khách

  Dừng: ./dung.sh (giữ dữ liệu). Xóa sạch dữ liệu: docker compose down -v
TXT
if command -v xdg-open >/dev/null 2>&1; then xdg-open "$URL/vi" >/dev/null 2>&1 || true
elif command -v open >/dev/null 2>&1; then open "$URL/vi" || true; fi
