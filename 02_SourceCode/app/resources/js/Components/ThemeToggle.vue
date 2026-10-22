<script setup>
import { onMounted } from 'vue';
import Icon from '@/Components/Icon.vue';
import { useTheme } from '@/Composables/useTheme';
import { useT } from '@/Composables/useT';

/** Nút tròn bật/tắt theme "Trực ban đêm"; trạng thái qua aria-pressed, title mô tả hành động kế tiếp. */
const { theme, toggle, syncFromUser } = useTheme();
const { t } = useT();

onMounted(syncFromUser);
</script>

<template>
    <button
        type="button"
        class="clay-btn clay-btn-icon rounded-full bg-raised"
        :aria-pressed="theme === 'dark' ? 'true' : 'false'"
        :aria-label="t('common.theme_dark_toggle')"
        :title="theme === 'dark' ? t('common.theme_light') : t('common.theme_dark')"
        @click="toggle"
    >
        <Icon :name="theme === 'dark' ? 'sun' : 'moon'" />
    </button>
</template>
