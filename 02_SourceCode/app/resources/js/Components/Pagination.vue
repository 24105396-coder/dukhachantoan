<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useT } from '@/Composables/useT';

// Nhận paginator chuẩn Laravel (toArray): links[], from, to, total
const props = defineProps({
    paginator: { type: Object, default: null },
    links: { type: Array, default: null },
});
const { t } = useT();

// Prop phân trang dạng phẳng (paginator->toArray()): không có khóa meta
const items = computed(() => props.links || props.paginator?.links || []);
const meta = computed(() => props.paginator || {});

// Laravel trả nhãn "&laquo; Previous" / "Next &raquo;": thay bằng chuỗi dịch
function label(link, i) {
    if (i === 0) return t('common.previous');
    if (i === items.value.length - 1) return t('common.next');
    return link.label;
}
</script>

<template>
    <nav v-if="items.length > 3" class="mt-8 flex flex-col items-center gap-3" :aria-label="t('common.pagination')">
        <p v-if="meta.total" class="text-sm text-muted">{{ t('common.showing', { from: meta.from ?? 0, to: meta.to ?? 0, total: meta.total }) }}</p>
        <ul class="flex flex-wrap justify-center gap-1">
            <li v-for="(link, i) in items" :key="i">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="btn btn-sm min-w-9"
                    :class="link.active ? 'btn-primary' : ''"
                    :aria-current="link.active ? 'page' : undefined"
                    preserve-scroll
                >
                    {{ label(link, i) }}
                </Link>
                <span v-else class="btn btn-sm min-w-9" aria-disabled="true">{{ label(link, i) }}</span>
            </li>
        </ul>
    </nav>
</template>
