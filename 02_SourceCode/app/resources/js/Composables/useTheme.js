import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

// Khóa localStorage; app.blade.php đọc cùng khóa để đặt data-theme trước khi vẽ (tránh nháy màu)
export const THEME_STORAGE_KEY = 'dka-theme';
const THEMES = ['light', 'dark'];

function readStored() {
    try {
        const v = localStorage.getItem(THEME_STORAGE_KEY);
        return THEMES.includes(v) ? v : null;
    } catch {
        return null;
    }
}

function readInitial() {
    if (typeof document === 'undefined') return 'light';
    const attr = document.documentElement.getAttribute('data-theme');
    // Lựa chọn đã lưu thắng giá trị mặc định do máy chủ render
    return readStored() || (attr === 'dark' ? 'dark' : 'light');
}

const current = ref(readInitial());
if (typeof document !== 'undefined') {
    document.documentElement.setAttribute('data-theme', current.value);
}

function apply(theme) {
    current.value = theme;
    document.documentElement.setAttribute('data-theme', theme);
    try {
        localStorage.setItem(THEME_STORAGE_KEY, theme);
    } catch {
        // trình duyệt chặn storage (chế độ riêng tư): vẫn đổi theme trong phiên
    }
}

/**
 * Theme "Ban ngày" (light) / "Trực ban đêm" (dark) qua thuộc tính data-theme trên <html>.
 * Lưu localStorage; khi đã đăng nhập thì lưu thêm vào users.theme (PATCH account.theme, không tải lại trang).
 */
export function useTheme() {
    const page = usePage();

    /** Lần đầu trên thiết bị mới: lấy theme đã lưu trong tài khoản. */
    const syncFromUser = () => {
        const userTheme = page.props.auth?.user?.theme;
        if (THEMES.includes(userTheme) && !readStored()) apply(userTheme);
    };

    const setTheme = (theme) => {
        if (!THEMES.includes(theme)) return;
        apply(theme);
        if (!page.props.auth?.user) return;
        const locale = page.props.locale || 'vi';
        router.patch(
            `/${locale}/tai-khoan/giao-dien`,
            { theme },
            { preserveScroll: true, preserveState: true, only: ['auth', 'flash'] },
        );
    };

    const toggle = () => setTheme(current.value === 'dark' ? 'light' : 'dark');

    return { theme: current, setTheme, applyLocal: apply, toggle, syncFromUser };
}
