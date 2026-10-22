<script setup>
import { ref, watch, nextTick, onBeforeUnmount } from 'vue';
import { useT } from '@/Composables/useT';

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
const cancelBtn = ref(null);
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
            nextTick(() => cancelBtn.value?.focus());
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
            <div class="card w-full max-w-md p-5" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-msg">
                <h2 id="confirm-title" class="text-xl font-bold">{{ title }}</h2>
                <p id="confirm-msg" class="mt-2 text-sm text-muted">{{ message }}</p>
                <slot />
                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <button ref="cancelBtn" type="button" class="btn" @click="cancel">{{ t('common.cancel') }}</button>
                    <button type="button" class="btn" :class="danger ? 'btn-danger' : 'btn-primary'" :disabled="processing" @click="emit('confirm')">
                        {{ confirmText || t('common.confirm') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
