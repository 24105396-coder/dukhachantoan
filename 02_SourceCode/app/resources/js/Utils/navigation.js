// Kiến trúc điều hướng: menu công khai (header pill) và menu dashboard theo vai trò (sidebar)

/** Menu công khai (đường dẫn chưa có tiền tố locale). `short` = nhãn gọn cho header pill desktop; menu di động và chân trang dùng nav.<key>. */
export const PUBLIC_NAV = [
    { key: 'alerts', short: 'nav.alerts', path: '/canh-bao', icon: 'bell-ring' },
    { key: 'map', short: 'nav.short.map', path: '/ban-do', icon: 'map' },
    { key: 'weather', short: 'nav.weather', path: '/thoi-tiet', icon: 'cloud-sun' },
    { key: 'contacts', short: 'nav.contacts', path: '/danh-ba-khan-cap', icon: 'phone' },
    { key: 'guides', short: 'nav.short.guides', path: '/huong-dan-ung-pho', icon: 'life-buoy' },
    { key: 'trips', short: 'nav.short.trips', path: '/hanh-trinh', icon: 'route' },
];

const TRAVELER = ['traveler', 'partner'];
const STAFF = ['agency', 'admin'];
const ADMIN = ['admin'];

/**
 * Menu dashboard. `primary: true` = xuất hiện trên thanh dưới điện thoại (tối đa 4 mục + nút Menu).
 * `label` = khóa lang của nhãn. `exact` = chỉ đánh dấu đang chọn khi trùng đường dẫn tuyệt đối.
 */
export const DASHBOARD_NAV = [
    { key: 'dashboard', label: 'admin.nav.dashboard', path: '/quan-tri', icon: 'dashboard', roles: STAFF, exact: true, primary: true, group: 'operate' },
    { key: 'admin_alerts', label: 'admin.nav.alerts', path: '/quan-tri/canh-bao', icon: 'bell-ring', roles: STAFF, primary: true, group: 'operate' },
    { key: 'admin_trips', label: 'admin.nav.trips', path: '/quan-tri/hanh-trinh', icon: 'route', roles: STAFF, group: 'operate' },
    { key: 'admin_checkins', label: 'admin.nav.checkins', path: '/quan-tri/an-toan', icon: 'hand', roles: STAFF, primary: true, group: 'operate' },
    { key: 'admin_notifications', label: 'admin.nav.notifications', path: '/quan-tri/thong-bao', icon: 'send', roles: STAFF, group: 'operate' },
    { key: 'admin_incidents', label: 'admin.nav.incidents', path: '/quan-tri/su-co', icon: 'shield-alert', roles: STAFF, group: 'operate' },
    { key: 'admin_contacts', label: 'admin.nav.contacts', path: '/quan-tri/danh-ba', icon: 'phone', roles: STAFF, group: 'content' },
    { key: 'admin_guides', label: 'admin.nav.guides', path: '/quan-tri/huong-dan', icon: 'life-buoy', roles: STAFF, group: 'content' },
    { key: 'admin_zones', label: 'admin.nav.zones', path: '/quan-tri/vung', icon: 'layers', roles: STAFF, group: 'content' },
    { key: 'admin_sources', label: 'admin.nav.sources', path: '/quan-tri/nguon', icon: 'radio', roles: STAFF, group: 'content' },
    { key: 'admin_scenarios', label: 'admin.nav.scenarios', path: '/quan-tri/mo-phong', icon: 'flask', roles: ADMIN, group: 'system' },
    { key: 'admin_users', label: 'admin.nav.users', path: '/quan-tri/nguoi-dung', icon: 'users', roles: ADMIN, group: 'system' },
    { key: 'admin_audit', label: 'admin.nav.audit', path: '/quan-tri/nhat-ky', icon: 'scroll', roles: ADMIN, group: 'system' },
    { key: 'admin_backup', label: 'admin.nav.backup', path: '/quan-tri/sao-luu', icon: 'hard-drive', roles: ADMIN, group: 'system' },

    { key: 'my_trips', label: 'nav.trips', path: '/hanh-trinh', icon: 'route', roles: TRAVELER, primary: true, group: 'personal' },
    { key: 'notifications', label: 'nav.notifications', path: '/thong-bao', icon: 'bell', roles: [...TRAVELER, ...STAFF], primary: true, group: 'personal', badge: 'unread' },
    { key: 'report_incident', label: 'nav.report_incident', path: '/bao-su-co', icon: 'megaphone', roles: TRAVELER, primary: true, group: 'personal' },
    { key: 'alerts', label: 'nav.alerts', path: '/canh-bao', icon: 'triangle-alert', roles: TRAVELER, group: 'personal' },
    { key: 'account', label: 'nav.account', path: '/tai-khoan', icon: 'user-cog', roles: [...TRAVELER, ...STAFF], group: 'personal' },
];

export const NAV_GROUPS = ['operate', 'content', 'system', 'personal'];

/** Mục menu dashboard mà vai trò được thấy (máy chủ vẫn kiểm quyền). */
export function dashboardNavFor(role) {
    return DASHBOARD_NAV.filter((item) => item.roles.includes(role));
}
