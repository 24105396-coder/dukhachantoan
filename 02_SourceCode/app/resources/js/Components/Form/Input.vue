<script setup>
/** Ô nhập clay-inset; mọi thuộc tính HTML khác (type, autocomplete, pattern…) truyền thẳng xuống <input>. */
defineOptions({ inheritAttrs: false });
defineProps({
    invalid: { type: Boolean, default: false },
});
const model = defineModel({ type: [String, Number, null], default: '' });
</script>

<template>
    <input v-model="model" v-bind="$attrs" class="clay-input" :aria-invalid="invalid ? 'true' : undefined" />
</template>
