<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import FormField from '@/Components/FormField.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { enumOptions } from '@/Utils/options';

const props = defineProps({
    bookings: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    routes: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    transitions: { type: Object, default: null },
    canChangeStatus: { type: Boolean, default: false },
});
const { t, te } = useT();
const { money, dateTime } = useFormat();
const lr = useLocaleRoute();

// Máy trạng thái lấy từ Booking::TRANSITIONS của máy chủ; mặc định chỉ dùng khi thiếu prop
const DEFAULT_TRANSITIONS = {
    held: ['pending_payment', 'paid', 'cancelled', 'expired'],
    pending_payment: ['paid', 'cancelled', 'expired', 'held'],
    paid: ['refunded', 'cancelled'],
};
const STATUSES = ['held', 'pending_payment', 'paid', 'cancelled', 'expired', 'refunded'];
const TRANSITIONS = computed(() => props.transitions || DEFAULT_TRANSITIONS);
const nextStatuses = (row) => (props.canChangeStatus ? TRANSITIONS.value[row.status] || [] : []);

const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword'), placeholder: t('admin.bookings.search_placeholder') },
    { name: 'status', type: 'select', label: t('common.status'), options: props.statuses.length ? enumOptions(props.statuses, te, 'status') : STATUSES.map((s) => ({ value: s, label: te('status', s) })) },
    { name: 'route', type: 'select', label: t('nav.tours'), options: props.routes.map((r) => ({ value: r.slug ?? r.id, label: r.name ?? r.title })) },
    { name: 'from', type: 'date', label: t('common.from') },
    { name: 'to', type: 'date', label: t('common.to') },
]);

const columns = computed(() => [
    { key: 'code', label: t('booking.code') },
    { key: 'user', label: t('admin.bookings.customer') },
    { key: 'slot', label: t('admin.bookings.tour_slot') },
    { key: 'quantity', label: t('booking.quantity'), class: 'text-right' },
    { key: 'total_vnd', label: t('common.total'), class: 'text-right' },
    { key: 'status', label: t('common.status') },
]);

const target = ref(null);
const form = useForm({ status: '', reason: '' });
const open = ref(false);

function ask(booking, status) {
    target.value = booking;
    form.reset();
    form.clearErrors();
    form.status = status;
    open.value = true;
}

function apply() {
    form.patch(lr(`/quan-tri/dat-lich/${target.value.code}/trang-thai`), {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <Head :title="t('admin.bookings.title')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.bookings.title')" :description="t('admin.bookings.intro')" />
    <FilterBar :filters="filters" :fields="fields" />

    <div class="mt-6">
        <DataTable :columns="columns" :rows="bookings?.data || []" :empty-text="t('admin.bookings.empty')" :caption="t('admin.bookings.title')">
            <template #cell-code="{ row }">
                <span class="font-semibold">{{ row.code }}</span>
                <span class="block text-xs text-muted">{{ dateTime(row.created_at) }}</span>
            </template>
            <template #cell-user="{ row }">
                <span class="block">{{ row.user?.name || row.contact_name }}</span>
                <span class="block break-all text-xs text-muted">{{ row.user?.email || row.contact_email }}</span>
                <span v-if="row.contact_phone" class="block text-xs text-muted">{{ row.contact_phone }}</span>
            </template>
            <template #cell-slot="{ row }">
                <span class="block font-medium">{{ row.slot?.route?.name }}</span>
                <span class="block text-xs text-muted">{{ dateTime(row.slot?.starts_at) }}</span>
            </template>
            <template #cell-total_vnd="{ value }"><span class="whitespace-nowrap">{{ money(value) }}</span></template>
            <template #cell-status="{ row }"><StatusBadge :status="row.status" :label="row.status_label" /></template>
            <template #actions="{ row }">
                <div class="flex flex-wrap justify-end gap-1">
                    <button
                        v-for="next in nextStatuses(row)"
                        :key="next"
                        type="button"
                        class="btn btn-sm"
                        :class="['cancelled', 'refunded', 'expired'].includes(next) ? 'text-danger' : ''"
                        @click="ask(row, next)"
                    >
                        → {{ te('status', next) }}
                    </button>
                    <span v-if="canChangeStatus && !nextStatuses(row).length" class="text-xs text-muted">{{ t('admin.bookings.final_state') }}</span>
                </div>
            </template>
        </DataTable>
        <Pagination :paginator="bookings" />
    </div>

    <ConfirmDialog
        v-model:open="open"
        :title="t('admin.bookings.change_status')"
        :message="target ? t('admin.bookings.change_status_message', { code: target.code, from: te('status', target.status), to: te('status', form.status) }) : ''"
        :danger="['cancelled', 'refunded', 'expired'].includes(form.status)"
        :processing="form.processing"
        @confirm="apply"
    >
        <FormField v-model="form.reason" class="mt-4" :label="`${t('admin.bookings.reason')} (${t('common.optional')})`" type="textarea" :rows="3" :error="form.errors.reason || form.errors.status" />
    </ConfirmDialog>
</template>
