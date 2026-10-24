import axios from 'axios';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { i18nPlugin } from '@/Composables/useT';

// axios tự gửi X-XSRF-TOKEN từ cookie; header này giúp Laravel nhận diện AJAX
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const APP_NAME = 'DuKhachAnToan';
const FONT_FALLBACK_DELAY_MS = 200;
const PROGRESS_COLOR = '#10A688'; // brand-500 (thanh tiến trình Inertia không đọc được biến CSS)

// Nạp trang theo nhu cầu: mỗi trang là một chunk riêng, bundle đầu vào chỉ còn khung ứng dụng
const pages = import.meta.glob('./Pages/**/*.vue');

// Trang dùng khung dashboard (sidebar): khu quản trị và khu cá nhân của người đã đăng nhập
const DASHBOARD_PREFIXES = ['Admin/', 'Account/', 'Notifications/', 'Incidents/', 'Requests/', 'Trips/'];
// Trang công khai theo token trong thư mục Trips (người thân xem/báo an toàn, không cần đăng nhập)
const PUBLIC_EXCEPTIONS = ['Trips/Shared', 'Trips/SharedCheckin'];

/** Layout mặc định theo tiền tố tên trang; trang có thể tự khai báo `layout` để ghi đè. */
export function layoutFor(name) {
    if (name.startsWith('Auth/')) return import('@/Layouts/AuthLayout.vue');
    if (!PUBLIC_EXCEPTIONS.includes(name) && DASHBOARD_PREFIXES.some((p) => name.startsWith(p))) {
        return import('@/Layouts/DashboardLayout.vue');
    }
    return import('@/Layouts/PublicLayout.vue');
}

createInertiaApp({
    title: (title) => (title ? `${title} – ${APP_NAME}` : APP_NAME),
    resolve: async (name) => {
        const loader = pages[`./Pages/${name}.vue`];
        if (!loader) {
            throw new Error(`Inertia page not found: ${name}`);
        }
        const [page, layout] = await Promise.all([loader(), layoutFor(name)]);
        if (page.default.layout === undefined) {
            page.default.layout = layout.default;
        }
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18nPlugin)
            .mount(el);
        // Gỡ chân trang tĩnh phía máy chủ (partials/academic-footer) để không hiển thị trùng với AppFooter
        document.getElementById('static-footer')?.remove();
        // Nạp font tự host khi rảnh, sau khi nội dung đầu tiên đã vẽ (hàm định nghĩa trong app.blade.php)
        const whenIdle = window.requestIdleCallback || ((cb) => window.setTimeout(cb, FONT_FALLBACK_DELAY_MS));
        whenIdle(() => window.dkaLoadFonts?.());
    },
    progress: {
        color: PROGRESS_COLOR,
        showSpinner: false,
    },
});
