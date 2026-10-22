<script setup>
import { computed } from 'vue';
import { useT } from '@/Composables/useT';

// Badge trạng thái chung cho booking/payment/contribution/content
const props = defineProps({
    status: { type: String, required: true },
    group: { type: String, default: 'status' },
    // Nhãn đã dịch sẵn từ backend (vd status_label); không có thì tra enums
    label: { type: String, default: null },
});
const { te } = useT();
const TONES = {
    paid: 'badge-success', succeeded: 'badge-success', approved: 'badge-success', published: 'badge-success', open: 'badge-success',
    held: 'badge-warning', pending_payment: 'badge-warning', pending: 'badge-warning', initiated: 'badge-warning', needs_revision: 'badge-warning', draft: 'badge-warning',
    cancelled: 'badge-danger', failed: 'badge-danger', rejected: 'badge-danger', expired: 'badge-danger', closed: 'badge-danger',
    refunded: 'badge-info', archived: 'badge-info',
};
const tone = computed(() => TONES[props.status] || '');
</script>

<template>
    <span class="badge" :class="tone">{{ label || te(group, status) }}</span>
</template>
