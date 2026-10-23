<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import Badge from '@/Components/Badge.vue';
import Icon from '@/Components/Icon.vue';
import MapBase from '@/Components/MapBase.vue';
import EmptyState from '@/Components/EmptyState.vue';
import RefreshIndicator from '@/Components/RefreshIndicator.vue';
import NotificationItem from '@/Components/NotificationItem.vue';
import LegTimeline from '@/Components/Trip/LegTimeline.vue';
import MatchItem from '@/Components/Trip/MatchItem.vue';
import CheckinItem from '@/Components/Trip/CheckinItem.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { usePolling, DEFAULT_POLL_MS } from '@/Composables/usePolling';
import { normalizeLevel } from '@/Utils/levels';

/** Hành trình cho cơ quan hỗ trợ (TripDetail đầy đủ liên hệ): chặng, cảnh báo khớp, báo an toàn, thành viên, thông báo đã gửi. */
const props = defineProps({
    trip: { type: Object, required: true },
    zonesGeo: { type: Object, default: null },
});
const { t, te } = useT();
const { date } = useFormat();
const lr = useLocaleRoute();
const { refresh, isRefreshing, lastRefreshedAt } = usePolling(DEFAULT_POLL_MS, ['trip']);
const level = computed(() => normalizeLevel(props.trip.risk_level) ?? 0);
const legs = computed(() => [...(props.trip.legs || [])].sort((a, b) => a.leg_order - b.leg_order));
const legPoint = (leg) => ({ lat: leg.lat ?? leg.destination?.lat ?? leg.zone?.centroid?.lat, lng: leg.lng ?? leg.destination?.lng ?? leg.zone?.centroid?.lng });
const markers = computed(() => legs.value.map((l) => ({ id: l.id, ...legPoint(l), level: l.risk_level ?? 0, label: `${t('trip.leg_n', { n: l.leg_order })}: ${l.title || l.zone?.name || ''}` })));
const openHelp = computed(() => (props.trip.checkins || []).filter((c) => c.status === 'need_help' && c.response_status !== 'closed'));
const crumbs = computed(() => [{ label: t('admin.nav.trips'), href: lr('/quan-tri/hanh-trinh') }, { label: props.trip.code }]);
const telHref = (p) => `tel:${String(p || '').replace(/[^\d+]/g, '')}`;
</script>

<template>
    <Head :title="trip.code" />
    <PageHeader :breadcrumbs="crumbs" :eyebrow="trip.code" :title="trip.title">
        <template #actions>
            <RefreshIndicator :refreshing="isRefreshing" :last-refreshed-at="lastRefreshedAt" :interval-seconds="DEFAULT_POLL_MS / 1000" @refresh="refresh" />
        </template>
    </PageHeader>
    <div class="mb-6 flex flex-wrap items-center gap-2 text-sm">
        <Badge :level="level" show-level-number />
        <span class="pill">{{ te('status', trip.status) }}</span>
        <span class="pill"><Icon name="calendar" :size="14" />{{ date(trip.start_date) }} – {{ date(trip.end_date) }}</span>
        <span class="pill"><Icon name="users" :size="14" />{{ t('trip.people', { n: trip.group_size ?? 1 }) }}</span>
        <span v-if="trip.nationality" class="pill">{{ t('account.nationality') }}: {{ trip.nationality }}</span>
    </div>

    <div v-if="openHelp.length" class="mb-6 flex flex-wrap items-center gap-3 callout callout-4 p-4" role="alert">
        <Icon name="siren" :size="26" />
        <p class="font-bold">{{ t('admin.trips.open_help', { n: openHelp.length }) }}</p>
        <Link :href="lr(`/quan-tri/an-toan?trip=${trip.code}`)" class="clay-btn clay-btn-danger clay-btn-sm ml-auto">{{ t('admin.trips.respond') }}</Link>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.5fr_1fr]">
        <div class="min-w-0 space-y-8">
            <section aria-labelledby="at-matches" class="space-y-3">
                <h2 id="at-matches" class="section-title text-xl">{{ t('trip.matches', { n: trip.matches?.length || 0 }) }}</h2>
                <ul v-if="trip.matches?.length" class="space-y-3">
                    <li v-for="m in trip.matches" :key="m.id"><MatchItem :match="m" /></li>
                </ul>
                <EmptyState v-else :title="t('trip.no_matches')" compact />
            </section>
            <section aria-labelledby="at-legs" class="space-y-3">
                <h2 id="at-legs" class="section-title text-xl">{{ t('trip.legs') }}</h2>
                <LegTimeline v-if="legs.length" :legs="legs" :show-alerts="false" />
                <EmptyState v-else :title="t('trip.no_legs')" compact />
            </section>
            <MapBase :geojson="zonesGeo" :markers="markers" :route="legs.map(legPoint)" :aria-label="t('trip.map')" />
        </div>
        <div class="min-w-0 space-y-8">
            <section class="clay-card-sm space-y-3 p-4 md:p-5" aria-labelledby="at-contact">
                <h2 id="at-contact" class="section-title text-lg">{{ t('trip.people_title') }}</h2>
                <dl class="grid gap-1 text-sm">
                    <div class="flex flex-wrap gap-2"><dt class="text-muted">{{ t('admin.trips.owner') }}:</dt><dd class="font-semibold">{{ trip.owner?.name || '—' }}</dd></div>
                    <div v-if="trip.partner?.name" class="flex flex-wrap gap-2"><dt class="text-muted">{{ t('trip.partner') }}:</dt><dd>{{ trip.partner.name }}</dd></div>
                    <div class="flex flex-wrap gap-2"><dt class="text-muted">{{ t('trip_form.contact_name') }}:</dt><dd class="font-semibold">{{ trip.contact_name || '—' }}</dd></div>
                    <div class="flex flex-wrap gap-2"><dt class="text-muted">{{ t('common.phone') }}:</dt><dd><a v-if="trip.contact_phone" :href="telHref(trip.contact_phone)" class="link">{{ trip.contact_phone }}</a><span v-else>—</span></dd></div>
                    <div v-if="trip.contact_email" class="flex flex-wrap gap-2"><dt class="text-muted">{{ t('common.email') }}:</dt><dd class="break-all"><a :href="`mailto:${trip.contact_email}`" class="link">{{ trip.contact_email }}</a></dd></div>
                    <div v-if="trip.preferred_locale" class="flex flex-wrap gap-2"><dt class="text-muted">{{ t('auth.preferred_locale') }}:</dt><dd>{{ te('locale', trip.preferred_locale) }}</dd></div>
                </dl>
                <ul v-if="trip.members?.length" class="divide-y divide-line text-sm">
                    <li v-for="m in trip.members" :key="m.id" class="py-1.5">
                        <span class="font-semibold">{{ m.full_name }}</span>
                        <Badge v-if="m.is_leader" tone="brand" size="sm" class="ml-1">{{ t('trip.leader') }}</Badge>
                        <span class="block text-xs text-muted">{{ m.phone_masked }} · {{ m.nationality || '—' }} · {{ te('age_group', m.age_group) }}<template v-if="m.special_needs"> · <strong class="text-fg">{{ m.special_needs }}</strong></template></span>
                    </li>
                </ul>
            </section>
            <section aria-labelledby="at-checkins" class="space-y-3">
                <h2 id="at-checkins" class="section-title text-xl">{{ t('trip.checkins') }}</h2>
                <ul v-if="trip.checkins?.length" class="space-y-2">
                    <li v-for="c in trip.checkins" :key="c.id"><CheckinItem :checkin="c" /></li>
                </ul>
                <EmptyState v-else :title="t('trip.no_checkin')" compact />
            </section>
            <section aria-labelledby="at-notifs" class="space-y-3">
                <h2 id="at-notifs" class="section-title text-xl">{{ t('admin.trips.notifications') }}</h2>
                <ul v-if="trip.notifications?.length" class="space-y-2">
                    <li v-for="n in trip.notifications" :key="n.id"><NotificationItem :notification="n" :can-mark-read="false" /></li>
                </ul>
                <EmptyState v-else :title="t('notifications.empty')" compact />
            </section>
        </div>
    </div>
</template>
