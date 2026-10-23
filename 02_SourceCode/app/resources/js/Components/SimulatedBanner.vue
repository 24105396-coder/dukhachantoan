<script setup>
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';

/**
 * Nhãn MÔ PHỎNG (nền trắng, viền trái màu mức 2, icon bình thí nghiệm, chữ hoa) đặt đầu trang chi tiết cảnh báo
 * sinh từ kịch bản, để không ai nhầm với cảnh báo thật. scenario: { slug, name } | null.
 */
defineProps({
    scenario: { type: Object, default: null },
});
const { t } = useT();
</script>

<template>
    <div class="callout callout-2 flex items-start gap-3 px-4 py-3" role="note" data-simulated>
        <span class="icon-tile h-9 w-9"><Icon name="flask" :size="18" /></span>
        <div class="min-w-0">
            <p class="text-sm font-extrabold uppercase tracking-[0.08em]">{{ t('common.simulated_badge') }}</p>
            <p class="text-sm">{{ t('alert.simulated_notice') }}</p>
            <p v-if="scenario?.name" class="mt-1 text-sm font-semibold">{{ t('alert.scenario', { name: scenario.name }) }}</p>
        </div>
    </div>
</template>
