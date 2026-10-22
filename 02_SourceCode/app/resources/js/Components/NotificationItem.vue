<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import Badge from '@/Components/Badge.vue';
import Button from '@/Components/Button.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

/**
 * Một thông báo (Resource NotificationItem). Hiển thị minh bạch trạng thái gửi: đang xếp hàng, đã gửi,
 * thất bại, số lần thử, giờ thử lại, lỗi cuối và nhật ký từng lần gửi (deliveries). Emit `read` khi bấm "Đánh dấu đã đọc".
 */
const props = defineProps({
    notification: { type: Object, required: true },
    canMarkRead: { type: Boolean, default: true },
    processing: { type: Boolean, default: false },
});
const emit = defineEmits(['read']);
const { t, te } = useT();
const { dateTime, ago } = useFormat();

const n = computed(() => props.notification);
const isUnread = computed(() => !n.value.read_at);
const STATUS = {
    queued: { icon: 'clock', cls: 'bg-sky-100' },
    sending: { icon: 'send', cls: 'bg-sky-100' },
    sent: { icon: 'check', cls: 'bg-mint-100' },
    read: { icon: 'check', cls: 'bg-mint-100' },
    failed: { icon: 'circle-x', cls: 'bg-level-4-soft border-level-4' },
    cancelled: { icon: 'ban', cls: 'bg-soft' },
};
const status = computed(() => STATUS[n.value.status] || STATUS.queued);
const CHANNEL_ICON = { inapp: 'bell', email: 'mail', sms: 'smartphone' };
const isSafeHref = (url) => typeof url === 'string' && (/^\/(?!\/)/.test(url) || /^https?:\/\//i.test(url));
const isInternal = (url) => /^\/(?!\/)/.test(url);
</script>

<template>
    <article
        class="relative rounded-[20px] border p-4"
        :class="isUnread ? 'border-brand-400 bg-surface shadow-clay-sm' : 'border-line bg-soft/60'"
        :data-unread="isUnread ? 'true' : 'false'"
    >
        <div class="flex flex-wrap items-center gap-2">
            <Badge v-if="n.level" :level="n.level" size="sm" />
            <span class="pill text-xs"><Icon :name="CHANNEL_ICON[n.channel] || 'bell'" :size="14" />{{ te('channel', n.channel) }}</span>
            <span class="pill text-xs" :class="status.cls"><Icon :name="status.icon" :size="14" />{{ te('status', n.status) }}</span>
            <span v-if="isUnread" class="pill border-brand-400 text-xs font-bold">{{ t('notification.unread') }}</span>
            <time class="ml-auto text-xs text-muted" :datetime="n.created_at" :title="dateTime(n.created_at)">{{ ago(n.created_at) }}</time>
        </div>
        <h3 class="mt-2 font-display text-base font-bold leading-snug">{{ n.title }}</h3>
        <p v-if="n.body" class="mt-1 whitespace-pre-line text-sm">{{ n.body }}</p>
        <p v-if="n.alert || n.trip" class="mt-2 flex flex-wrap gap-2 text-xs text-muted">
            <span v-if="n.alert">{{ t('notification.alert_ref', { code: n.alert.code }) }}</span>
            <span v-if="n.trip">{{ t('notification.trip_ref', { code: n.trip.code, title: n.trip.title }) }}</span>
        </p>

        <!-- Minh bạch hàng đợi: số lần thử, lịch thử lại, lỗi cuối -->
        <p v-if="n.attempts || n.next_attempt_at || n.last_error" class="mt-2 rounded-xl bg-soft p-2 text-xs" :role="n.status === 'failed' ? 'status' : undefined">
            <span v-if="n.attempts">{{ t('notification.attempts', { n: n.attempts }) }}</span>
            <span v-if="n.next_attempt_at && n.status !== 'sent'"> · {{ t('notification.next_attempt', { time: dateTime(n.next_attempt_at) }) }}</span>
            <span v-if="n.sent_at"> · {{ t('notification.sent_at', { time: dateTime(n.sent_at) }) }}</span>
            <span v-if="n.last_error" class="block font-semibold text-danger">{{ t('notification.last_error') }}: {{ n.last_error }}</span>
        </p>

        <details v-if="n.deliveries?.length" class="mt-2 text-xs">
            <summary class="cursor-pointer font-semibold text-link">{{ t('notification.deliveries', { n: n.deliveries.length }) }}</summary>
            <ol class="mt-2 space-y-1">
                <li v-for="d in n.deliveries" :key="d.attempt_no + '-' + d.channel" class="flex flex-wrap gap-x-2 rounded-lg bg-soft px-2 py-1">
                    <span class="font-semibold">#{{ d.attempt_no }}</span>
                    <span>{{ te('channel', d.channel) }}</span>
                    <span>{{ te('status', d.status) }}</span>
                    <span v-if="d.response_code">{{ t('notification.code', { code: d.response_code }) }}</span>
                    <span v-if="d.duration_ms != null">{{ d.duration_ms }} ms</span>
                    <time class="text-muted" :datetime="d.attempted_at">{{ dateTime(d.attempted_at) }}</time>
                </li>
            </ol>
        </details>

        <div v-if="(n.action_url && isSafeHref(n.action_url)) || (canMarkRead && isUnread)" class="mt-3 flex flex-wrap gap-2">
            <Link v-if="n.action_url && isInternal(n.action_url)" :href="n.action_url" class="clay-btn clay-btn-sm">{{ t('common.view_detail') }}</Link>
            <a v-else-if="n.action_url && isSafeHref(n.action_url)" :href="n.action_url" class="clay-btn clay-btn-sm" target="_blank" rel="noopener noreferrer">{{ t('common.view_detail') }}</a>
            <Button v-if="canMarkRead && isUnread" size="sm" variant="ghost" icon="check" :loading="processing" @click="emit('read', n)">{{ t('notification.mark_read') }}</Button>
        </div>
    </article>
</template>
