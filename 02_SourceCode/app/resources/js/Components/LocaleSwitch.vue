<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';

/** Chuyển vi ↔ en: tải lại toàn trang để <html lang> và nội dung dữ liệu đúng locale. */
defineProps({
    compact: { type: Boolean, default: false },
});
const page = usePage();
const { t } = useT();

const other = computed(() => {
    const list = page.props.locales || [
        { code: 'vi', label: 'Tiếng Việt' },
        { code: 'en', label: 'English' },
    ];
    return list.find((l) => l.code !== page.props.locale) || list[0];
});
const href = computed(() => page.props.currentUrlOtherLocale || `/${other.value.code}`);
</script>

<template>
    <a
        :href="href"
        class="clay-btn clay-btn-sm"
        :class="compact ? 'clay-btn-icon' : ''"
        :hreflang="other.code"
        :lang="other.code"
        :aria-label="t('common.switch_language', { language: other.label })"
    >
        <Icon name="languages" :size="16" />
        <span v-if="!compact" class="uppercase">{{ other.code }}</span>
    </a>
</template>
