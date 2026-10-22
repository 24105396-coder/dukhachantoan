<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useT } from '@/Composables/useT';

// Đếm ngược thời gian giữ chỗ; ưu tiên seconds (máy chủ tính) để tránh lệch đồng hồ
const props = defineProps({
    seconds: { type: Number, default: null },
    expiresAt: { type: String, default: null },
});
const emit = defineEmits(['expired']);
const { t } = useT();

const initial = () => {
    if (props.seconds != null) return Math.max(0, Math.floor(props.seconds));
    if (props.expiresAt) return Math.max(0, Math.floor((new Date(props.expiresAt).getTime() - Date.now()) / 1000));
    return 0;
};
const left = ref(initial());
let timer = null;

const mm = computed(() => String(Math.floor(left.value / 60)).padStart(2, '0'));
const ss = computed(() => String(left.value % 60).padStart(2, '0'));
const urgent = computed(() => left.value > 0 && left.value <= 60);

onMounted(() => {
    timer = setInterval(() => {
        if (left.value <= 0) {
            clearInterval(timer);
            emit('expired');
            return;
        }
        left.value -= 1;
    }, 1000);
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <div class="inline-flex items-center gap-3 rounded-xl px-4 py-2" :class="urgent ? 'bg-danger-soft text-danger' : 'bg-accent-soft text-accent-strong'" role="timer" :aria-label="t('booking.time_left')">
        <span class="text-sm font-medium">{{ left > 0 ? t('booking.time_left') : t('booking.hold_expired') }}</span>
        <span v-if="left > 0" class="font-display text-2xl font-bold tabular-nums" aria-live="off">{{ mm }}:{{ ss }}</span>
    </div>
</template>
