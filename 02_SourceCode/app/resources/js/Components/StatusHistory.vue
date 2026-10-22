<script setup>
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

/** Lịch sử trạng thái [{ status, at, by }] dạng dòng thời gian dọc. */
defineProps({
    items: { type: Array, default: () => [] },
});
const { t, te } = useT();
const { dateTime } = useFormat();
const byName = (by) => (typeof by === 'object' && by !== null ? by.name : by);
</script>

<template>
    <ol v-if="items.length" class="relative space-y-3 border-l-2 border-line pl-5">
        <li v-for="(h, i) in items" :key="i" class="relative">
            <span class="absolute -left-[1.6rem] top-1.5 h-3 w-3 rounded-full bg-brand-500 ring-4 ring-[var(--bg-surface)]" aria-hidden="true"></span>
            <p class="font-semibold">{{ te('status', h.status) }}</p>
            <p class="text-xs text-muted">
                <time :datetime="h.at">{{ dateTime(h.at) }}</time>
                <template v-if="byName(h.by)"> · {{ byName(h.by) }}</template>
            </p>
        </li>
    </ol>
    <p v-else class="text-sm text-muted">{{ t('common.no_data') }}</p>
</template>
