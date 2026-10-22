<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useT } from '@/Composables/useT';

/**
 * Thẻ <title> + meta description/Open Graph cho trang công khai.
 * head-key trùng thuộc tính `inertia` mà app.blade.php render sẵn phía máy chủ,
 * nên Inertia thay thế thẻ cũ thay vì thêm bản trùng.
 * Mô tả lấy từ dữ liệu (summary/description), nếu trống dùng khóa lang `fallbackKey`, cuối cùng là mô tả mặc định.
 */
const props = defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    fallbackKey: { type: String, default: '' },
    image: { type: String, default: '' },
});
const { t, has } = useT();
const MAX_LENGTH = 160;

const text = computed(() => {
    const raw = props.description?.trim()
        || (props.fallbackKey && has(props.fallbackKey) ? t(props.fallbackKey) : '')
        || t('meta.default_description');
    const clean = raw.replace(/\s+/g, ' ');
    return clean.length > MAX_LENGTH ? `${clean.slice(0, MAX_LENGTH - 1).trimEnd()}…` : clean;
});
</script>

<template>
    <Head :title="title">
        <meta head-key="description" name="description" :content="text" />
        <meta head-key="og:title" property="og:title" :content="title || 'DiSanSo'" />
        <meta head-key="og:description" property="og:description" :content="text" />
        <meta v-if="image" head-key="og:image" property="og:image" :content="image" />
        <slot />
    </Head>
</template>
