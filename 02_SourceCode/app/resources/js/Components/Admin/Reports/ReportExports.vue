<script setup>
import Icon from '@/Components/Icon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

/**
 * Khối "Xuất Excel": mỗi bộ dữ liệu (datasets[] = { key, label, count, url }) một dòng với số dòng và nút tải
 * `<a download>`; url do máy chủ dựng sẵn kèm bộ lọc hiện tại. Tệp .xlsx có sheet Meta ghi phạm vi và nhãn mô phỏng.
 */
defineProps({
    datasets: { type: Array, default: () => [] },
});
const { t } = useT();
const { number } = useFormat();
/** Chỉ nhận URL nội bộ hoặc http(s). */
const isSafeHref = (url) => typeof url === 'string' && (/^\/(?!\/)/.test(url) || /^https?:\/\//i.test(url));
</script>

<template>
    <section class="clay-card-sm p-4 md:p-5" aria-labelledby="report-exports-title">
        <h2 id="report-exports-title" class="section-title text-lg">{{ t('ui.exports.title') }}</h2>
        <p class="mt-1 text-sm text-muted">{{ t('ui.exports.intro') }}</p>
        <ul v-if="datasets.length" class="mt-3 grid gap-2 md:grid-cols-2">
            <li v-for="d in datasets" :key="d.key" class="flex items-center gap-3 rounded-[var(--radius-card-sm)] border border-line p-3">
                <span class="icon-tile h-9 w-9"><Icon name="file" :size="18" /></span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold">{{ d.label }}</p>
                    <p class="text-xs text-muted">{{ t('ui.exports.rows', { n: number(d.count ?? 0) }) }}</p>
                </div>
                <a v-if="isSafeHref(d.url)" :href="d.url" download class="clay-btn clay-btn-sm" :data-export="d.key">
                    <Icon name="download" :size="16" /><span class="hidden sm:inline">{{ t('ui.exports.download') }}</span>
                    <span class="sr-only">{{ d.label }} (.xlsx)</span>
                </a>
            </li>
        </ul>
        <EmptyState v-else class="mt-3" :title="t('ui.exports.empty')" compact />
    </section>
</template>
