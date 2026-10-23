<script setup>
import { computed } from 'vue';
import Badge from '@/Components/Badge.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { normalizeLevel, LEVELS } from '@/Utils/levels';

/** Thẻ mức rủi ro quốc gia ở trang chủ: nền trắng, viền trái màu mức + badge + giờ cập nhật. */
const props = defineProps({
    national: { type: Object, default: null }, // { level, label, updated_at }
});
const { t } = useT();
const { stamp } = useFormat();
const level = computed(() => normalizeLevel(props.national?.level));
const TONES = { 0: 'callout-0', 1: 'callout-1', 2: 'callout-2', 3: 'callout-3', 4: 'callout-4' };
const advice = computed(() => (level.value === null ? '' : t(`risk.advice_${level.value}`)));
</script>

<template>
    <section class="callout p-5 md:p-6" :class="level !== null ? TONES[level] : ''" aria-labelledby="national-risk-title">
        <p id="national-risk-title" class="eyebrow">{{ t('home.national_level') }}</p>
        <template v-if="level !== null">
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <span class="icon-tile"><Icon :name="LEVELS[level].icon" :size="22" /></span>
                <div>
                    <Badge :level="level" size="md" :label="national.label || undefined" show-level-number />
                    <p v-if="national.updated_at" class="mt-1.5 text-xs text-muted">{{ t('common.updated') }}: {{ stamp(national.updated_at) }}</p>
                </div>
            </div>
            <p class="mt-4 text-sm font-medium">{{ advice }}</p>
        </template>
        <p v-else class="mt-3 text-sm text-muted">{{ t('risk.no_data') }}</p>
        <slot />
    </section>
</template>
