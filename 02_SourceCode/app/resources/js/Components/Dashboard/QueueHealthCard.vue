<script setup>
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

/** Sức khỏe hàng đợi thông báo: số đang chờ, thất bại, bản ghi chờ lâu nhất; tỉ lệ gửi thành công 24h. */
defineProps({
    queue: { type: Object, default: () => ({}) }, // { queued, failed, oldest_queued_at }
    successRate: { type: [Number, String], default: null },
    notifications: { type: Object, default: () => ({}) }, // { queued, sent, failed, read }
});
const { t } = useT();
const { number, percent, ago } = useFormat();
const show = (v) => (v === null || v === undefined ? '—' : number(v));
</script>

<template>
    <section class="clay-card-sm p-4 md:p-5" aria-labelledby="queue-title">
        <h2 id="queue-title" class="section-title text-lg">{{ t('dashboard.queue_title') }}</h2>
        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
            <div class="rounded-lg border border-line bg-soft p-3">
                <dt class="text-muted">{{ t('dashboard.queue_queued') }}</dt>
                <dd class="font-display text-2xl font-bold">{{ show(queue?.queued) }}</dd>
            </div>
            <div class="callout p-3" :class="queue?.failed ? 'callout-4' : 'callout-0'">
                <dt class="text-muted">{{ t('dashboard.queue_failed') }}</dt>
                <dd class="font-display text-2xl font-bold">{{ show(queue?.failed) }}</dd>
            </div>
            <div class="col-span-2 flex items-center gap-2 text-xs text-muted">
                <Icon name="clock" :size="14" />
                <dt>{{ t('dashboard.queue_oldest') }}:</dt>
                <dd class="font-semibold text-fg">{{ queue?.oldest_queued_at ? ago(queue.oldest_queued_at) : t('common.none') }}</dd>
            </div>
            <div class="col-span-2 flex items-center gap-2 text-xs text-muted">
                <Icon name="send" :size="14" />
                <dt>{{ t('dashboard.delivery_rate') }}:</dt>
                <dd class="font-semibold text-fg">{{ successRate === null || successRate === undefined ? '—' : percent(successRate, 1) }}</dd>
            </div>
        </dl>
        <p class="eyebrow mt-4">{{ t('dashboard.notifications_24h') }}</p>
        <dl class="mt-2 grid grid-cols-2 gap-2 text-center text-xs sm:grid-cols-4">
            <div v-for="k in ['queued', 'sent', 'failed', 'read']" :key="k" class="rounded-xl bg-soft p-2">
                <dt class="text-muted">{{ t(`enums.status.${k}`) }}</dt>
                <dd class="font-bold">{{ show(notifications?.[k]) }}</dd>
            </div>
        </dl>
    </section>
</template>
