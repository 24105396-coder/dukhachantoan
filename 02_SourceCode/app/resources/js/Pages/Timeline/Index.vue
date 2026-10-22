<script setup>

import PageHeader from '@/Components/PageHeader.vue';
import TimelineView from '@/Components/TimelineView.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import PageMeta from '@/Components/Public/PageMeta.vue';

defineProps({
    events: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});
const { t } = useT();
const { year } = useFormat();
</script>

<template>
    <PageMeta :title="t('timeline.title')" fallback-key="timeline.description" />
    <div class="container-page py-8">
        <PageHeader :title="t('timeline.title')" :eyebrow="t('timeline.eyebrow')" :description="t('timeline.description')" />

        <nav v-if="periods.length" class="mb-6 overflow-x-auto pb-2" :aria-label="t('timeline.periods_label')">
            <ol class="flex min-w-max gap-2">
                <li v-for="p in periods" :key="p.code" class="rounded-xl border border-border bg-surface px-3 py-2 text-sm">
                    <span class="block font-semibold">{{ p.name }}</span>
                    <span class="text-xs text-muted">{{ year(p.start_year) }}<template v-if="p.end_year != null"> – {{ year(p.end_year) }}</template></span>
                </li>
            </ol>
        </nav>

        <div class="max-w-4xl">
            <TimelineView :events="events" :periods="periods" filterable :initial-filters="filters" />
        </div>
    </div>
</template>
