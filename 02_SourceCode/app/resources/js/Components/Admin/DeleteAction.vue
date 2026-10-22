<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { useT } from '@/Composables/useT';

/** Nút xóa có hộp xác nhận, gửi DELETE tới url. */
const props = defineProps({
    url: { type: String, required: true },
    name: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    disabledReason: { type: String, default: '' },
    small: { type: Boolean, default: true },
});
const { t } = useT();
const open = ref(false);
const processing = ref(false);

function confirm() {
    processing.value = true;
    router.delete(props.url, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            open.value = false;
        },
    });
}
</script>

<template>
    <button
        type="button"
        class="btn btn-ghost btn-icon text-danger"
        :class="small ? 'btn-sm' : ''"
        :disabled="disabled"
        :title="disabled ? disabledReason : t('common.delete')"
        :aria-label="`${t('common.delete')} ${name}`"
        @click="open = true"
    >
        <Icon name="trash" :size="18" />
    </button>
    <ConfirmDialog
        v-model:open="open"
        :title="t('admin.common.delete_title')"
        :message="t('admin.common.delete_message', { name })"
        :confirm-text="t('common.delete')"
        danger
        :processing="processing"
        @confirm="confirm"
    />
</template>
