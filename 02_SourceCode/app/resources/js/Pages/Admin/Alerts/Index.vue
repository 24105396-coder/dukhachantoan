<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import ExportButton from '@/Components/Admin/ExportButton.vue';
import FilterBar from '@/Components/FilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import Pagination from '@/Components/Pagination.vue';
import Badge from '@/Components/Badge.vue';
import Button from '@/Components/Button.vue';
import Icon from '@/Components/Icon.vue';
import SourceStamp from '@/Components/SourceStamp.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useVisitLoading } from '@/Composables/useVisitLoading';
import { enumOptions, rowsOf, ALERT_LEVELS } from '@/Utils/options';

/** Quản lý cảnh báo (agency: tỉnh mình; admin: toàn quốc): lọc, xem, soạn mới. Dòng nguồn + giờ cập nhật trên từng cảnh báo. */
const props = defineProps({
    alerts: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    hazards: { type: Array, default: () => [] },
    provinces: { type: Array, default: () => [] },
});
const { t, te } = useT();
const { dateTime } = useFormat();
const lr = useLocaleRoute();
const { loading } = useVisitLoading();
const STATUSES = ['draft', 'active', 'updated', 'expired', 'cancelled'];

const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword'), placeholder: t('admin.alerts.search_placeholder') },
    { name: 'status', type: 'select', label: t('common.status'), options: STATUSES.map((s) => ({ value: s, label: te('status', s) })) },
    { name: 'severity', type: 'select', label: t('alerts.severity'), options: ALERT_LEVELS.map((l) => ({ value: l, label: t('level.with_number', { n: l, label: t(`enums.levels.${l}`) }) })) },
    { name: 'hazard', type: 'select', label: t('alerts.hazard'), options: enumOptions(props.hazards, te, 'hazard') },
    ...(props.provinces.length ? [{ name: 'province', type: 'select', label: t('common.province'), options: enumOptions(props.provinces) }] : []),
    { name: 'simulated', type: 'select', label: t('alerts.simulated_filter'), options: [{ value: '0', label: t('alerts.real_only') }, { value: '1', label: t('alerts.simulated_only') }] },
]);
const columns = computed(() => [
    { key: 'severity', label: t('alerts.severity') },
    { key: 'headline', label: t('admin.common.title') },
    { key: 'status', label: t('common.status') },
    { key: 'window', label: t('alert.valid_time') },
    { key: 'source', label: t('common.source') },
]);
</script>

<template>
    <Head :title="t('admin.nav.alerts')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.nav.alerts')" :description="t('admin.alerts.intro')">
        <template #actions>
            <ExportButton dataset="canh-bao" />
            <Button :href="lr('/quan-tri/canh-bao/tao')" variant="primary" icon="plus">{{ t('admin.alerts.create') }}</Button>
        </template>
    </PageHeader>
    <FilterBar :filters="filters" :fields="fields" class="mb-4" />
    <DataTable :columns="columns" :rows="rowsOf(alerts)" row-key="code" :loading="loading" :caption="t('admin.nav.alerts')" :empty-text="t('alerts.empty')" :empty-hint="t('alerts.empty_hint')">
        <template #cell-severity="{ row }"><Badge :level="row.severity" size="sm" show-level-number /></template>
        <template #cell-headline="{ row }">
            <Link :href="lr(`/quan-tri/canh-bao/${row.code}`)" class="link">{{ row.headline }}</Link>
            <span class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-muted">
                <span class="font-mono">{{ row.code }}</span>
                <span v-if="row.hazard"><Icon :name="row.hazard.icon || row.hazard.code" fallback="alert" :size="12" class="inline align-[-1px]" /> {{ row.hazard.name }}</span>
                <span v-if="row.zones?.length">· {{ row.zones.map((z) => z.name).slice(0, 3).join(', ') }}<template v-if="row.zones.length > 3"> +{{ row.zones.length - 3 }}</template></span>
                <Badge v-if="row.is_simulated" tone="outline" size="sm" icon="flask">{{ t('common.simulated') }}</Badge>
            </span>
        </template>
        <template #cell-status="{ row }"><span class="pill whitespace-nowrap text-xs">{{ te('status', row.status) }}</span></template>
        <template #cell-window="{ row }"><span class="whitespace-nowrap text-xs">{{ dateTime(row.starts_at) }}<br />{{ row.ends_at ? dateTime(row.ends_at) : t('alert.until_further_notice') }}</span></template>
        <template #cell-source="{ row }"><SourceStamp class="min-w-48" :source="row.source" :updated-at="row.updated_at_source || row.issued_at" /></template>
        <template #actions="{ row }">
            <Button :href="lr(`/quan-tri/canh-bao/${row.code}`)" size="sm" variant="ghost" icon="eye" icon-only :label="t('common.view_detail') + ' ' + row.code" />
            <Button v-if="['draft', 'active', 'updated'].includes(row.status)" :href="lr(`/quan-tri/canh-bao/${row.code}/sua`)" size="sm" variant="ghost" icon="edit" icon-only :label="t('common.edit') + ' ' + row.code" />
        </template>
    </DataTable>
    <Pagination :paginator="alerts" />
</template>
