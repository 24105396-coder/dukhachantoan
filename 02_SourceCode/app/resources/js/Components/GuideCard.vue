<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import Badge from '@/Components/Badge.vue';
import { useT } from '@/Composables/useT';

/** Thẻ hướng dẫn ứng phó theo Resource GuideCard. */
defineProps({
    guide: { type: Object, required: true },
});
const { t } = useT();
</script>

<template>
    <article class="clay-card-sm flex h-full flex-col gap-2.5 p-4">
        <div class="flex items-center gap-3">
            <span class="icon-tile h-9 w-9"><Icon :name="guide.hazard?.icon || guide.hazard?.code || 'life-buoy'" fallback="life-buoy" /></span>
            <span v-if="guide.hazard?.name" class="eyebrow">{{ guide.hazard.name }}</span>
        </div>
        <h3 class="font-display text-base font-bold leading-snug md:text-[1.0625rem]">
            <Link v-if="guide.url" :href="guide.url" class="hover:text-link hover:underline">{{ guide.title }}</Link>
            <span v-else>{{ guide.title }}</span>
        </h3>
        <p v-if="guide.summary" class="line-clamp-3 text-sm text-muted">{{ guide.summary }}</p>
        <div v-if="guide.min_severity" class="mt-auto">
            <Badge :level="guide.min_severity" size="sm" soft :label="t('guide.from_level', { label: t(`enums.levels.${guide.min_severity}`) })" />
        </div>
    </article>
</template>
