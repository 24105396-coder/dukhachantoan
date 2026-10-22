<?php

return [
    'site_types' => [
        'di_tich' => 'Historical & cultural relic', 'danh_thang' => 'Scenic landscape',
        'di_san_phi_vat_the' => 'Intangible heritage', 'bao_tang' => 'Museum', 'lang_nghe' => 'Traditional craft village',
    ],
    'levels' => [
        'unesco' => 'World Heritage (UNESCO)', 'quoc_gia_dac_biet' => 'Special national relic',
        'quoc_gia' => 'National relic', 'cap_tinh' => 'Provincial relic', 'chua_xep_hang' => 'Not yet ranked',
    ],
    'artifact_types' => [
        'tuong' => 'Statue', 'bia_ky' => 'Stele', 'do_gom' => 'Ceramics', 'do_dong' => 'Bronze', 'vu_khi' => 'Weapon',
        'trang_phuc' => 'Costume', 'kien_truc' => 'Architectural element', 'tai_lieu' => 'Document', 'bao_vat' => 'Treasure', 'khac' => 'Other',
    ],
    'figure_types' => [
        'vua_chua' => 'Monarch', 'tuong_linh' => 'General', 'nha_van_hoa' => 'Cultural figure', 'anh_hung' => 'National hero',
        'nhan_vat_ton_giao' => 'Religious figure', 'nghe_nhan' => 'Artisan', 'khac' => 'Other',
    ],
    'relation_types' => [
        'sang_lap' => 'Founder', 'duoc_tho' => 'Worshipped here', 'gan_lien' => 'Associated', 'trung_tu' => 'Restorer',
        'sinh_ra' => 'Birthplace', 'an_tang' => 'Burial place',
    ],
    'event_types' => [
        'xay_dung' => 'Construction', 'trung_tu' => 'Restoration', 'cong_nhan' => 'Recognition', 'su_kien' => 'Event',
        'sinh' => 'Birth', 'mat' => 'Death', 'phat_hien' => 'Discovery', 'khac' => 'Other',
    ],
    'licenses' => [
        'public_domain' => 'Public domain', 'cc_by' => 'CC BY', 'cc_by_sa' => 'CC BY-SA', 'cc_by_nc' => 'CC BY-NC',
        'all_rights_reserved' => 'All rights reserved', 'simulated' => 'Simulated illustration (team-made)',
    ],
    'tag_groups' => [
        'chu_de' => 'Theme', 'loai_hinh' => 'Type', 'ton_giao' => 'Religion & belief', 'phong_cach' => 'Style',
        'vat_lieu' => 'Material', 'thoi_ky' => 'Period', 'vung_mien' => 'Region',
    ],
    'contribution_types' => [
        'new_site' => 'Propose a new site', 'new_artifact' => 'Propose a new artifact', 'correction' => 'Correction',
        'story' => 'Story / memory', 'media' => 'Photo or media',
    ],
    'contribution_statuses' => [
        'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'needs_revision' => 'Needs revision',
    ],
    'booking_statuses' => [
        'held' => 'Held', 'pending_payment' => 'Awaiting payment', 'paid' => 'Paid', 'cancelled' => 'Cancelled',
        'expired' => 'Hold expired', 'refunded' => 'Refunded',
    ],
    'payment_statuses' => [
        'initiated' => 'Initiated', 'succeeded' => 'Succeeded', 'failed' => 'Failed', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded',
    ],
    'difficulties' => ['de' => 'Easy', 'vua' => 'Moderate', 'kho' => 'Hard'],
    'statuses' => ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'],
    'media_types' => ['image' => 'Image', 'audio' => 'Audio', 'video' => 'Video', 'model3d' => '3D model', 'document' => 'Document'],
    'roles' => ['admin' => 'System administrator', 'manager' => 'Content manager', 'contributor' => 'Partner', 'member' => 'Member'],
];
