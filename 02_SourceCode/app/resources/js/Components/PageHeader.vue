<script setup>
import Breadcrumbs from '@/Components/Breadcrumbs.vue';

/** Tiêu đề trang thống nhất: breadcrumbs + eyebrow + h1 + mô tả + slot `actions`. */
defineProps({
    title: { type: String, required: true },
    eyebrow: { type: String, default: '' },
    description: { type: String, default: '' },
    breadcrumbs: { type: Array, default: () => [] },
});
</script>

<template>
    <header class="mb-6 space-y-3">
        <Breadcrumbs :items="breadcrumbs" />
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div class="min-w-0">
                <p v-if="eyebrow" class="eyebrow mb-1">{{ eyebrow }}</p>
                <h1>{{ title }}</h1>
                <p v-if="description" class="mt-2 max-w-2xl text-muted">{{ description }}</p>
            </div>
            <div v-if="$slots.actions" class="flex flex-wrap gap-2"><slot name="actions" /></div>
        </div>
    </header>
</template>
