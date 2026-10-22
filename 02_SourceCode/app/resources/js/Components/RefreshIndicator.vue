<script setup>
import Button from '@/Components/Button.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

/** Nút Làm mới + thời điểm làm mới gần nhất, đi cùng usePolling (trang Dashboard, Trip/Show, Notifications). */
defineProps({
    refreshing: { type: Boolean, default: false },
    lastRefreshedAt: { type: [Date, String], default: null },
    intervalSeconds: { type: Number, default: 30 },
});
const emit = defineEmits(['refresh']);
const { t } = useT();
const { time } = useFormat();
</script>

<template>
    <div class="flex flex-wrap items-center gap-2 text-xs text-muted">
        <span aria-live="polite">
            {{ t('polling.auto', { s: intervalSeconds }) }}
            <template v-if="lastRefreshedAt"> · {{ t('polling.last', { time: time(lastRefreshedAt) }) }}</template>
        </span>
        <Button size="sm" icon="refresh" :loading="refreshing" @click="emit('refresh')">{{ t('common.refresh') }}</Button>
    </div>
</template>
