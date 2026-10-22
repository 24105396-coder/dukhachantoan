<script setup>
// Danh sách thông tin dạng nhãn – giá trị; bỏ qua mục rỗng
defineProps({
    items: { type: Array, default: () => [] }, // [{ label, value, href? }]
    title: { type: String, default: '' },
});
</script>

<template>
    <div class="card p-4">
        <h2 v-if="title" class="mb-3 font-body text-base font-bold">{{ title }}</h2>
        <dl class="space-y-3 text-sm">
            <template v-for="(item, i) in items" :key="i">
                <div v-if="item.value !== null && item.value !== undefined && item.value !== ''">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-muted">{{ item.label }}</dt>
                    <dd class="mt-0.5 break-words">
                        <a v-if="item.href" :href="item.href" target="_blank" rel="noopener noreferrer" class="link">{{ item.value }}</a>
                        <template v-else>{{ item.value }}</template>
                    </dd>
                </div>
            </template>
        </dl>
    </div>
</template>
