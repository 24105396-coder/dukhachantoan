# DiSanSo Analytics (FastAPI)

Dịch vụ Python nội bộ của DiSanSo: tìm kiếm ngữ nghĩa TF-IDF đa ngữ (chịu được gõ không dấu), gợi ý thẻ chủ đề cho đóng góp cộng đồng, kiểm định chất lượng dữ liệu đa ngữ và thống kê tổng quan. Laravel gọi dịch vụ qua `App\Services\AnalyticsClient` (timeout 2 giây, lỗi thì dùng FULLTEXT MySQL).

## Cấu trúc

| Tệp | Vai trò |
| --- | --- |
| `app/main.py` | Ứng dụng FastAPI, xác thực `X-Analytics-Token`, lifespan dựng chỉ mục + reindex định kỳ 10 phút |
| `app/db.py` | SQLAlchemy + PyMySQL (utf8mb4), `load_documents()`, `load_tags()`, nạp bảng cho kiểm định/thống kê |
| `app/text.py` | Chuẩn hóa NFC, chữ thường, bỏ dấu tiếng Việt (bảng ánh xạ đầy đủ, đ→d), tokenizer |
| `app/vectorize.py` | Bộ vector lai TF-IDF word (1,2)-gram + char_wb (2,4)-gram |
| `app/search.py` | `SearchIndex`: một không gian vector cho mỗi locale, tiêu đề ×3, gộp depth |
| `app/tags.py` | `TagSuggester`: cosine với mô tả thẻ + bỏ phiếu thẻ của top-5 tài liệu giống nhất |
| `app/quality.py` | 11 phép kiểm định chất lượng (Q01–Q11) chạy trên DataFrame |
| `app/stats.py` | Thống kê tổng quan |
| `kiem_dinh_du_lieu.py` | CLI kiểm định (bản sao ở `03_Database/kiem_dinh_du_lieu.py`) |

## Chạy

```bash
# Cùng toàn hệ thống (từ 02_SourceCode)
docker compose up -d --build
curl -s localhost:8001/healthz
# Tài liệu OpenAPI: http://localhost:8001/docs

# Kiểm định dữ liệu (exit 0 = sạch, 1 = có lỗi mức error, 2 = không kết nối được DB)
docker compose exec analytics python kiem_dinh_du_lieu.py
docker compose exec analytics python kiem_dinh_du_lieu.py --json

# Cục bộ không Docker (MySQL của compose ở cổng 3307)
cd python-service
python3 -m venv .venv && . .venv/bin/activate
pip install -r requirements-dev.txt
DB_HOST=127.0.0.1 DB_PORT=3307 DB_PASSWORD=disanso_secret ANALYTICS_TOKEN=dev-analytics-token \
  uvicorn app.main:app --port 8001
```

Biến môi trường: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `ANALYTICS_TOKEN` (bắt buộc; thiếu thì mọi endpoint có bảo vệ trả 401), tùy chọn `REINDEX_INTERVAL_SECONDS` (mặc định 600), `LOG_LEVEL`.

Nếu MySQL chưa sẵn sàng khi khởi động, dịch vụ vẫn chạy với chỉ mục rỗng (`/healthz` báo `index_size = 0`) và tự thử lại sau mỗi chu kỳ reindex; `/reindex` có thể gọi tay ngay khi seed xong.

## Kiểm thử

```bash
# Cục bộ (không cần MySQL: dùng DataFrame fixture trong tests/conftest.py)
pip install -r requirements-dev.txt && python -m pytest

# Trong image (pytest không cài sẵn trong image sản phẩm)
docker run --rm -v "$PWD":/src -w /src --entrypoint sh 02_sourcecode-analytics \
  -c "pip install -q pytest==8.3.5 httpx==0.28.1 && python -m pytest"
```

## API (mọi endpoint trừ `/healthz` cần header `X-Analytics-Token`)

| Method | URL | Body → Kết quả |
| --- | --- | --- |
| GET | `/healthz` | `{status, index_size, indexed_at}` |
| POST | `/search` | `{q, locale='vi', limit=20 (1..100), types?: ['site','artifact','figure']}` → `{engine:'tfidf', took_ms, results:[{subject_type, subject_id, slug, score, matched_terms}]}` |
| POST | `/suggest-tags` | `{text, locale='vi', limit=5 (1..20)}` → `{suggestions:[{tag_id, slug, score}]}` |
| GET | `/quality-report` | `{generated_at, summary:{errors, warnings, checks}, checks:[{code, severity, title, count, samples}]}` |
| POST | `/reindex` | `{indexed, took_ms}` (503 nếu DB lỗi, chỉ mục cũ được giữ) |
| GET | `/stats/overview` | `{sites, artifacts, figures, interpretations, media, by_province:[{province_id, code, name, name_en, region, sites}], by_period:[{period_id, code, name, name_en, start_year, sites, artifacts, figures}]}` |

Mã lỗi: 401 token sai/thiếu, 422 dữ liệu vào không hợp lệ (locale ngoài vi/en, q rỗng, types lạ), 503 DB không truy cập được.

`summary.errors`/`summary.warnings` là tổng số bản ghi vi phạm của các phép kiểm mức `error`/`warning`; `samples` tối đa 10 mục.

### Danh mục kiểm định

| Mã | Mức | Nội dung |
| --- | --- | --- |
| Q01_MISSING_DEPTH1_VI | error | Thực thể `published` thiếu diễn giải depth 1 tiếng Việt |
| Q02_MISSING_DEPTH1_EN | error | Thực thể `published` thiếu diễn giải depth 1 tiếng Anh |
| Q03_COORD_OUT_OF_BBOX | error | Tọa độ di sản ngoài khung 8.0–23.5 N, 102.0–110.0 E (hoặc thiếu) |
| Q04_DUPLICATE_SLUG | error | Slug trùng trong cùng loại sau chuẩn hóa (chữ hoa, dấu) |
| Q05_DUPLICATE_SLUG_CROSS_TYPE | warning | Cùng slug ở hai loại thực thể (site/artifact/figure/route) |
| Q06_MEDIA_MISSING_LICENSE | error | Media không có license |
| Q07_MEDIA_MISSING_SOURCE | warning | Media không có cả `source_name` lẫn `source_url` |
| Q08_EVENT_OUTSIDE_PERIOD | warning | `event_year` ngoài `[start_year, end_year]` của thời kỳ (end_year NULL = đến nay) |
| Q09_STOP_UNPUBLISHED_SITE | error | Tuyến `published` có điểm dừng trỏ di sản chưa công bố hoặc đã xóa mềm |
| Q10_SLOT_OVERBOOKED | error | Khung giờ có `booked_count > capacity` |
| Q11_MT_UNREVIEWED | warning | `is_machine_translated = 1` nhưng `reviewed_by` rỗng |

## Thuật toán

**Tìm kiếm.** Mỗi bản ghi `interpretations` (của thực thể `published`, chưa xóa mềm) là một tài liệu: `tiêu đề ×3 + tóm tắt + nội dung + tên thẻ cùng locale`. Văn bản được chuẩn hóa NFC, chữ thường, rồi ghép bản có dấu với bản không dấu. Mỗi locale có một bộ vector riêng gồm TF-IDF word (1,2)-gram (sublinear tf) và TF-IDF char_wb (2,4)-gram. Điểm = 0,6·cosine_word + 0,4·cosine_char. Tài liệu không chung từ nào với truy vấn chỉ được giữ nếu điểm char ≥ max(0,15; 0,6 × điểm char cao nhất) để loại nhiễu n-gram ký tự mà vẫn bắt được lỗi gõ ("hoang thnah") hay viết dính ("trongdong"). Các depth của cùng thực thể được gộp, lấy điểm cao nhất; `matched_terms` là các token truy vấn (bỏ hư từ) xuất hiện trong văn bản tài liệu dạng không dấu.

**Gợi ý thẻ.** Điểm thẻ = 0,5·cosine(văn bản, mô tả thẻ) + 0,5·phiếu bầu. Mô tả thẻ = tên ×2 + slug + mô tả (`tag_translations`), biểu diễn trong cùng không gian vector với tài liệu của locale đó. Phiếu bầu: 5 tài liệu giống văn bản nhất, mỗi tài liệu bầu cho các thẻ của nó với trọng số bằng độ giống đã chuẩn hóa (tổng = 1).

## Hạn chế của thuật toán

1. **Không hiểu ngữ nghĩa thật.** TF-IDF là mô hình túi từ: không nhận ra từ đồng nghĩa ("kinh đô" – "kinh thành" – "cố đô") hay quan hệ khái niệm nếu không có chữ chung; không có embedding hay mô hình ngôn ngữ.
2. **Không tách từ ghép tiếng Việt.** Tokenizer tách theo âm tiết; bigram word bù một phần ("thăng long") nhưng không có từ điển từ ghép (pyvi/underthesea) nên "hoàng thành" và "thành" trong "thành phố" vẫn chia sẻ token.
3. **Bỏ dấu gây nhập nhằng.** Bản không dấu giúp người gõ không dấu nhưng làm trùng các từ khác nghĩa ("trống/trong", "cổ/có/cô"); điểm của bản có dấu giúp xếp hạng đúng hơn khi người dùng gõ có dấu, nhưng truy vấn không dấu ngắn có thể trả kết quả lạc đề.
4. **Không xuyên ngữ.** Mỗi locale một chỉ mục; truy vấn tiếng Anh trên locale `vi` không được dịch, chỉ khớp tên riêng không dấu.
5. **Ngưỡng và trọng số chọn thủ công** (0,6/0,4; tiêu đề ×3; ngưỡng char 0,15 và 0,6) trên tập dữ liệu nhỏ, chưa được đánh giá bằng tập truy vấn có nhãn (precision@k, nDCG).
6. **Độ mới của chỉ mục.** Chỉ mục nằm trong bộ nhớ, dựng lại toàn bộ mỗi 10 phút hoặc khi gọi `/reindex`; nội dung vừa công bố có thể chưa tìm thấy ngay. Dựng lại toàn bộ phù hợp với vài nghìn tài liệu, không phù hợp quy mô lớn (cần chỉ mục tăng dần hoặc công cụ chuyên dụng).
7. **Gợi ý thẻ phụ thuộc dữ liệu đã gắn thẻ.** Mô tả thẻ ngắn nên cosine yếu; phần bỏ phiếu chỉ tốt khi các thực thể hiện có được gắn thẻ đầy đủ và đúng (thiên lệch theo thẻ phổ biến).
8. **Kiểm định dựa trên luật.** Khung tọa độ chữ nhật là xấp xỉ (một phần Lào/Campuchia/Trung Quốc nằm trong khung; quần đảo Hoàng Sa, Trường Sa nằm ngoài kinh độ 110° nên sẽ bị báo); không phát hiện được sai sót nội dung (năm sai nhưng vẫn trong thời kỳ, bản dịch sai nghĩa).
