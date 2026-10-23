# deploy/ – mẫu biến môi trường cho Railway

- `railway-web.env`: dịch vụ web (Laravel). Dán vào Variables → Raw Editor của dịch vụ `web`.
- `railway-analytics.env`: dịch vụ Python. Dán vào dịch vụ `analytics`.
- Render dùng `render.yaml` ở gốc kho (Blueprint): 2 dịch vụ web Docker gói Free (`dukhach-web`, `dukhach-analytics`), vùng Singapore.
  Render không có MySQL quản lý → dùng MySQL ngoài (Aiven Free, Railway MySQL…) và điền `DB_HOST/PORT/DATABASE/USERNAME/PASSWORD`
  cho cả hai dịch vụ khi Apply; MySQL yêu cầu TLS thì dán nội dung chứng thư CA (PEM) vào `DB_SSL_CA`.
  Entrypoint tự suy `APP_URL` (từ `RENDER_EXTERNAL_URL`), `ANALYTICS_URL` (từ `ANALYTICS_HOST/PORT`) và `APP_KEY` (từ `APP_KEY_SEED`).
  Gói Free ngủ sau 15 phút không truy cập, không có đĩa bền: ảnh tải lên và bản sao lưu mất khi deploy lại (dữ liệu trong MySQL ngoài vẫn giữ).

Quy trình đầy đủ: `04_HuongDanSuDung/HuongDan_CaiDat_QuanTri.md` Mục 4. Không bao giờ commit tệp đã điền giá trị bí mật thật.
