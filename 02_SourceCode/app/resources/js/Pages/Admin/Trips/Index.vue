<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import Pagination from '@/Components/Pagination.vue';
import Badge from '@/Components/Badge.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useVisitLoading } from '@/Composables/useVisitLoading';
import { enumOptions, rowsOf } from '@/Utils/options';

/** Hành trình trong phạm vi phụ trách: lọc tỉnh, vùng, khoảng ngày, mức rủi ro, trạng thái. */
const props = defineProps({
    trips: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    provinces: { type: Array, default: () => [] },
    zones: { type: Array, default: () => [] },
});
const { t, te } = useT();
const { date, ago } = useFormat();
const lr = useLocaleRoute();
const { loading } = useVisitLoading();
const STATUSES = ['draft', 'active', 'completed', 'cancelled'];

const fields = computed(() => [
    ...(props.provinces.length ? [{ name: 'province', type: 'select', label: t('common.province'), options: enumOptions(props.provinces) }] : []),
    ...(props.zones.length ? [{ name: 'zone', type: 'select', label: t('trip_form.zone'), options: enumOptions(props.zones) }] : []),
    { name: 'date_from', type: 'date', label: t('common.from') },
    { name: 'date_to', type: 'date', label: t('common.to') },
    { name: 'risk_level', type: 'select', label: t('trip.risk_level'), options: [0, 1, 2, 3, 4].map((l) => ({ value: l, label: t('level.with_number', { n: l, label: t(`enums.levels.${l}`) }) })) },
    { name: 'status', type: 'select', label: t('common.status'), options: STATUSES.map((s) => ({ value: s, label: te('status', s) })) },
]);
const columns = computed(() => [
    { key: 'code', label: t('trip.code') },
    { key: 'risk_level', label: t('trip.risk_level') },
    { key: 'owner', label: t('admin.trips.owner') },
    { key: 'dates', label: t('trip.dates') },
    { key: 'group_size', label: t('trip.group_size'), class: 'text-right' },
    { key: 'active_alerts_count', label: t('trip.alerts'), class: 'text-right' },
    { key: 'last_checkin', label: t('trip.last_checkin') },
]);
</script>

<template>
    <Head :title="t('admin.nav.trips')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.nav.trips')" :description="t('admin.trips.intro')" />
    <FilterBar :filters="filters" :fields="fields" class="mb-4" />
    <DataTable :columns="columns" :rows="rowsOf(trips)" row-key="code" :loading="loading" :caption="t('admin.nav.trips')" :empty-text="t('trips.empty_filtered')">
        <template #cell-code="{ row }">
            <Link :href="lr(`/quan-tri/hanh-trinh/${row.code}`)" class="link font-mono">{{ row.code }}</Link>
            <span class="block max-w-[16rem] truncate text-xs text-muted">{{ row.title }}</span>
            <span class="pill mt-1 text-[0.6875rem]">{{ te('status', row.status) }}</span>
        </template>
        <template #cell-risk_level="{ row }"><Badge :level="row.risk_level ?? 0" size="sm" /></template>
        <template #cell-owner="{ row }">{{ row.owner?.name || '—' }}<span v-if="row.partner?.name" class="block text-xs text-muted">{{ row.partner.name }}</span></template>
        <template #cell-dates="{ row }"><span class="whitespace-nowrap">{{ date(row.start_date) }} – {{ date(row.end_date) }}</span></template>
        <template #cell-last_checkin="{ row }">
            <span v-if="row.last_checkin" class="whitespace-nowrap" :class="row.last_checkin.status === 'need_help' ? 'font-bold text-danger' : ''">{{ te('checkin_status', row.last_checkin.status) }} · {{ ago(row.last_checkin.checked_at) }}</span>
            <span v-else class="text-muted">{{ t('trip.no_checkin') }}</span>
        </template>
    </DataTable>
    <Pagination :paginator="trips" />
</template>
