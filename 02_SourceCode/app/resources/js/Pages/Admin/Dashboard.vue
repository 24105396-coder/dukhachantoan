<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import KpiCard from '@/Components/KpiCard.vue';
import ChartCard from '@/Components/ChartCard.vue';
import MapBase from '@/Components/MapBase.vue';
import AlertCard from '@/Components/AlertCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Icon from '@/Components/Icon.vue';
import RefreshIndicator from '@/Components/RefreshIndicator.vue';
import NeedHelpList from '@/Components/Dashboard/NeedHelpList.vue';
import QueueHealthCard from '@/Components/Dashboard/QueueHealthCard.vue';
import AffectedTripsTable from '@/Components/Dashboard/AffectedTripsTable.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { usePolling, DEFAULT_POLL_MS } from '@/Composables/usePolling';
import { ALERT_LEVELS } from '@/Utils/options';

/** Dashboard cơ quan hỗ trợ / quản trị: làm mới 30 giây/lần (agency chỉ thấy dữ liệu tỉnh mình – máy chủ lọc). */
const props = defineProps({
    kpis: { type: Object, default: () => ({}) },
    affectedTrips: { type: Array, default: () => [] },
    needHelp: { type: Array, default: () => [] },
    recentAlerts: { type: Array, default: () => [] },
    zonesGeo: { type: Object, default: null },
    queueHealth: { type: Object, default: () => ({}) },
});
const page = usePage();
const { t } = useT();
const { number, stamp } = useFormat();
const RELOAD_PROPS = ['kpis', 'affectedTrips', 'needHelp', 'recentAlerts', 'zonesGeo', 'queueHealth'];
const { refresh, isRefreshing, lastRefreshedAt } = usePolling(DEFAULT_POLL_MS, RELOAD_PROPS);

const k = computed(() => props.kpis || {});
const fmt = (v) => (v === null || v === undefined ? null : number(v));
const province = computed(() => page.props.auth?.user?.province?.name || t('admin.users.province_all'));

const cards = computed(() => [
    { key: 'active_alerts', icon: 'bell-ring', tone: 'peach' },
    { key: 'trips_in_affected_zones', icon: 'route', tone: 'lemon' },
    { key: 'travelers_affected', icon: 'users', tone: 'sky' },
    { key: 'checkins_need_help_open', icon: 'siren', tone: 'peach' },
    { key: 'trips_active', icon: 'luggage', tone: 'mint' },
    { key: 'sources_ok', icon: 'radio', tone: 'mint' },
]);
const severityLabels = computed(() => ALERT_LEVELS.map((l) => t(`enums.levels.${l}`)));
const severityValues = computed(() => {
    const by = k.value.alerts_by_severity || {};
    const values = ALERT_LEVELS.map((l) => Number(by[l] ?? by[String(l)] ?? 0));
    return values.some((v) => v > 0) ? values : [];
});
const checkinLabels = computed(() => ['safe', 'need_help', 'unknown'].map((s) => t(`enums.checkin_status.${s}`)));
const checkinValues = computed(() => {
    const c = k.value.checkins_last_24h || {};
    const values = [c.safe, c.need_help, c.unknown].map((v) => Number(v || 0));
    return values.some((v) => v > 0) ? values : [];
});
</script>

<template>
    <Head :title="t('admin.dashboard.title')" />
    <PageHeader :eyebrow="province" :title="t('admin.dashboard.title')" :description="t('admin.dashboard.intro')">
        <template #actions>
            <RefreshIndicator :refreshing="isRefreshing" :last-refreshed-at="lastRefreshedAt" :interval-seconds="DEFAULT_POLL_MS / 1000" @refresh="refresh" />
        </template>
    </PageHeader>

    <p v-if="page.props.app?.analyticsUp === false" class="mb-4 flex items-start gap-2 rounded-2xl border border-level-2 bg-level-2-soft p-3 text-sm font-semibold" role="status">
        <Icon name="triangle-alert" :size="18" class="mt-0.5" />{{ t('admin.dashboard.analytics_down') }}
    </p>
    <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3" :aria-label="t('dashboard.kpis')">
        <KpiCard v-for="c in cards" :key="c.key" :label="t(`dashboard.kpi.${c.key}`)" :value="fmt(k[c.key])" :icon="c.icon" :tone="c.tone" />
    </section>
    <p v-if="k.last_weather_fetch" class="mt-3 text-xs text-muted">{{ t('dashboard.last_weather_fetch', { time: stamp(k.last_weather_fetch) }) }}</p>

    <div class="mt-6 grid gap-4 xl:grid-cols-2">
        <ChartCard :title="t('dashboard.alerts_by_severity')" type="bar" levels :labels="severityLabels" :values="severityValues" :series-label="t('dashboard.kpi.active_alerts')" />
        <ChartCard :title="t('dashboard.checkins_24h')" type="doughnut" :color-vars="['--level-0', '--level-4', '--level-2']" :labels="checkinLabels" :values="checkinValues" />
    </div>

    <div class="mt-6 grid gap-4 xl:grid-cols-[1.5fr_1fr]">
        <MapBase :geojson="zonesGeo" bare height-class="h-[300px] md:h-[380px]" :aria-label="t('dashboard.map_title')" class="clay-card-sm p-3" />
        <div class="grid gap-4">
            <NeedHelpList :items="needHelp" />
            <QueueHealthCard :queue="queueHealth" :success-rate="k.delivery_success_rate" :notifications="k.notifications_24h" />
        </div>
    </div>

    <section class="mt-8 space-y-3" aria-labelledby="affected-title">
        <h2 id="affected-title" class="section-title text-xl">{{ t('dashboard.affected_trips_title') }}</h2>
        <AffectedTripsTable :trips="affectedTrips" />
    </section>

    <section class="mt-8 space-y-3" aria-labelledby="recent-alerts-title">
        <h2 id="recent-alerts-title" class="section-title text-xl">{{ t('dashboard.recent_alerts_title') }}</h2>
        <div v-if="recentAlerts.length" class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
            <AlertCard v-for="a in recentAlerts" :key="a.code || a.id" :alert="a" compact />
        </div>
        <EmptyState v-else :title="t('dashboard.recent_alerts_empty')" compact />
    </section>
</template>
