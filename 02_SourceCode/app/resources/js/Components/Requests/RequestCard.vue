<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import RequestTimeline from '@/Components/Requests/RequestTimeline.vue';
import { requestState } from '@/Components/Requests/requestStatus';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

/**
 * Một yêu cầu đã gửi (Resource RequestItem): sự cố đã báo (`type="incident"`) hoặc check-in "cần hỗ trợ" (`type="checkin"`).
 * Khối `.callout` sọc trái theo trạng thái, nhãn trạng thái (chữ + icon), dòng thời gian 3 bước, ghi chú phản hồi của cơ quan.
 * `highlighted` = mục vừa gửi (?moi=) – viền brand.
 */
const props = defineProps({
    item: { type: Object, required: true },
    type: { type: String, required: true }, // incident | checkin
    highlighted: { type: Boolean, default: false },
});
const { t, te } = useT();
const { dateTime } = useFormat();

/** Bước + nhãn + icon + lớp viền theo trạng thái. */
const state = computed(() => requestState(props.type, props.item));
const isIncident = computed(() => props.type === 'incident');
/** Mốc giờ của 3 bước: gửi, bắt đầu xử lý (chỉ check-in có), xong. */
const times = computed(() => (isIncident.value
    ? { 1: props.item.submitted_at, 3: props.item.reviewed_at }
    : { 1: props.item.checked_at, 2: props.item.response_status === 'in_progress' ? props.item.responded_at : null, 3: props.item.response_status === 'closed' ? props.item.responded_at : null }));
/** Ghi chú phản hồi của cơ quan và vai trò người phản hồi. */
const note = computed(() => (isIncident.value ? props.item.review_note : props.item.response_note));
const noteRole = computed(() => (isIncident.value ? props.item.reviewer_role : props.item.responder_role));
const noteAt = computed(() => (isIncident.value ? props.item.reviewed_at : props.item.responded_at));
/** Tiêu đề: tên sự cố, hoặc "Cần hỗ trợ – <hành trình>". */
const title = computed(() => (isIncident.value
    ? props.item.title
    : t('ui.requests.checkin_title', { trip: props.item.trip?.title || '—' })));
/** Liên kết cảnh báo tạo từ sự cố (chỉ khi backend gửi url hoặc code). */
const alertHref = computed(() => props.item.alert?.url || null);
const domId = computed(() => `req-${props.type}-${props.item.id}`);
</script>

<template>
    <article
        :id="domId"
        class="callout scroll-mt-24 p-4 outline-none"
        :class="[state.callout, highlighted ? 'is-highlighted' : '']"
        tabindex="-1"
        :data-request="type"
        :data-step="state.step"
    >
        <div class="flex flex-wrap items-center gap-2">
            <span class="pill text-xs font-bold"><Icon :name="state.icon" :size="14" />{{ t(`ui.requests.status.${state.key}`) }}</span>
            <span v-if="isIncident && item.hazard" class="pill text-xs"><Icon :name="item.hazard.code || 'alert'" fallback="alert" :size="14" />{{ item.hazard.name }}</span>
            <span v-if="!isIncident && item.status" class="pill text-xs"><Icon :name="item.status === 'need_help' ? 'siren' : 'hand'" :size="14" />{{ te('checkin_status', item.status) }}</span>
            <span v-if="item.is_simulated" class="pill border-dashed px-2 py-0 text-[0.6875rem]"><Icon name="flask" :size="12" />{{ t('common.simulated') }}</span>
            <span class="ml-auto font-mono text-xs text-muted">#{{ item.id }}</span>
        </div>

        <h3 class="mt-2 font-display text-base font-bold leading-snug">{{ title }}</h3>
        <p v-if="isIncident && (item.zone?.name || item.province?.name)" class="mt-1 flex items-center gap-1.5 text-sm text-muted">
            <Icon name="map-pin" :size="14" />{{ [item.zone?.name, item.province?.name].filter(Boolean).join(', ') }}
        </p>
        <p v-if="!isIncident && item.message" class="mt-1 whitespace-pre-line text-sm">{{ item.message }}</p>
        <p class="mt-1 text-xs text-muted">
            {{ t('ui.requests.submitted_at', { time: dateTime(isIncident ? item.submitted_at : item.checked_at) || '—' }) }}
        </p>

        <RequestTimeline class="mt-3" :step="state.step" :rejected="state.key === 'rejected'" :times="times" />

        <div v-if="note" class="mt-3 rounded-lg bg-soft p-3 text-sm">
            <p class="text-xs font-bold text-muted">
                {{ t('ui.requests.agency_note') }}<template v-if="noteRole"> · {{ te('roles', noteRole) }}</template><template v-if="noteAt"> · {{ dateTime(noteAt) }}</template>
            </p>
            <p class="mt-1 whitespace-pre-line">{{ note }}</p>
        </div>
        <p v-else-if="state.step === 1" class="mt-3 text-xs text-muted">{{ t('ui.requests.waiting_hint') }}</p>

        <p v-if="isIncident && item.alert" class="mt-3 flex flex-wrap items-center gap-1.5 text-sm">
            <Icon name="bell-ring" :size="16" class="text-muted" />
            <span>{{ t('ui.requests.alert_created') }}:</span>
            <Link v-if="alertHref" :href="alertHref" class="link">{{ item.alert.title }}</Link>
            <span v-else class="font-semibold">{{ item.alert.title }}</span>
        </p>
    </article>
</template>
