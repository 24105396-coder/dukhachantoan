<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import PageMeta from '@/Components/Public/PageMeta.vue';
import Badge from '@/Components/Badge.vue';
import Button from '@/Components/Button.vue';
import Icon from '@/Components/Icon.vue';
import AlertCard from '@/Components/AlertCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import LegTimeline from '@/Components/Trip/LegTimeline.vue';
import CheckinItem from '@/Components/Trip/CheckinItem.vue';
import HotlineStrip from '@/Components/Home/HotlineStrip.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { normalizeLevel } from '@/Utils/levels';

/**
 * Trang người thân xem hành trình qua liên kết chia sẻ (token): không hiển thị liên hệ/thành viên,
 * chỉ chặng, mức rủi ro, 3 lần báo an toàn gần nhất và cảnh báo đang ảnh hưởng.
 */
const props = defineProps({
    trip: { type: Object, required: true },
    checkins: { type: Array, default: () => [] },
    alerts: { type: Array, default: () => [] },
});
const page = usePage();
const { t, te } = useT();
const { date } = useFormat();
const level = computed(() => normalizeLevel(props.trip.risk_level) ?? 0);
const basePath = computed(() => (page.url || '').split('?')[0].replace(/\/$/, ''));
const checkinHref = computed(() => `${basePath.value}/bao-an-toan`);
</script>

<template>
    <PageMeta :title="t('shared.title', { title: trip.title })" fallback-key="shared.intro">
        <meta head-key="robots" name="robots" content="noindex, nofollow" />
    </PageMeta>
    <div class="container-page space-y-6 py-8 md:py-10">
        <header class="clay-card space-y-3 p-5 md:p-7">
            <p class="eyebrow">{{ t('shared.eyebrow') }}</p>
            <h1>{{ trip.title }}</h1>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <Badge :level="level" show-level-number />
                <span class="pill">{{ te('status', trip.status) }}</span>
                <span class="pill"><Icon name="calendar" :size="14" />{{ date(trip.start_date) }} – {{ date(trip.end_date) }}</span>
                <span class="pill"><Icon name="users" :size="14" />{{ t('trip.people', { n: trip.group_size ?? 1 }) }}</span>
                <span v-if="trip.partner?.name" class="pill"><Icon name="building" :size="14" />{{ trip.partner.name }}</span>
            </div>
            <p class="text-sm text-muted">{{ t('shared.privacy') }}</p>
            <div class="flex flex-wrap gap-2 pt-1">
                <Button :href="checkinHref" variant="primary" size="lg" icon="shield-check">{{ t('shared.report_safety') }}</Button>
            </div>
        </header>

        <div class="grid gap-6 lg:grid-cols-[1.3fr_1fr]">
            <section aria-labelledby="sh-legs" class="space-y-3">
                <h2 id="sh-legs" class="section-title text-xl">{{ t('trip.legs') }}</h2>
                <LegTimeline v-if="trip.legs?.length" :legs="trip.legs" />
                <EmptyState v-else :title="t('trip.no_legs')" compact />
            </section>
            <div class="space-y-6">
                <section aria-labelledby="sh-checkins" class="space-y-3">
                    <h2 id="sh-checkins" class="section-title text-xl">{{ t('shared.latest_checkins') }}</h2>
                    <ul v-if="checkins.length" class="space-y-2">
                        <li v-for="c in checkins" :key="c.id"><CheckinItem :checkin="c" :show-response="false" /></li>
                    </ul>
                    <EmptyState v-else :title="t('trip.no_checkin')" :description="t('shared.no_checkins_hint')" compact />
                </section>
                <section aria-labelledby="sh-alerts" class="space-y-3">
                    <h2 id="sh-alerts" class="section-title text-xl">{{ t('shared.alerts') }}</h2>
                    <ul v-if="alerts.length" class="space-y-3">
                        <li v-for="a in alerts" :key="a.code"><AlertCard :alert="a" compact /></li>
                    </ul>
                    <EmptyState v-else :title="t('shared.no_alerts')" compact />
                </section>
                <section class="clay-card-sm space-y-2 p-4" aria-labelledby="sh-hot">
                    <h2 id="sh-hot" class="text-base font-bold">{{ t('hotline.title') }}</h2>
                    <HotlineStrip />
                </section>
            </div>
        </div>
    </div>
</template>
