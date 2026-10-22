<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Icon from '@/Components/Icon.vue';
import Countdown from '@/Components/Countdown.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

defineOptions({ layout: AuthLayout });

const props = defineProps({
    payment: { type: Object, required: true },
});
const { t } = useT();
const { money } = useFormat();
const lr = useLocaleRoute();

const form = useForm({ outcome: '' });
// Giao dịch đã xử lý (thành công/thất bại/hủy) thì không cho bấm lại
const isOpen = computed(() => !props.payment.status || props.payment.status === 'initiated');
const chosen = ref('');

function send(outcome) {
    chosen.value = outcome;
    form.outcome = outcome;
    form.post(lr(`/cong-thanh-toan/${props.payment.txn_ref}/ket-qua`));
}
</script>

<template>
    <Head :title="t('payment.sandbox_title')" />
    <div class="card overflow-hidden">
        <div class="bg-warning-soft p-4 text-warning" role="alert">
            <p class="flex items-center gap-2 font-bold uppercase tracking-wider"><Icon name="alert" :size="18" />SANDBOX</p>
            <p class="mt-1 text-sm">{{ t('payment.sandbox_banner') }}</p>
        </div>
        <div class="p-6">
            <h1 class="text-2xl font-bold">{{ t('payment.sandbox_title') }}</h1>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between gap-2"><dt class="text-muted">{{ t('booking.code') }}</dt><dd class="font-semibold">{{ payment.booking_code }}</dd></div>
                <div v-if="payment.route_name" class="flex justify-between gap-2"><dt class="text-muted">{{ t('nav.tours') }}</dt><dd class="text-right font-semibold">{{ payment.route_name }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-muted">{{ t('payment.txn_ref') }}</dt><dd><code class="break-all text-xs">{{ payment.txn_ref }}</code></dd></div>
                <div class="flex items-baseline justify-between gap-2 border-t border-border pt-2">
                    <dt class="font-semibold">{{ t('payment.amount') }}</dt>
                    <dd class="font-display text-3xl font-bold text-primary">{{ money(payment.amount_vnd) }}</dd>
                </div>
            </dl>
            <div v-if="payment.expires_at" class="mt-4"><Countdown :expires-at="payment.expires_at" /></div>

            <p v-if="!isOpen" class="mt-6 rounded-lg bg-info-soft p-3 text-sm text-info" role="status">{{ t('payment.already_processed') }}</p>
            <p v-if="isOpen" class="mt-6 text-sm text-muted">{{ t('payment.choose_outcome') }}</p>
            <div v-if="isOpen" class="mt-3 grid gap-2">
                <button type="button" class="btn btn-primary w-full" :disabled="form.processing" @click="send('success')">
                    <Icon name="check" :size="18" />{{ form.processing && chosen === 'success' ? t('common.loading') : t('payment.outcome_success') }}
                </button>
                <button type="button" class="btn btn-danger w-full" :disabled="form.processing" @click="send('fail')">
                    <Icon name="alert" :size="18" />{{ form.processing && chosen === 'fail' ? t('common.loading') : t('payment.outcome_fail') }}
                </button>
                <button type="button" class="btn w-full" :disabled="form.processing" @click="send('cancel')">
                    <Icon name="close" :size="18" />{{ form.processing && chosen === 'cancel' ? t('common.loading') : t('payment.outcome_cancel') }}
                </button>
            </div>
            <p v-if="form.errors.outcome" class="field-error" role="alert">{{ form.errors.outcome }}</p>
            <Link v-if="payment.booking_url" :href="payment.booking_url" class="btn btn-ghost mt-3 w-full">{{ t('payment.back_to_booking') }}</Link>
            <p class="mt-6 flex items-start gap-2 text-xs text-muted"><Icon name="shield" :size="16" />{{ t('payment.signature_note') }}</p>
        </div>
    </div>
</template>
