<script setup>
import { Link } from '@inertiajs/vue3';

// Tiêu đề khối nội dung với liên kết "xem tất cả" tùy chọn
defineProps({
    title: { type: String, required: true },
    eyebrow: { type: String, default: '' },
    href: { type: String, default: null },
    linkText: { type: String, default: '' },
});
</script>

<template>
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <p v-if="eyebrow" class="eyebrow mb-1">{{ eyebrow }}</p>
            <h2 class="section-title">{{ title }}</h2>
        </div>
        <Link v-if="href" :href="href" class="link text-sm font-semibold">{{ linkText }} →</Link>
    </div>
</template>
