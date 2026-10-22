<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

/**
 * Thẻ chỉ số: nhãn + số lớn (Baloo 2, 2rem) + icon trong ô vuông clay.
 * `value` null/undefined hiển thị "—"; `loading` hiển thị skeleton. tone: surface | mint | peach | lemon | sky.
 */
const props = defineProps({
    label: { type: String, required: true },
    value: { type: [Number, String], default: null },
    icon: { type: String, default: 'activity' },
    tone: { type: String, default: 'surface' },
    hint: { type: String, default: '' },
    href: { type: String, default: null },
    loading: { type: Boolean, default: false },
});
const TONES = { surface: '', mint: 'bg-mint-100', peach: 'bg-peach-100', lemon: 'bg-lemon-100', sky: 'bg-sky-100' };
const display = computed(() => (props.value === null || props.value === undefined || props.value === '' ? '—' : props.value));
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href || undefined"
        class="clay-card-sm flex items-start gap-4 p-4 transition-transform md:p-5"
        :class="[TONES[tone] ?? '', href ? 'hover:-translate-y-0.5' : '']"
    >
        <span class="icon-tile"><Icon :name="icon" :size="22" /></span>
        <div class="min-w-0">
            <p class="text-sm font-semibold text-muted">{{ label }}</p>
            <p v-if="loading" class="skeleton mt-1 h-8 w-16" aria-hidden="true"></p>
            <p v-else class="font-display text-[2rem] font-bold leading-tight text-fg">{{ display }}</p>
            <p v-if="hint" class="text-xs text-muted">{{ hint }}</p>
        </div>
    </component>
</template>
