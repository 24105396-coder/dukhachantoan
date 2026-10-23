# DuKhachAnToan – mã nguồn (CSE703073 ĐT-15)

Hệ thống cảnh báo rủi ro và hỗ trợ du khách theo thời gian thực. Sản phẩm học thuật, học phần Lập trình ứng dụng web trong du lịch 2 (CSE703073), Đại học Phenikaa. Dữ liệu cá nhân trong dữ liệu mẫu là mô phỏng.

**Bản trực tuyến:** https://dukhach-web.onrender.com (kiểm tra sống `/healthz`; dịch vụ Python https://dukhach-analytics.onrender.com) – Render (Blueprint `render.yaml` ở gốc kho, 2 dịch vụ Docker gói Free, Singapore) + Aiven for MySQL 8.4 gói Free (TLS). Gói Free ngủ sau 15 phút không truy cập, lần mở đầu chờ 30–90 giây. Kho: https://github.com/truongnguyen123123/test3.

## Chạy nhanh (Docker)

Yêu cầu: Docker Desktop (Windows/macOS) hoặc Docker Engine ≥ 24 kèm Compose v2; RAM trống ≥ 4 GB.

- Windows: nhấn đúp `chay.bat`; macOS/Linux: `./chay.sh`. Script tạo `.env` từ `.env.example`, dựng 3 container, chờ `/healthz` rồi mở trình duyệt. Dừng: `dung.bat` / `./dung.sh`. Nạp lại dữ liệu mẫu sạch: `nap-lai-du-lieu.bat` / `./nap-lai-du-lieu.sh`.
- Thủ công:
  ```bash
  cd 02_SourceCode
  cp .env.example .env
  docker compose up -d --build      # lần đầu 5–10 phút
  docker compose logs -f app        # chờ migrate → seed → Apache
  ```

| Dịch vụ | Địa chỉ |
| --- | --- |
| Web (Laravel + Vue) | http://localhost:8081 (quản trị: `/vi/quan-tri`) |
| Trạng thái | http://localhost:8081/healthz |
| OpenAPI | http://localhost:8081/api/v1/openapi.json |
| Python (FastAPI, chỉ máy cục bộ) | http://localhost:8002/healthz |
| MySQL 8.0 (chỉ máy cục bộ) | `localhost:3308`, CSDL `dukhach` |

Cổng đổi được bằng `APP_FORWARD_PORT`, `ANALYTICS_FORWARD_PORT`, `DB_FORWARD_PORT` trong `.env`.

## Tài khoản mẫu

Mật khẩu chung `DuKhach@2026` (biến `SEED_DEMO_PASSWORD`).

| Email | Vai trò | Sau đăng nhập |
| --- | --- | --- |
| `admin@dukhach.test` | Quản trị hệ thống | `/vi/quan-tri` |
| `agency@dukhach.test` | Cơ quan hỗ trợ du khách tỉnh Khánh Hòa | `/vi/quan-tri` (số liệu trong tỉnh) |
| `partner@dukhach.test` | Đơn vị lữ hành | `/vi/hanh-trinh` |
| `traveler@dukhach.test`, `traveler1..6@dukhach.test` | Du khách có hành trình mẫu | `/vi/hanh-trinh` |

## Cấu trúc

```
02_SourceCode/
├── compose.yaml            # db (MySQL 8.0), analytics (FastAPI), app (Apache + PHP 8.3)
├── .env.example            # biến môi trường mẫu (giá trị mô phỏng)
├── chay.*, dung.*, nap-lai-du-lieu.*   # chạy / dừng / nạp lại dữ liệu
├── deploy/                 # Dockerfile.web, Dockerfile.analytics (Render, xem ../render.yaml); mẫu biến Railway (dự phòng)
├── db-init/                # tạo CSDL kiểm thử dukhach_test
├── app/                    # Laravel 12 + Inertia 2 + Vue 3.5 + Tailwind 4 + Leaflet
│   ├── app/Http/{Controllers,Middleware,Requests,Resources}
│   ├── app/Services/       # Trip, Alert, Matching, Notification, Risk, Weather, Simulation, Checkin…
│   ├── app/Console/Commands/   # dispatch-notifications, match-alerts, expire-alerts, fetch-weather, backup-db
│   ├── database/{migrations,seeders,data}/   # dữ liệu nguồn JSON có trích dẫn
│   ├── resources/js/{Pages,Components,Layouts,Composables}
│   ├── lang/{vi,en}.json
│   ├── routes/{web,api,console}.php
│   ├── tests/{Feature,Unit}/   # PHPUnit
│   ├── tests/e2e/          # Playwright
│   └── docker/             # Dockerfile, entrypoint, cấu hình Apache/PHP
└── python-service/         # thời tiết Open-Meteo, điểm rủi ro, đối sánh không gian, thống kê
```

Khi dịch vụ Python không phản hồi, web vẫn chạy: đối sánh dùng phương án dự phòng PHP, điểm rủi ro giữ giá trị cũ, các trang dashboard hiện thông báo.

## Kiểm thử (chạy từ `02_SourceCode`, hệ thống đang chạy)

```bash
# PHPUnit (CSDL dukhach_test)
docker run --rm -u $(id -u):$(id -g) --network dukhach_default -v "$PWD/app":/var/www/html \
  -w /var/www/html -e HOME=/tmp --entrypoint php dukhach-app artisan test
# pytest
(cd python-service && docker run --rm -u root -v "$PWD":/src -w /src --entrypoint sh dukhach-analytics \
  -c "pip install -q -r requirements-dev.txt && python -m pytest -q")
# Playwright 3 trình duyệt (máy thiếu thư viện WebKit thì chạy trong image chính thức)
(cd app && npx playwright test --project=chromium --project=firefox --project=webkit)
docker run --rm --network host --ipc=host -v "$PWD/app":/app -w /app \
  mcr.microsoft.com/playwright:v1.64.1-noble npx playwright test
```

Kết quả kiểm thử ngày 23/10/2026: lượt 1 (commit `abb8c86`) 136 ca hộp đen, 127 đạt, 10 lỗi; lượt 2 sau sửa (commit `5733203`) 136/136 đạt; PHPUnit 189 đạt; pytest 112 đạt; ma trận 3 trình duyệt × 3 khổ 54/54. Chi tiết và minh chứng ở `../05_KiemThu/`.

Lưu ý vận hành:
- Sao lưu thủ công phải chạy dưới người dùng web: `docker compose exec -u www-data app php artisan app:backup-db` (chạy bằng root sẽ tạo tệp sao lưu thuộc root mà ứng dụng không đọc được).
- Sau khi chạy kiểm thử, CSDL còn dữ liệu do kiểm thử sinh ra; chạy `nap-lai-du-lieu.sh` (hoặc `.bat`) trước khi chấm hay quay video.

Cài đặt không Docker, vận hành, sao lưu và triển khai trực tuyến (Render + Aiven; Railway dự phòng): `../04_HuongDanSuDung/HuongDan_CaiDat_QuanTri.md`. CSDL độc lập (schema, ERD, từ điển dữ liệu): `../03_Database`.
