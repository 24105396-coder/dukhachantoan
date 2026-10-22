<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import Pagination from '@/Components/Pagination.vue';
import Icon from '@/Components/Icon.vue';
import RefreshIndicator from '@/Components/RefreshIndicator.vue';
import CheckinResponseForm from '@/Components/Admin/CheckinResponseForm.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { usePolling, DEFAULT_POLL_MS } from '@/Composables/usePolling';
import { useVisitLoading } from '@/Composables/useVisitLoading';
import { enumOptions, rowsOf } from '@/Utils/options';

/** Báo an toàn của du khách: "Cần hỗ trợ" nổi bật, phản hồi inline (trạng thái xử lý + ghi chú). Tự làm mới 30 giây. */
const props = defineProps({
    checkins: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    provinces: { type: Array, default: () => [] },
});
const { t, te } = useT();
const { dateTime, ago, number } = useFormat();
const lr = useLocaleRoute();
const { loading } = useVisitLoading();
const { refresh, isRefreshing, lastRefreshedAt } = usePolling(DEFAULT_POLL_MS, ['checkins']);

const fields = computed(() => [
    { name: 'status', type: 'select', label: t('admin.checkins.status'), options: ['need_help', 'safe', 'unknown'].map((s) => ({ value: s, label: te('checkin_status', s) })) },
    { name: 'response_status', type: 'select', label: t('admin.checkins.response_status'), options: ['open', 'in_progress', 'closed'].map((s) => ({ value: s, label: te('status', s) })) },
    ...(props.provinces.length ? [{ name: 'province', type: 'select', label: t('common.province'), options: enumOptions(props.provinces) }] : []),
]);
const columns = computed(() => [
    { key: 'status', label: t('admin.checkins.status') },
    { key: 'who', label: t('admin.checkins.who') },
    { key: 'message', label: t('checkin.message') },
    { key: 'response', label: t('admin.checkins.response') },
]);
const ROW_TONE = { need_help: 'bg-level-4-soft border-level-4', safe: 'bg-level-0-soft border-level-0', unknown: 'bg-soft border-line' };
const STATUS_ICON = { need_help: 'siren', safe: 'shield-check', unknown: 'help' };
const mapHref = (c) => `https://www.openstreetmap.org/?mlat=${c.lat}&mlon=${c.lng}#map=15/${c.lat}/${c.lng}`;
</script>

<template>
    <Head :title="t('admin.nav.checkins')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.nav.checkins')" :description="t('admin.checkins.intro')">
        <template #actions>
            <RefreshIndicator :refreshing="isRefreshing" :last-refreshed-at="lastRefreshedAt" :interval-seconds="DEFAULT_POLL_MS / 1000" @refresh="refresh" />
        </template>
    </PageHeader>
    <FilterBar :filters="filters" :fields="fields" class="mb-4" />
    <DataTable :columns="columns" :rows="rowsOf(checkins)" :loading="loading" :caption="t('admin.nav.checkins')" :empty-text="t('admin.checkins.empty')">
        <template #cell-status="{ row }">
            <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-2.5 py-1 text-xs font-bold" :class="ROW_TONE[row.status] || ROW_TONE.unknown">
                <Icon :name="STATUS_ICON[row.status] || 'help'" :size="14" />{{ te('checkin_status', row.status) }}
            </span>
            <time class="mt-1 block whitespace-nowrap text-xs text-muted" :datetime="row.checked_at" :title="dateTime(row.checked_at)">{{ ago(row.checked_at) }}</time>
        </template>
        <template #cell-who="{ row }">
            <span class="font-semibold">{{ row.user?.name || t('dashboard.anonymous') }}</span>
            <Link v-if="row.trip?.code" :href="lr(`/quan-tri/hanh-trinh/${row.trip.code}`)" class="link block font-mono text-xs">{{ row.trip.code }}</Link>
            <span class="block text-xs text-muted">{{ te('checkin_source', row.source) }}<template v-if="row.alert"> · {{ row.alert.code }}</template></span>
        </template>
        <template #cell-message="{ row }">
            <p class="max-w-xs break-words">{{ row.message || '—' }}</p>
            <a v-if="row.lat != null && row.lng != null" :href="mapHref(row)" target="_blank" rel="noopener noreferrer" class="link text-xs">
                <Icon name="map-pin" :size="12" class="inline align-[-1px]" /> {{ number(row.lat, 4) }}, {{ number(row.lng, 4) }}
            </a>
        </template>
        <template #cell-response="{ row }">
            <p v-if="row.responder?.name" class="mb-1 text-xs text-muted">{{ t('admin.checkins.last_response', { name: row.responder.name, time: dateTime(row.responded_at) }) }}</p>
            <CheckinResponseForm :checkin="row" />
        </template>
    </DataTable>
    <Pagination :paginator="checkins" />
</template>
