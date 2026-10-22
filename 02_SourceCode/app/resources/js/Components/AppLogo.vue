<script setup>
defineProps({
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <span class="inline-flex items-center gap-2">
        <svg width="34" height="34" viewBox="0 0 40 40" aria-hidden="true" focusable="false" class="shrink-0">
            <circle cx="20" cy="20" r="19" fill="var(--ds-primary)" />
            <path d="M8 29h24M11 26h18M13 26V17M18 26V17M22 26V17M27 26V17M10 16h20L20 9z" stroke="var(--ds-on-primary)" stroke-width="1.8" fill="none" stroke-linejoin="round" />
            <circle cx="20" cy="20" r="17" fill="none" stroke="var(--ds-accent)" stroke-width="1" stroke-dasharray="2 3" />
        </svg>
        <span v-if="!compact" class="font-display text-xl font-bold tracking-tight">
            DiSan<span class="text-primary">So</span>
        </span>
    </span>
</template>
