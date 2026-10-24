<script setup>
import { computed } from 'vue';
import KpiCard from '@/Components/KpiCard.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

/** 12 thẻ KPI nhỏ của trang Báo cáo theo `summary` (ReportService). Giá trị thiếu hiển thị "—". */
const props = defineProps({
    summary: { type: Object, default: () => ({}) },
});
const { t } = useT();
const { number } = useFormat();

const CARDS = [
    { key: 'alerts_live', icon: 'bell-ring' },
    { key: 'alerts_total', icon: 'list' },
    { key: 'real_alerts', icon: 'radio' },
    { key: 'simulated_alerts', icon: 'flask' },
    { key: 'trips_total', icon: 'route' },
    { key: 'trips_affected', icon: 'triangle-alert' },
    { key: 'notifications_sent', icon: 'send' },
    { key: 'notifications_failed', icon: 'circle-x' },
    { key: 'delivery_rate_pct', icon: 'gauge', percent: true },
    { key: 'checkins_need_help_open', icon: 'siren' },
    { key: 'incidents_pending', icon: 'shield-alert' },
    { key: 'contacts_verified', icon: 'phone' },
];

/**
 * Định dạng một chỉ số: số nguyên theo locale, hoặc phần trăm 1 chữ số thập phân.
 * @param {{key: string, percent?: boolean}} card thẻ
 * @returns {string|null} chuỗi hiển thị hoặc null khi thiếu
 */
function display(card) {
    const v = props.summary?.[card.key];
    if (v === null || v === undefined || v === '') return null;
    return card.percent ? `${number(v, 1)}%` : number(v);
}
const cards = computed(() => CARDS.map((c) => ({ ...c, value: display(c) })));
</script>

<template>
    <ul class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 xl:grid-cols-4" :aria-label="t('ui.reports.kpis')">
        <li v-for="c in cards" :key="c.key">
            <KpiCard
                :label="t(`ui.reports.kpi.${c.key}`)"
                :value="c.value"
                :icon="c.icon"
                class="h-full !gap-3 !p-3 [&_.icon-tile]:hidden sm:[&_.icon-tile]:inline-flex"
            />
        </li>
    </ul>
</template>
