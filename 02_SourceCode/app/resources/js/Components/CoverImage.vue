<script setup>
import { ref } from 'vue';
import Icon from '@/Components/Icon.vue';

// Ảnh bìa có nền dự phòng khi thiếu ảnh hoặc ảnh lỗi
defineProps({
    src: { type: String, default: null },
    alt: { type: String, default: '' },
    icon: { type: String, default: 'landmark' },
    ratio: { type: String, default: 'aspect-[4/3]' },
    eager: { type: Boolean, default: false },
});
const failed = ref(false);
</script>

<template>
    <div class="relative overflow-hidden bg-surface-2" :class="ratio">
        <img
            v-if="src && !failed"
            :src="src"
            :alt="alt"
            :loading="eager ? 'eager' : 'lazy'"
            :fetchpriority="eager ? 'high' : undefined"
            decoding="async"
            class="h-full w-full object-cover"
            @error="failed = true"
        />
        <div v-else class="flex h-full w-full items-center justify-center text-accent" role="img" :aria-label="alt">
            <Icon :name="icon" :size="44" :stroke-width="1.2" />
        </div>
        <slot />
    </div>
</template>
