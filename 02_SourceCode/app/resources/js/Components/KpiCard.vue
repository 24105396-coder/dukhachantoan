<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

/**
 * Thẻ chỉ số: nhãn + số lớn (Plus Jakarta Sans 800, 1.75rem) + icon trong ô vuông.
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
// Giữ prop tone để không đổi hợp đồng; giao diện "Clean Teal" bỏ thẻ pastel nên mọi tone đều là thẻ trắng
const TONES = { surface: '', mint: '', peach: '', lemon: '', sky: '' };
const display = computed(() => (props.value === null || props.value === undefined || props.value === '' ? '—' : props.value));
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href || undefined"
        class="clay-card-sm flex items-start gap-4 p-4 transition-transform md:p-5"
        :class="[TONES[tone] ?? '', href ? 'hover:border-[var(--border-strong)]' : '']"
    >
        <span class="icon-tile"><Icon :name="icon" :size="22" /></span>
        <div class="min-w-0">
            <p class="text-sm font-semibold text-muted">{{ label }}</p>
            <p v-if="loading" class="skeleton mt-1 h-8 w-16" aria-hidden="true"></p>
            <p v-else class="font-display text-[1.75rem] font-extrabold leading-tight tracking-tight text-fg">{{ display }}</p>
            <p v-if="hint" class="text-xs text-muted">{{ hint }}</p>
        </div>
    </component>
</template>
