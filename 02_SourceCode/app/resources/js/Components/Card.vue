<script setup>
import { computed } from 'vue';

/**
 * Thẻ trắng viền mảnh bo 14px. tone: surface | mint | peach | lemon | sky (giữ để tương thích, hiển thị như surface).
 * padding: none | sm | md | lg. `as` cho thẻ ngữ nghĩa (article, section…).
 */
const props = defineProps({
    as: { type: String, default: 'div' },
    tone: { type: String, default: 'surface' },
    padding: { type: String, default: 'md' },
    small: { type: Boolean, default: false },
});
// Giữ prop tone để không đổi hợp đồng; giao diện "Clean Teal" bỏ thẻ pastel nên mọi tone đều là thẻ trắng
const TONES = { surface: '', mint: '', peach: '', lemon: '', sky: '' };
const PADS = { none: '', sm: 'p-4', md: 'p-5 md:p-6', lg: 'p-6 md:p-8' };
const classes = computed(() => [props.small ? 'clay-card-sm' : 'clay-card', TONES[props.tone] ?? '', PADS[props.padding] ?? PADS.md]);
</script>

<template>
    <component :is="as" :class="classes">
        <slot />
    </component>
</template>
