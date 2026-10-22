import { ref } from 'vue';

const RESET_MS = 2500;

/** Sao chép văn bản vào bộ nhớ tạm; `copied` bật trong 2,5 giây để hiển thị phản hồi (aria-live ở trang). */
export function useClipboard() {
    const copied = ref(false);
    const failed = ref(false);
    let timer = null;

    async function copy(text) {
        failed.value = false;
        try {
            await navigator.clipboard.writeText(String(text ?? ''));
            copied.value = true;
        } catch {
            failed.value = true;
            copied.value = false;
        }
        window.clearTimeout(timer);
        timer = window.setTimeout(() => {
            copied.value = false;
            failed.value = false;
        }, RESET_MS);
        return copied.value;
    }

    return { copy, copied, failed };
}
