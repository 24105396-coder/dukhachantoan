<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import PageMeta from '@/Components/Public/PageMeta.vue';
import Button from '@/Components/Button.vue';
import KpiCard from '@/Components/KpiCard.vue';
import AlertCard from '@/Components/AlertCard.vue';
import MapBase from '@/Components/MapBase.vue';
import EmptyState from '@/Components/EmptyState.vue';
import WeatherNowCard from '@/Components/WeatherNowCard.vue';
import GuideCard from '@/Components/GuideCard.vue';
import Icon from '@/Components/Icon.vue';
import NationalRiskPanel from '@/Components/Home/NationalRiskPanel.vue';
import HotlineStrip from '@/Components/Home/HotlineStrip.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { usePermissions } from '@/Composables/usePermissions';

/** Trang chủ: mức rủi ro quốc gia, bản đồ vùng, cảnh báo nổi bật, thời tiết điểm du lịch, hướng dẫn. */
const props = defineProps({
    national: { type: Object, default: null },
    zonesGeo: { type: Object, default: null },
    topAlerts: { type: Array, default: () => [] },
    weatherHighlights: { type: Array, default: () => [] },
    guides: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});
const { t } = useT();
const { number } = useFormat();
const lr = useLocaleRoute();
const { user } = usePermissions();

const fmt = (v) => (v === null || v === undefined ? null : number(v));
const kpis = computed(() => [
    { key: 'alerts_active', icon: 'bell-ring', tone: 'peach', href: lr('/canh-bao') },
    { key: 'zones', icon: 'layers', tone: 'mint', href: lr('/ban-do') },
    { key: 'trips_active', icon: 'route', tone: 'sky', href: null },
    { key: 'contacts', icon: 'phone', tone: 'lemon', href: lr('/danh-ba-khan-cap') },
]);
const zoneCount = computed(() => props.zonesGeo?.features?.length || 0);
</script>

<template>
    <PageMeta :title="t('home.title')" fallback-key="home.subtitle" />

    <div class="container-page space-y-12 py-8 md:py-10">
        <!-- Hero -->
        <section class="grid items-start gap-6 lg:grid-cols-[1.2fr_1fr]" aria-labelledby="home-title">
            <div class="space-y-5 pt-2">
                <p class="eyebrow">{{ t('home.eyebrow') }}</p>
                <h1 id="home-title" class="text-[1.625rem] leading-tight sm:text-3xl lg:text-[2.5rem]">{{ t('home.title') }}</h1>
                <p class="max-w-xl text-lg text-muted">{{ t('home.subtitle') }}</p>
                <div class="flex flex-wrap gap-3">
                    <Button :href="lr('/canh-bao')" variant="primary" size="lg" icon="bell-ring">{{ t('home.cta_alerts') }}</Button>
                    <Button :href="user ? lr('/hanh-trinh/tao') : lr('/dang-ky')" size="lg" icon="route">{{ t('home.cta_trip') }}</Button>
                </div>
                <div class="space-y-2 pt-2">
                    <p class="text-sm font-semibold">{{ t('hotline.title') }}</p>
                    <HotlineStrip />
                </div>
            </div>
            <NationalRiskPanel :national="national">
                <div class="mt-5 grid grid-cols-2 gap-3">
                    <KpiCard
                        v-for="k in kpis"
                        :key="k.key"
                        :label="t(`home.stats.${k.key}`)"
                        :value="fmt(stats?.[k.key])"
                        :icon="k.icon"
                        :tone="k.tone"
                        :href="k.href"
                        class="!p-3 [&_.icon-tile]:hidden sm:[&_.icon-tile]:inline-flex"
                    />
                </div>
            </NationalRiskPanel>
        </section>

        <!-- Bản đồ rủi ro theo vùng -->
        <section aria-labelledby="home-map-title" class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="home-map-title" class="section-title">{{ t('home.map_title') }}</h2>
                    <p class="mt-1 text-sm text-muted">{{ t('home.map_intro', { n: zoneCount }) }}</p>
                </div>
                <Link :href="lr('/ban-do')" class="link inline-flex items-center gap-1">{{ t('home.open_map') }}<Icon name="arrow-right" :size="16" /></Link>
            </div>
            <MapBase :geojson="zonesGeo" :aria-label="t('home.map_title')" />
        </section>

        <!-- Cảnh báo nổi bật -->
        <section aria-labelledby="home-alerts-title" class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 id="home-alerts-title" class="section-title">{{ t('home.top_alerts') }}</h2>
                <Link :href="lr('/canh-bao')" class="link inline-flex items-center gap-1">{{ t('common.view_all') }}<Icon name="arrow-right" :size="16" /></Link>
            </div>
            <div v-if="topAlerts.length" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <AlertCard v-for="a in topAlerts" :key="a.code || a.id" :alert="a" />
            </div>
            <EmptyState v-else :title="t('home.no_alerts')" :description="t('home.no_alerts_hint')" />
        </section>

        <!-- Thời tiết điểm du lịch -->
        <section aria-labelledby="home-weather-title" class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 id="home-weather-title" class="section-title">{{ t('home.weather') }}</h2>
                <Link :href="lr('/thoi-tiet')" class="link inline-flex items-center gap-1">{{ t('home.open_weather') }}<Icon name="arrow-right" :size="16" /></Link>
            </div>
            <div v-if="weatherHighlights.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <WeatherNowCard v-for="w in weatherHighlights" :key="w.zone?.code" :weather="w" />
            </div>
            <EmptyState v-else :title="t('home.no_weather')" :description="t('home.no_weather_hint')" compact />
        </section>

        <!-- Hướng dẫn ứng phó -->
        <section aria-labelledby="home-guides-title" class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 id="home-guides-title" class="section-title">{{ t('home.guides') }}</h2>
                <Link :href="lr('/huong-dan-ung-pho')" class="link inline-flex items-center gap-1">{{ t('common.view_all') }}<Icon name="arrow-right" :size="16" /></Link>
            </div>
            <div v-if="guides.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <GuideCard v-for="g in guides" :key="g.slug || g.id" :guide="g" />
            </div>
            <EmptyState v-else :title="t('home.no_guides')" compact />
        </section>

        <!-- Kêu gọi đăng ký hành trình -->
        <section class="callout callout-brand grid items-center gap-6 p-6 md:grid-cols-[1fr_auto] md:p-8" aria-labelledby="home-cta-title">
            <div>
                <h2 id="home-cta-title" class="text-xl md:text-2xl">{{ t('home.trip_cta_title') }}</h2>
                <p class="mt-2 max-w-2xl text-muted">{{ t('home.trip_cta_text') }}</p>
            </div>
            <Button :href="user ? lr('/hanh-trinh/tao') : lr('/dang-ky')" variant="primary" size="lg" icon-right="arrow-right">
                {{ user ? t('home.trip_cta_button_user') : t('home.trip_cta_button_guest') }}
            </Button>
        </section>
    </div>
</template>
