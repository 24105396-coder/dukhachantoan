<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Badge from '@/Components/Badge.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { normalizeLevel } from '@/Utils/levels';

/** Thanh trạng thái rủi ro quốc gia từ shared prop `risk` { national_level, active_alerts, updated_at }: nền trắng, viền trái màu mức. */
const page = usePage();
const { t } = useT();
const { stamp } = useFormat();
const lr = useLocaleRoute();

const risk = computed(() => page.props.risk || null);
const level = computed(() => normalizeLevel(risk.value?.national_level));
const TONES = { 0: 'callout-0', 1: 'callout-1', 2: 'callout-2', 3: 'callout-3', 4: 'callout-4' };
</script>

<template>
    <div v-if="risk && level !== null" class="container-page relative z-10 mt-3" data-print-hide>
        <div class="callout flex flex-wrap items-center gap-x-4 gap-y-1.5 px-4 py-2 text-sm" :class="TONES[level]" role="status">
            <span class="font-semibold">{{ t('home.national_level') }}</span>
            <Badge :level="level" size="sm" />
            <Link :href="lr('/canh-bao')" class="inline-flex items-center gap-1 font-semibold text-link hover:underline">
                <Icon name="bell-ring" :size="16" />{{ t('risk.active_alerts', { n: risk.active_alerts ?? 0 }) }}
            </Link>
            <span v-if="risk.updated_at" class="text-xs text-muted sm:ml-auto">{{ t('common.updated') }}: {{ stamp(risk.updated_at) }}</span>
        </div>
    </div>
</template>
