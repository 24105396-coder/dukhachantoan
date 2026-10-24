<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

/**
 * Chú giải nguồn dữ liệu: "● Dữ liệu thật – Open-Meteo/cơ quan · ◌ Mô phỏng – kịch bản diễn tập".
 * Đặt dưới bộ lọc/tiêu đề các trang có cảnh báo, thời tiết (Home, Alerts/Index, Map, Weather, Báo cáo).
 * `showLink` thêm liên kết tới mục "Nguồn dữ liệu" của trang Giới thiệu.
 */
defineProps({
    showLink: { type: Boolean, default: true },
});
const { t } = useT();
const lr = useLocaleRoute();
</script>

<template>
    <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted" role="note" data-legend>
        <span class="sr-only">{{ t('ui.legend.title') }}:</span>
        <span class="inline-flex items-center gap-1.5">
            <span class="inline-block h-2.5 w-2.5 rounded-full bg-brand-500" aria-hidden="true"></span>
            <span><strong class="font-semibold text-fg">{{ t('ui.legend.real') }}</strong> – {{ t('ui.legend.real_hint') }}</span>
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="inline-block h-2.5 w-2.5 rounded-full border border-dashed border-[var(--text-muted)]" aria-hidden="true"></span>
            <Icon name="flask" :size="12" />
            <span><strong class="font-semibold text-fg">{{ t('common.simulated') }}</strong> – {{ t('ui.legend.simulated_hint') }}</span>
        </span>
        <Link v-if="showLink" :href="`${lr('/gioi-thieu')}#nguon-du-lieu`" class="link font-semibold">{{ t('ui.legend.learn_more') }}</Link>
    </p>
</template>
