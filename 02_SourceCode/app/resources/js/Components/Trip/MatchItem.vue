<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Badge from '@/Components/Badge.vue';
import Button from '@/Components/Button.vue';
import SourceStamp from '@/Components/SourceStamp.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { normalizeLevel } from '@/Utils/levels';

/**
 * Cảnh báo khớp với một chặng (Resource MatchItem): mức, tiêu đề, chặng, kiểu khớp, khoảng cách, khoảng thời gian trùng,
 * dòng nguồn bắt buộc và nút "Đã biết" (emit acknowledge) khi chưa xác nhận.
 */
const props = defineProps({
    match: { type: Object, required: true },
    canAcknowledge: { type: Boolean, default: false },
    processing: { type: Boolean, default: false },
});
const emit = defineEmits(['acknowledge']);
const { t, te } = useT();
const { dateTime, km } = useFormat();
const a = computed(() => props.match.alert || {});
const level = computed(() => normalizeLevel(a.value.severity) ?? 1);
const isAcked = computed(() => props.match.status === 'acknowledged' || !!props.match.acknowledged_at);
const BORDER = { 1: 'border-level-1', 2: 'border-level-2', 3: 'border-level-3', 4: 'border-level-4' };
</script>

<template>
    <article class="rounded-[20px] border-2 bg-surface p-4" :class="BORDER[level]" :data-severity="level">
        <div class="flex flex-wrap items-center gap-2">
            <Badge :level="level" show-level-number :label="a.severity_label || undefined" />
            <span class="pill">{{ te('match_type', match.match_type) }}</span>
            <span v-if="isAcked" class="pill"><span aria-hidden="true">✓</span> {{ te('status', 'acknowledged') }}</span>
        </div>
        <h3 class="mt-2 font-display text-base font-bold leading-snug md:text-lg">
            <Link :href="a.url || '#'" class="hover:text-link hover:underline">{{ a.headline }}</Link>
        </h3>
        <dl class="mt-2 grid gap-1 text-sm sm:grid-cols-2">
            <div><dt class="inline text-muted">{{ t('trip.leg') }}: </dt><dd class="inline font-semibold">{{ t('trip.leg_n', { n: match.leg?.leg_order }) }} – {{ match.leg?.title }}</dd></div>
            <div v-if="match.distance_km !== null && match.distance_km !== undefined"><dt class="inline text-muted">{{ t('match.distance') }}: </dt><dd class="inline font-semibold">{{ km(match.distance_km) }}</dd></div>
            <div class="sm:col-span-2"><dt class="inline text-muted">{{ t('match.overlap') }}: </dt><dd class="inline">{{ dateTime(match.overlap_from) }} – {{ dateTime(match.overlap_to) }}</dd></div>
        </dl>
        <p v-if="a.instructions" class="mt-2 line-clamp-3 text-sm">{{ a.instructions }}</p>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-line pt-3">
            <SourceStamp :source="a.source" :updated-at="a.updated_at_source || a.issued_at" :simulated="!!a.is_simulated" />
            <Button v-if="canAcknowledge && !isAcked" size="sm" icon="check" :loading="processing" @click="emit('acknowledge', match)">{{ t('match.acknowledge') }}</Button>
            <span v-else-if="isAcked && match.acknowledged_at" class="text-xs text-muted">{{ t('match.acknowledged_at', { time: dateTime(match.acknowledged_at) }) }}</span>
        </div>
    </article>
</template>
