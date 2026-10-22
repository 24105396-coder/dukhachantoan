<script setup>
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

/** Thẻ dự báo một ngày (Resource ForecastDay) dùng trong dải cuộn ngang 7 ngày. */
defineProps({
    day: { type: Object, required: true },
});
const { t } = useT();
const { weekday, temp, mm, kph, percent } = useFormat();
</script>

<template>
    <article class="clay-card-sm flex w-40 shrink-0 snap-start flex-col gap-2 p-4">
        <h3 class="text-sm font-bold"><time :datetime="day.date">{{ weekday(day.date) }}</time></h3>
        <div class="flex items-center gap-2">
            <span class="icon-tile h-10 w-10"><Icon :name="day.icon || 'cloud-sun'" fallback="cloud-sun" :size="22" /></span>
            <p class="text-xs text-muted">{{ day.weather_label }}</p>
        </div>
        <p class="font-display text-xl font-bold leading-none">
            {{ temp(day.tmax_c) }} <span class="text-sm font-semibold text-muted">/ {{ temp(day.tmin_c) }}</span>
        </p>
        <dl class="grid gap-0.5 text-xs">
            <div class="flex justify-between gap-2"><dt class="text-muted">{{ t('weather.rain') }}</dt><dd class="font-semibold">{{ mm(day.rain_mm) }}</dd></div>
            <div v-if="day.rain_prob != null" class="flex justify-between gap-2"><dt class="text-muted">{{ t('weather.rain_prob') }}</dt><dd class="font-semibold">{{ percent(day.rain_prob) }}</dd></div>
            <div class="flex justify-between gap-2"><dt class="text-muted">{{ t('weather.wind_max') }}</dt><dd class="font-semibold">{{ kph(day.wind_max_kph) }}</dd></div>
        </dl>
    </article>
</template>
