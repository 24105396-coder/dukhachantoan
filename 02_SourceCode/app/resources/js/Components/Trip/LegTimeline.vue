<script setup>
import Icon from '@/Components/Icon.vue';
import Badge from '@/Components/Badge.vue';
import SourceStamp from '@/Components/SourceStamp.vue';
import { Link } from '@inertiajs/vue3';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { normalizeLevel } from '@/Utils/levels';

/** Dòng thời gian các chặng (LegItem[]): số thứ tự, vùng/điểm đến, giờ, lưu trú, phương tiện, badge mức, cảnh báo trên chặng. */
defineProps({
    legs: { type: Array, default: () => [] },
    showAlerts: { type: Boolean, default: true },
});
const { t } = useT();
const { dateTime } = useFormat();
const DOT = {
    0: 'bg-level-0 text-level-0-fg', 1: 'bg-level-1 text-level-1-fg', 2: 'bg-level-2 text-level-2-fg',
    3: 'bg-level-3 text-level-3-fg', 4: 'bg-level-4 text-level-4-fg',
};
const lv = (v) => normalizeLevel(v) ?? 0;
</script>

<template>
    <ol class="relative space-y-4 border-l-2 border-dashed border-line pl-6">
        <li v-for="leg in legs" :key="leg.id ?? leg.leg_order" class="relative">
            <span
                class="absolute -left-[2.1rem] top-1 flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold ring-4 ring-[var(--bg-surface)]"
                :class="DOT[lv(leg.risk_level)]"
                aria-hidden="true"
            >{{ leg.leg_order }}</span>
            <div class="clay-card-sm p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-display text-base font-bold">
                        <span class="sr-only">{{ t('trip.leg_n', { n: leg.leg_order }) }}: </span>{{ leg.title || leg.zone?.name }}
                    </h3>
                    <Badge :level="lv(leg.risk_level)" size="sm" class="ml-auto" />
                </div>
                <p class="mt-1 text-sm text-muted">
                    <Icon name="map-pin" :size="14" class="inline align-[-2px]" />
                    {{ leg.zone?.name }}<template v-if="leg.destination?.name"> · {{ leg.destination.name }}</template>
                </p>
                <p class="mt-1 text-sm"><time :datetime="leg.starts_at">{{ dateTime(leg.starts_at) }}</time> → <time :datetime="leg.ends_at">{{ dateTime(leg.ends_at) }}</time></p>
                <dl v-if="leg.lodging || leg.transport || leg.note" class="mt-2 grid gap-1 text-sm">
                    <div v-if="leg.lodging" class="flex gap-2"><dt class="text-muted"><Icon name="bed" :size="16" /><span class="sr-only">{{ t('trip.lodging') }}</span></dt><dd>{{ leg.lodging }}</dd></div>
                    <div v-if="leg.transport" class="flex gap-2"><dt class="text-muted"><Icon name="bus" :size="16" /><span class="sr-only">{{ t('trip.transport') }}</span></dt><dd>{{ leg.transport }}</dd></div>
                    <div v-if="leg.note" class="flex gap-2"><dt class="text-muted"><Icon name="message" :size="16" /><span class="sr-only">{{ t('common.note') }}</span></dt><dd>{{ leg.note }}</dd></div>
                </dl>
                <p v-if="leg.destination?.safety_note" class="mt-2 rounded-xl bg-lemon-100 p-2 text-xs">
                    <Icon name="info" :size="14" class="inline align-[-2px]" /> {{ leg.destination.safety_note }}
                </p>
                <ul v-if="showAlerts && leg.alerts?.length" class="mt-3 space-y-2">
                    <li v-for="a in leg.alerts" :key="a.code" class="rounded-xl border border-line bg-soft p-2.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge :level="a.severity" size="sm" />
                            <Link :href="a.url || '#'" class="link text-sm">{{ a.headline }}</Link>
                        </div>
                        <SourceStamp class="mt-1" :source="a.source" :updated-at="a.updated_at_source || a.issued_at" :simulated="!!a.is_simulated" />
                    </li>
                </ul>
            </div>
        </li>
    </ol>
</template>
