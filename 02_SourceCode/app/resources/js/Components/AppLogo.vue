<script setup>
/** Logo chữ "DuKhachAnToan." – dấu chấm màu brand; `compact` chỉ hiện biểu tượng (thanh icon 80px). */
defineProps({
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <span class="inline-flex items-center gap-2 font-display font-extrabold leading-none text-fg">
        <span class="icon-tile h-10 w-10 bg-linear-to-br from-brand-300 to-brand-500 text-on-brand" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 21s7-3.5 7-9.5V5.5L12 3 5 5.5v6C5 17.5 12 21 12 21z" />
                <path d="M9 12l2 2 4-4" />
            </svg>
        </span>
        <span v-if="!compact" class="text-lg tracking-tight sm:text-xl">DuKhachAnToan<span class="text-brand-500">.</span></span>
    </span>
</template>
