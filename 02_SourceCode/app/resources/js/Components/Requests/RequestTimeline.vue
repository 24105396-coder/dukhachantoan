<script setup>
import { computed } from 'vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

/**
 * Dòng thời gian 3 bước Đã gửi → Đang xử lý → Đã xử lý. Bước đã qua có dấu ✓, bước hiện tại đậm và
 * `aria-current="step"`; trạng thái luôn có chữ + icon (không chỉ dựa màu).
 * `rejected` đổi nhãn bước cuối thành "Không xác minh được". `times` = { 1: iso, 2: iso, 3: iso } giờ của từng bước.
 */
const props = defineProps({
    step: { type: Number, required: true },
    rejected: { type: Boolean, default: false },
    times: { type: Object, default: () => ({}) },
});
const { t } = useT();
const { stamp } = useFormat();

/** Ba bước với nhãn, trạng thái (done | current | todo) và giờ nếu có. */
const steps = computed(() => [1, 2, 3].map((n) => {
    const label = n === 3 && props.rejected ? t('ui.requests.step.rejected') : t(`ui.requests.step.${n}`);
    const state = n < props.step ? 'done' : n === props.step ? 'current' : 'todo';
    return { n, label, state, at: props.times?.[n] ? stamp(props.times[n]) : '' };
}));
/**
 * Icon theo trạng thái bước: đã qua ✓; bước cuối đang chọn = hoàn tất (hoặc ✗ khi không xác minh được); đang chờ = đồng hồ.
 * @param {{n: number, state: string}} s bước
 * @returns {string} tên icon
 */
function iconOf(s) {
    if (s.state === 'done') return 'check';
    if (s.state === 'todo') return 'ellipsis';
    if (s.n === 3) return props.rejected ? 'circle-x' : 'circle-check';
    return 'clock';
}
</script>

<template>
    <ol class="grid grid-cols-3 gap-1.5 text-xs" :aria-label="t('ui.requests.timeline')">
        <li
            v-for="s in steps"
            :key="s.n"
            class="flex min-w-0 flex-col gap-1 rounded-lg border px-2 py-1.5"
            :class="s.state === 'current' ? 'is-selected border-brand-400 font-bold' : s.state === 'done' ? 'border-line bg-soft font-semibold' : 'border-dashed border-[var(--border-strong)] text-muted'"
            :aria-current="s.state === 'current' ? 'step' : undefined"
        >
            <span class="flex items-center gap-1">
                <Icon :name="iconOf(s)" :size="14" />
                <span class="sr-only">{{ t(`ui.requests.step_state.${s.state}`) }}:</span>
                <span class="min-w-0 break-words leading-tight">{{ s.label }}</span>
            </span>
            <time v-if="s.at && s.state !== 'todo'" class="font-normal text-muted" :datetime="times[s.n]">{{ s.at }}</time>
        </li>
    </ol>
</template>
