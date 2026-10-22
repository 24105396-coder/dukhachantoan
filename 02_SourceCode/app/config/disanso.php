<?php

return [
    'version' => env('APP_VERSION', '1.0.0'),

    // Thông tin chân trang bắt buộc theo đề thi (Mục 2.4): dùng chung cho Vue, trang Blade và trang lỗi
    'academic' => [
        'course' => 'Lập trình ứng dụng web trong du lịch 2',
        'course_code' => 'CSE703073',
        'team' => env('APP_TEAM_NAME', 'Nhóm 4 – DiSanSo'),
    ],

    'locales' => [
        'vi' => 'Tiếng Việt',
        'en' => 'English',
    ],

    'analytics' => [
        'url' => env('ANALYTICS_URL', 'http://analytics:8001'),
        'token' => env('ANALYTICS_TOKEN', ''),
        'timeout' => (float) env('ANALYTICS_TIMEOUT', 2),
        'status_ttl' => 60,
    ],

    'payment' => [
        'gateway' => env('PAYMENT_GATEWAY', 'sandbox'),
        // Không có giá trị mặc định: entrypoint Docker sinh ngẫu nhiên và lưu trong volume nếu thiếu.
        'sandbox_secret' => (string) env('PAYMENT_SANDBOX_SECRET', ''),
    ],

    'booking' => [
        'hold_minutes' => (int) env('BOOKING_HOLD_MINUTES', 10),
        'max_quantity' => 10,
        // Chống chiếm giữ chỗ: số đặt lịch chưa thanh toán đồng thời và tổng chỗ mỗi ngày cho một tài khoản
        'max_active_per_user' => (int) env('BOOKING_MAX_ACTIVE_PER_USER', 3),
        'max_seats_per_day' => (int) env('BOOKING_MAX_SEATS_PER_DAY', 10),
        'cancel_before_hours' => 24,
    ],

    // Bản đồ nền: google (tile Google Maps, mặc định theo yêu cầu giảng viên) hoặc osm (OpenStreetMap, dữ liệu mở)
    'map' => [
        'provider' => env('MAP_PROVIDER', 'google'),
    ],

    'seed_demo_password' => env('SEED_DEMO_PASSWORD', 'DiSan@2026'),

    'quality_cache_minutes' => 10,

    // Bộ đệm dữ liệu trang công khai (giây); store "file" để không thêm truy vấn MySQL. TTL 0 = tắt.
    'page_cache' => [
        'store' => env('PAGE_CACHE_STORE', 'file'),
        'ttl' => (int) env('PAGE_CACHE_TTL', 60),
    ],
];
