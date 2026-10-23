<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import Badge from '@/Components/Badge.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { normalizeLevel } from '@/Utils/levels';

/** Thẻ hành trình theo Resource TripCard: mã, tên, khoảng ngày, badge mức rủi ro, chặng kế tiếp, báo an toàn gần nhất. */
const props = defineProps({
    trip: { type: Object, required: true },
    headingLevel: { type: Number, default: 3 },
});
const { t, te } = useT();
const { date, dateTime, ago } = useFormat();
const level = computed(() => normalizeLevel(props.trip.risk_level) ?? 0);
const STRIPES = { 0: 'before:bg-level-0', 1: 'before:bg-level-1', 2: 'before:bg-level-2', 3: 'before:bg-level-3', 4: 'before:bg-level-4' };
const headingTag = computed(() => `h${Math.min(6, Math.max(2, props.headingLevel))}`);
const isInactive = computed(() => ['cancelled', 'completed'].includes(props.trip.status));
</script>

<template>
    <article
        class="clay-card-sm relative flex h-full flex-col gap-2.5 overflow-hidden p-4 pl-5 before:absolute before:inset-y-0 before:left-0 before:w-1"
        :class="[STRIPES[level], isInactive ? 'opacity-80' : '']"
        :data-risk="level"
    >
        <div class="flex flex-wrap items-center gap-2">
            <Badge :level="level" size="sm" />
            <span class="pill">{{ te('status', trip.status) }}</span>
            <span class="ml-auto font-mono text-xs text-muted">{{ trip.code }}</span>
        </div>
        <component :is="headingTag" class="font-display text-base font-bold leading-snug md:text-[1.0625rem]">
            <Link :href="trip.url || '#'" class="hover:text-link hover:underline">{{ trip.title }}</Link>
        </component>
        <dl class="grid gap-1.5 text-sm">
            <div class="flex items-start gap-2">
                <dt class="mt-0.5 text-muted"><Icon name="calendar" :size="16" /><span class="sr-only">{{ t('trip.dates') }}</span></dt>
                <dd>{{ date(trip.start_date) }} – {{ date(trip.end_date) }}</dd>
            </div>
            <div class="flex items-start gap-2">
                <dt class="mt-0.5 text-muted"><Icon name="users" :size="16" /><span class="sr-only">{{ t('trip.group_size') }}</span></dt>
                <dd>{{ t('trip.people', { n: trip.group_size ?? 1 }) }} · {{ t('trip.legs_count', { n: trip.legs_count ?? 0 }) }}</dd>
            </div>
            <div v-if="trip.next_leg" class="flex items-start gap-2">
                <dt class="mt-0.5 text-muted"><Icon name="navigation" :size="16" /><span class="sr-only">{{ t('trip.next_leg') }}</span></dt>
                <dd>
                    <span class="font-semibold">{{ trip.next_leg.title || trip.next_leg.zone?.name }}</span>
                    <span class="block text-xs text-muted">{{ dateTime(trip.next_leg.starts_at) }}</span>
                </dd>
            </div>
            <div v-if="trip.partner?.name" class="flex items-start gap-2">
                <dt class="mt-0.5 text-muted"><Icon name="building" :size="16" /><span class="sr-only">{{ t('trip.partner') }}</span></dt>
                <dd>{{ trip.partner.name }}</dd>
            </div>
        </dl>
        <div class="mt-auto flex flex-wrap items-center gap-2 border-t border-line pt-3 text-xs">
            <span v-if="trip.active_alerts_count" class="pill bg-level-3-soft">
                <Icon name="triangle-alert" :size="14" />{{ t('trip.active_alerts', { n: trip.active_alerts_count }) }}
            </span>
            <span v-if="trip.last_checkin" class="text-muted">
                {{ t('trip.last_checkin') }}: <strong class="text-fg">{{ te('checkin_status', trip.last_checkin.status) }}</strong> · {{ ago(trip.last_checkin.checked_at) }}
            </span>
            <span v-else class="text-muted">{{ t('trip.no_checkin') }}</span>
        </div>
    </article>
</template>
