<script setup>
import { Link } from '@inertiajs/vue3';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

defineProps({
    tags: { type: Array, default: () => [] },
    limit: { type: Number, default: 0 },
});
const lr = useLocaleRoute();
</script>

<template>
    <ul v-if="tags?.length" class="flex flex-wrap gap-1.5">
        <li v-for="tag in (limit ? tags.slice(0, limit) : tags)" :key="tag.slug">
            <Link :href="tag.url || lr(`/the/${tag.slug}`)" class="badge hover:border-primary hover:text-primary">#{{ tag.name }}</Link>
        </li>
    </ul>
</template>
