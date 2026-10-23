<?php

return [
    'version' => env('APP_VERSION', '0.1.0'),

    // Thông tin chân trang bắt buộc theo đề thi (Mục 2.4): dùng chung cho Vue, trang Blade và trang lỗi
    'academic' => [
        'course' => 'Lập trình ứng dụng web trong du lịch 2',
        'course_code' => 'CSE703073',
        'topic' => 'ĐT-15 – Hệ thống cảnh báo rủi ro và hỗ trợ du khách theo thời gian thực',
        'team' => env('APP_TEAM_NAME', 'Nhóm – DuKhachAnToan'),
    ],

    'locales' => [
        'vi' => 'Tiếng Việt',
        'en' => 'English',
    ],

    'analytics' => [
        'url' => env('ANALYTICS_URL', 'http://analytics:8001'),
        'token' => env('ANALYTICS_TOKEN', ''),
        // Đề yêu cầu dự phòng nhanh: 3 giây rồi chuyển sang tính toán bằng PHP
        'timeout' => (float) env('ANALYTICS_TIMEOUT', 3),
        'status_ttl' => 60,
        // /healthz thăm dò trực tiếp: timeout ngắn, nhớ kết quả tối đa vài giây
        'probe_timeout' => (float) env('ANALYTICS_PROBE_TIMEOUT', 1.5),
        'probe_ttl' => 5,
    ],

    // Chế độ mô phỏng: bật nút chạy kịch bản và nhãn [MÔ PHỎNG] trên giao diện
    'simulation_mode' => (bool) env('SIMULATION_MODE', true),

    'notifications' => [
        // SMS mô phỏng thất bại ngẫu nhiên để chứng minh cơ chế thử lại; 0 = luôn thành công
        'sms_fail_rate' => (float) env('SMS_SIM_FAIL_RATE', 0.1),
        'dispatch_batch' => (int) env('NOTIFY_DISPATCH_BATCH', 100),
        // Bản tin gộp mức 1 gửi lúc giờ này hằng ngày (email/SMS)
        'digest_hour' => (int) env('NOTIFY_DIGEST_HOUR', 7),
        // Thông báo kẹt ở trạng thái "sending" quá số phút này (worker chết) được đưa lại hàng đợi
        'stale_sending_minutes' => 10,
    ],

    // 5 vùng du lịch hiển thị thời tiết trên trang chủ
    'weather' => [
        'highlight_zones' => ['ha-noi', 'vinh-ha-long', 'da-nang', 'vinh-nha-trang', 'phu-quoc'],
        'default_zone' => 'ha-noi',
    ],

    // Mô phỏng: bước có at_minute > 0 chạy trễ bằng job (realtime) hoặc tạo ngay với starts_at lệch (instant)
    'simulation' => [
        'default_mode' => env('SIMULATION_MODE_RUN', 'realtime'),
    ],

    // Bản đồ nền: osm (OpenStreetMap, dữ liệu mở, mặc định) hoặc google
    'map' => [
        'provider' => env('MAP_PROVIDER', 'osm'),
    ],

    'seed_demo_password' => env('SEED_DEMO_PASSWORD', 'DuKhach@2026'),
    // Thư mục JSON hạt giống; rỗng = database/data (test trỏ sang tests/Fixtures/seed)
    'seed_data_path' => env('SEED_DATA_PATH'),

    // Bộ đệm dữ liệu trang công khai (giây); store "file" để không thêm truy vấn MySQL. TTL 0 = tắt.
    'page_cache' => [
        'store' => env('PAGE_CACHE_STORE', 'file'),
        'ttl' => (int) env('PAGE_CACHE_TTL', 60),
    ],
];
