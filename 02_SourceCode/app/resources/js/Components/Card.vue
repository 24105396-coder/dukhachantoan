<script setup>
import { computed } from 'vue';

/**
 * Thẻ clay bo 28px. tone: surface | mint | peach | lemon | sky (pastel nhấn, tự đổi bản tối).
 * padding: none | sm | md | lg. `as` cho thẻ ngữ nghĩa (article, section…).
 */
const props = defineProps({
    as: { type: String, default: 'div' },
    tone: { type: String, default: 'surface' },
    padding: { type: String, default: 'md' },
    small: { type: Boolean, default: false },
});
const TONES = { surface: '', mint: 'bg-mint-100', peach: 'bg-peach-100', lemon: 'bg-lemon-100', sky: 'bg-sky-100' };
const PADS = { none: '', sm: 'p-4', md: 'p-5 md:p-6', lg: 'p-6 md:p-8' };
const classes = computed(() => [props.small ? 'clay-card-sm' : 'clay-card', TONES[props.tone] ?? '', PADS[props.padding] ?? PADS.md]);
</script>

<template>
    <component :is="as" :class="classes">
        <slot />
    </component>
</template>
