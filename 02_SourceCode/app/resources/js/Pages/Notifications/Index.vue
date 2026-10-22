<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import NotificationItem from '@/Components/NotificationItem.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import SkeletonList from '@/Components/SkeletonList.vue';
import Button from '@/Components/Button.vue';
import RefreshIndicator from '@/Components/RefreshIndicator.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { usePolling, DEFAULT_POLL_MS } from '@/Composables/usePolling';
import { useVisitLoading } from '@/Composables/useVisitLoading';
import { rowsOf } from '@/Utils/options';

/**
 * Hộp thông báo: lọc chưa đọc (?unread=1), đánh dấu đã đọc từng mục / tất cả, hiển thị trạng thái gửi,
 * số lần thử, lịch thử lại và lỗi để người dùng hiểu vì sao thông báo đến muộn. Tự làm mới 30 giây.
 */
const props = defineProps({
    notifications: { type: Object, default: () => ({ data: [] }) },
    unread: { type: Number, default: 0 },
    filters: { type: Object, default: null },
});
const page = usePage();
const { t } = useT();
const lr = useLocaleRoute();
const { loading } = useVisitLoading();
const { refresh, isRefreshing, lastRefreshedAt } = usePolling(DEFAULT_POLL_MS, ['notifications', 'unread', 'auth']);

const rows = computed(() => rowsOf(props.notifications));
// Bộ lọc lấy từ prop nếu máy chủ trả, nếu không thì đọc query string
const onlyUnread = computed(() => {
    if (props.filters && 'unread' in props.filters) return ['1', 1, true, 'true'].includes(props.filters.unread);
    return new URLSearchParams((page.url || '').split('?')[1] || '').get('unread') === '1';
});
const reading = reactive({});
const readingAll = ref(false);

function markRead(n) {
    reading[n.id] = true;
    router.post(lr(`/thong-bao/${n.id}/da-doc`), {}, { preserveScroll: true, onFinish: () => (reading[n.id] = false) });
}
function markAll() {
    readingAll.value = true;
    router.post(lr('/thong-bao/doc-tat-ca'), {}, { preserveScroll: true, onFinish: () => (readingAll.value = false) });
}
const tabs = computed(() => [
    { key: 'all', label: t('notifications.tab_all'), href: lr('/thong-bao'), active: !onlyUnread.value },
    { key: 'unread', label: t('notifications.tab_unread', { n: props.unread }), href: lr('/thong-bao?unread=1'), active: onlyUnread.value },
]);
const STATUS_HELP = ['queued', 'sent', 'failed', 'read'];
</script>

<template>
    <Head :title="t('nav.notifications')" />
    <PageHeader :title="t('nav.notifications')" :description="t('notifications.intro')">
        <template #actions>
            <RefreshIndicator :refreshing="isRefreshing" :last-refreshed-at="lastRefreshedAt" :interval-seconds="DEFAULT_POLL_MS / 1000" @refresh="refresh" />
        </template>
    </PageHeader>

    <div class="mb-5 flex flex-wrap items-center gap-2">
        <nav :aria-label="t('notifications.filter')" class="flex flex-wrap gap-2">
            <Link
                v-for="tab in tabs"
                :key="tab.key"
                :href="tab.href"
                class="clay-btn clay-btn-sm"
                :class="tab.active ? 'clay-btn-primary' : ''"
                :aria-current="tab.active ? 'page' : undefined"
                preserve-scroll
            >{{ tab.label }}</Link>
        </nav>
        <Button v-if="unread > 0" size="sm" variant="ghost" icon="check" class="ml-auto" :loading="readingAll" @click="markAll">{{ t('notifications.read_all') }}</Button>
    </div>

    <SkeletonList v-if="loading" :count="4" :cols="1" height="h-16" />
    <ul v-else-if="rows.length" class="space-y-3">
        <li v-for="n in rows" :key="n.id"><NotificationItem :notification="n" :processing="!!reading[n.id]" @read="markRead" /></li>
    </ul>
    <EmptyState v-else :title="onlyUnread ? t('notifications.empty_unread') : t('notifications.empty')" :description="t('notifications.empty_hint')" />
    <Pagination :paginator="notifications" />

    <details class="mt-8 rounded-2xl border border-line bg-soft p-4 text-sm">
        <summary class="cursor-pointer font-semibold">{{ t('notifications.status_help_title') }}</summary>
        <dl class="mt-3 grid gap-2 sm:grid-cols-2">
            <div v-for="s in STATUS_HELP" :key="s"><dt class="font-semibold">{{ t(`enums.status.${s}`) }}</dt><dd class="text-muted">{{ t(`notifications.status_help.${s}`) }}</dd></div>
        </dl>
    </details>
</template>
