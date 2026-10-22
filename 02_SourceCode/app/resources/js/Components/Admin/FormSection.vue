<script setup>
/** Khối nhóm trường trong form quản trị. */
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <section class="card p-4 md:p-5">
        <header class="mb-4">
            <h2 class="font-body text-lg font-bold">{{ title }}</h2>
            <p v-if="description" class="text-sm text-muted">{{ description }}</p>
        </header>
        <slot />
    </section>
</template>
