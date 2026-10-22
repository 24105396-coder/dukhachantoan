<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import DataTable from '@/Components/DataTable.vue';
import Badge from '@/Components/Badge.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

/** Hành trình nằm trong vùng có cảnh báo (TripCard) – bảng ưu tiên theo mức rủi ro. */
defineProps({
    trips: { type: Array, default: () => [] },
});
const { t, te } = useT();
const { date, ago } = useFormat();
const columns = computed(() => [
    { key: 'code', label: t('trip.code') },
    { key: 'risk_level', label: t('trip.risk_level') },
    { key: 'dates', label: t('trip.dates') },
    { key: 'group_size', label: t('trip.group_size'), class: 'text-right' },
    { key: 'active_alerts_count', label: t('trip.alerts'), class: 'text-right' },
    { key: 'last_checkin', label: t('trip.last_checkin') },
]);
</script>

<template>
    <DataTable :columns="columns" :rows="trips" :caption="t('dashboard.affected_trips_title')" :empty-text="t('dashboard.affected_trips_empty')">
        <template #cell-code="{ row }">
            <Link :href="row.url || '#'" class="link">{{ row.code }}</Link>
            <span class="block max-w-[14rem] truncate text-xs text-muted">{{ row.title }}</span>
        </template>
        <template #cell-risk_level="{ row }"><Badge :level="row.risk_level ?? 0" size="sm" /></template>
        <template #cell-dates="{ row }"><span class="whitespace-nowrap">{{ date(row.start_date) }} – {{ date(row.end_date) }}</span></template>
        <template #cell-last_checkin="{ row }">
            <span v-if="row.last_checkin" class="whitespace-nowrap">{{ te('checkin_status', row.last_checkin.status) }} · {{ ago(row.last_checkin.checked_at) }}</span>
            <span v-else class="text-muted">{{ t('trip.no_checkin') }}</span>
        </template>
    </DataTable>
</template>
