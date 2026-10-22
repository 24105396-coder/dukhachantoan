<script setup>
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';

/**
 * Nhãn MÔ PHỎNG nổi bật (khung nét đứt, icon bình thí nghiệm, chữ hoa) đặt đầu trang chi tiết cảnh báo
 * sinh từ kịch bản, để không ai nhầm với cảnh báo thật. scenario: { slug, name } | null.
 */
defineProps({
    scenario: { type: Object, default: null },
});
const { t } = useT();
</script>

<template>
    <div class="flex items-start gap-3 rounded-[20px] border-2 border-dashed border-level-2 bg-level-2-soft p-4" role="note" data-simulated>
        <span class="icon-tile"><Icon name="flask" :size="22" /></span>
        <div class="min-w-0">
            <p class="font-display text-lg font-extrabold uppercase tracking-[0.12em]">{{ t('common.simulated_badge') }}</p>
            <p class="text-sm">{{ t('alert.simulated_notice') }}</p>
            <p v-if="scenario?.name" class="mt-1 text-sm font-semibold">{{ t('alert.scenario', { name: scenario.name }) }}</p>
        </div>
    </div>
</template>
