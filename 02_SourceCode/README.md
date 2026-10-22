# DuKhachAnToan – Nền tảng số hóa và diễn giải di sản văn hóa đa ngữ

Sản phẩm học thuật, phục vụ mục đích đào tạo.
- Học phần: Lập trình ứng dụng web trong du lịch 2 (CSE703073), Đại học Phenikaa.
- Đề tài ĐT-04, nhóm 4 (DuKhachAnToan), lớp 20xx-2-CSE703073-01.
- Giảng viên: TS. Nguyễn Văn Tánh.

## 1. Mô tả

DuKhachAnToan tách **hồ sơ** (di sản, hiện vật, nhân vật lịch sử) khỏi **lớp diễn giải**. Lớp diễn giải tổ chức theo hai trục:
- **ngôn ngữ:** `vi`, `en`;
- **mức chuyên sâu:** 1 phổ thông, 2 chuyên sâu, 3 học thuật.

Các chức năng chính:
- thư viện media có siêu dữ liệu nguồn gốc và giấy phép;
- thuyết minh âm thanh (tệp MP3 hoặc TTS của trình duyệt);
- bản đồ Leaflet nền Google Maps (chuyển được sang OpenStreetMap), dòng thời gian tương tác;
- tìm kiếm ngữ nghĩa theo thẻ bằng TF-IDF, có dự phòng FULLTEXT;
- tuyến tham quan có đặt lịch giữ chỗ 10 phút và cổng thanh toán sandbox;
- đóng góp cộng đồng có kiểm duyệt;
- khu quản trị, kiểm định chất lượng dữ liệu;
- API mở có OpenAPI 3.1, JSON-LD schema.org, GeoJSON, iCalendar.

Dữ liệu hạt giống: 56 di sản, 62 hiện vật, 43 nhân vật, 453 bản diễn giải, 652 media, 365 sự kiện, 8 tuyến, 80 khung giờ, tổng 1.711 bản ghi du lịch.

## 2. Kiến trúc

```
┌──────────────────────────── Trình duyệt ────────────────────────────┐
│ Vue 3.5 + Inertia 2 · Tailwind 4 (2 theme) · Leaflet · Chart.js      │
│ model-viewer (npm) · Web Speech API (TTS)                            │
└───────────────┬──────────────────────────────────────────────────────┘
                │ HTTP(S) · cookie phiên HttpOnly + header X-XSRF-TOKEN
                ▼
┌─────────────── app: Apache 2.4 + PHP 8.3 + Laravel 12 ───────────────┐
│ Middleware (SetLocale, SecurityHeaders, role, throttle)               │
│   → Controllers + FormRequest → Services → Eloquent Models            │
│ Services: Booking, Payment + SandboxGateway, Search, ContentLocalizer,│
│           JsonLd, GeoJson, Ical, AuditLogger, AnalyticsClient         │
│ Lịch nền: app:expire-holds (mỗi phút), app:backup-db (02:00)          │
└───────┬───────────────────────────────────────────────┬──────────────┘
        │ PDO, truy vấn tham số hóa                     │ HTTP nội bộ, X-Analytics-Token,
        ▼                                               ▼ timeout 2 s (lỗi → FULLTEXT)
┌──────── db: MySQL 8.0 ────────┐        ┌──── analytics: FastAPI (Python 3.12) ────┐
│ 36 bảng (28 nghiệp vụ),       │◄───────│ /search TF-IDF lai từ + ký tự            │
│ utf8mb4, InnoDB, FULLTEXT,    │ chỉ đọc│ /suggest-tags · /quality-report (Q01–Q11) │
│ khóa dòng khi giữ chỗ         │SQLAlch.│ /reindex · /stats/overview · /healthz     │
└───────────────────────────────┘        └───────────────────────────────────────────┘
```

Kiểu kiến trúc: đơn khối mô-đun ba tầng (trình bày, nghiệp vụ, truy cập dữ liệu), kèm một dịch vụ phân tích Python tách rời. Hệ thống suy giảm có kiểm soát: khi Python không phản hồi, trang vẫn hoạt động và chỉ chuyển sang tìm kiếm cơ bản.

## 3. Cấu trúc thư mục

Cấu trúc tham chiếu trong đề thi gồm `backend/`, `frontend/`, `python-service/`, `docker/` và `docker-compose.yml`. Dự án ánh xạ như sau: `app/` gộp cả backend (Laravel) và frontend (Vue trong `app/resources/js`) vì dùng Inertia, frontend được render qua controller Laravel nên không tách thành ứng dụng riêng; cấu hình Docker nằm ở `app/docker/` và `python-service/Dockerfile`, còn `docker-compose.yml` mang tên chuẩn mới của Compose v2 là `compose.yaml`.

```
02_SourceCode/
├── chay.bat, dung.bat           # Windows: khởi động / dừng bằng một lệnh
├── chay.sh, dung.sh             # macOS/Linux: tương tự
├── compose.yaml                 # 3 dịch vụ: db, analytics, app
├── deploy/                      # mẫu biến môi trường Railway (web, analytics); render.yaml ở gốc kho
├── .env.example                 # mẫu biến môi trường (giá trị mô phỏng)
├── app/                         # Laravel 12 + Inertia + Vue
│   ├── app/Http/{Controllers,Middleware,Requests,Resources}
│   ├── app/Services/            # nghiệp vụ (controller mỏng)
│   ├── app/Models/ (+Concerns)  # model + trait HasInterpretations, HasMedia, HasTags, Translatable
│   ├── app/Policies/            # phân quyền theo đối tượng
│   ├── app/Console/Commands/    # app:backup-db, app:expire-holds, app:is-seeded
│   ├── database/migrations/     # 11 tệp migration
│   ├── database/seeders/, database/data/*.json   # dữ liệu nguồn song ngữ có trích dẫn
│   ├── resources/js/{Pages,Components,Layouts,Composables}  # 42 trang, 50 component
│   ├── resources/css/app.css    # token màu cho 2 theme
│   ├── resources/openapi/openapi.json            # đặc tả OpenAPI 3.1
│   ├── lang/{vi,en}.json        # chuỗi giao diện
│   ├── routes/{web,api,console}.php
│   ├── tests/Feature/           # PHPUnit (27 tệp, 194 ca)
│   ├── tests/e2e/               # Playwright
│   ├── docker/{Dockerfile,entrypoint.sh,php.ini}
│   └── railway.json             # cấu hình dịch vụ web trên Railway
└── python-service/              # FastAPI
    ├── app/{main,db,text,vectorize,search,tags,quality,stats}.py
    ├── kiem_dinh_du_lieu.py     # CLI kiểm định chất lượng dữ liệu
    ├── run.py, railway.json     # khởi chạy uvicorn dual-stack IPv4/IPv6 theo PORT; cấu hình Railway
    ├── requirements.txt, requirements-dev.txt
    └── tests/                   # pytest (56 ca)
```

CSDL độc lập (`schema.sql`, `seed_sample.sql`, ERD, từ điển dữ liệu) nằm trong `../03_Database`.

## 4. Biến môi trường

Sao chép `.env.example` thành `.env`, đặt cùng thư mục với `compose.yaml`. Không đưa `.env` vào Git. Khi triển khai thật, phải đổi mọi giá trị bí mật.

| Biến | Ý nghĩa | Giá trị trong `.env.example` |
| --- | --- | --- |
| `APP_ENV`, `APP_DEBUG` | Môi trường, chế độ gỡ lỗi | `production`, `false` |
| `APP_URL` | URL gốc | `http://localhost:8081` |
| `APP_KEY` | Khóa mã hóa phiên/cookie; để trống thì entrypoint tự sinh và lưu vào volume | (trống) |
| `LOG_LEVEL` | Mức nhật ký | `info` |
| `LOG_STACK` | Kênh nhật ký trong stack; `daily` tách tệp theo ngày `storage/logs/laravel-YYYY-MM-DD.log` | `daily` |
| `LOG_DAILY_DAYS` | Số ngày giữ tệp nhật ký (tệp cũ hơn tự xóa) | `14` |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Tài khoản MySQL ứng dụng | `dukhach`, `dukhach`, `dukhach_secret` |
| `DB_ROOT_PASSWORD` | Mật khẩu root MySQL (dùng để tạo CSDL kiểm thử) | `root_secret` |
| `APP_FORWARD_PORT`, `ANALYTICS_FORWARD_PORT`, `DB_FORWARD_PORT` | Cổng ánh xạ ra máy chủ | `8080`, `8001`, `3307` |
| `ANALYTICS_TOKEN` | Token dùng chung giữa app và Python | `dev-analytics-token` |
| `PAYMENT_SANDBOX_SECRET` | Khóa HMAC-SHA256 của cổng sandbox | `sandbox-secret-change-me` |
| `SEED_DEMO_PASSWORD` | Mật khẩu tài khoản mẫu khi seed | `DuKhach@2026` |
| `SESSION_SECURE_COOKIE` | Chỉ gửi cookie qua HTTPS | `false` (đặt `true` khi go-live) |
| `MAP_PROVIDER` | Bản đồ nền: `google` (tile Google Maps) hoặc `osm` (OpenStreetMap) | `google` |

`compose.yaml` cố định thêm các biến: `SESSION_DRIVER=database`, `CACHE_STORE=database`, `PAYMENT_GATEWAY=sandbox`, `BOOKING_HOLD_MINUTES=10`, `TRUSTED_PROXIES` (mặc định rỗng, chỉ đặt `*` khi chạy sau proxy của PaaS), `ANALYTICS_URL=http://analytics:8001`.

## 5. Yêu cầu môi trường và chạy hệ thống

### 5.1. Yêu cầu môi trường

| Cách chạy | Phần mềm cần có |
| --- | --- |
| Docker (khuyến nghị) | Docker Engine ≥ 24 (hoặc Docker Desktop trên Windows/macOS) kèm Docker Compose v2 là đủ; mọi thành phần khác nằm trong image. RAM trống ≥ 4 GB, đĩa trống ≥ 5 GB |
| Không Docker (Mục 6) | PHP 8.3 với các phần mở rộng `pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`; Composer 2; Node.js 20; Python 3.12; MySQL 8.0 |

Đã thử nghiệm trên: Ubuntu 22.04 (máy phát triển, Docker). Windows/macOS: [CẬP NHẬT].

### 5.2. Chạy bằng Docker

**Cách nhanh (một lệnh):** Windows nhấn đúp `chay.bat` (hoặc gõ `.\chay.bat` trong PowerShell); macOS/Linux chạy `./chay.sh`. Script kiểm tra Docker, tự tạo `.env` từ `.env.example` nếu chưa có, dựng và chạy 3 container, chờ `/healthz` trả về OK rồi mở trình duyệt và in tài khoản mẫu. Dừng bằng `dung.bat` / `./dung.sh`.

**Cách thủ công:**

```bash
cd 02_SourceCode
cp .env.example .env              # Windows PowerShell: copy .env.example .env
docker compose up --build         # chạy nền: docker compose up -d --build
docker compose logs -f app        # theo dõi entrypoint: chờ DB → migrate → seed → Apache
docker compose ps
```

- Web: http://localhost:8081 (tự chuyển tới `/vi`). Quản trị: http://localhost:8081/vi/quan-tri.
- Trạng thái sống: http://localhost:8081/healthz. OpenAPI: http://localhost:8081/api/v1/openapi.json.
- Dịch vụ Python: http://localhost:8002/healthz. MySQL: `localhost:3307`.
- Tài khoản mẫu (mật khẩu `DuKhach@2026`): `admin@dukhach.test`, `manager@dukhach.test`, `partner@dukhach.test` (đối tác), `user@dukhach.test` (thành viên).
- Dừng hệ thống: `docker compose down`. Xóa sạch dữ liệu: `docker compose down -v`.
- Lệnh Artisan: `docker compose exec -u www-data app php artisan <lệnh>`. Ví dụ nạp lại dữ liệu: `migrate:fresh --seed --force`.

## 6. Cài đặt thủ công không Docker

> **Lưu ý:** cách này **chưa được kiểm chứng** trên máy không có Docker; nhóm chỉ chạy thử hệ thống bằng Docker Compose (Ubuntu 22.04). Các lệnh dưới đây suy ra từ cấu hình dự án, dùng khi máy chấm không cài được Docker.

1. Tạo CSDL MySQL 8.0 rỗng `dukhach` (utf8mb4_unicode_ci) và một tài khoản có toàn quyền trên CSDL đó.
2. Ứng dụng web (thư mục `app/`):
   ```bash
   cd app
   composer install
   cp .env.example .env            # Windows: copy .env.example .env
   # Sửa .env: DB_CONNECTION=mysql, DB_HOST=127.0.0.1, DB_PORT=3306, DB_DATABASE=dukhach,
   #           DB_USERNAME, DB_PASSWORD; ANALYTICS_URL=http://127.0.0.1:8001; ANALYTICS_TOKEN;
   #           PAYMENT_SANDBOX_SECRET; SEED_DEMO_PASSWORD
   php artisan key:generate
   php artisan migrate --seed
   php artisan storage:link
   npm ci && npm run build
   php artisan serve               # http://127.0.0.1:8000
   ```
3. Dịch vụ Python (thư mục `python-service/`, terminal khác):
   ```bash
   cd python-service
   python3.12 -m venv .venv && . .venv/bin/activate     # Windows: .venv\Scripts\activate
   pip install -r requirements.txt
   # đặt biến môi trường DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
   # và ANALYTICS_TOKEN trùng giá trị trong app/.env
   uvicorn app.main:app --port 8001
   ```
4. Nếu dịch vụ Python không chạy, ứng dụng web vẫn hoạt động và tìm kiếm tự chuyển sang FULLTEXT MySQL. Lịch nền (giải phóng giữ chỗ, sao lưu, đồng bộ lượt xem) cần thêm `php artisan schedule:work` ở một terminal riêng.

## 7. Kiểm thử

Máy phát triển không cần cài PHP, mọi lệnh PHP chạy qua Docker. Các lệnh dưới đây chạy từ `02_SourceCode`, khi hệ thống đang chạy.

```bash
# Cài vendor dev cho PHPUnit (lần đầu)
docker run --rm -u $(id -u):$(id -g) -v "$PWD/app":/app -w /app composer:2 install

# PHPUnit: image runtime build --no-dev nên mount mã nguồn; dùng CSDL test dukhach_test
docker run --rm -u $(id -u):$(id -g) --network 02_sourcecode_default \
  -v "$PWD/app":/var/www/html -w /var/www/html -e HOME=/tmp \
  --entrypoint php 02_sourcecode-app artisan test
#   Kết quả 18/10/2026 (lượt r3, 05_KiemThu/unit/phpunit_r3_18102026.txt): 208 passed, 1 skipped (thiếu pcntl)

# pytest (chạy từ thư mục python-service)
cd python-service
docker run --rm -v "$PWD":/src -w /src --entrypoint sh 02_sourcecode-analytics \
  -c "pip install -q -r requirements-dev.txt && python -m pytest -q"
#   Kết quả 18/10/2026: 56 passed
cd ..

# Playwright ba trình duyệt (chạy từ app/; base URL lấy từ E2E_BASE_URL, mặc định http://localhost:8081)
cd app
npx playwright install chromium firefox webkit
npx playwright test --project=chromium --project=firefox --project=webkit
npx playwright show-report tests/e2e/report
cd ..

# Lighthouse mobile
npx lighthouse http://localhost:8081/vi --form-factor=mobile --output=json \
  --output-path=../05_KiemThu/lighthouse/home.json --chrome-flags="--headless"

# Kiểm định chất lượng dữ liệu (mã thoát 0: không lỗi; 1: có lỗi; 2: không kết nối được CSDL)
docker compose exec analytics python kiem_dinh_du_lieu.py

# Rà soát phụ thuộc
docker run --rm -u $(id -u):$(id -g) -v "$PWD/app":/app -w /app composer:2 audit
(cd app && npm audit)
```

Kết quả kiểm thử, ảnh minh chứng và báo cáo Lighthouse nằm trong `../05_KiemThu`. Vòng hồi quy 18/10/2026: 118 ca, 115 Đạt, 3 Không đạt (`../05_KiemThu/BaoCao_KiemThu.md`). Danh mục ca kiểm thử nằm trong sheet CHECKLIST_KIEMTHU của `../07_QuanTriDuAn/CSE703073_QuanTriDuAn_KiemThu.xlsx`.

## 8. Vận hành

- **Sao lưu:** chạy tự động khi container khởi động và lúc 02:00 hằng ngày, giữ 7 bản trong volume `app_storage` (`storage/app/backups`). Chạy tay: `docker compose exec -u www-data app php artisan app:backup-db`.
- **Nhật ký:**
  - hạ tầng: `docker compose logs -f app`;
  - ứng dụng: `storage/logs/laravel.log`, `backup.log`;
  - nghiệp vụ: bảng `audit_logs`, xem tại `/vi/quan-tri/nhat-ky`.
- Hướng dẫn chi tiết, kèm go-live lên Railway/Render: `../04_HuongDanSuDung/HuongDan_CaiDat_QuanTri.md`.

## 9. Thành viên

| Họ tên | MSSV | Vai trò |
| --- | --- | --- |
| <Họ tên 1> (Trưởng nhóm) | <MSSV1> | V1 Trưởng nhóm, quản trị cấu hình; V6 Nghiệp vụ và tài liệu |
| <Họ tên 2> | <MSSV2> | V2 Kiến trúc sư dữ liệu |
| <Họ tên 3> | <MSSV3> | V3 Phát triển máy chủ |
| <Họ tên 4> | <MSSV4> | V4 Phát triển giao diện; V5 Kiểm thử và triển khai |

## 10. Giấy phép

- **Mã nguồn do nhóm viết:** sản phẩm học thuật, chỉ phục vụ mục đích đào tạo trong học phần CSE703073 [CẬP NHẬT: ghi giấy phép mở nếu nhóm quyết định công bố, ví dụ MIT].
- **Dữ liệu `database/data/*.json`:** biên soạn từ nguồn công khai, có ghi nguồn trong từng bản ghi. Ảnh và âm thanh hạt giống là tư liệu mô phỏng (giấy phép `simulated`).
- **Thư viện bên thứ ba:** giữ giấy phép gốc (MIT, BSD, Apache-2.0, GPL-2.0 cho MySQL, OFL cho phông chữ, ODbL cho dữ liệu OpenStreetMap, điều khoản Google Maps cho tile bản đồ nền). Danh mục đầy đủ ở `../00_INFO.md`, Mục 10.
