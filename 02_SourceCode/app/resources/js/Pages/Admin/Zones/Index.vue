<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import Pagination from '@/Components/Pagination.vue';
import Badge from '@/Components/Badge.vue';
import Button from '@/Components/Button.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useVisitLoading } from '@/Composables/useVisitLoading';
import { enumOptions, rowsOf } from '@/Utils/options';

/** Danh sách vùng theo dõi (ZoneCard): điểm và mức rủi ro hiện tại, số cảnh báo, sửa tên/hình học. */
const props = defineProps({
    zones: { type: [Object, Array], default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    provinces: { type: Array, default: () => [] },
    zoneTypes: { type: Array, default: () => [] },
});
const { t, te } = useT();
const { number, ago } = useFormat();
const lr = useLocaleRoute();
const { loading } = useVisitLoading();
const ZONE_TYPES = ['province', 'district', 'coast', 'island', 'mountain', 'urban', 'custom'];
const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword') },
    ...(props.provinces.length ? [{ name: 'province', type: 'select', label: t('common.province'), options: enumOptions(props.provinces) }] : []),
    { name: 'zone_type', type: 'select', label: t('common.type'), options: (props.zoneTypes.length ? props.zoneTypes : ZONE_TYPES).map((z) => ({ value: z.code ?? z, label: z.name ?? te('zone_type', z) })) },
]);
const columns = computed(() => [
    { key: 'name', label: t('common.name') },
    { key: 'risk', label: t('risk.score_title') },
    { key: 'active_alerts_count', label: t('trip.alerts'), class: 'text-right' },
]);
</script>

<template>
    <Head :title="t('admin.nav.zones')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.nav.zones')" :description="t('admin.zones.intro')" />
    <FilterBar :filters="filters" :fields="fields" class="mb-4" />
    <DataTable :columns="columns" :rows="rowsOf(zones)" row-key="code" :loading="loading" :caption="t('admin.nav.zones')" :empty-text="t('common.no_data')">
        <template #cell-name="{ row }">
            <span class="font-semibold">{{ row.name }}</span>
            <span class="block text-xs text-muted">{{ te('zone_type', row.zone_type) }} · {{ row.province?.name }} · <span class="font-mono">{{ row.code }}</span></span>
        </template>
        <template #cell-risk="{ row }">
            <template v-if="row.risk">
                <Badge :level="row.risk.level" size="sm" />
                <span class="ml-1 font-semibold">{{ number(row.risk.score) }}/100</span>
                <span class="block text-xs text-muted">{{ ago(row.risk.computed_at) }}</span>
            </template>
            <span v-else class="text-muted">{{ t('risk.no_data') }}</span>
        </template>
        <template #cell-active_alerts_count="{ row }">
            <Link v-if="row.active_alerts_count" :href="lr(`/quan-tri/canh-bao?q=${encodeURIComponent(row.name)}`)" class="link">{{ row.active_alerts_count }}</Link>
            <span v-else>0</span>
        </template>
        <template #actions="{ row }">
            <Button :href="lr(`/thoi-tiet/${row.code}`)" size="sm" variant="ghost" icon="cloud-sun" icon-only :label="`${t('map.zone_weather')}: ${row.name}`" />
            <Button :href="lr(`/quan-tri/vung/${row.code}/sua`)" size="sm" variant="ghost" icon="edit" icon-only :label="`${t('common.edit')} ${row.name}`" />
        </template>
    </DataTable>
    <Pagination v-if="!Array.isArray(zones)" :paginator="zones" />
</template>
