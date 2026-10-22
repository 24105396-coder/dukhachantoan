<?php

return [
    'app' => [
        'notice' => 'Sản phẩm học thuật – phục vụ mục đích đào tạo. Một phần cảnh báo và dữ liệu cá nhân là mô phỏng.',
    ],
    'auth' => [
        'logged_in' => 'Đăng nhập thành công. Chào mừng :name!',
        'logged_out' => 'Bạn đã đăng xuất.',
        'registered' => 'Tạo tài khoản thành công.',
        'inactive' => 'Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên.',
    ],
    'profile' => [
        'updated' => 'Đã cập nhật hồ sơ.',
        'password_updated' => 'Đã đổi mật khẩu.',
    ],
    'admin' => [
        'created' => 'Đã tạo mới.',
        'updated' => 'Đã lưu thay đổi.',
        'deleted' => 'Đã xóa.',
        'user_updated' => 'Đã cập nhật người dùng.',
        'cannot_demote_self' => 'Bạn không thể tự hạ quyền hoặc khóa chính mình.',
        'backup_ok' => 'Đã sao lưu cơ sở dữ liệu.',
        'backup_failed' => 'Sao lưu thất bại, xem nhật ký máy chủ.',
    ],
    'guides' => [
        'contacts_hint' => 'Khẩn cấp gọi 112 (cứu nạn), 113 (công an), 114 (cứu hỏa), 115 (cấp cứu). Danh bạ theo tỉnh ở trang Danh bạ khẩn cấp.',
    ],
    'notify' => [
        'match_body' => "Hành trình \":trip\" – chặng :place nằm trong vùng ảnh hưởng.\n:description\nKhuyến cáo: :instructions\nNguồn: :source",
        'digest_title' => 'Bản tin thông tin ngày :date (:count mục)',
        'reminder_prefix' => '[Nhắc lại]',
        'checkin_response_title' => 'Cơ quan hỗ trợ đã phản hồi báo an toàn – :trip',
        'need_help_title' => '[KHẨN CẤP] Du khách cần hỗ trợ – :trip (:code)',
        'need_help_body' => "Liên hệ: :contact\nLời nhắn: :message\nVị trí: :position",
    ],
    'alerts' => [
        'created' => 'Đã tạo cảnh báo nháp.',
        'updated' => 'Đã lưu cảnh báo.',
        'not_editable' => 'Cảnh báo đã hết hiệu lực hoặc bị hủy, không sửa được.',
        'invalid_transition' => 'Chuyển trạng thái không hợp lệ.',
        'invalid_transition_from' => 'Không thể chuyển cảnh báo từ ":from" sang ":to".',
        'default_source' => 'Cơ quan hỗ trợ du khách',
        'invalid_geometry' => 'Hình học không hợp lệ: cần GeoJSON Polygon/MultiPolygon hoặc {type: Circle, center: {lat, lng}, radius_km}.',
        'transitioned' => 'Cảnh báo chuyển sang ":status" (đối sánh mới: :matches, thông báo: :notifications).',
    ],
    'trips' => [
        'created' => 'Đã tạo hành trình.',
        'updated' => 'Đã cập nhật hành trình.',
        'cancelled' => 'Đã hủy hành trình, các thông báo đang chờ đã dừng.',
        'acknowledged' => 'Đã xác nhận cảnh báo.',
        'not_editable' => 'Hành trình đã hoàn thành hoặc đã hủy, không sửa được.',
        'not_cancellable' => 'Hành trình đã hoàn thành hoặc đã hủy.',
        'leg_end_before_start' => 'Giờ kết thúc chặng phải sau giờ bắt đầu.',
        'leg_outside_dates' => 'Chặng phải nằm trong khoảng ngày của hành trình.',
        'leg_overlap' => 'Chặng bị chồng thời gian với chặng :other.',
    ],
    'checkins' => [
        'recorded' => 'Đã ghi nhận báo an toàn.',
        'responded' => 'Đã lưu phản hồi.',
    ],
    'notifications' => [
        'all_read' => 'Đã đánh dấu :count thông báo là đã đọc.',
        'requeued' => 'Đã đưa thông báo vào hàng đợi gửi lại.',
        'broadcast' => 'Đã xếp :notifications thông báo cho :recipients người nhận.',
    ],
    'incidents' => [
        'reported' => 'Đã gửi báo cáo sự cố, cơ quan hỗ trợ sẽ xác minh.',
        'reviewed' => 'Đã lưu kết quả duyệt.',
        'verify_first' => 'Chỉ tạo cảnh báo từ sự cố đã xác minh.',
        'alert_source' => 'Báo cáo thực địa đã xác minh bởi :org',
        'alert_created' => 'Đã tạo cảnh báo nháp từ sự cố, kiểm tra rồi phát hành.',
    ],
    'simulation' => [
        'no_steps' => 'Kịch bản chưa có bước nào.',
        'source_name' => 'Kịch bản mô phỏng: :name',
        'already_finished' => 'Lần chạy đã kết thúc.',
        'started' => 'Đã chạy kịch bản: :alerts cảnh báo, :matches đối sánh, :notifications thông báo (bước trễ sẽ tiếp tục theo lịch).',
        'finished' => 'Đã kết thúc mô phỏng, các cảnh báo mô phỏng đã hết hiệu lực.',
    ],
    'sources' => [
        'fetched' => 'Đã lấy dữ liệu Open-Meteo, :drafts cảnh báo nháp mới.',
        'fetch_failed' => 'Dịch vụ phân tích không phản hồi, giữ dữ liệu cũ.',
    ],
    'locales' => [
        'vi' => 'Tiếng Việt',
        'en' => 'English',
    ],
];
