<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Countdown from '@/Components/Countdown.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import DataTable from '@/Components/DataTable.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

const props = defineProps({
    booking: { type: Object, required: true },
    sandboxGateway: { type: Boolean, default: true },
});
const { t, te } = useT();
const { money, dateTime, longDate, time } = useFormat();
const lr = useLocaleRoute();

const isHolding = computed(() => ['held', 'pending_payment'].includes(props.booking.status));
const payForm = useForm({});
const cancelForm = useForm({});
const confirmCancel = ref(false);

const pay = () => payForm.post(lr(`/dat-lich/${props.booking.code}/thanh-toan`));
const cancel = () =>
    cancelForm.post(lr(`/dat-lich/${props.booking.code}/huy`), {
        preserveScroll: true,
        onFinish: () => (confirmCancel.value = false),
    });
// Hết hạn giữ chỗ: tải lại để máy chủ cập nhật trạng thái expired
const onExpired = () => router.reload({ only: ['booking'] });

const paymentColumns = computed(() => [
    { key: 'txn_ref', label: t('payment.txn_ref') },
    { key: 'gateway', label: t('payment.gateway') },
    { key: 'amount_vnd', label: t('payment.amount') },
    { key: 'status', label: t('common.status') },
    { key: 'paid_at', label: t('payment.paid_at') },
]);
</script>

<template>
    <Head :title="t('booking.title', { code: booking.code })" />
    <div class="container-page max-w-4xl py-8">
        <Breadcrumbs :items="[{ label: t('nav.dashboard'), href: lr('/tai-khoan') }, { label: booking.code }]" />

        <div class="mt-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="eyebrow">{{ t('booking.code') }}</p>
                <h1 class="font-display text-3xl font-bold tracking-wide">{{ booking.code }}</h1>
            </div>
            <StatusBadge :status="booking.status" :label="booking.status_label" />
        </div>

        <div v-if="isHolding" class="mt-4 flex flex-wrap items-center gap-3">
            <Countdown :seconds="booking.seconds_left" :expires-at="booking.hold_expires_at" @expired="onExpired" />
            <p class="text-sm text-muted">{{ t('booking.hold_explain') }}</p>
        </div>

        <div class="mt-6 grid gap-6 md:grid-cols-2">
            <section class="card p-5">
                <h2 class="font-body text-lg font-bold">{{ t('booking.tour_info') }}</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div>
                        <dt class="text-muted">{{ t('nav.tours') }}</dt>
                        <dd class="font-semibold">
                            <Link v-if="booking.slot?.route?.url" :href="booking.slot.route.url" class="link">{{ booking.slot.route.name }}</Link>
                            <span v-else>{{ booking.slot?.route?.name }}</span>
                        </dd>
                    </div>
                    <div><dt class="text-muted">{{ t('booking.departure') }}</dt><dd class="font-semibold">{{ longDate(booking.slot?.starts_at) }}, {{ time(booking.slot?.starts_at) }}</dd></div>
                    <div v-if="booking.slot?.guide_name"><dt class="text-muted">{{ t('tours.guide') }}</dt><dd>{{ booking.slot.guide_name }}</dd></div>
                    <div v-if="booking.slot?.language"><dt class="text-muted">{{ t('tours.language') }}</dt><dd>{{ te('locale', booking.slot.language) }}</dd></div>
                </dl>
            </section>
            <section class="card p-5">
                <h2 class="font-body text-lg font-bold">{{ t('booking.contact') }}</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div><dt class="text-muted">{{ t('booking.contact_name') }}</dt><dd class="font-semibold">{{ booking.contact_name }}</dd></div>
                    <div><dt class="text-muted">{{ t('booking.contact_phone') }}</dt><dd>{{ booking.contact_phone }}</dd></div>
                    <div><dt class="text-muted">{{ t('booking.contact_email') }}</dt><dd class="break-all">{{ booking.contact_email }}</dd></div>
                    <div v-if="booking.note"><dt class="text-muted">{{ t('common.note') }}</dt><dd class="whitespace-pre-line">{{ booking.note }}</dd></div>
                </dl>
            </section>
        </div>

        <section class="card mt-6 p-5">
            <h2 class="font-body text-lg font-bold">{{ t('booking.payment_summary') }}</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-2"><dt class="text-muted">{{ t('booking.unit_price') }}</dt><dd>{{ money(booking.unit_price_vnd) }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-muted">{{ t('booking.quantity') }}</dt><dd>× {{ booking.quantity }}</dd></div>
                <div class="flex justify-between gap-2 border-t border-border pt-2"><dt class="font-semibold">{{ t('common.total') }}</dt><dd class="font-display text-2xl font-bold text-primary">{{ money(booking.total_vnd) }}</dd></div>
            </dl>
            <div class="mt-4 flex flex-wrap gap-2">
                <button v-if="booking.can_pay" type="button" class="btn btn-primary" :disabled="payForm.processing" @click="pay">
                    <Icon name="ticket" :size="18" />{{ t('booking.pay_now') }}
                </button>
                <button v-if="booking.can_cancel" type="button" class="btn btn-danger" @click="confirmCancel = true">{{ t('booking.cancel') }}</button>
                <Link :href="lr('/tai-khoan')" class="btn">{{ t('booking.back_to_account') }}</Link>
            </div>
            <p v-if="booking.can_pay && sandboxGateway" class="mt-3 text-xs text-muted">{{ t('payment.sandbox_note') }}</p>
            <p v-if="booking.status === 'paid'" class="mt-3 rounded-lg bg-success-soft p-3 text-sm text-success">{{ t('booking.paid_message') }}</p>
            <p v-if="booking.cancel_reason" class="mt-3 text-sm text-muted">{{ t('booking.cancel_reason') }}: {{ booking.cancel_reason }}</p>
        </section>

        <section class="mt-6">
            <h2 class="mb-3 font-body text-lg font-bold">{{ t('booking.payment_history') }}</h2>
            <DataTable :columns="paymentColumns" :rows="booking.payments || []" row-key="txn_ref" :empty-text="t('booking.no_payments')" :caption="t('booking.payment_history')">
                <template #cell-txn_ref="{ value }"><code class="text-xs">{{ value }}</code></template>
                <template #cell-amount_vnd="{ value }">{{ money(value) }}</template>
                <template #cell-status="{ value }"><StatusBadge :status="value" /></template>
                <template #cell-paid_at="{ value }">{{ value ? dateTime(value) : '—' }}</template>
            </DataTable>
        </section>

        <ConfirmDialog
            v-model:open="confirmCancel"
            :title="t('booking.cancel_title')"
            :message="booking.status === 'paid' ? t('booking.cancel_paid_message') : t('booking.cancel_message')"
            :confirm-text="t('booking.cancel')"
            danger
            :processing="cancelForm.processing"
            @confirm="cancel"
        />
    </div>
</template>
