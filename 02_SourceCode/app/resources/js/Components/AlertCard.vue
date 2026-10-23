<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import Badge from '@/Components/Badge.vue';
import SourceStamp from '@/Components/SourceStamp.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { normalizeLevel } from '@/Utils/levels';

/**
 * Thẻ cảnh báo theo Resource AlertCard: mức (màu + icon + chữ), loại hiểm họa, tiêu đề, vùng ảnh hưởng,
 * khung thời gian hiệu lực, trạng thái và dòng nguồn bắt buộc. `compact` rút gọn mô tả (dùng trong lưới dày).
 */
const props = defineProps({
    alert: { type: Object, required: true },
    compact: { type: Boolean, default: false },
    headingLevel: { type: Number, default: 3 },
});
const { t, te } = useT();
const { dateTime } = useFormat();

const level = computed(() => normalizeLevel(props.alert.severity) ?? 1);
const STRIPES = { 0: 'before:bg-level-0', 1: 'before:bg-level-1', 2: 'before:bg-level-2', 3: 'before:bg-level-3', 4: 'before:bg-level-4' };
const zones = computed(() => (props.alert.zones || []).map((z) => z.name).filter(Boolean));
const MAX_ZONES = 3;
const zoneText = computed(() => {
    const list = zones.value.slice(0, MAX_ZONES).join(', ');
    const more = zones.value.length - MAX_ZONES;
    return more > 0 ? `${list} ${t('alert.more_zones', { n: more })}` : list;
});
const isInactive = computed(() => ['expired', 'cancelled'].includes(props.alert.status));
const window_ = computed(() => {
    const from = dateTime(props.alert.starts_at);
    const to = dateTime(props.alert.ends_at);
    if (from && to) return `${from} – ${to}`;
    return from || to;
});
const headingTag = computed(() => `h${Math.min(6, Math.max(2, props.headingLevel))}`);
</script>

<template>
    <article
        class="clay-card-sm relative flex h-full flex-col gap-2.5 overflow-hidden p-4 pl-5 before:absolute before:inset-y-0 before:left-0 before:w-1"
        :class="[STRIPES[level], isInactive ? 'opacity-80' : '']"
        :data-severity="level"
    >
        <div class="flex flex-wrap items-center gap-2">
            <Badge :level="level" :label="alert.severity_label || undefined" show-level-number />
            <span v-if="alert.hazard" class="pill">
                <Icon :name="alert.hazard.icon || alert.hazard.code || 'alert'" :size="14" fallback="alert" />{{ alert.hazard.name }}
            </span>
            <span v-if="alert.status && alert.status !== 'active'" class="pill" :class="isInactive ? 'line-through decoration-1' : ''">{{ te('status', alert.status) }}</span>
        </div>

        <component :is="headingTag" class="font-display text-base font-bold leading-snug md:text-[1.0625rem]">
            <Link v-if="alert.url" :href="alert.url" class="hover:text-link hover:underline focus-visible:underline">{{ alert.headline }}</Link>
            <span v-else>{{ alert.headline }}</span>
        </component>

        <p v-if="!compact && alert.description" class="line-clamp-3 text-sm text-muted">{{ alert.description }}</p>

        <dl class="grid gap-1.5 text-sm">
            <div v-if="zoneText" class="flex items-start gap-2">
                <dt class="mt-0.5 text-muted"><Icon name="map-pin" :size="16" /><span class="sr-only">{{ t('alert.zones') }}</span></dt>
                <dd class="font-medium">{{ zoneText }}</dd>
            </div>
            <div v-if="window_" class="flex items-start gap-2">
                <dt class="mt-0.5 text-muted"><Icon name="calendar-clock" :size="16" /><span class="sr-only">{{ t('alert.valid_time') }}</span></dt>
                <dd>{{ window_ }}</dd>
            </div>
        </dl>

        <div class="mt-auto border-t border-line pt-2.5">
            <SourceStamp :source="alert.source" :updated-at="alert.updated_at_source || alert.issued_at" :simulated="!!alert.is_simulated" />
        </div>
    </article>
</template>
