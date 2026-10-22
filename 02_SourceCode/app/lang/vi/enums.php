<?php

return [
    'site_types' => [
        'di_tich' => 'Di tích lịch sử – văn hóa', 'danh_thang' => 'Danh lam thắng cảnh',
        'di_san_phi_vat_the' => 'Di sản phi vật thể', 'bao_tang' => 'Bảo tàng', 'lang_nghe' => 'Làng nghề truyền thống',
    ],
    'levels' => [
        'unesco' => 'Di sản thế giới (UNESCO)', 'quoc_gia_dac_biet' => 'Di tích quốc gia đặc biệt',
        'quoc_gia' => 'Di tích quốc gia', 'cap_tinh' => 'Di tích cấp tỉnh', 'chua_xep_hang' => 'Chưa xếp hạng',
    ],
    'artifact_types' => [
        'tuong' => 'Tượng', 'bia_ky' => 'Bia ký', 'do_gom' => 'Đồ gốm', 'do_dong' => 'Đồ đồng', 'vu_khi' => 'Vũ khí',
        'trang_phuc' => 'Trang phục', 'kien_truc' => 'Thành phần kiến trúc', 'tai_lieu' => 'Tài liệu', 'bao_vat' => 'Bảo vật', 'khac' => 'Khác',
    ],
    'figure_types' => [
        'vua_chua' => 'Vua chúa', 'tuong_linh' => 'Tướng lĩnh', 'nha_van_hoa' => 'Nhà văn hóa', 'anh_hung' => 'Anh hùng dân tộc',
        'nhan_vat_ton_giao' => 'Nhân vật tôn giáo', 'nghe_nhan' => 'Nghệ nhân', 'khac' => 'Khác',
    ],
    'relation_types' => [
        'sang_lap' => 'Người sáng lập', 'duoc_tho' => 'Được thờ phụng', 'gan_lien' => 'Gắn liền', 'trung_tu' => 'Trùng tu',
        'sinh_ra' => 'Nơi sinh', 'an_tang' => 'Nơi an táng',
    ],
    'event_types' => [
        'xay_dung' => 'Xây dựng', 'trung_tu' => 'Trùng tu', 'cong_nhan' => 'Công nhận', 'su_kien' => 'Sự kiện',
        'sinh' => 'Sinh', 'mat' => 'Mất', 'phat_hien' => 'Phát hiện', 'khac' => 'Khác',
    ],
    'licenses' => [
        'public_domain' => 'Phạm vi công cộng', 'cc_by' => 'CC BY', 'cc_by_sa' => 'CC BY-SA', 'cc_by_nc' => 'CC BY-NC',
        'all_rights_reserved' => 'Bảo lưu mọi quyền', 'simulated' => 'Minh họa mô phỏng (do nhóm tạo)',
    ],
    'tag_groups' => [
        'chu_de' => 'Chủ đề', 'loai_hinh' => 'Loại hình', 'ton_giao' => 'Tôn giáo – tín ngưỡng', 'phong_cach' => 'Phong cách',
        'vat_lieu' => 'Vật liệu', 'thoi_ky' => 'Thời kỳ', 'vung_mien' => 'Vùng miền',
    ],
    'contribution_types' => [
        'new_site' => 'Đề xuất di sản mới', 'new_artifact' => 'Đề xuất hiện vật mới', 'correction' => 'Đính chính thông tin',
        'story' => 'Câu chuyện / ký ức', 'media' => 'Tư liệu hình ảnh',
    ],
    'contribution_statuses' => [
        'pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối', 'needs_revision' => 'Cần bổ sung',
    ],
    'booking_statuses' => [
        'held' => 'Đang giữ chỗ', 'pending_payment' => 'Chờ thanh toán', 'paid' => 'Đã thanh toán', 'cancelled' => 'Đã hủy',
        'expired' => 'Hết hạn giữ chỗ', 'refunded' => 'Đã hoàn tiền',
    ],
    'payment_statuses' => [
        'initiated' => 'Khởi tạo', 'succeeded' => 'Thành công', 'failed' => 'Thất bại', 'cancelled' => 'Đã hủy', 'refunded' => 'Đã hoàn tiền',
    ],
    'difficulties' => ['de' => 'Dễ', 'vua' => 'Vừa', 'kho' => 'Khó'],
    'statuses' => ['draft' => 'Bản nháp', 'published' => 'Đã công bố', 'archived' => 'Lưu trữ'],
    'media_types' => ['image' => 'Hình ảnh', 'audio' => 'Âm thanh', 'video' => 'Video', 'model3d' => 'Mô hình 3D', 'document' => 'Tài liệu'],
    'roles' => ['admin' => 'Quản trị hệ thống', 'manager' => 'Quản lý nghiệp vụ', 'contributor' => 'Đơn vị cộng tác', 'member' => 'Thành viên'],
];
