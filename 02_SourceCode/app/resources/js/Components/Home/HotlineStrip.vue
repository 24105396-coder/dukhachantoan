<script setup>
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';

/** Số khẩn cấp toàn quốc (cố định theo quy định): bấm để gọi trên điện thoại. */
const { t } = useT();
const HOTLINES = [
    { number: '112', icon: 'life-buoy' },
    { number: '113', icon: 'shield' },
    { number: '114', icon: 'flame' },
    { number: '115', icon: 'ambulance' },
];
</script>

<template>
    <ul class="flex flex-wrap gap-2" :aria-label="t('hotline.title')">
        <li v-for="h in HOTLINES" :key="h.number">
            <a :href="`tel:${h.number}`" class="pill bg-raised py-1.5 hover:border-brand-400" :aria-label="t('hotline.call', { number: h.number, name: t(`hotline.${h.number}`) })">
                <Icon :name="h.icon" :size="16" />
                <span class="font-display text-base font-bold">{{ h.number }}</span>
                <span class="text-xs text-muted">{{ t(`hotline.${h.number}`) }}</span>
            </a>
        </li>
    </ul>
</template>
