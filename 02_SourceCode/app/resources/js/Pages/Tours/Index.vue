<script setup>
import { computed } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import RouteCard from '@/Components/RouteCard.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useT } from '@/Composables/useT';
import { enumOptions } from '@/Utils/options';
import PageMeta from '@/Components/Public/PageMeta.vue';

const props = defineProps({
    routes: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    provinces: { type: Array, default: () => [] },
    difficulties: { type: Array, default: () => [] },
});
const { t, te } = useT();

const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword'), placeholder: t('tours.search_placeholder') },
    {
        name: 'province',
        type: 'select',
        label: t('common.province'),
        options: props.provinces.map((p) => ({ value: p.code, label: p.name })),
    },
    {
        name: 'difficulty',
        type: 'select',
        label: t('tours.difficulty'),
        options: enumOptions(props.difficulties.length ? props.difficulties : ['de', 'vua', 'kho'], te, 'difficulty'),
    },
]);
const items = computed(() => props.routes?.data || []);
</script>

<template>
    <PageMeta :title="t('tours.title')" fallback-key="tours.intro" />
    <div class="container-page py-8">
        <PageHeader :eyebrow="t('nav.tours')" :title="t('tours.title')" :description="t('tours.intro')" />
        <FilterBar :filters="filters" :fields="fields" />
        <div class="mt-6">
            <EmptyState v-if="!items.length" icon="route" :title="t('tours.empty')" :description="t('tours.empty_hint')" />
            <ul v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="(r, i) in items" :key="r.id"><RouteCard :route="r" :heading-level="2" :eager="i === 0" /></li>
            </ul>
        </div>
        <Pagination :paginator="routes" />
    </div>
</template>
