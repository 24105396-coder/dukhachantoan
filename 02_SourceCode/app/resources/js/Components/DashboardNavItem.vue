<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

/** Một mục menu dashboard (bo 8px, mục chọn nền ngọc nhạt); `rail` = chỉ icon (thanh 80px), nhãn vẫn có cho trình đọc màn hình. */
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
        class="relative flex items-center gap-3 rounded-lg font-semibold transition-colors"
        :class="[
            rail ? 'h-11 w-11 justify-center' : 'px-3 py-2 text-sm',
            active ? 'is-selected' : 'text-muted hover:bg-soft hover:text-fg',
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
