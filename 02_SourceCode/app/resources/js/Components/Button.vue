<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

/**
 * Nút clay. `href` → Inertia Link (hoặc <a> khi `external`); ngược lại là <button>.
 * variant: primary (gradient brand) | secondary (mint) | danger (gradient mức 4) | ghost.
 * Nút chỉ có icon phải truyền `label` để có tên truy cập được.
 */
const props = defineProps({
    variant: { type: String, default: 'secondary' },
    size: { type: String, default: 'md' }, // sm | md | lg
    href: { type: String, default: null },
    external: { type: Boolean, default: false },
    method: { type: String, default: undefined },
    type: { type: String, default: 'button' },
    icon: { type: String, default: '' },
    iconRight: { type: String, default: '' },
    iconOnly: { type: Boolean, default: false },
    label: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
});

const VARIANTS = { primary: 'clay-btn-primary', secondary: '', danger: 'clay-btn-danger', ghost: 'clay-btn-ghost' };
const SIZES = { sm: 'clay-btn-sm', md: '', lg: 'clay-btn-lg' };
const iconSize = computed(() => (props.size === 'sm' ? 16 : props.size === 'lg' ? 22 : 18));
const classes = computed(() => [
    'clay-btn',
    VARIANTS[props.variant] ?? '',
    SIZES[props.size] ?? '',
    props.iconOnly ? 'clay-btn-icon' : '',
    props.block ? 'clay-btn-block' : '',
]);
const isDisabled = computed(() => props.disabled || props.loading);
</script>

<template>
    <a
        v-if="href && external"
        :href="href"
        :class="classes"
        :aria-label="iconOnly ? label : undefined"
        target="_blank"
        rel="noopener noreferrer"
    >
        <Icon v-if="icon" :name="icon" :size="iconSize" />
        <span v-if="!iconOnly"><slot>{{ label }}</slot></span>
        <Icon v-if="iconRight" :name="iconRight" :size="iconSize" />
    </a>
    <Link
        v-else-if="href"
        :href="href"
        :method="method"
        :as="method && method !== 'get' ? 'button' : 'a'"
        :class="classes"
        :aria-label="iconOnly ? label : undefined"
        :aria-disabled="isDisabled ? 'true' : undefined"
    >
        <Icon v-if="icon" :name="icon" :size="iconSize" />
        <span v-if="!iconOnly"><slot>{{ label }}</slot></span>
        <Icon v-if="iconRight" :name="iconRight" :size="iconSize" />
    </Link>
    <button
        v-else
        :type="type"
        :class="classes"
        :disabled="isDisabled"
        :aria-busy="loading ? 'true' : undefined"
        :aria-label="iconOnly ? label : undefined"
    >
        <Icon v-if="loading" name="refresh" :size="iconSize" class="animate-spin" />
        <Icon v-else-if="icon" :name="icon" :size="iconSize" />
        <span v-if="!iconOnly"><slot>{{ label }}</slot></span>
        <Icon v-if="iconRight" :name="iconRight" :size="iconSize" />
    </button>
</template>
