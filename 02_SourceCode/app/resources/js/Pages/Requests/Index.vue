<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import Button from '@/Components/Button.vue';
import Icon from '@/Components/Icon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import SkeletonList from '@/Components/SkeletonList.vue';
import Pagination from '@/Components/Pagination.vue';
import RefreshIndicator from '@/Components/RefreshIndicator.vue';
import RequestCard from '@/Components/Requests/RequestCard.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useVisitLoading } from '@/Composables/useVisitLoading';
import { usePolling, DEFAULT_POLL_MS } from '@/Composables/usePolling';
import { useImages } from '@/Composables/useImages';
import { rowsOf } from '@/Utils/options';

/**
 * "Yêu cầu của tôi" (requests.index): du khách theo dõi sự cố đã báo và lần check-in "cần hỗ trợ".
 * Hai tab, mỗi mục có dòng thời gian 3 bước; `highlight` (?moi=incident:12) mở đúng tab, cuộn tới và viền brand 3 giây.
 * Làm mới 30 giây/lần để thấy cơ quan cập nhật trạng thái.
 */
const props = defineProps({
    incidents: { type: [Array, Object], default: () => [] },
    checkins: { type: [Array, Object], default: () => [] },
    highlight: { type: Object, default: null },
    counts: { type: Object, default: () => ({}) },
});
const { t } = useT();
const lr = useLocaleRoute();
const { loading } = useVisitLoading();
const images = useImages();
const { refresh, isRefreshing, lastRefreshedAt } = usePolling(DEFAULT_POLL_MS, ['incidents', 'checkins', 'counts']);

const HIGHLIGHT_MS = 3000;
const TABS = ['incident', 'checkin'];

/** Hàng của hai danh sách (mảng hoặc Paginated). */
const incidentRows = computed(() => rowsOf(props.incidents));
const checkinRows = computed(() => rowsOf(props.checkins));
/** Tab đang mở: theo highlight, nếu không thì tab đầu có dữ liệu. */
const tab = ref(props.highlight?.type === 'checkin' || (!incidentRows.value.length && checkinRows.value.length) ? 'checkin' : 'incident');
/** Khóa "type-id" của mục đang được làm nổi bật (null khi hết 3 giây). */
const highlightedKey = ref(null);
let highlightTimer = null;

const rows = computed(() => (tab.value === 'incident' ? incidentRows.value : checkinRows.value));
const paginator = computed(() => (tab.value === 'incident' ? props.incidents : props.checkins));
const hasAny = computed(() => incidentRows.value.length + checkinRows.value.length > 0);
/** Ba chỉ số tổng: chờ tiếp nhận, đang xử lý, đã xử lý. */
const summary = computed(() => [
    { key: 'pending', icon: 'clock', value: Number(props.counts?.pending ?? 0) },
    { key: 'in_progress', icon: 'refresh', value: Number(props.counts?.in_progress ?? 0) },
    { key: 'done', icon: 'circle-check', value: Number(props.counts?.done ?? 0) },
]);
const tabCount = (key) => (key === 'incident' ? incidentRows.value.length : checkinRows.value.length);

/**
 * Chuyển tab bằng phím mũi tên trái/phải (mẫu WAI-ARIA tabs).
 * @param {KeyboardEvent} e sự kiện bàn phím trên nút tab
 * @returns {void}
 */
function onTabKey(e) {
    if (!['ArrowLeft', 'ArrowRight'].includes(e.key)) return;
    e.preventDefault();
    const next = TABS[(TABS.indexOf(tab.value) + (e.key === 'ArrowRight' ? 1 : TABS.length - 1)) % TABS.length];
    tab.value = next;
    nextTick(() => document.getElementById(`req-tab-${next}`)?.focus());
}

/**
 * Cuộn tới mục vừa gửi, đưa focus vào nó và viền brand trong 3 giây.
 * @returns {void}
 */
function focusHighlight() {
    const h = props.highlight;
    if (!h?.type || h.id === undefined || h.id === null) return;
    const key = `${h.type}-${h.id}`;
    const el = document.getElementById(`req-${key}`);
    if (!el) return;
    const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    el.scrollIntoView({ block: 'center', behavior: reduce ? 'auto' : 'smooth' });
    el.focus({ preventScroll: true });
    highlightedKey.value = key;
    highlightTimer = setTimeout(() => (highlightedKey.value = null), HIGHLIGHT_MS);
}

onMounted(() => nextTick(focusHighlight));
onBeforeUnmount(() => clearTimeout(highlightTimer));
</script>

<template>
    <Head :title="t('nav.requests')" />
    <PageHeader :eyebrow="t('nav.account')" :title="t('nav.requests')" :description="t('ui.requests.intro')">
        <template #actions>
            <RefreshIndicator :refreshing="isRefreshing" :last-refreshed-at="lastRefreshedAt" :interval-seconds="DEFAULT_POLL_MS / 1000" @refresh="refresh" />
            <Button :href="lr('/bao-su-co')" variant="primary" icon="megaphone">{{ t('nav.report_incident') }}</Button>
        </template>
    </PageHeader>

    <ul class="mb-5 grid grid-cols-3 gap-2" :aria-label="t('ui.requests.summary')">
        <li v-for="s in summary" :key="s.key" class="clay-card-sm flex min-w-0 flex-col gap-0.5 p-3">
            <span class="flex items-center gap-1.5 text-xs font-semibold text-muted"><Icon :name="s.icon" :size="14" /><span class="truncate">{{ t(`ui.requests.count.${s.key}`) }}</span></span>
            <span class="font-display text-xl font-extrabold">{{ s.value }}</span>
        </li>
    </ul>

    <div role="tablist" class="mb-4 flex flex-wrap gap-2" :aria-label="t('nav.requests')">
        <button
            v-for="key in TABS"
            :id="`req-tab-${key}`"
            :key="key"
            type="button"
            role="tab"
            class="clay-btn clay-btn-sm"
            :class="tab === key ? 'is-selected border-brand-400' : ''"
            :aria-selected="tab === key ? 'true' : 'false'"
            :aria-controls="tab === key ? 'req-panel' : undefined"
            :tabindex="tab === key ? 0 : -1"
            @click="tab = key"
            @keydown="onTabKey"
        >
            <Icon :name="key === 'incident' ? 'megaphone' : 'siren'" :size="16" />
            {{ t(`ui.requests.tab.${key}`) }}
            <span class="rounded-full bg-soft px-2 text-xs">{{ tabCount(key) }}</span>
        </button>
    </div>

    <section id="req-panel" role="tabpanel" :aria-labelledby="`req-tab-${tab}`">
        <SkeletonList v-if="loading" :count="3" :cols="1" height="h-16" />
        <template v-else-if="rows.length">
            <ul class="grid gap-3">
                <li v-for="r in rows" :key="r.id">
                    <RequestCard :item="r" :type="tab" :highlighted="highlightedKey === `${tab}-${r.id}`" />
                </li>
            </ul>
            <Pagination :paginator="Array.isArray(paginator) ? null : paginator" />
        </template>
        <EmptyState
            v-else
            :image="hasAny ? undefined : images.hero('home')"
            :title="t(`ui.requests.empty.${tab}`)"
            :description="t(`ui.requests.empty.${tab}_hint`)"
        >
            <Button :href="lr('/bao-su-co')" variant="primary" icon="megaphone">{{ t('nav.report_incident') }}</Button>
            <Button v-if="tab === 'checkin'" :href="lr('/hanh-trinh')" icon="route">{{ t('nav.trips') }}</Button>
        </EmptyState>
    </section>
</template>
