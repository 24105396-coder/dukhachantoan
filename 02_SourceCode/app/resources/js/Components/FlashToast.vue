<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';

/**
 * Thông báo nổi từ flash.success / flash.error của máy chủ; đọc qua aria-live. Tự ẩn sau 5 giây; thông điệp dài
 * hoặc có liên kết (flash.success_link, vd "Xem trạng thái" yêu cầu vừa gửi) giữ 12 giây để kịp đọc và bấm.
 */
const page = usePage();
const { t } = useT();
const toasts = ref([]);
let seq = 0;
const timers = new Map();
const AUTO_HIDE_MS = 5000;
const LONG_HIDE_MS = 12000;
const LONG_MESSAGE_CHARS = 80;
/** Chỉ nhận liên kết nội bộ cùng gốc (bắt đầu bằng một dấu /). */
const isInternal = (url) => typeof url === 'string' && /^\/(?!\/)/.test(url);

function dismiss(id) {
    toasts.value = toasts.value.filter((x) => x.id !== id);
    clearTimeout(timers.get(id));
    timers.delete(id);
}

/**
 * Thêm một toast và hẹn giờ ẩn.
 * @param {'success'|'error'} type loại thông báo
 * @param {string} message nội dung đã dịch từ máy chủ
 * @param {string|null} link URL nội bộ cho nút "Xem trạng thái" (bỏ qua nếu không hợp lệ)
 * @returns {void}
 */
function push(type, message, link = null) {
    if (!message) return;
    const id = ++seq;
    const href = isInternal(link) ? link : null;
    toasts.value = [...toasts.value, { id, type, message, href }];
    const isLong = !!href || String(message).length > LONG_MESSAGE_CHARS;
    timers.set(id, setTimeout(() => dismiss(id), isLong ? LONG_HIDE_MS : AUTO_HIDE_MS));
}

watch(
    () => page.props.flash,
    (flash) => {
        push('success', flash?.success, flash?.success_link);
        push('error', flash?.error);
    },
    { immediate: true, deep: true },
);

onBeforeUnmount(() => timers.forEach((tm) => clearTimeout(tm)));
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 top-24 z-[1100] flex flex-col items-center gap-2 px-4" aria-live="polite" role="status">
        <TransitionGroup
            enter-from-class="opacity-0 -translate-y-2"
            enter-active-class="transition duration-200"
            leave-to-class="opacity-0"
            leave-active-class="transition duration-150"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="clay-card-sm pointer-events-auto flex w-full max-w-md items-start gap-3 border-2 p-3 pr-2"
                :class="toast.type === 'error' ? 'border-level-4' : 'border-brand-400'"
            >
                <span class="icon-tile h-9 w-9" :class="toast.type === 'error' ? 'text-danger' : 'text-success'">
                    <Icon :name="toast.type === 'error' ? 'circle-alert' : 'circle-check'" :size="18" />
                </span>
                <div class="min-w-0 flex-1 pt-1.5">
                    <p class="text-sm font-medium">{{ toast.message }}</p>
                    <Link v-if="toast.href" :href="toast.href" class="link mt-1 inline-flex items-center gap-1 text-sm" @click="dismiss(toast.id)">
                        {{ t('ui.requests.view_status') }}<Icon name="arrow-right" :size="14" />
                    </Link>
                </div>
                <button type="button" class="clay-btn clay-btn-ghost clay-btn-sm clay-btn-icon" :aria-label="t('common.close')" @click="dismiss(toast.id)">
                    <Icon name="close" :size="16" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
