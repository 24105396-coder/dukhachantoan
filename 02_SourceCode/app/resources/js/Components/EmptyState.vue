<script setup>
import Figure from '@/Components/Figure.vue';

/**
 * Trạng thái trống: minh họa SVG tông mint + tiêu đề + câu hướng dẫn ngắn; slot mặc định cho nút hành động.
 * `variant="error"` dùng cho lỗi tải dữ liệu (role=alert). `image` (từ useImages) thay SVG bằng ảnh nhỏ 16:9
 * (thiếu ảnh → nền brand + icon), chỉ dùng ở trạng thái trống của trang ít dữ liệu.
 */
defineProps({
    image: { type: Object, default: undefined },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    compact: { type: Boolean, default: false },
    variant: { type: String, default: 'empty' }, // empty | error
});
</script>

<template>
    <div
        class="flex flex-col items-center justify-center rounded-[var(--radius-clay)] border border-dashed border-[var(--border-strong)] bg-surface text-center"
        :class="compact ? 'gap-2 p-5' : 'gap-3 p-8 md:p-10'"
        :role="variant === 'error' ? 'alert' : undefined"
    >
        <Figure v-if="image !== undefined" :image="image" class="w-48 max-w-full" ratio-class="aspect-[16/9]" credit="tooltip" fallback-icon="shield-check" />
        <svg v-else :width="compact ? 72 : 112" :height="compact ? 54 : 84" viewBox="0 0 160 120" fill="none" aria-hidden="true">
            <ellipse cx="80" cy="108" rx="56" ry="8" class="fill-[var(--bg-soft)]" />
            <rect x="34" y="22" width="92" height="74" rx="22" class="fill-[var(--bg-soft)] stroke-brand-300" stroke-width="3" />
            <circle cx="64" cy="56" r="5" class="fill-brand-500" />
            <circle cx="96" cy="56" r="5" class="fill-brand-500" />
            <path v-if="variant === 'error'" d="M64 80c8-8 24-8 32 0" class="stroke-brand-600" stroke-width="4" stroke-linecap="round" />
            <path v-else d="M64 74c8 8 24 8 32 0" class="stroke-brand-600" stroke-width="4" stroke-linecap="round" />
        </svg>
        <p class="font-display text-base font-bold">{{ title }}</p>
        <p v-if="description" class="max-w-md text-sm text-muted">{{ description }}</p>
        <div v-if="$slots.default" class="mt-1 flex flex-wrap justify-center gap-2"><slot /></div>
    </div>
</template>
