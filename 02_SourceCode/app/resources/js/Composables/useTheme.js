import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const STORAGE_KEY = 'disanso-theme';
const current = ref(readInitial());

function readInitial() {
    if (typeof document === 'undefined') return 'light';
    return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
}

function apply(theme) {
    current.value = theme;
    document.documentElement.setAttribute('data-theme', theme);
    try {
        localStorage.setItem(STORAGE_KEY, theme);
    } catch {
        // trình duyệt chặn storage (chế độ riêng tư): vẫn đổi theme trong phiên
    }
}

/**
 * Quản lý theme light/dark. Khi đã đăng nhập, lưu lựa chọn vào users.theme
 * qua PATCH account.profile.update (không tải lại trang).
 */
export function useTheme() {
    const page = usePage();

    const syncFromUser = () => {
        const userTheme = page.props.auth?.user?.theme;
        if (userTheme === 'light' || userTheme === 'dark') {
            let stored = null;
            try {
                stored = localStorage.getItem(STORAGE_KEY);
            } catch {
                stored = null;
            }
            if (!stored) apply(userTheme);
        }
    };

    const setTheme = (theme) => {
        apply(theme);
        if (!page.props.auth?.user) return;
        const locale = page.props.locale || 'vi';
        // Chỉ gửi theme: backend cập nhật từng phần và quay lại trang cũ (không ghi đè sđt/ngôn ngữ)
        router.patch(
            `/${locale}/tai-khoan/ho-so`,
            { theme },
            { preserveScroll: true, preserveState: true, only: ['auth', 'flash'] },
        );
    };

    const toggle = () => setTheme(current.value === 'dark' ? 'light' : 'dark');

    return { theme: current, setTheme, toggle, syncFromUser };
}
