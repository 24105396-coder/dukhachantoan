<script setup>
import { useT } from '@/Composables/useT';

defineProps({
    count: { type: Number, default: 6 },
    variant: { type: String, default: 'card' }, // card | row
});
const { t } = useT();
</script>

<template>
    <div role="status" :aria-label="t('common.loading')">
        <div v-if="variant === 'card'" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="i in count" :key="i" class="card overflow-hidden">
                <div class="skeleton aspect-[4/3] rounded-none"></div>
                <div class="space-y-2 p-4">
                    <div class="skeleton h-3 w-1/3"></div>
                    <div class="skeleton h-5 w-3/4"></div>
                    <div class="skeleton h-3 w-full"></div>
                    <div class="skeleton h-3 w-5/6"></div>
                </div>
            </div>
        </div>
        <div v-else class="space-y-2">
            <div v-for="i in count" :key="i" class="skeleton h-12 w-full"></div>
        </div>
        <span class="sr-only">{{ t('common.loading') }}</span>
    </div>
</template>
