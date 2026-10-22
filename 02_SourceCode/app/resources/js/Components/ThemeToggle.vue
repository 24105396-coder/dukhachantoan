<script setup>
import { onMounted } from 'vue';
import Icon from '@/Components/Icon.vue';
import { useTheme } from '@/Composables/useTheme';
import { useT } from '@/Composables/useT';

const { theme, toggle, syncFromUser } = useTheme();
const { t } = useT();

onMounted(syncFromUser);
</script>

<template>
    <!-- Nút bật/tắt: nhãn cố định "Giao diện tối", trạng thái qua aria-pressed; title mô tả hành động kế tiếp -->
    <button
        type="button"
        class="btn btn-ghost btn-icon"
        :aria-pressed="theme === 'dark'"
        :aria-label="t('common.theme_dark_toggle')"
        :title="theme === 'dark' ? t('common.theme_light') : t('common.theme_dark')"
        @click="toggle"
    >
        <Icon :name="theme === 'dark' ? 'sun' : 'moon'" />
    </button>
</template>
