<script setup>
import Icon from '@/Components/Icon.vue';

defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: '—' },
    icon: { type: String, default: 'activity' },
    hint: { type: String, default: '' },
    tone: { type: String, default: 'primary' }, // primary | accent | success | info
});
const toneClass = {
    primary: 'bg-primary-soft text-primary',
    accent: 'bg-accent-soft text-accent-strong',
    success: 'bg-success-soft text-success',
    info: 'bg-info-soft text-info',
};
</script>

<template>
    <div class="card flex items-start gap-4 p-4">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" :class="toneClass[tone] || toneClass.primary">
            <Icon :name="icon" />
        </span>
        <div class="min-w-0">
            <p class="text-sm text-muted">{{ label }}</p>
            <p class="font-display text-2xl font-bold">{{ value }}</p>
            <p v-if="hint" class="text-xs text-muted">{{ hint }}</p>
        </div>
    </div>
</template>
