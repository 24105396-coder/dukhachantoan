<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import PageMeta from '@/Components/Public/PageMeta.vue';
import PageHeader from '@/Components/PageHeader.vue';
import WeatherNowCard from '@/Components/WeatherNowCard.vue';
import ForecastDay from '@/Components/ForecastDay.vue';
import RiskScore from '@/Components/RiskScore.vue';
import EmptyState from '@/Components/EmptyState.vue';
import SkeletonList from '@/Components/SkeletonList.vue';
import Button from '@/Components/Button.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useVisitLoading } from '@/Composables/useVisitLoading';

/** Thời tiết theo vùng: chọn vùng, thời tiết hiện tại, dự báo 7 ngày (thẻ ngang cuộn), điểm rủi ro + yếu tố. */
const props = defineProps({
    zone: { type: Object, default: null },
    now: { type: Object, default: null },
    forecast: { type: Array, default: () => [] },
    risk: { type: Object, default: null },
    zones: { type: Array, default: () => [] },
});
const { t } = useT();
const lr = useLocaleRoute();
const { loading } = useVisitLoading();
const picked = ref(props.zone?.code || '');
watch(() => props.zone?.code, (c) => (picked.value = c || ''));

function go() {
    if (picked.value && picked.value !== props.zone?.code) router.get(lr(`/thoi-tiet/${picked.value}`), {}, { preserveScroll: true });
}
const riskWithTime = computed(() => (props.risk ? { computed_at: props.zone?.risk?.computed_at, ...props.risk } : null));
const crumbs = computed(() => [{ label: t('nav.home'), href: lr('/') }, { label: t('nav.weather') }]);
</script>

<template>
    <PageMeta :title="zone ? t('weather.title_zone', { zone: zone.name }) : t('nav.weather')" fallback-key="weather.intro" />
    <div class="container-page space-y-6 py-8 md:py-10">
        <PageHeader :breadcrumbs="crumbs" :eyebrow="zone?.province?.name || t('nav.weather')" :title="zone ? t('weather.title_zone', { zone: zone.name }) : t('nav.weather')" :description="t('weather.intro')">
            <template #actions>
                <form class="flex flex-wrap items-end gap-2" @submit.prevent="go">
                    <div>
                        <label for="weather-zone" class="field-label">{{ t('weather.choose_zone') }}</label>
                        <select id="weather-zone" v-model="picked" class="clay-input min-w-56">
                            <option v-for="z in zones" :key="z.code" :value="z.code">{{ z.name }}</option>
                        </select>
                    </div>
                    <Button type="submit" variant="primary" icon="cloud-sun">{{ t('weather.view') }}</Button>
                </form>
            </template>
        </PageHeader>

        <SkeletonList v-if="loading" :count="2" :cols="2" />
        <template v-else-if="zone">
            <div class="grid gap-4 lg:grid-cols-2">
                <WeatherNowCard v-if="now" :weather="now" />
                <EmptyState v-else :title="t('home.no_weather')" :description="t('home.no_weather_hint')" compact />
                <RiskScore :risk="riskWithTime" />
            </div>
            <p v-if="zone.active_alerts_count" class="flex flex-wrap items-center gap-2 callout callout-3 p-3 text-sm font-semibold" role="status">
                <Icon name="triangle-alert" :size="18" />{{ t('weather.zone_alerts', { n: zone.active_alerts_count }) }}
                <Button :href="lr(`/canh-bao?province=${zone.province?.code || ''}`)" size="sm" class="ml-auto">{{ t('home.cta_alerts') }}</Button>
            </p>

            <section aria-labelledby="fc-title" class="space-y-3">
                <h2 id="fc-title" class="section-title text-xl">{{ t('weather.forecast_7') }}</h2>
                <ul v-if="forecast.length" class="flex snap-x gap-3 overflow-x-auto pb-3" tabindex="0" :aria-label="t('weather.forecast_7')">
                    <li v-for="d in forecast" :key="d.date"><ForecastDay :day="d" /></li>
                </ul>
                <EmptyState v-else :title="t('weather.no_forecast')" :description="t('home.no_weather_hint')" compact />
                <p class="text-xs text-muted">{{ t('footer.data_credit') }}</p>
            </section>
        </template>
        <EmptyState v-else :title="t('weather.no_zone')" :description="t('weather.no_zone_hint')" />
    </div>
</template>
