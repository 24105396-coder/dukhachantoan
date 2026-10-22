<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import EmptyState from '@/Components/EmptyState.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

/**
 * Trục thời gian dọc, nhóm sự kiện theo thời kỳ; lọc tại chỗ theo thời kỳ, loại, từ khóa.
 * compact = dạng mini (trang chi tiết), không có bộ lọc và không nhóm.
 */
const props = defineProps({
    events: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    filterable: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
    initialFilters: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const { year } = useFormat();

const EVENT_TYPES = ['xay_dung', 'trung_tu', 'cong_nhan', 'su_kien', 'sinh', 'mat', 'phat_hien', 'khac'];
const fPeriod = ref(props.initialFilters.period || '');
const fType = ref(props.initialFilters.type || '');
const fQuery = ref(props.initialFilters.q || '');

function periodOf(ev) {
    if (ev.period?.code) return ev.period.code;
    const p = props.periods.find(
        (x) => (x.start_year == null || ev.year >= x.start_year) && (x.end_year == null || ev.year <= x.end_year),
    );
    return p?.code || '_other';
}

const filtered = computed(() => {
    const q = fQuery.value.trim().toLowerCase();
    return [...props.events]
        .filter((ev) => !fType.value || ev.event_type === fType.value)
        .filter((ev) => !fPeriod.value || periodOf(ev) === fPeriod.value)
        .filter((ev) => !q || `${ev.title} ${ev.description || ''} ${ev.subject?.title || ''}`.toLowerCase().includes(q))
        .sort((a, b) => (a.year ?? 0) - (b.year ?? 0) || (a.month ?? 0) - (b.month ?? 0));
});

const groups = computed(() => {
    if (props.compact) return [{ code: '_all', name: '', items: filtered.value }];
    const map = new Map();
    filtered.value.forEach((ev) => {
        const code = periodOf(ev);
        if (!map.has(code)) {
            const p = props.periods.find((x) => x.code === code) || ev.period;
            map.set(code, { code, name: p?.name || t('timeline.other_period'), start: p?.start_year, end: p?.end_year, items: [] });
        }
        map.get(code).items.push(ev);
    });
    return [...map.values()];
});

const dateLabel = (ev) => {
    const parts = [];
    if (ev.day) parts.push(String(ev.day).padStart(2, '0'));
    if (ev.month) parts.push(String(ev.month).padStart(2, '0'));
    const prefix = parts.length ? `${parts.join('/')}/` : '';
    const main = `${prefix}${year(ev.year)}`;
    return ev.end_year && ev.end_year !== ev.year ? `${main} – ${year(ev.end_year)}` : main;
};

const dotClass = (importance) =>
    importance >= 3 ? 'h-4 w-4 bg-primary' : importance === 2 ? 'h-3.5 w-3.5 bg-accent' : 'h-3 w-3 bg-muted';

function reset() {
    fPeriod.value = '';
    fType.value = '';
    fQuery.value = '';
}
</script>

<template>
    <div>
        <div v-if="filterable" class="card mb-6 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <label for="tl-q" class="label">{{ t('common.keyword') }}</label>
                <input id="tl-q" v-model="fQuery" type="search" class="input" :placeholder="t('timeline.search_placeholder')" />
            </div>
            <div>
                <label for="tl-period" class="label">{{ t('common.period') }}</label>
                <select id="tl-period" v-model="fPeriod" class="input">
                    <option value="">{{ t('common.all') }}</option>
                    <option v-for="p in periods" :key="p.code" :value="p.code">{{ p.name }}</option>
                </select>
            </div>
            <div>
                <label for="tl-type" class="label">{{ t('timeline.event_type') }}</label>
                <select id="tl-type" v-model="fType" class="input">
                    <option value="">{{ t('common.all') }}</option>
                    <option v-for="code in EVENT_TYPES" :key="code" :value="code">{{ te('event_type', code) }}</option>
                </select>
            </div>
            <div class="flex items-center justify-between gap-2 sm:col-span-2 lg:col-span-4">
                <p class="text-sm text-muted" aria-live="polite">{{ t('timeline.count', { n: filtered.length }) }}</p>
                <button type="button" class="btn btn-sm" @click="reset">{{ t('common.reset_filters') }}</button>
            </div>
        </div>

        <EmptyState v-if="!filtered.length" icon="timeline" :title="t('timeline.empty')" :compact="compact" />

        <div v-for="group in groups" v-else :key="group.code" class="mb-8 last:mb-0">
            <h2 v-if="!compact" class="sticky top-16 z-10 mb-4 flex flex-wrap items-baseline gap-x-3 bg-bg/95 py-2 text-xl font-bold backdrop-blur">
                {{ group.name }}
                <span v-if="group.start != null || group.end != null" class="font-body text-sm font-medium text-muted">
                    {{ year(group.start) }}<template v-if="group.end != null"> – {{ year(group.end) }}</template>
                </span>
            </h2>
            <ol class="relative ml-2 border-l-2 border-border pl-6" :class="compact ? 'space-y-4' : 'space-y-6'">
                <li v-for="ev in group.items" :key="ev.id" class="relative">
                    <span class="absolute top-1.5 -translate-x-1/2 rounded-full ring-4 ring-bg" :class="dotClass(ev.importance)" style="left: calc(-1.5rem - 1px)" aria-hidden="true"></span>
                    <p class="font-display text-lg font-bold text-primary">{{ dateLabel(ev) }}</p>
                    <div :class="compact ? '' : 'card mt-1 p-4'">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-body text-base font-semibold">{{ ev.title }}</h3>
                            <span class="badge">{{ te('event_type', ev.event_type) }}</span>
                        </div>
                        <p v-if="ev.description" class="mt-1 text-sm text-muted" :class="compact ? 'line-clamp-2' : ''">{{ ev.description }}</p>
                        <Link v-if="ev.subject && !compact" :href="ev.subject.url" class="link mt-2 inline-block text-sm">
                            {{ ev.subject.title }} →
                        </Link>
                    </div>
                </li>
            </ol>
        </div>
    </div>
</template>
