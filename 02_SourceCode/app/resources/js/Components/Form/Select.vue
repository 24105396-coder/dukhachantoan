<script setup>
/** Hộp chọn clay-inset. options: [{ value, label }]; `placeholder` thành lựa chọn rỗng đầu tiên. */
defineOptions({ inheritAttrs: false });
defineProps({
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: '' },
    invalid: { type: Boolean, default: false },
});
const model = defineModel({ type: [String, Number, null], default: '' });
</script>

<template>
    <select v-model="model" v-bind="$attrs" class="clay-input" :aria-invalid="invalid ? 'true' : undefined">
        <option v-if="placeholder" value="">{{ placeholder }}</option>
        <option v-for="o in options" :key="o.value" :value="o.value">{{ o.label }}</option>
    </select>
</template>
