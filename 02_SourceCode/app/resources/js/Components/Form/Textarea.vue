<script setup>
/** Vùng nhập nhiều dòng clay-inset. */
defineOptions({ inheritAttrs: false });
defineProps({
    rows: { type: Number, default: 4 },
    invalid: { type: Boolean, default: false },
});
const model = defineModel({ type: [String, null], default: '' });
</script>

<template>
    <textarea v-model="model" v-bind="$attrs" :rows="rows" class="clay-input" :aria-invalid="invalid ? 'true' : undefined"></textarea>
</template>
