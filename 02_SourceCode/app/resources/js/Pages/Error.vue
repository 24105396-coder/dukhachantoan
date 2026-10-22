<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import Button from '@/Components/Button.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

// Trang lỗi thân thiện: không hiển thị chi tiết kỹ thuật hay đường dẫn máy chủ
const props = defineProps({
    status: { type: Number, default: 500 },
});
const { t } = useT();
const lr = useLocaleRoute();
const KNOWN = [403, 404, 419, 429, 500, 503];
const code = computed(() => (KNOWN.includes(props.status) ? props.status : 500));
const icon = computed(() => ({ 403: 'lock', 404: 'map', 419: 'clock', 429: 'timer', 503: 'refresh' })[code.value] || 'circle-alert');
const reload = () => window.location.reload();
</script>

<template>
    <Head :title="t(`error.${code}_title`)" />
    <div class="container-page flex min-h-[60vh] items-center justify-center py-12">
        <div class="clay-card max-w-lg p-8 text-center md:p-10">
            <span class="icon-tile mx-auto mb-4 h-16 w-16"><Icon :name="icon" :size="30" /></span>
            <p class="font-display text-7xl font-extrabold leading-none text-link">{{ status }}</p>
            <h1 class="mt-3 text-2xl md:text-3xl">{{ t(`error.${code}_title`) }}</h1>
            <p class="mt-3 text-muted">{{ t(`error.${code}_text`) }}</p>
            <div class="mt-7 flex flex-wrap justify-center gap-2">
                <Button :href="lr('/')" variant="primary" icon="home">{{ t('error.back_home') }}</Button>
                <Button :href="lr('/danh-ba-khan-cap')" icon="phone">{{ t('nav.contacts') }}</Button>
                <Button v-if="code >= 500 || code === 419" icon="refresh" @click="reload">{{ t('error.reload') }}</Button>
            </div>
        </div>
    </div>
</template>
