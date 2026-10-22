import { usePage } from '@inertiajs/vue3';

/**
 * Trả hàm dựng URL có tiền tố locale hiện tại: lr('/di-san') → '/vi/di-san'.
 * Đường dẫn rỗng hoặc '/' trả về trang chủ của locale.
 */
export function useLocaleRoute() {
    const page = usePage();
    return (path = '') => {
        const locale = page.props.locale || 'vi';
        if (!path || path === '/') return `/${locale}`;
        return `/${locale}${path.startsWith('/') ? path : `/${path}`}`;
    };
}
