<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import AlertCard from '@/Components/AlertCard.vue';
import KpiCard from '@/Components/KpiCard.vue';
import DataTable from '@/Components/DataTable.vue';
import MapBase from '@/Components/MapBase.vue';
import StatusHistory from '@/Components/StatusHistory.vue';
import SimulatedBanner from '@/Components/SimulatedBanner.vue';
import AlertLifecycle from '@/Components/Admin/AlertLifecycle.vue';
import Button from '@/Components/Button.vue';
import RefreshIndicator from '@/Components/RefreshIndicator.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { usePolling, DEFAULT_POLL_MS } from '@/Composables/usePolling';

/** Chi tiết cảnh báo cho cơ quan: vòng đời, hành trình khớp (MatchItem), thống kê thông báo, bản đồ, lịch sử. */
const props = defineProps({
    alert: { type: Object, required: true },
    matches: { type: Array, default: () => [] },
    notifications: { type: Object, default: () => ({}) },
    zonesGeo: { type: Object, default: null },
});
const { t, te } = useT();
const { dateTime, km, number, percent } = useFormat();
const lr = useLocaleRoute();
const { refresh, isRefreshing, lastRefreshedAt } = usePolling(DEFAULT_POLL_MS, ['alert', 'matches', 'notifications']);

const stats = computed(() => props.notifications || {});
const total = computed(() => stats.value.total ?? ['queued', 'sent', 'failed', 'read'].reduce((s, k) => s + Number(stats.value[k] || 0), 0));
const KPIS = [
    { key: 'queued', icon: 'clock', tone: 'sky' },
    { key: 'sent', icon: 'send', tone: 'mint' },
    { key: 'failed', icon: 'circle-x', tone: 'peach' },
    { key: 'read', icon: 'check', tone: 'lemon' },
];
const columns = computed(() => [
    { key: 'trip', label: t('trip.code') },
    { key: 'leg', label: t('trip.leg') },
    { key: 'match_type', label: t('admin.alerts.match_type') },
    { key: 'distance_km', label: t('match.distance'), class: 'text-right' },
    { key: 'overlap', label: t('match.overlap') },
    { key: 'status', label: t('common.status') },
]);
const overlays = computed(() => (props.alert.geometry ? [{ id: props.alert.code, geometry: props.alert.geometry, level: props.alert.severity, label: props.alert.headline }] : []));
const crumbs = computed(() => [{ label: t('admin.nav.alerts'), href: lr('/quan-tri/canh-bao') }, { label: props.alert.code }]);
</script>

<template>
    <Head :title="alert.code" />
    <PageHeader :breadcrumbs="crumbs" :eyebrow="alert.code" :title="alert.headline">
        <template #actions>
            <RefreshIndicator :refreshing="isRefreshing" :last-refreshed-at="lastRefreshedAt" :interval-seconds="DEFAULT_POLL_MS / 1000" @refresh="refresh" />
        </template>
    </PageHeader>
    <div class="mb-6 flex flex-wrap items-center gap-2">
        <AlertLifecycle :alert="alert" size="sm" />
        <Button v-if="alert.url && ['active', 'updated', 'expired'].includes(alert.status)" :href="alert.url" size="sm" variant="ghost" icon="external">{{ t('admin.common.view_public') }}</Button>
    </div>
    <SimulatedBanner v-if="alert.is_simulated" :scenario="alert.scenario" class="mb-6" />

    <div class="grid gap-6 xl:grid-cols-[1fr_1.2fr]">
        <div class="space-y-6">
            <AlertCard :alert="alert" :heading-level="2" />
            <section v-if="alert.instructions" class="clay-card-sm p-5" aria-labelledby="as-inst">
                <h2 id="as-inst" class="section-title text-lg">{{ t('alert.instructions') }}</h2>
                <p class="mt-2 whitespace-pre-line text-sm">{{ alert.instructions }}</p>
                <p class="mt-2 text-xs text-muted">{{ t('admin.alerts.translations', { list: (alert.translations_available || []).map((l) => te('locale', l)).join(', ') || '—' }) }}</p>
            </section>
            <section class="clay-card-sm p-5" aria-labelledby="as-hist">
                <h2 id="as-hist" class="section-title mb-4 text-lg">{{ t('alert.history') }}</h2>
                <StatusHistory :items="alert.history || []" />
            </section>
        </div>
        <div class="space-y-6">
            <section aria-labelledby="as-stats" class="space-y-3">
                <h2 id="as-stats" class="section-title text-lg">{{ t('admin.alerts.notification_stats', { n: number(total) }) }}</h2>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <KpiCard v-for="k in KPIS" :key="k.key" :label="te('status', k.key)" :value="number(stats[k.key] ?? 0)" :icon="k.icon" :tone="k.tone" class="!p-3 [&_.icon-tile]:hidden lg:[&_.icon-tile]:inline-flex" />
                </div>
                <p v-if="stats.delivery_success_rate != null" class="text-xs text-muted">{{ t('dashboard.delivery_rate') }}: {{ percent(stats.delivery_success_rate, 1) }}</p>
                <Link :href="lr(`/quan-tri/thong-bao?alert=${alert.code}`)" class="link text-sm">{{ t('admin.alerts.view_queue') }}</Link>
            </section>
            <MapBase :geojson="zonesGeo" :overlays="overlays" :aria-label="t('alert.map_title')" height-class="h-[280px] md:h-[340px]" />
        </div>
    </div>

    <section class="mt-8 space-y-3" aria-labelledby="as-matches">
        <h2 id="as-matches" class="section-title text-xl">{{ t('admin.alerts.matches', { n: matches.length }) }}</h2>
        <DataTable :columns="columns" :rows="matches" :caption="t('admin.alerts.matches', { n: matches.length })" :empty-text="t('admin.alerts.no_matches')" :empty-hint="alert.status === 'draft' ? t('admin.alerts.no_matches_draft') : ''">
            <template #cell-trip="{ row }">
                <Link v-if="row.trip?.code" :href="lr(`/quan-tri/hanh-trinh/${row.trip.code}`)" class="link font-mono">{{ row.trip.code }}</Link>
                <span v-else class="text-muted">—</span>
                <span v-if="row.trip?.title" class="block max-w-[12rem] truncate text-xs text-muted">{{ row.trip.title }}</span>
            </template>
            <template #cell-leg="{ row }">{{ t('trip.leg_n', { n: row.leg?.leg_order }) }}<span class="block text-xs text-muted">{{ row.leg?.title }}</span></template>
            <template #cell-match_type="{ row }">{{ te('match_type', row.match_type) }}</template>
            <template #cell-distance_km="{ row }">{{ row.distance_km != null ? km(row.distance_km) : '—' }}</template>
            <template #cell-overlap="{ row }"><span class="whitespace-nowrap text-xs">{{ dateTime(row.overlap_from) }}<br />{{ dateTime(row.overlap_to) }}</span></template>
            <template #cell-status="{ row }">
                <span class="pill text-xs">{{ te('status', row.status) }}</span>
                <span v-if="row.acknowledged_at" class="block text-xs text-muted">{{ dateTime(row.acknowledged_at) }}</span>
            </template>
        </DataTable>
    </section>
</template>
