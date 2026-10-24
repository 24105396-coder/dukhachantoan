<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import ChartCard from '@/Components/ChartCard.vue';
import Badge from '@/Components/Badge.vue';
import Icon from '@/Components/Icon.vue';
import DataLegend from '@/Components/DataLegend.vue';
import ExportButton from '@/Components/Admin/ExportButton.vue';
import ReportKpis from '@/Components/Admin/Reports/ReportKpis.vue';
import ReportExports from '@/Components/Admin/Reports/ReportExports.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useVisitLoading } from '@/Composables/useVisitLoading';
import { enumOptions } from '@/Utils/options';

/**
 * Báo cáo & xuất Excel (admin.reports.index): lọc khoảng ngày + tỉnh (admin), 12 KPI, thống kê theo mức/loại/tỉnh/tháng/nguồn,
 * danh sách bộ dữ liệu tải .xlsx. Agency chỉ thấy tỉnh mình (máy chủ lọc, `provinces` rỗng). Mọi bảng ghi rõ phần thật/mô phỏng.
 */
const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    byLevel: { type: Array, default: () => [] },
    byHazard: { type: Array, default: () => [] },
    byProvince: { type: Array, default: () => [] },
    byMonth: { type: Array, default: () => [] },
    bySource: { type: Array, default: () => [] },
    datasets: { type: Array, default: () => [] },
    provinces: { type: Array, default: () => [] },
    generated_at: { type: String, default: null },
    scope: { type: Object, default: () => ({}) },
});
const { t } = useT();
const { number, date, stamp } = useFormat();
const { loading } = useVisitLoading();

/** Bộ lọc: từ ngày, đến ngày, tỉnh (chỉ admin có danh sách tỉnh). */
const fields = computed(() => [
    { name: 'from', type: 'date', label: t('common.from') },
    { name: 'to', type: 'date', label: t('common.to') },
    ...(props.provinces.length ? [{ name: 'province', type: 'select', label: t('common.province'), options: enumOptions(props.provinces) }] : []),
]);
const scopeName = computed(() => props.scope?.province?.name || t('admin.users.province_all'));
const period = computed(() => t('ui.reports.period', { from: date(props.filters?.from) || '—', to: date(props.filters?.to) || '—' }));
/** Chú thích thật/mô phỏng dùng dưới mọi bảng. */
const mixNote = computed(() => t('ui.reports.note_mix', {
    real: number(props.summary?.real_alerts ?? 0),
    sim: number(props.summary?.simulated_alerts ?? 0),
}));

const sum = (rows, key) => rows.reduce((acc, r) => acc + Number(r[key] || 0), 0);
/**
 * Tỉ trọng phần trăm của một giá trị trong tổng.
 * @param {number} value giá trị
 * @param {number} total tổng
 * @returns {string} "12,5%" hoặc "—" khi tổng bằng 0
 */
function share(value, total) {
    return total ? `${number((Number(value || 0) / total) * 100, 1)}%` : '—';
}
const levelTotal = computed(() => sum(props.byLevel, 'count'));
const hazardTotal = computed(() => sum(props.byHazard, 'count'));
/** "2026-09" → "09/2026". */
const monthLabel = (m) => (typeof m === 'string' && /^\d{4}-\d{2}$/.test(m) ? `${m.slice(5)}/${m.slice(0, 4)}` : m || '—');

const levelCols = computed(() => [
    { key: 'level', label: t('alerts.severity') },
    { key: 'count', label: t('ui.reports.col.alerts'), class: 'text-right' },
    { key: 'share', label: t('ui.reports.col.share'), class: 'text-right' },
]);
const hazardCols = computed(() => [
    { key: 'name', label: t('alerts.hazard') },
    { key: 'count', label: t('ui.reports.col.alerts'), class: 'text-right' },
    { key: 'share', label: t('ui.reports.col.share'), class: 'text-right' },
]);
const provinceCols = computed(() => [
    { key: 'name', label: t('common.province') },
    { key: 'alerts', label: t('ui.reports.col.alerts'), class: 'text-right' },
    { key: 'trips', label: t('ui.reports.col.trips'), class: 'text-right' },
    { key: 'checkins_need_help', label: t('ui.reports.col.need_help'), class: 'text-right' },
]);
const monthCols = computed(() => [
    { key: 'month', label: t('ui.reports.col.month') },
    { key: 'alerts', label: t('ui.reports.col.alerts'), class: 'text-right' },
    { key: 'notifications_sent', label: t('ui.reports.col.sent'), class: 'text-right' },
    { key: 'notifications_failed', label: t('ui.reports.col.failed'), class: 'text-right' },
]);
const sourceCols = computed(() => [
    { key: 'name', label: t('common.source') },
    { key: 'kind', label: t('ui.reports.col.kind') },
    { key: 'alerts', label: t('ui.reports.col.alerts'), class: 'text-right' },
    { key: 'last_fetched_at', label: t('ui.reports.col.last_fetched') },
]);

/** Biểu đồ: cảnh báo theo mức (màu mức) và theo tháng. */
const levelLabels = computed(() => props.byLevel.map((r) => r.label || t(`enums.levels.${r.level}`)));
const levelValues = computed(() => (levelTotal.value ? props.byLevel.map((r) => Number(r.count || 0)) : []));
const monthLabels = computed(() => props.byMonth.map((r) => monthLabel(r.month)));
const monthValues = computed(() => (sum(props.byMonth, 'alerts') ? props.byMonth.map((r) => Number(r.alerts || 0)) : []));
const KIND_ICON = { open_meteo: 'cloud-sun', manual: 'building', simulated: 'flask' };
</script>

<template>
    <Head :title="t('nav.reports')" />
    <PageHeader :eyebrow="`${t('admin.title')} · ${scopeName}`" :title="t('nav.reports')" :description="t('ui.reports.intro')">
        <template #actions>
            <ExportButton dataset="tong-hop" :href="datasets.find((d) => d.key === 'tong-hop')?.url || null" />
        </template>
    </PageHeader>

    <FilterBar :filters="filters" :fields="fields" class="mb-3" />
    <div class="mb-5 flex flex-wrap items-center justify-between gap-2">
        <DataLegend />
        <p class="text-xs text-muted">
            {{ period }}<template v-if="generated_at"> · {{ t('ui.reports.generated_at', { time: stamp(generated_at) }) }}</template>
        </p>
    </div>

    <ReportKpis :summary="summary" />

    <div class="mt-6 grid gap-4 xl:grid-cols-2">
        <ChartCard :title="t('ui.reports.by_level')" type="bar" levels :labels="levelLabels" :values="levelValues" :series-label="t('ui.reports.col.alerts')" />
        <ChartCard :title="t('ui.reports.by_month')" type="line" :labels="monthLabels" :values="monthValues" :series-label="t('ui.reports.col.alerts')" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="min-w-0 space-y-2" aria-labelledby="rp-level">
            <h2 id="rp-level" class="section-title text-lg">{{ t('ui.reports.by_level') }}</h2>
            <DataTable :columns="levelCols" :rows="byLevel" row-key="level" :loading="loading" :caption="t('ui.reports.by_level')">
                <template #cell-level="{ row }"><Badge :level="row.level" :label="row.label || undefined" size="sm" show-level-number /></template>
                <template #cell-count="{ value }">{{ number(value ?? 0) }}</template>
                <template #cell-share="{ row }">{{ share(row.count, levelTotal) }}</template>
            </DataTable>
            <p class="text-xs text-muted">{{ mixNote }}</p>
        </section>

        <section class="min-w-0 space-y-2" aria-labelledby="rp-hazard">
            <h2 id="rp-hazard" class="section-title text-lg">{{ t('ui.reports.by_hazard') }}</h2>
            <DataTable :columns="hazardCols" :rows="byHazard" row-key="code" :loading="loading" :caption="t('ui.reports.by_hazard')">
                <template #cell-name="{ row }"><span class="inline-flex items-center gap-1.5"><Icon :name="row.code || 'alert'" fallback="alert" :size="14" />{{ row.name }}</span></template>
                <template #cell-count="{ value }">{{ number(value ?? 0) }}</template>
                <template #cell-share="{ row }">{{ share(row.count, hazardTotal) }}</template>
            </DataTable>
            <p class="text-xs text-muted">{{ mixNote }}</p>
        </section>

        <section class="min-w-0 space-y-2 xl:col-span-2" aria-labelledby="rp-province">
            <h2 id="rp-province" class="section-title text-lg">{{ t('ui.reports.by_province') }}</h2>
            <DataTable :columns="provinceCols" :rows="byProvince" row-key="province_id" :loading="loading" :caption="t('ui.reports.by_province')">
                <template #cell-alerts="{ value }">{{ number(value ?? 0) }}</template>
                <template #cell-trips="{ value }">{{ number(value ?? 0) }}</template>
                <template #cell-checkins_need_help="{ value }">{{ number(value ?? 0) }}</template>
            </DataTable>
            <p class="text-xs text-muted">{{ t('ui.reports.note_top15') }} {{ mixNote }}</p>
        </section>

        <section class="min-w-0 space-y-2" aria-labelledby="rp-month">
            <h2 id="rp-month" class="section-title text-lg">{{ t('ui.reports.by_month') }}</h2>
            <DataTable :columns="monthCols" :rows="byMonth" row-key="month" :loading="loading" :caption="t('ui.reports.by_month')">
                <template #cell-month="{ value }"><span class="whitespace-nowrap">{{ monthLabel(value) }}</span></template>
                <template #cell-alerts="{ value }">{{ number(value ?? 0) }}</template>
                <template #cell-notifications_sent="{ value }">{{ number(value ?? 0) }}</template>
                <template #cell-notifications_failed="{ value }">{{ number(value ?? 0) }}</template>
            </DataTable>
            <p class="text-xs text-muted">{{ mixNote }}</p>
        </section>
    </div>

    <section class="mt-6 min-w-0 space-y-2" aria-labelledby="rp-source">
        <h2 id="rp-source" class="section-title text-lg">{{ t('ui.reports.by_source') }}</h2>
        <DataTable :columns="sourceCols" :rows="bySource" row-key="source_id" :loading="loading" :caption="t('ui.reports.by_source')">
            <template #cell-kind="{ row }">
                <span class="pill whitespace-nowrap text-xs" :class="row.kind === 'simulated' ? 'border-dashed' : ''">
                    <Icon :name="KIND_ICON[row.kind] || 'radio'" :size="14" />{{ t(`ui.reports.kind.${row.kind || 'manual'}`) }}
                </span>
            </template>
            <template #cell-alerts="{ value }">{{ number(value ?? 0) }}</template>
            <template #cell-last_fetched_at="{ value }"><span class="whitespace-nowrap text-xs">{{ stamp(value) || '—' }}</span></template>
        </DataTable>
        <p class="text-xs text-muted">{{ t('ui.reports.note_source') }}</p>
    </section>

    <div class="mt-8">
        <ReportExports :datasets="datasets" />
    </div>
</template>
