<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { useT } from '@/Composables/useT';
import { useClipboard } from '@/Composables/useClipboard';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

/** Nhóm nút của trang hành trình: Sửa, Sao chép liên kết chia sẻ, Tải lịch iCal, Hủy hành trình (có xác nhận). */
const props = defineProps({
    trip: { type: Object, required: true },
});
const { t } = useT();
const lr = useLocaleRoute();
const { copy, copied, failed } = useClipboard();
const confirmOpen = ref(false);
const cancelling = ref(false);

function cancelTrip() {
    cancelling.value = true;
    router.post(lr(`/hanh-trinh/${props.trip.code}/huy`), {}, {
        preserveScroll: true,
        onFinish: () => {
            cancelling.value = false;
            confirmOpen.value = false;
        },
    });
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Button v-if="trip.can_edit" :href="lr(`/hanh-trinh/${trip.code}/sua`)" icon="edit" size="sm">{{ t('common.edit') }}</Button>
        <Button v-if="trip.share_url" icon="share" size="sm" @click="copy(trip.share_url)">{{ copied ? t('trip.share_copied') : t('trip.share') }}</Button>
        <Button v-if="trip.ical_url" :href="trip.ical_url" external icon="calendar-plus" size="sm">{{ t('trip.ical') }}</Button>
        <Button v-if="trip.can_cancel" icon="ban" size="sm" variant="ghost" class="text-danger" @click="confirmOpen = true">{{ t('trip.cancel') }}</Button>
        <span class="sr-only" aria-live="polite">{{ copied ? t('trip.share_copied') : '' }}</span>
        <p v-if="failed" class="w-full text-xs text-danger" role="status">{{ t('trip.share_copy_failed', { url: trip.share_url }) }}</p>
        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="t('trip.cancel_title')"
            :message="t('trip.cancel_message', { code: trip.code })"
            :confirm-text="t('trip.cancel')"
            danger
            :processing="cancelling"
            @confirm="cancelTrip"
        />
    </div>
</template>
