<script setup>
import { computed, ref, reactive } from 'vue';
import { Link } from '@inertiajs/vue3';
import PageMeta from '@/Components/Public/PageMeta.vue';
import MapBase from '@/Components/MapBase.vue';
import AlertCard from '@/Components/AlertCard.vue';
import Badge from '@/Components/Badge.vue';
import Button from '@/Components/Button.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useGeolocation } from '@/Composables/useGeolocation';
import { usePermissions } from '@/Composables/usePermissions';
import { normalizeLevel } from '@/Utils/levels';

/**
 * Bản đồ rủi ro toàn màn trong thẻ clay: lớp vùng tô theo mức, lớp phạm vi cảnh báo, lớp chặng của tôi,
 * chú giải pill, panel bên liệt kê cảnh báo của vùng được bấm, nút định vị (xin phép trình duyệt).
 */
const props = defineProps({
    zonesGeo: { type: Object, default: null },
    alerts: { type: Array, default: () => [] },
    legend: { type: Array, default: () => [] },
    myLegs: { type: Array, default: () => [] },
    center: { type: Object, default: null },
});
const { t } = useT();
const lr = useLocaleRoute();
const { user } = usePermissions();
const geo = useGeolocation();
const LOCATE_ZOOM = 10;

const layers = reactive({ zones: true, alerts: true, legs: true });
const selected = ref(null); // properties của vùng được bấm
const focus = ref(null);

const zonesShown = computed(() => (layers.zones ? props.zonesGeo : null));
const overlays = computed(() => (layers.alerts
    ? props.alerts.filter((a) => a.geometry).map((a) => ({
        id: a.code, geometry: a.geometry, level: a.severity, label: a.headline, url: a.url, description: a.hazard?.name,
    }))
    : []));
const legPoint = (leg) => ({
    lat: leg.lat ?? leg.destination?.lat ?? leg.zone?.centroid?.lat,
    lng: leg.lng ?? leg.destination?.lng ?? leg.zone?.centroid?.lng,
});
const sortedLegs = computed(() => [...props.myLegs].sort((a, b) => (a.leg_order ?? 0) - (b.leg_order ?? 0)));
const legMarkers = computed(() => (layers.legs
    ? sortedLegs.value.map((leg) => ({
        id: `leg-${leg.id}`, ...legPoint(leg), level: leg.risk_level ?? 0,
        label: `${t('trip.leg_n', { n: leg.leg_order })}: ${leg.title || leg.zone?.name || ''}`,
    }))
    : []));
const route = computed(() => (layers.legs ? sortedLegs.value.map(legPoint) : []));

const panelAlerts = computed(() => {
    if (!selected.value?.code) return props.alerts;
    return props.alerts.filter((a) => (a.zones || []).some((z) => z.code === selected.value.code));
});

function onSelect(item) {
    if (item?.type === 'alert' || !item?.code) return;
    selected.value = item;
}
async function locate() {
    const c = await geo.locate();
    if (c) focus.value = { lat: c.lat, lng: c.lng, zoom: LOCATE_ZOOM };
}
const geoError = computed(() => ({ denied: t('checkin.geo_denied'), unavailable: t('checkin.geo_unavailable'), unsupported: t('checkin.geo_unsupported') }[geo.status.value] || ''));
const zoneCount = computed(() => props.zonesGeo?.features?.length || 0);
</script>

<template>
    <PageMeta :title="t('nav.map')" fallback-key="map.intro" />
    <div class="container-page py-6 md:py-8">
        <header class="mb-4 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="eyebrow mb-1">{{ t('map.eyebrow', { n: zoneCount }) }}</p>
                <h1>{{ t('nav.map') }}</h1>
                <p class="mt-1 max-w-2xl text-muted">{{ t('map.intro') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button icon="locate" :loading="geo.status.value === 'locating'" @click="locate">{{ t('map.locate') }}</Button>
                <Button :href="lr('/canh-bao')" icon="list">{{ t('map.list_view') }}</Button>
            </div>
        </header>
        <p v-if="geoError" class="mb-3 text-sm text-danger" role="status">{{ geoError }}</p>

        <div class="grid gap-4 lg:grid-cols-[1fr_22rem]">
            <div class="clay-card p-3 md:p-4">
                <fieldset class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                    <legend class="sr-only">{{ t('map.layers') }}</legend>
                    <span class="eyebrow" aria-hidden="true">{{ t('map.layers') }}</span>
                    <label class="inline-flex items-center gap-2 font-semibold"><input v-model="layers.zones" type="checkbox" class="clay-checkbox" />{{ t('map.layer_zones') }}</label>
                    <label class="inline-flex items-center gap-2 font-semibold"><input v-model="layers.alerts" type="checkbox" class="clay-checkbox" />{{ t('map.layer_alerts', { n: alerts.length }) }}</label>
                    <label v-if="user" class="inline-flex items-center gap-2 font-semibold"><input v-model="layers.legs" type="checkbox" class="clay-checkbox" />{{ t('map.layer_my_legs', { n: myLegs.length }) }}</label>
                </fieldset>
                <MapBase
                    bare
                    scroll-zoom
                    :geojson="zonesShown"
                    :overlays="overlays"
                    :markers="legMarkers"
                    :route="route"
                    :legend="legend"
                    :center="center"
                    :focus="focus"
                    :user-location="geo.coords.value"
                    :selected="selected?.code || null"
                    height-class="h-[60vh] min-h-[360px] lg:h-[calc(100vh-15rem)]"
                    :aria-label="t('nav.map')"
                    @select="onSelect"
                />
                <p v-if="user && !myLegs.length" class="mt-2 text-xs text-muted">
                    {{ t('map.no_legs') }} <Link :href="lr('/hanh-trinh/tao')" class="link">{{ t('home.trip_cta_button_user') }}</Link>
                </p>
            </div>

            <aside class="clay-card flex max-h-none flex-col p-4 lg:max-h-[calc(100vh-11rem)]" aria-labelledby="map-panel-title" aria-live="polite">
                <div class="mb-3 flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h2 id="map-panel-title" class="section-title text-lg">
                            {{ selected ? selected.name : t('map.active_alerts') }}
                        </h2>
                        <div v-if="selected" class="mt-2 flex flex-wrap items-center gap-2">
                            <Badge :level="normalizeLevel(selected.level) ?? 0" size="sm" />
                            <Link :href="lr(`/thoi-tiet/${selected.code}`)" class="link text-sm">{{ t('map.zone_weather') }}</Link>
                        </div>
                    </div>
                    <Button v-if="selected" size="sm" variant="ghost" icon="close" icon-only :label="t('map.clear_selection')" @click="selected = null" />
                </div>
                <p v-if="!selected" class="mb-3 text-xs text-muted"><Icon name="info" :size="14" class="inline align-[-2px]" /> {{ t('map.panel_hint') }}</p>
                <ul v-if="panelAlerts.length" class="-mr-2 space-y-3 overflow-y-auto pr-2">
                    <li v-for="a in panelAlerts" :key="a.code"><AlertCard :alert="a" compact /></li>
                </ul>
                <EmptyState v-else :title="selected ? t('map.zone_no_alerts') : t('home.no_alerts')" compact />
            </aside>
        </div>
    </div>
</template>
