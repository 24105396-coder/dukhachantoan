<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

/**
 * Nút vòng đời cảnh báo theo trạng thái, mỗi nút có hộp xác nhận:
 * draft → Phát hành (POST …/phat-hanh: đối sánh + xếp thông báo); active|updated → Hết hiệu lực (…/het-hieu-luc);
 * draft|active|updated → Hủy (…/huy). Nút Sửa dẫn tới form (Cập nhật nội dung khi đang hiệu lực).
 */
const props = defineProps({
    alert: { type: Object, required: true },
    size: { type: String, default: 'md' },
    showEdit: { type: Boolean, default: true },
});
const { t } = useT();
const lr = useLocaleRoute();
const pending = ref(null);
const processing = ref(false);
const base = computed(() => lr(`/quan-tri/canh-bao/${props.alert.code}`));
const st = computed(() => props.alert.status);
const ACTIONS = {
    publish: { path: 'phat-hanh', danger: false },
    expire: { path: 'het-hieu-luc', danger: false },
    cancel: { path: 'huy', danger: true },
};
const can = computed(() => ({
    publish: st.value === 'draft',
    expire: ['active', 'updated'].includes(st.value),
    cancel: ['draft', 'active', 'updated'].includes(st.value),
    edit: ['draft', 'active', 'updated'].includes(st.value),
}));
function run() {
    const action = ACTIONS[pending.value];
    if (!action) return;
    processing.value = true;
    router.post(`${base.value}/${action.path}`, {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            pending.value = null;
        },
    });
}
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <Button v-if="showEdit && can.edit" :href="`${base}/sua`" :size="size" icon="edit">{{ st === 'draft' ? t('common.edit') : t('admin.alerts.update') }}</Button>
        <Button v-if="can.publish" :size="size" variant="primary" icon="send" @click="pending = 'publish'">{{ t('admin.alerts.publish') }}</Button>
        <Button v-if="can.expire" :size="size" icon="clock" @click="pending = 'expire'">{{ t('admin.alerts.expire') }}</Button>
        <Button v-if="can.cancel" :size="size" variant="ghost" icon="ban" class="text-danger" @click="pending = 'cancel'">{{ t('admin.alerts.cancel') }}</Button>
        <ConfirmDialog
            :open="!!pending"
            :title="pending ? t(`admin.alerts.confirm_${pending}_title`) : ''"
            :message="pending ? t(`admin.alerts.confirm_${pending}`, { code: alert.code }) : ''"
            :confirm-text="pending ? t(`admin.alerts.${pending}`) : ''"
            :danger="!!ACTIONS[pending]?.danger"
            :processing="processing"
            @confirm="run"
            @cancel="pending = null"
        />
    </div>
</template>
