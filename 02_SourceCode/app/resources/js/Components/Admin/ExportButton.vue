<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

/**
 * Nút "Xuất Excel" nhỏ ở header các màn Admin index: tải `admin.exports.download` (/quan-tri/xuat-excel/{dataset})
 * kèm đúng bộ lọc đang áp dụng (query hiện tại, bỏ `page`). Dùng <a download> vì là tệp, không phải trang Inertia.
 * dataset ∈ tong-hop | canh-bao | hanh-trinh | thong-bao | an-toan | su-co | danh-ba | nhat-ky.
 */
const props = defineProps({
    dataset: { type: String, required: true },
    href: { type: String, default: null },
});
const page = usePage();
const { t } = useT();
const lr = useLocaleRoute();

/** Query của URL hiện tại (bộ lọc FilterBar), bỏ tham số phân trang. */
const currentQuery = computed(() => {
    const qs = (page.url || '').split('?')[1] || '';
    const params = new URLSearchParams(qs);
    params.delete('page');
    return params.toString();
});
/** URL tải: ưu tiên `href` máy chủ cấp (datasets[].url), nếu không tự dựng từ dataset + bộ lọc. */
const url = computed(() => {
    if (props.href) return props.href;
    const base = lr(`/quan-tri/xuat-excel/${encodeURIComponent(props.dataset)}`);
    return currentQuery.value ? `${base}?${currentQuery.value}` : base;
});
</script>

<template>
    <a :href="url" class="clay-btn clay-btn-sm" download :data-export="dataset">
        <Icon name="download" :size="16" />
        <span>{{ t('ui.exports.button') }}</span>
        <span class="sr-only">{{ t('ui.exports.button_hint') }}</span>
    </a>
</template>
