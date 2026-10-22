<script setup>
/** Khung chờ khi tải danh sách: `count` thẻ giả theo lưới `cols` (1–3). */
defineProps({
    count: { type: Number, default: 3 },
    cols: { type: Number, default: 3 },
    height: { type: String, default: 'h-40' },
});
const GRID = { 1: '', 2: 'md:grid-cols-2', 3: 'md:grid-cols-2 lg:grid-cols-3' };
</script>

<template>
    <div class="grid gap-4" :class="GRID[cols] ?? GRID[3]" aria-hidden="true">
        <div v-for="i in count" :key="i" class="clay-card-sm space-y-3 p-5">
            <div class="skeleton h-5 w-1/3"></div>
            <div class="skeleton w-full" :class="height"></div>
            <div class="skeleton h-4 w-2/3"></div>
        </div>
    </div>
</template>
