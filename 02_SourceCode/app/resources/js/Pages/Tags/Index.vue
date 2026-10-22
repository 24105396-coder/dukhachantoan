<script setup>
import { Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useT } from '@/Composables/useT';
import PageMeta from '@/Components/Public/PageMeta.vue';

defineProps({
    groups: { type: Array, default: () => [] },
});
const { t, te } = useT();

// Cỡ chữ theo mức độ sử dụng để tạo "mây thẻ"
const sizeClass = (n) => (n >= 20 ? 'text-lg' : n >= 8 ? 'text-base' : 'text-sm');
</script>

<template>
    <PageMeta :title="t('tags.title')" fallback-key="tags.description" />
    <div class="container-page py-8">
        <PageHeader :title="t('tags.title')" :eyebrow="t('tags.eyebrow')" :description="t('tags.description')" />
        <EmptyState v-if="!groups.length" icon="tag" :title="t('tags.empty')" />
        <div v-else class="grid gap-5 md:grid-cols-2">
            <section v-for="g in groups" :key="g.group" class="card p-5">
                <h2 class="mb-4 text-xl font-bold">{{ g.label || te('tag_group', g.group) }}</h2>
                <ul class="flex flex-wrap gap-2">
                    <li v-for="tag in g.tags || []" :key="tag.slug">
                        <Link :href="tag.url" class="inline-flex items-center gap-1.5 rounded-full border border-border px-3 py-1 hover:border-primary hover:text-primary" :class="sizeClass(tag.usage_count || 0)" :title="tag.description || undefined">
                            #{{ tag.name }}
                            <span class="text-xs text-muted">{{ tag.usage_count ?? 0 }}</span>
                        </Link>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
