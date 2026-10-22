# Dịch vụ phân tích DuKhachAnToan (Python)

FastAPI nội bộ, Laravel gọi qua `AnalyticsClient` (`ANALYTICS_URL`, header `X-Analytics-Token`).
Nhiệm vụ: lấy thời tiết Open-Meteo cho tâm từng vùng, tính điểm rủi ro 0–100, đối sánh không gian
cảnh báo với chặng hành trình (shapely + haversine), sinh cảnh báo nháp từ ngưỡng thời tiết, thống kê,
kiểm định chất lượng dữ liệu (Q01–Q10).

## Endpoint
| Method | URL | Token | Ghi chú |
| --- | --- | --- | --- |
| GET | /healthz | không | `{status, db: up/down, last_fetch, version, updated_at}`; DB tắt vẫn 200 |
| POST | /weather/refresh | có | `{zone_codes?, force?}` → `{fetched, cached, zones, observations, forecasts, took_ms, last_fetch, error?}` |
| POST | /risk/compute | có | `{zone_codes?}` → `[{zone_code, score, level, delta}]`, ghi `risk_scores` |
| POST | /match | có | `{alert_id, trip_id?}` → `{alert_id, alert_code, matches: [...], total, created}`, ghi `alert_matches` |
| POST | /geo/contains | có | `{geometry, points: [{id, lat, lng}]}` → `{inside, count, distances_km}` |
| POST | /weather/derive-alerts | có | `{zone_codes?, days? (1–7, mặc định 3)}` → `{drafts, count, generated_at}`; không ghi DB |
| GET | /stats/overview | có | thống kê dashboard (không dữ liệu cá nhân) |

Sai/thiếu token → 401 (token server chưa đặt cũng 401). DB không truy cập được → 503. Lỗi mạng
Open-Meteo → 200 với `fetched: 0` và `error`.

## Biến môi trường
`DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD` (hoặc `DB_URL` dạng SQLAlchemy),
`ANALYTICS_TOKEN`, `APP_ENV` (khác `production` thì bật `/docs`), `APP_TIMEZONE` (mặc định
Asia/Ho_Chi_Minh, khớp Laravel), `OPEN_METEO_URL`, `OPEN_METEO_TIMEOUT` (10 s), `WEATHER_CACHE_TTL` (600 s),
`OPEN_METEO_BATCH` (50 điểm/request), `PORT` (8001), `UVICORN_HOST` (`dual` = IPv4 + IPv6).

## Chạy
```bash
cd 02_SourceCode/python-service
docker build -t dukhach-analytics .
# MySQL của compose ở 127.0.0.1:3308
docker run --rm --network host -e PORT=8002 -e DB_HOST=127.0.0.1 -e DB_PORT=3308 -e DB_DATABASE=dukhach \
  -e DB_USERNAME=dukhach -e DB_PASSWORD=dukhach_secret -e ANALYTICS_TOKEN=dev-analytics-token dukhach-analytics
curl -s localhost:8002/healthz
curl -s -X POST localhost:8002/weather/refresh -H 'X-Analytics-Token: dev-analytics-token' \
  -H 'Content-Type: application/json' -d '{"zone_codes":["khanh-hoa"]}'
```

## Kiểm thử
```bash
docker run --rm -u root -v "$PWD":/src -w /src --entrypoint sh dukhach-analytics \
  -c "pip install -q -r requirements-dev.txt && python -m pytest -q"
```
Test dùng SQLite in-memory dựng từ `app/schema.py` và respx giả lập Open-Meteo, không cần mạng hay MySQL.

## Kiểm định dữ liệu
```bash
docker compose exec analytics python -I kiem_dinh_du_lieu.py          # bảng; --json để xuất JSON
DB_HOST=127.0.0.1 DB_PORT=3308 DB_PASSWORD=dukhach_secret python3 -I ../../03_Database/kiem_dinh_du_lieu.py
```
Mã thoát 0 = không lỗi, 1 = có lỗi mức error, 2 = không kết nối được DB. Tệp ở `03_Database/` là bản sao giống hệt.

## Cấu trúc
`app/main.py` (route, token), `openmeteo.py` (client, cache), `weather.py` (upsert quan trắc/dự báo),
`risk.py` (công thức thuần) + `risk_service.py` (đọc DB, ghi `risk_scores`), `matching.py`, `derive.py`,
`geo.py` (shapely, haversine), `repo.py` (truy vấn chung), `schema.py` (SQLAlchemy Core), `stats.py`.
