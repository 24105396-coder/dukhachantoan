<script setup>
import { computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import Badge from '@/Components/Badge.vue';
import Icon from '@/Components/Icon.vue';
import MapBase from '@/Components/MapBase.vue';
import EmptyState from '@/Components/EmptyState.vue';
import RefreshIndicator from '@/Components/RefreshIndicator.vue';
import WeatherNowCard from '@/Components/WeatherNowCard.vue';
import NotificationItem from '@/Components/NotificationItem.vue';
import LegTimeline from '@/Components/Trip/LegTimeline.vue';
import MatchItem from '@/Components/Trip/MatchItem.vue';
import CheckinItem from '@/Components/Trip/CheckinItem.vue';
import CheckinPanel from '@/Components/Trip/CheckinPanel.vue';
import TripActions from '@/Components/Trip/TripActions.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { usePolling, DEFAULT_POLL_MS } from '@/Composables/usePolling';
import { normalizeLevel } from '@/Utils/levels';

/**
 * Chi tiết hành trình (TripDetail): dòng thời gian chặng, bản đồ, cảnh báo khớp + "Đã biết", báo an toàn,
 * thông báo, chia sẻ/iCal/hủy. Tự làm mới prop `trip` mỗi 30 giây.
 */
const props = defineProps({
    trip: { type: Object, required: true },
    zonesGeo: { type: Object, default: null },
    weather: { type: Array, default: () => [] },
});
const { t, te } = useT();
const { date } = useFormat();
const lr = useLocaleRoute();
const { refresh, isRefreshing, lastRefreshedAt } = usePolling(DEFAULT_POLL_MS, ['trip']);

const level = computed(() => normalizeLevel(props.trip.risk_level) ?? 0);
const legs = computed(() => [...(props.trip.legs || [])].sort((a, b) => a.leg_order - b.leg_order));
const matches = computed(() => [...(props.trip.matches || [])].sort((a, b) => (b.alert?.severity ?? 0) - (a.alert?.severity ?? 0)));
const urgent = computed(() => matches.value.filter((m) => m.alert?.severity >= 4 && m.status !== 'acknowledged'));
const matchAlerts = computed(() => {
    const seen = new Map();
    matches.value.forEach((m) => m.alert?.code && seen.set(m.alert.code, m.alert));
    return [...seen.values()];
});
const legPoint = (leg) => ({ lat: leg.lat ?? leg.destination?.lat ?? leg.zone?.centroid?.lat, lng: leg.lng ?? leg.destination?.lng ?? leg.zone?.centroid?.lng });
const markers = computed(() => legs.value.map((l) => ({ id: l.id, ...legPoint(l), level: l.risk_level ?? 0, label: `${t('trip.leg_n', { n: l.leg_order })}: ${l.title || l.zone?.name || ''}` })));
const route = computed(() => legs.value.map(legPoint));
const overlays = computed(() => matchAlerts.value.filter((a) => a.geometry).map((a) => ({ id: a.code, geometry: a.geometry, level: a.severity, label: a.headline, url: a.url })));

const acking = reactive({});
function acknowledge(match) {
    acking[match.id] = true;
    router.post(lr(`/hanh-trinh/${props.trip.code}/xac-nhan/${match.id}`), {}, {
        preserveScroll: true,
        only: ['trip', 'flash'],
        onFinish: () => (acking[match.id] = false),
    });
}
const reading = reactive({});
function markRead(n) {
    reading[n.id] = true;
    router.post(lr(`/thong-bao/${n.id}/da-doc`), {}, { preserveScroll: true, only: ['trip', 'auth', 'flash'], onFinish: () => (reading[n.id] = false) });
}
const crumbs = computed(() => [{ label: t('nav.trips'), href: lr('/hanh-trinh') }, { label: props.trip.code }]);
</script>

<template>
    <Head :title="trip.title" />
    <PageHeader :breadcrumbs="crumbs" :eyebrow="trip.code" :title="trip.title">
        <template #actions>
            <RefreshIndicator :refreshing="isRefreshing" :last-refreshed-at="lastRefreshedAt" :interval-seconds="DEFAULT_POLL_MS / 1000" @refresh="refresh" />
        </template>
    </PageHeader>

    <div class="mb-5 flex flex-wrap items-center gap-2 text-sm">
        <Badge :level="level" show-level-number />
        <span class="pill">{{ te('status', trip.status) }}</span>
        <span class="pill"><Icon name="calendar" :size="14" />{{ date(trip.start_date) }} – {{ date(trip.end_date) }}</span>
        <span class="pill"><Icon name="users" :size="14" />{{ t('trip.people', { n: trip.group_size ?? 1 }) }}</span>
        <span v-if="trip.partner?.name" class="pill"><Icon name="building" :size="14" />{{ trip.partner.name }}</span>
    </div>
    <TripActions :trip="trip" class="mb-6" />

    <div v-if="urgent.length" class="mb-6 flex items-start gap-3 callout callout-4 p-4" role="alert">
        <Icon name="siren" :size="28" class="mt-0.5" />
        <div>
            <p class="font-display text-lg font-bold">{{ t('trip.urgent_title', { n: urgent.length }) }}</p>
            <p class="text-sm">{{ t('trip.urgent_text') }}</p>
        </div>
    </div>

    <div class="grid gap-5 xl:grid-cols-[1.5fr_1fr]">
        <div class="min-w-0 space-y-8">
            <!-- Điện thoại/tablet: báo an toàn đặt lên đầu; desktop nằm ở cột phải -->
            <CheckinPanel v-if="trip.can_checkin" class="xl:hidden" :url="lr(`/hanh-trinh/${trip.code}/bao-an-toan`)" :alerts="matchAlerts" />
            <section aria-labelledby="tr-matches" class="space-y-3">
                <h2 id="tr-matches" class="section-title text-xl">{{ t('trip.matches', { n: matches.length }) }}</h2>
                <ul v-if="matches.length" class="space-y-3">
                    <li v-for="m in matches" :key="m.id">
                        <MatchItem :match="m" :can-acknowledge="trip.can_checkin || trip.can_edit" :processing="!!acking[m.id]" @acknowledge="acknowledge" />
                    </li>
                </ul>
                <EmptyState v-else :title="t('trip.no_matches')" :description="t('trip.no_matches_hint')" compact />
            </section>

            <section aria-labelledby="tr-legs" class="space-y-3">
                <h2 id="tr-legs" class="section-title text-xl">{{ t('trip.legs') }}</h2>
                <LegTimeline v-if="legs.length" :legs="legs" :show-alerts="false" />
                <EmptyState v-else :title="t('trip.no_legs')" compact />
            </section>

            <section aria-labelledby="tr-map" class="space-y-3">
                <h2 id="tr-map" class="section-title text-xl">{{ t('trip.map') }}</h2>
                <MapBase :geojson="zonesGeo" :markers="markers" :route="route" :overlays="overlays" :aria-label="t('trip.map')" />
            </section>

            <section v-if="weather.length" aria-labelledby="tr-weather" class="space-y-3">
                <h2 id="tr-weather" class="section-title text-xl">{{ t('trip.weather') }}</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <WeatherNowCard v-for="w in weather" :key="w.zone?.code" :weather="w" />
                </div>
            </section>
        </div>

        <div class="min-w-0 space-y-8">
            <CheckinPanel v-if="trip.can_checkin" class="hidden xl:block" :url="lr(`/hanh-trinh/${trip.code}/bao-an-toan`)" :alerts="matchAlerts" />

            <section aria-labelledby="tr-checkins" class="space-y-3">
                <h2 id="tr-checkins" class="section-title text-xl">{{ t('trip.checkins') }}</h2>
                <ul v-if="trip.checkins?.length" class="space-y-2">
                    <li v-for="c in trip.checkins" :key="c.id"><CheckinItem :checkin="c" /></li>
                </ul>
                <EmptyState v-else :title="t('trip.no_checkin')" compact />
            </section>

            <section class="clay-card-sm space-y-3 p-4 md:p-5" aria-labelledby="tr-people">
                <h2 id="tr-people" class="section-title text-lg">{{ t('trip.people_title') }}</h2>
                <dl class="grid gap-1 text-sm">
                    <div class="flex gap-2"><dt class="text-muted">{{ t('trip_form.contact_name') }}:</dt><dd class="font-semibold">{{ trip.contact_name || '—' }}</dd></div>
                    <div class="flex gap-2"><dt class="text-muted">{{ t('common.phone') }}:</dt><dd>{{ trip.contact_phone || '—' }}</dd></div>
                    <div v-if="trip.contact_email" class="flex gap-2"><dt class="text-muted">{{ t('common.email') }}:</dt><dd class="break-all">{{ trip.contact_email }}</dd></div>
                    <div v-if="trip.preferred_locale" class="flex gap-2"><dt class="text-muted">{{ t('auth.preferred_locale') }}:</dt><dd>{{ te('locale', trip.preferred_locale) }}</dd></div>
                </dl>
                <ul v-if="trip.members?.length" class="divide-y divide-line text-sm">
                    <li v-for="m in trip.members" :key="m.id" class="flex flex-wrap items-center gap-x-2 py-1.5">
                        <span class="font-semibold">{{ m.full_name }}</span>
                        <Badge v-if="m.is_leader" tone="brand" size="sm">{{ t('trip.leader') }}</Badge>
                        <span class="text-muted">{{ m.phone_masked }}</span>
                        <span class="text-xs text-muted">{{ te('age_group', m.age_group) }}<template v-if="m.special_needs"> · {{ m.special_needs }}</template></span>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted">{{ t('trip_form.no_members') }}</p>
            </section>

            <section aria-labelledby="tr-notifs" class="space-y-3">
                <h2 id="tr-notifs" class="section-title text-xl">{{ t('nav.notifications') }}</h2>
                <ul v-if="trip.notifications?.length" class="space-y-2">
                    <li v-for="n in trip.notifications" :key="n.id"><NotificationItem :notification="n" :processing="!!reading[n.id]" @read="markRead" /></li>
                </ul>
                <EmptyState v-else :title="t('notifications.empty')" compact />
            </section>
        </div>
    </div>
</template>
