<script setup>
import { computed } from 'vue';
import PageMeta from '@/Components/Public/PageMeta.vue';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import AlertCard from '@/Components/AlertCard.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import SkeletonList from '@/Components/SkeletonList.vue';
import Button from '@/Components/Button.vue';
import DataLegend from '@/Components/DataLegend.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useVisitLoading } from '@/Composables/useVisitLoading';
import { enumOptions, rowsOf, ALERT_LEVELS } from '@/Utils/options';

/** Danh sách cảnh báo công khai: lọc theo từ khóa, mức, loại hiểm họa, tỉnh, trạng thái, mô phỏng; phân trang. */
const props = defineProps({
    alerts: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    hazards: { type: Array, default: () => [] },
    provinces: { type: Array, default: () => [] },
});
const { t, te } = useT();
const lr = useLocaleRoute();
const { loading } = useVisitLoading();

const rows = computed(() => rowsOf(props.alerts));
const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword'), placeholder: t('alerts.search_placeholder') },
    { name: 'severity', type: 'select', label: t('alerts.severity'), options: ALERT_LEVELS.map((l) => ({ value: l, label: t('level.with_number', { n: l, label: t(`enums.levels.${l}`) }) })) },
    { name: 'hazard', type: 'select', label: t('alerts.hazard'), options: enumOptions(props.hazards, te, 'hazard') },
    { name: 'province', type: 'select', label: t('common.province'), options: enumOptions(props.provinces) },
    { name: 'status', type: 'select', label: t('common.status'), placeholder: t('alerts.status_any'), options: [{ value: 'active', label: te('status', 'active') }, { value: 'expired', label: te('status', 'expired') }] },
    { name: 'simulated', type: 'select', label: t('alerts.simulated_filter'), options: [{ value: '0', label: t('alerts.real_only') }, { value: '1', label: t('alerts.simulated_only') }] },
]);
const total = computed(() => props.alerts?.meta?.total ?? props.alerts?.total ?? rows.value.length);
</script>

<template>
    <PageMeta :title="t('alerts.title')" fallback-key="alerts.intro" />
    <div class="container-page py-8 md:py-10">
        <PageHeader :eyebrow="t('alerts.eyebrow')" :title="t('alerts.title')" :description="t('alerts.intro')">
            <template #actions>
                <Button :href="lr('/ban-do')" icon="map">{{ t('alerts.view_map') }}</Button>
            </template>
        </PageHeader>

        <FilterBar :filters="filters" :fields="fields" class="mb-3" />
        <DataLegend class="mb-3" />
        <p class="mb-4 text-sm text-muted" aria-live="polite">{{ t('alerts.count', { n: total }) }}</p>

        <SkeletonList v-if="loading" :count="6" :cols="3" height="h-24" />
        <ul v-else-if="rows.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <li v-for="a in rows" :key="a.code || a.id"><AlertCard :alert="a" :heading-level="2" /></li>
        </ul>
        <EmptyState v-else :title="t('alerts.empty')" :description="t('alerts.empty_hint')">
            <Button :href="lr('/canh-bao')" variant="ghost">{{ t('common.reset_filters') }}</Button>
        </EmptyState>

        <Pagination :paginator="alerts" />
    </div>
</template>
