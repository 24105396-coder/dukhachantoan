<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';

const page = usePage();
const { t } = useT();
const toasts = ref([]);
let seq = 0;
const timers = new Map();

const AUTO_HIDE_MS = 5000;

function push(type, message) {
    if (!message) return;
    const id = ++seq;
    toasts.value = [...toasts.value, { id, type, message }];
    timers.set(id, setTimeout(() => dismiss(id), AUTO_HIDE_MS));
}

function dismiss(id) {
    toasts.value = toasts.value.filter((x) => x.id !== id);
    clearTimeout(timers.get(id));
    timers.delete(id);
}

watch(
    () => page.props.flash,
    (flash) => {
        push('success', flash?.success);
        push('error', flash?.error);
    },
    { immediate: true, deep: true },
);

onBeforeUnmount(() => timers.forEach((tm) => clearTimeout(tm)));
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 top-20 z-[1100] flex flex-col items-center gap-2 px-4" aria-live="polite" role="status">
        <TransitionGroup
            enter-from-class="opacity-0 -translate-y-2"
            enter-active-class="transition duration-200"
            leave-to-class="opacity-0"
            leave-active-class="transition duration-150"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="card pointer-events-auto flex w-full max-w-md items-start gap-3 p-3 pr-2"
                :class="toast.type === 'error' ? 'border-danger' : 'border-success'"
            >
                <span :class="toast.type === 'error' ? 'text-danger' : 'text-success'" class="mt-0.5">
                    <Icon :name="toast.type === 'error' ? 'alert' : 'check'" />
                </span>
                <p class="flex-1 text-sm">{{ toast.message }}</p>
                <button type="button" class="btn btn-ghost btn-sm btn-icon" :aria-label="t('common.close')" @click="dismiss(toast.id)">
                    <Icon name="close" :size="16" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
