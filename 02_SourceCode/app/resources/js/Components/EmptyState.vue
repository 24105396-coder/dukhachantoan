<script setup>
import Icon from '@/Components/Icon.vue';

defineProps({
    icon: { type: String, default: 'inbox' },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border text-center" :class="compact ? 'p-6' : 'p-10'">
        <span class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-accent-soft text-accent-strong">
            <Icon :name="icon" />
        </span>
        <p class="font-semibold">{{ title }}</p>
        <p v-if="description" class="mt-1 max-w-md text-sm text-muted">{{ description }}</p>
        <div v-if="$slots.default" class="mt-4"><slot /></div>
    </div>
</template>
