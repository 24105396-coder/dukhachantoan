<script setup>
/**
 * Trạng thái trống: minh họa SVG tông mint + tiêu đề + câu hướng dẫn ngắn; slot mặc định cho nút hành động.
 * `variant="error"` dùng cho lỗi tải dữ liệu (role=alert).
 */
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    compact: { type: Boolean, default: false },
    variant: { type: String, default: 'empty' }, // empty | error
});
</script>

<template>
    <div
        class="flex flex-col items-center justify-center rounded-[20px] border border-dashed border-line bg-soft/60 text-center"
        :class="compact ? 'gap-2 p-5' : 'gap-3 p-8 md:p-10'"
        :role="variant === 'error' ? 'alert' : undefined"
    >
        <svg :width="compact ? 72 : 112" :height="compact ? 54 : 84" viewBox="0 0 160 120" fill="none" aria-hidden="true">
            <ellipse cx="80" cy="108" rx="56" ry="8" class="fill-mint-200" />
            <rect x="34" y="22" width="92" height="74" rx="22" class="fill-mint-100 stroke-brand-300" stroke-width="3" />
            <circle cx="64" cy="56" r="5" class="fill-brand-500" />
            <circle cx="96" cy="56" r="5" class="fill-brand-500" />
            <path v-if="variant === 'error'" d="M64 80c8-8 24-8 32 0" class="stroke-brand-600" stroke-width="4" stroke-linecap="round" />
            <path v-else d="M64 74c8 8 24 8 32 0" class="stroke-brand-600" stroke-width="4" stroke-linecap="round" />
            <circle cx="128" cy="24" r="10" class="fill-peach-200" />
            <circle cx="30" cy="36" r="6" class="fill-lemon-200" />
        </svg>
        <p class="font-display text-lg font-bold">{{ title }}</p>
        <p v-if="description" class="max-w-md text-sm text-muted">{{ description }}</p>
        <div v-if="$slots.default" class="mt-1 flex flex-wrap justify-center gap-2"><slot /></div>
    </div>
</template>
