<script setup>
import { computed } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import ArtifactCard from '@/Components/ArtifactCard.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useT } from '@/Composables/useT';
import PageMeta from '@/Components/Public/PageMeta.vue';

const props = defineProps({
    artifacts: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    types: { type: Array, default: () => [] },
    sites: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
});
const { t, te } = useT();

const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword'), placeholder: t('artifacts.search_placeholder') },
    { name: 'type', type: 'select', label: t('common.type'), options: props.types.map((x) => ({ value: x.code, label: x.name || te('artifact_type', x.code) })) },
    { name: 'site', type: 'select', label: t('artifacts.site'), options: props.sites.map((s) => ({ value: s.slug, label: s.title })) },
    ...(props.periods.length
        ? [{ name: 'period', type: 'select', label: t('common.period'), options: props.periods.map((p) => ({ value: p.code, label: p.name })) }]
        : []),
    { name: 'treasure', type: 'checkbox', label: t('artifacts.only_treasures') },
]);
const list = computed(() => props.artifacts?.data || []);
const total = computed(() => props.artifacts?.total ?? list.value.length);
</script>

<template>
    <PageMeta :title="t('artifacts.title')" fallback-key="artifacts.description" />
    <div class="container-page py-8">
        <PageHeader :title="t('artifacts.title')" :eyebrow="t('artifacts.eyebrow')" :description="t('artifacts.description')" />
        <FilterBar :filters="filters" :fields="fields" />
        <p class="mt-5 text-sm text-muted" aria-live="polite">{{ t('artifacts.result_count', { n: total }) }}</p>
        <EmptyState v-if="!list.length" class="mt-4" icon="vase" :title="t('artifacts.empty')" :description="t('sites.empty_hint')" />
        <div v-else class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
            <ArtifactCard v-for="(a, i) in list" :key="a.id" :artifact="a" :heading-level="2" :eager="i < 2" />
        </div>
        <Pagination :paginator="artifacts" />
    </div>
</template>
