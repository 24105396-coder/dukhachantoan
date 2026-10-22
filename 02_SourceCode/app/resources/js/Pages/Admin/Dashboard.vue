<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import ChartCard from '@/Components/ChartCard.vue';
import DataTable from '@/Components/DataTable.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

const props = defineProps({
    kpis: { type: Object, default: () => ({}) },
    charts: { type: Object, default: () => ({}) },
    recentAudit: { type: Array, default: () => [] },
    analyticsUp: { type: Boolean, default: true },
});
const { t, te } = useT();
const { number, money, dateTime, date } = useFormat();
const lr = useLocaleRoute();

const k = computed(() => props.kpis || {});
const ratio = computed(() => {
    const r = Number(k.value.publishedRatio ?? 0);
    return `${number(r <= 1 ? r * 100 : r, 1)}%`;
});
const cards = computed(() => [
    { label: t('admin.dashboard.sites'), value: number(k.value.sites), icon: 'landmark', tone: 'primary', hint: t('admin.dashboard.published_ratio', { ratio: ratio.value }) },
    { label: t('admin.dashboard.artifacts_figures'), value: `${number(k.value.artifacts)} / ${number(k.value.figures)}`, icon: 'vase', tone: 'accent', hint: t('admin.dashboard.records', { n: number(k.value.records) }) },
    { label: t('admin.dashboard.bookings30d'), value: number(k.value.bookings30d), icon: 'ticket', tone: 'info', hint: t('admin.dashboard.revenue', { amount: money(k.value.revenue30d ?? 0) }) },
    { label: t('admin.dashboard.pending_contributions'), value: number(k.value.pendingContributions), icon: 'inbox', tone: 'success', hint: t('admin.dashboard.users', { n: number(k.value.users) }) },
]);

const views = computed(() => props.charts?.viewsByDay || []);
const byStatus = computed(() => props.charts?.bookingsByStatus || []);
const byProvince = computed(() => props.charts?.sitesByProvince || []);
const engines = computed(() => props.charts?.searchEngines || []);

const auditColumns = computed(() => [
    { key: 'created_at', label: t('admin.audit.time') },
    { key: 'user', label: t('admin.audit.user') },
    { key: 'action', label: t('admin.audit.action') },
    { key: 'subject', label: t('admin.audit.subject') },
]);
const userName = (u) => (u && typeof u === 'object' ? u.name || u.email : u) || t('admin.audit.system');
const subjectText = (s) => (s && typeof s === 'object' ? [te('subject_type', s.type), s.title || s.id].filter(Boolean).join(' – ') : s) || '—';
</script>

<template>
    <Head :title="t('admin.dashboard.title')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.dashboard.title')" :description="t('admin.dashboard.intro')">
        <template #actions>
            <a :href="lr('/quan-tri/thong-ke/xuat?type=bookings')" class="btn btn-sm"><Icon name="download" :size="16" />{{ t('admin.dashboard.export_bookings') }}</a>
            <a :href="lr('/quan-tri/thong-ke/xuat?type=views')" class="btn btn-sm"><Icon name="download" :size="16" />{{ t('admin.dashboard.export_views') }}</a>
        </template>
    </PageHeader>

    <p v-if="!analyticsUp" class="mb-6 flex items-start gap-2 rounded-lg bg-warning-soft p-3 text-sm text-warning" role="alert">
        <Icon name="alert" :size="18" />{{ t('admin.dashboard.analytics_down') }}
    </p>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard v-for="c in cards" :key="c.label" v-bind="c" />
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <ChartCard :title="t('admin.dashboard.chart_views')" type="line" :labels="views.map((x) => date(x.date, { day: '2-digit', month: '2-digit' }))" :values="views.map((x) => x.views)" :series-label="t('common.views')" />
        <ChartCard :title="t('admin.dashboard.chart_bookings')" type="doughnut" :labels="byStatus.map((x) => x.label || te('status', x.status))" :values="byStatus.map((x) => x.count)" />
        <ChartCard :title="t('admin.dashboard.chart_provinces')" type="hbar" :labels="byProvince.map((x) => x.name)" :values="byProvince.map((x) => x.count)" :height="320" />
        <ChartCard :title="t('admin.dashboard.chart_engines')" type="doughnut" :labels="engines.map((x) => te('engine', x.engine))" :values="engines.map((x) => x.count)" :height="320" />
    </div>

    <section class="mt-6">
        <h2 class="mb-3 font-body text-lg font-bold">{{ t('admin.dashboard.recent_audit') }}</h2>
        <DataTable :columns="auditColumns" :rows="recentAudit" :caption="t('admin.dashboard.recent_audit')">
            <template #cell-created_at="{ value }"><span class="whitespace-nowrap">{{ dateTime(value) }}</span></template>
            <template #cell-user="{ value }">{{ userName(value) }}</template>
            <template #cell-action="{ value }"><code class="text-xs">{{ value }}</code></template>
            <template #cell-subject="{ value }">{{ subjectText(value) }}</template>
        </DataTable>
    </section>
</template>
