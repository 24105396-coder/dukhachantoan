import { ref, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';

export const DEFAULT_POLL_MS = 30000;

/**
 * Làm mới dữ liệu "gần thời gian thực" không cần WebSocket: gọi router.reload({ only }) theo chu kỳ.
 * Dừng khi tab ẩn; khi tab hiện lại mà đã quá hạn thì làm mới ngay. Không chồng hai lần tải.
 *
 * @param {number} intervalMs chu kỳ (mặc định 30 giây)
 * @param {string[]} only danh sách prop cần tải lại (rỗng = toàn trang)
 * @returns {{ refresh: Function, isRefreshing: import('vue').Ref<boolean>, lastRefreshedAt: import('vue').Ref<Date>, paused: import('vue').Ref<boolean>, pause: Function, resume: Function }}
 */
export function usePolling(intervalMs = DEFAULT_POLL_MS, only = []) {
    const isRefreshing = ref(false);
    const lastRefreshedAt = ref(new Date());
    const paused = ref(false);
    let timer = null;

    const refresh = () => {
        if (isRefreshing.value) return;
        isRefreshing.value = true;
        router.reload({
            only: only.length ? only : undefined,
            preserveScroll: true,
            onSuccess: () => {
                lastRefreshedAt.value = new Date();
            },
            onFinish: () => {
                isRefreshing.value = false;
            },
        });
    };

    const tick = () => {
        if (paused.value || document.hidden) return;
        refresh();
    };

    const start = () => {
        stop();
        timer = window.setInterval(tick, intervalMs);
    };

    function stop() {
        if (timer) window.clearInterval(timer);
        timer = null;
    }

    const onVisibility = () => {
        if (document.hidden) {
            stop();
            return;
        }
        if (!paused.value && Date.now() - lastRefreshedAt.value.getTime() >= intervalMs) refresh();
        if (!paused.value) start();
    };

    const pause = () => {
        paused.value = true;
        stop();
    };
    const resume = () => {
        paused.value = false;
        start();
    };

    onMounted(() => {
        document.addEventListener('visibilitychange', onVisibility);
        if (!document.hidden) start();
    });
    onBeforeUnmount(() => {
        document.removeEventListener('visibilitychange', onVisibility);
        stop();
    });

    return { refresh, isRefreshing, lastRefreshedAt, paused, pause, resume };
}
