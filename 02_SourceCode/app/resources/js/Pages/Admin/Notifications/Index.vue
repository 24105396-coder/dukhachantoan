<script setup>
import { computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import ExportButton from '@/Components/Admin/ExportButton.vue';
import FilterBar from '@/Components/FilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import Pagination from '@/Components/Pagination.vue';
import KpiCard from '@/Components/KpiCard.vue';
import Badge from '@/Components/Badge.vue';
import Button from '@/Components/Button.vue';
import Icon from '@/Components/Icon.vue';
import RefreshIndicator from '@/Components/RefreshIndicator.vue';
import BroadcastForm from '@/Components/Admin/BroadcastForm.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { usePolling, DEFAULT_POLL_MS } from '@/Composables/usePolling';
import { useVisitLoading } from '@/Composables/useVisitLoading';
import { rowsOf, CHANNELS, ALERT_LEVELS } from '@/Utils/options';

/**
 * Hàng đợi thông báo: thống kê, bảng (kênh, mức, trạng thái, số lần thử, lịch thử lại, lỗi cuối), nút Gửi lại
 * cho mục thất bại, form phát thông báo thủ công theo vùng/mức/kênh. Tự làm mới 30 giây.
 */
const props = defineProps({
    notifications: { type: Object, default: () => ({ data: [] }) },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    zones: { type: Array, default: () => [] },
});
const { t, te } = useT();
const { dateTime, ago, number, percent } = useFormat();
const lr = useLocaleRoute();
const { loading } = useVisitLoading();
const { refresh, isRefreshing, lastRefreshedAt } = usePolling(DEFAULT_POLL_MS, ['notifications', 'stats']);
const STATUSES = ['queued', 'sending', 'sent', 'failed', 'read', 'cancelled'];
const KPIS = [
    { key: 'queued', icon: 'clock', tone: 'sky' },
    { key: 'sending', icon: 'send', tone: 'mint' },
    { key: 'sent', icon: 'check', tone: 'mint' },
    { key: 'failed', icon: 'circle-x', tone: 'peach' },
    { key: 'read', icon: 'eye', tone: 'lemon' },
];
const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword'), placeholder: t('admin.queue.search_placeholder') },
    { name: 'status', type: 'select', label: t('common.status'), options: STATUSES.map((s) => ({ value: s, label: te('status', s) })) },
    { name: 'channel', type: 'select', label: t('admin.queue.channel'), options: CHANNELS.map((c) => ({ value: c, label: te('channel', c) })) },
    { name: 'level', type: 'select', label: t('alerts.severity'), options: ALERT_LEVELS.map((l) => ({ value: l, label: t(`enums.levels.${l}`) })) },
]);
const columns = computed(() => [
    { key: 'created_at', label: t('common.created_at') },
    { key: 'recipient', label: t('admin.queue.recipient') },
    { key: 'title', label: t('admin.common.title') },
    { key: 'status', label: t('common.status') },
    { key: 'attempts', label: t('admin.queue.attempts') },
]);
const STATUS_TONE = { failed: 'border-level-4 bg-level-4-soft', sent: 'border-level-0', read: 'border-line', queued: 'border-level-1', sending: 'border-level-1' };
const resending = reactive({});
function resend(n) {
    resending[n.id] = true;
    router.post(lr('/quan-tri/thong-bao/gui-lai'), { id: n.id }, { preserveScroll: true, only: ['notifications', 'stats', 'flash'], onFinish: () => (resending[n.id] = false) });
}
</script>

<template>
    <Head :title="t('admin.nav.notifications')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.nav.notifications')" :description="t('admin.queue.intro')">
        <template #actions>
            <ExportButton dataset="thong-bao" />
            <RefreshIndicator :refreshing="isRefreshing" :last-refreshed-at="lastRefreshedAt" :interval-seconds="DEFAULT_POLL_MS / 1000" @refresh="refresh" />
        </template>
    </PageHeader>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5" :aria-label="t('dashboard.kpis')">
        <KpiCard v-for="k in KPIS" :key="k.key" :label="te('status', k.key)" :value="number(stats?.[k.key] ?? 0)" :icon="k.icon" :tone="k.tone" class="!p-3 [&_.icon-tile]:hidden sm:[&_.icon-tile]:inline-flex" />
    </section>
    <p class="mt-2 flex flex-wrap gap-x-4 text-xs text-muted">
        <span v-if="stats?.delivery_success_rate != null">{{ t('dashboard.delivery_rate') }}: <strong class="text-fg">{{ percent(stats.delivery_success_rate, 1) }}</strong></span>
        <span v-if="stats?.oldest_queued_at">{{ t('dashboard.queue_oldest') }}: <strong class="text-fg">{{ ago(stats.oldest_queued_at) }}</strong></span>
        <span>{{ t('admin.queue.retry_policy') }}</span>
    </p>

    <details class="clay-card-sm mt-6 p-4 md:p-5" :open="false">
        <summary class="cursor-pointer font-display text-lg font-bold"><Icon name="megaphone" :size="20" class="inline align-[-3px]" /> {{ t('admin.broadcast.title') }}</summary>
        <p class="mt-1 mb-4 text-sm text-muted">{{ t('admin.broadcast.intro') }}</p>
        <BroadcastForm :zones="zones" />
    </details>

    <h2 class="section-title mt-8 mb-3 text-xl">{{ t('admin.queue.table') }}</h2>
    <FilterBar :filters="filters" :fields="fields" class="mb-4" />
    <DataTable :columns="columns" :rows="rowsOf(notifications)" :loading="loading" :caption="t('admin.queue.table')" :empty-text="t('notifications.empty')">
        <template #cell-created_at="{ row }"><time class="whitespace-nowrap text-xs" :datetime="row.created_at">{{ dateTime(row.created_at) }}</time></template>
        <template #cell-recipient="{ row }">
            <span class="font-semibold">{{ row.user?.name || '—' }}</span>
            <span class="mt-0.5 flex flex-wrap gap-1 text-xs"><span class="pill py-0 text-[0.6875rem]">{{ te('channel', row.channel) }}</span><Badge v-if="row.level" :level="row.level" size="sm" /></span>
        </template>
        <template #cell-title="{ row }">
            <p class="max-w-xs font-semibold">{{ row.title }}</p>
            <p class="text-xs text-muted">
                <template v-if="row.alert">{{ row.alert.code }}</template>
                <template v-if="row.trip"> · {{ row.trip.code }}</template>
            </p>
        </template>
        <template #cell-status="{ row }">
            <span class="pill whitespace-nowrap text-xs" :class="STATUS_TONE[row.status]">{{ te('status', row.status) }}</span>
            <span v-if="row.sent_at" class="block text-xs text-muted">{{ dateTime(row.sent_at) }}</span>
        </template>
        <template #cell-attempts="{ row }">
            <span class="font-semibold">{{ row.attempts ?? 0 }}</span>
            <span v-if="row.next_attempt_at && ['queued', 'failed'].includes(row.status)" class="block whitespace-nowrap text-xs text-muted">{{ t('notification.next_attempt', { time: dateTime(row.next_attempt_at) }) }}</span>
            <span v-if="row.last_error" class="block max-w-[14rem] break-words text-xs text-danger">{{ row.last_error }}</span>
        </template>
        <template #actions="{ row }">
            <Button v-if="row.status === 'failed'" size="sm" icon="repeat" :loading="!!resending[row.id]" @click="resend(row)">{{ t('admin.queue.resend') }}</Button>
        </template>
    </DataTable>
    <Pagination :paginator="notifications" />
</template>
