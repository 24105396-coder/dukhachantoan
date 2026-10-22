<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

/** Một mục menu dashboard dạng pill; `rail` = chỉ icon (thanh 80px), nhãn vẫn có cho trình đọc màn hình. */
defineProps({
    href: { type: String, required: true },
    label: { type: String, required: true },
    icon: { type: String, required: true },
    active: { type: Boolean, default: false },
    rail: { type: Boolean, default: false },
    badge: { type: Number, default: 0 },
});
</script>

<template>
    <Link
        :href="href"
        class="relative flex items-center gap-3 rounded-full font-semibold transition-colors"
        :class="[
            rail ? 'h-12 w-12 justify-center' : 'px-4 py-2.5 text-sm',
            active ? 'bg-linear-to-br from-brand-400 to-brand-500 text-on-brand shadow-clay-btn' : 'hover:bg-soft',
        ]"
        :aria-current="active ? 'page' : undefined"
        :title="rail ? label : undefined"
    >
        <Icon :name="icon" :size="rail ? 22 : 18" />
        <span :class="rail ? 'sr-only' : 'min-w-0 flex-1 truncate'">{{ label }}</span>
        <span
            v-if="badge"
            class="rounded-full bg-level-4 px-1.5 text-xs font-bold leading-5 text-level-4-fg"
            :class="rail ? 'absolute -right-1 -top-1' : ''"
        >{{ badge > 99 ? '99+' : badge }}</span>
    </Link>
</template>
