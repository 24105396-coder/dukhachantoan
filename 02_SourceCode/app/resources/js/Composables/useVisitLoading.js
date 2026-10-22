import { ref, onBeforeUnmount } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

/**
 * Cờ `loading` bật khi Inertia đang tải lại CHÍNH trang hiện tại (đổi bộ lọc, chuyển trang, reload),
 * để hiển thị skeleton thay vì danh sách cũ. Điều hướng sang trang khác không bật cờ.
 */
export function useVisitLoading() {
    const page = usePage();
    const loading = ref(false);
    const currentPath = () => (page.url || '').split('?')[0];

    const offStart = router.on('start', (e) => {
        const visit = e.detail.visit;
        if (visit.only?.length || visit.method !== 'get') return; // polling/đột biến: không thay danh sách bằng skeleton
        if (visit.url?.pathname === currentPath()) loading.value = true;
    });
    const offFinish = router.on('finish', () => {
        loading.value = false;
    });
    onBeforeUnmount(() => {
        offStart();
        offFinish();
    });
    return { loading };
}
