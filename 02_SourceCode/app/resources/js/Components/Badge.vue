<script setup>
import { computed } from 'vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { levelMeta } from '@/Utils/levels';

/**
 * Nhãn pill. Với `level` 0–4: nền màu mức cố định + icon + nhãn chữ (không chỉ dựa màu – WCAG 1.4.1),
 * chữ đạt AA (trắng trên mức 1, 4; ink-950 trên mức 0, 2, 3). Không có level: dùng `tone` trung tính.
 * `soft` = nền nhạt của mức (dùng trong thẻ đã có viền màu).
 */
const props = defineProps({
    level: { type: [Number, String], default: null },
    label: { type: String, default: '' },
    tone: { type: String, default: 'neutral' }, // neutral | brand | danger | outline
    icon: { type: String, default: '' },
    size: { type: String, default: 'md' }, // sm | md | lg
    soft: { type: Boolean, default: false },
    showLevelNumber: { type: Boolean, default: false },
});
const { t } = useT();
const meta = computed(() => levelMeta(props.level));

const LEVEL_SOLID = {
    0: 'bg-level-0 text-level-0-fg', 1: 'bg-level-1 text-level-1-fg', 2: 'bg-level-2 text-level-2-fg',
    3: 'bg-level-3 text-level-3-fg', 4: 'bg-level-4 text-level-4-fg',
};
const LEVEL_SOFT = {
    0: 'bg-level-0-soft text-fg border-level-0', 1: 'bg-level-1-soft text-fg border-level-1', 2: 'bg-level-2-soft text-fg border-level-2',
    3: 'bg-level-3-soft text-fg border-level-3', 4: 'bg-level-4-soft text-fg border-level-4',
};
const TONES = {
    neutral: 'bg-soft text-fg border-line',
    brand: 'bg-brand-100 text-ink-950 border-transparent dark:bg-brand-700 dark:text-white',
    danger: 'bg-level-4-soft text-fg border-level-4',
    outline: 'bg-transparent text-fg border-line',
};
const SIZES = { sm: 'px-2 py-0.5 text-xs', md: 'px-3 py-1 text-[0.8125rem]', lg: 'px-4 py-1.5 text-sm' };

const classes = computed(() => {
    const color = meta.value
        ? (props.soft ? LEVEL_SOFT : LEVEL_SOLID)[meta.value.level]
        : TONES[props.tone] ?? TONES.neutral;
    return ['inline-flex items-center gap-1.5 rounded-full border font-bold leading-tight', SIZES[props.size] ?? SIZES.md, color];
});
const text = computed(() => {
    if (props.label) return props.label;
    if (!meta.value) return '';
    const name = t(meta.value.key);
    return props.showLevelNumber ? t('level.with_number', { n: meta.value.level, label: name }) : name;
});
const iconName = computed(() => props.icon || meta.value?.icon || '');
const iconSize = computed(() => (props.size === 'sm' ? 12 : props.size === 'lg' ? 18 : 14));
</script>

<template>
    <span :class="classes" :data-level="meta?.level">
        <Icon v-if="iconName" :name="iconName" :size="iconSize" :stroke-width="2.25" />
        <span><slot>{{ text }}</slot></span>
    </span>
</template>
