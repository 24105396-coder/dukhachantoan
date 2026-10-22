<script setup>
// Tiêu đề trang thống nhất: eyebrow + h1 + mô tả + vùng thao tác
defineProps({
    title: { type: String, required: true },
    eyebrow: { type: String, default: '' },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div class="min-w-0">
            <p v-if="eyebrow" class="eyebrow mb-1">{{ eyebrow }}</p>
            <h1 class="text-3xl font-bold md:text-4xl">{{ title }}</h1>
            <p v-if="description" class="mt-2 max-w-2xl text-muted">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap gap-2"><slot name="actions" /></div>
    </div>
</template>
