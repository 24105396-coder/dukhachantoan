<script setup>
import { ref, watch, nextTick, onBeforeUnmount, useId } from 'vue';
import Button from '@/Components/Button.vue';
import { useT } from '@/Composables/useT';

/** Hộp xác nhận (alertdialog): Esc để hủy, tiêu điểm vào nút Hủy khi mở và trả lại khi đóng. */
const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    message: { type: String, default: '' },
    confirmText: { type: String, default: '' },
    danger: { type: Boolean, default: false },
    processing: { type: Boolean, default: false },
});
const emit = defineEmits(['confirm', 'cancel', 'update:open']);
const { t } = useT();
const uid = useId();
const dialog = ref(null);
let lastFocus = null;

function cancel() {
    emit('cancel');
    emit('update:open', false);
}
function onKey(e) {
    if (e.key === 'Escape') cancel();
}

watch(
    () => props.open,
    (v) => {
        if (v) {
            lastFocus = document.activeElement;
            document.addEventListener('keydown', onKey);
            nextTick(() => dialog.value?.querySelector('[data-cancel]')?.focus());
        } else {
            document.removeEventListener('keydown', onKey);
            lastFocus?.focus?.();
        }
    },
);
onBeforeUnmount(() => document.removeEventListener('keydown', onKey));
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[1300] flex items-center justify-center bg-overlay p-4" @click.self="cancel">
            <div
                ref="dialog"
                class="clay-card w-full max-w-md p-6"
                role="alertdialog"
                aria-modal="true"
                :aria-labelledby="`dlg-t-${uid}`"
                :aria-describedby="`dlg-m-${uid}`"
            >
                <h2 :id="`dlg-t-${uid}`" class="text-xl">{{ title }}</h2>
                <p :id="`dlg-m-${uid}`" class="mt-2 text-sm text-muted">{{ message }}</p>
                <slot />
                <div class="mt-6 flex flex-wrap justify-end gap-2">
                    <Button data-cancel @click="cancel">{{ t('common.cancel') }}</Button>
                    <Button :variant="danger ? 'danger' : 'primary'" :loading="processing" @click="emit('confirm')">
                        {{ confirmText || t('common.confirm') }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
