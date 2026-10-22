<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';

const page = usePage();
const { t } = useT();

const other = computed(() => {
    const list = page.props.locales || [
        { code: 'vi', label: 'Tiếng Việt' },
        { code: 'en', label: 'English' },
    ];
    return list.find((l) => l.code !== page.props.locale) || list[0];
});

// Đổi ngôn ngữ = tải lại toàn trang để <html lang> và bản dịch nội dung đúng locale
const href = computed(() => page.props.currentUrlOtherLocale || `/${other.value.code}`);
</script>

<template>
    <a
        :href="href"
        class="btn btn-ghost btn-sm"
        :hreflang="other.code"
        :lang="other.code"
        :aria-label="t('common.switch_language', { language: other.label })"
    >
        <Icon name="globe" :size="18" />
        <span class="uppercase">{{ other.code }}</span>
    </a>
</template>
