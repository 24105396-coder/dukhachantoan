<script setup>
import { computed } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import FigureCard from '@/Components/FigureCard.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useT } from '@/Composables/useT';
import PageMeta from '@/Components/Public/PageMeta.vue';

const props = defineProps({
    figures: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    types: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
});
const { t, te } = useT();

const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword'), placeholder: t('figures.search_placeholder') },
    { name: 'type', type: 'select', label: t('common.type'), options: props.types.map((x) => ({ value: x.code, label: x.name || te('figure_type', x.code) })) },
    { name: 'period', type: 'select', label: t('common.period'), options: props.periods.map((p) => ({ value: p.code, label: p.name })) },
]);
const list = computed(() => props.figures?.data || []);
const total = computed(() => props.figures?.total ?? list.value.length);
</script>

<template>
    <PageMeta :title="t('figures.title')" fallback-key="figures.description" />
    <div class="container-page py-8">
        <PageHeader :title="t('figures.title')" :eyebrow="t('figures.eyebrow')" :description="t('figures.description')" />
        <FilterBar :filters="filters" :fields="fields" />
        <p class="mt-5 text-sm text-muted" aria-live="polite">{{ t('figures.result_count', { n: total }) }}</p>
        <EmptyState v-if="!list.length" class="mt-4" icon="users" :title="t('figures.empty')" :description="t('sites.empty_hint')" />
        <div v-else class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
            <FigureCard v-for="(f, i) in list" :key="f.id" :figure="f" :heading-level="2" :eager="i < 2" />
        </div>
        <Pagination :paginator="figures" />
    </div>
</template>
