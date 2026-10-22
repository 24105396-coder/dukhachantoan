<script setup>
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';

/**
 * Chỉ báo các bước của wizard. steps: [{ key, label, hasError? }]; `current` là chỉ số bước đang mở.
 * Bước đã qua bấm được để quay lại (emit go); bước sau chỉ mở qua nút "Tiếp" để buộc kiểm tra dữ liệu.
 */
defineProps({
    steps: { type: Array, required: true },
    current: { type: Number, default: 0 },
});
const emit = defineEmits(['go']);
const { t } = useT();
</script>

<template>
    <nav :aria-label="t('stepper.label')" class="mb-6">
        <ol class="grid gap-2 sm:grid-cols-3">
            <li v-for="(s, i) in steps" :key="s.key">
                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded-full border px-3 py-2 text-left text-sm font-semibold transition-colors disabled:cursor-default"
                    :class="i === current ? 'border-transparent bg-linear-to-br from-brand-400 to-brand-500 text-on-brand shadow-clay-btn' : (s.hasError ? 'border-level-4 bg-level-4-soft' : 'border-line bg-soft')"
                    :aria-current="i === current ? 'step' : undefined"
                    :disabled="i > current"
                    @click="emit('go', i)"
                >
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-raised text-fg">
                        <Icon v-if="i < current && !s.hasError" name="check" :size="16" />
                        <Icon v-else-if="s.hasError" name="circle-alert" :size="16" />
                        <span v-else>{{ i + 1 }}</span>
                    </span>
                    <span class="min-w-0">
                        <span class="sr-only">{{ t('stepper.step', { n: i + 1, total: steps.length }) }}: </span>{{ s.label }}
                        <span v-if="s.hasError" class="sr-only"> – {{ t('admin.form.tab_has_errors') }}</span>
                    </span>
                </button>
            </li>
        </ol>
    </nav>
</template>
