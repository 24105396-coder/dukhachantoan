# DuKhachAnToan – Hệ thống cảnh báo rủi ro và hỗ trợ du khách theo thời gian thực

Dự án cuối kỳ CSE703073 (Đại học Phenikaa), đề tài ĐT-15. Hồ sơ nộp theo thư mục `00_INFO.md` … `07_QuanTriDuAn`.

| Thư mục | Nội dung |
| --- | --- |
| `00_INFO.md` | Tệp đầu mối (Mẫu PL-J): nhóm, kho Git, URL, video, tài khoản, cài đặt, chức năng, giấy phép |
| `01_BaoCao` | Báo cáo `BaoCao_DuKhachAnToan.docx` / `.pdf`, nguồn Markdown `src/`, hình `hinh/`, script dựng `build/` |
| `02_SourceCode` | Mã nguồn: `app` (Laravel 12 + Inertia + Vue 3.5), `python-service` (FastAPI), `compose.yaml`, `deploy/` |
| `03_Database` | `schema.sql`, `seed_sample.sql`, `ERD.mmd`/`ERD.png`, `TuDienDuLieu.md`, `kiem_dinh_du_lieu.py` |
| `04_HuongDanSuDung` | Hướng dẫn người dùng; hướng dẫn cài đặt, triển khai, quản trị |
| `05_KiemThu` | Danh mục ca kiểm thử, kết quả, minh chứng |
| `06_Demo` | Kịch bản và liên kết video minh chứng |
| `07_QuanTriDuAn` | Phân công (PL-E), khai báo AI (PL-D), 10 biên bản họp (PL-F), báo cáo mốc M1–M3, nhật ký công việc, hướng dẫn Git, tài liệu vấn đáp |

Chạy nhanh: mở Docker Desktop, nhấn đúp `02_SourceCode/chay.bat` (macOS/Linux: `./chay.sh`), mở http://localhost:8081/vi, đăng nhập `traveler@dukhach.test` / `DuKhach@2026`. Hướng dẫn cho người không chuyên: `07_QuanTriDuAn/QuyTrinh_LamDuAn_TuDauDenCuoi.md`.
