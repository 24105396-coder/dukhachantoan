<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import SourceStamp from '@/Components/SourceStamp.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

/** Thẻ thời tiết hiện tại theo Resource WeatherNow (nguồn Open-Meteo, hiển thị giờ lấy dữ liệu). */
const props = defineProps({
    weather: { type: Object, required: true },
});
const { t } = useT();
const { temp, kph, mm, number } = useFormat();
const lr = useLocaleRoute();
const href = computed(() => (props.weather.zone?.code ? lr(`/thoi-tiet/${props.weather.zone.code}`) : null));
</script>

<template>
    <article class="clay-card-sm flex h-full flex-col gap-3 bg-sky-100 p-5">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h3 class="font-display text-lg font-bold">
                    <Link v-if="href" :href="href" class="hover:text-link hover:underline">{{ weather.zone?.name }}</Link>
                    <span v-else>{{ weather.zone?.name }}</span>
                </h3>
                <p class="text-sm text-muted">{{ weather.weather_label }}</p>
            </div>
            <span class="icon-tile"><Icon :name="weather.icon || 'cloud-sun'" :size="24" fallback="cloud-sun" /></span>
        </div>
        <p class="font-display text-[2rem] font-bold leading-none">{{ temp(weather.temp_c) }}</p>
        <dl class="grid grid-cols-3 gap-2 text-xs">
            <div><dt class="text-muted">{{ t('weather.feels_like') }}</dt><dd class="font-semibold">{{ temp(weather.apparent_c) }}</dd></div>
            <div><dt class="text-muted">{{ t('weather.wind') }}</dt><dd class="font-semibold">{{ kph(weather.wind_kph) }}</dd></div>
            <div><dt class="text-muted">{{ t('weather.rain') }}</dt><dd class="font-semibold">{{ mm(weather.rain_mm) }}</dd></div>
            <div v-if="weather.gust_kph != null"><dt class="text-muted">{{ t('weather.gust') }}</dt><dd class="font-semibold">{{ kph(weather.gust_kph) }}</dd></div>
            <div v-if="weather.humidity != null"><dt class="text-muted">{{ t('weather.humidity') }}</dt><dd class="font-semibold">{{ number(weather.humidity) }}%</dd></div>
        </dl>
        <div class="mt-auto border-t border-line pt-2">
            <SourceStamp :source="weather.source || 'Open-Meteo'" :updated-at="weather.fetched_at || weather.observed_at" />
        </div>
    </article>
</template>
