<script setup>
/** Khối nhóm trường trong form quản trị. */
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <section class="clay-card-sm p-4 md:p-6">
        <header class="mb-4">
            <h2 class="section-title text-lg">{{ title }}</h2>
            <p v-if="description" class="text-sm text-muted">{{ description }}</p>
        </header>
        <slot />
    </section>
</template>
