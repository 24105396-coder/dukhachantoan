<script setup>
import { computed } from 'vue';
import Badge from '@/Components/Badge.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { normalizeLevel } from '@/Utils/levels';

/**
 * Điểm rủi ro vùng (0–100) + mức + các yếu tố đóng góp.
 * factors: mảng [{ code|key|name|label, value|score|points, weight?, note? }] hoặc object { tên: giá trị }.
 */
const props = defineProps({
    risk: { type: Object, default: null },
});
const { t, has } = useT();
const { number, stamp } = useFormat();

const level = computed(() => normalizeLevel(props.risk?.level) ?? 0);
const score = computed(() => Math.max(0, Math.min(100, Number(props.risk?.score ?? 0))));
const BAR = { 0: 'bg-level-0', 1: 'bg-level-1', 2: 'bg-level-2', 3: 'bg-level-3', 4: 'bg-level-4' };

const factorLabel = (code) => (has(`risk.factor.${code}`) ? t(`risk.factor.${code}`) : String(code).replaceAll('_', ' '));
const factors = computed(() => {
    const f = props.risk?.factors;
    if (!f) return [];
    if (Array.isArray(f)) {
        return f.map((x, i) => {
            if (typeof x !== 'object' || x === null) return { key: i, label: String(x), value: null };
            const code = x.code ?? x.key ?? x.name;
            return { key: code ?? i, label: x.label || factorLabel(code ?? i), value: x.value ?? x.score ?? x.points ?? null, note: x.note || '' };
        });
    }
    return Object.entries(f).map(([k, v]) => ({
        key: k,
        label: factorLabel(k),
        value: typeof v === 'object' && v !== null ? (v.value ?? v.score ?? null) : v,
        note: typeof v === 'object' && v !== null ? v.note || '' : '',
    }));
});
// Giá trị chuỗi có dấu + (xu hướng tăng) giữ dấu khi định dạng số
const show = (v) => (typeof v === 'string' && v.startsWith('+') && !Number.isNaN(Number(v)) ? `+${number(v.slice(1), 1)}` : typeof v === 'number' || (typeof v === 'string' && v !== '' && !Number.isNaN(Number(v))) ? number(v, 1) : String(v ?? '—'));
</script>

<template>
    <section class="clay-card-sm p-5" aria-labelledby="risk-score-title">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="risk-score-title" class="section-title text-lg">{{ t('risk.score_title') }}</h2>
            <Badge v-if="risk" :level="level" show-level-number />
        </div>
        <template v-if="risk">
            <p class="mt-3 font-display text-4xl font-bold">{{ number(score) }}<span class="text-base text-muted">/100</span></p>
            <div
                class="mt-2 h-3 w-full overflow-hidden rounded-full bg-soft"
                role="meter"
                :aria-valuenow="score"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-label="t('risk.score_title')"
            >
                <div class="h-full rounded-full" :class="BAR[level]" :style="{ width: `${score}%` }"></div>
            </div>
            <h3 v-if="factors.length" class="mt-4 text-sm font-bold">{{ t('risk.factors') }}</h3>
            <ul v-if="factors.length" class="mt-2 divide-y divide-line text-sm">
                <li v-for="f in factors" :key="f.key" class="flex items-start justify-between gap-3 py-1.5">
                    <span class="min-w-0">{{ f.label }}<span v-if="f.note" class="block text-xs text-muted">{{ f.note }}</span></span>
                    <span v-if="f.value !== null" class="shrink-0 font-semibold">{{ show(f.value) }}</span>
                </li>
            </ul>
            <p v-if="risk.computed_at" class="mt-3 text-xs text-muted">{{ t('risk.computed_at', { time: stamp(risk.computed_at) }) }}</p>
        </template>
        <p v-else class="mt-3 text-sm text-muted">{{ t('risk.no_data') }}</p>
    </section>
</template>
