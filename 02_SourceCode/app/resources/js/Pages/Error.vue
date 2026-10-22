<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
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
const icon = computed(() => ({ 403: 'shield', 404: 'map', 419: 'clock', 429: 'clock', 503: 'refresh' })[code.value] || 'alert');
const reload = () => window.location.reload();
</script>

<template>
    <Head :title="t(`error.${code}_title`)" />
    <div class="container-page flex min-h-[60vh] items-center justify-center py-16">
        <div class="max-w-lg text-center">
            <span class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-primary-soft text-primary"><Icon :name="icon" :size="30" /></span>
            <p class="font-display text-7xl font-bold text-primary">{{ status }}</p>
            <h1 class="mt-3 text-2xl font-bold md:text-3xl">{{ t(`error.${code}_title`) }}</h1>
            <p class="mt-3 text-muted">{{ t(`error.${code}_text`) }}</p>
            <div class="mt-7 flex flex-wrap justify-center gap-2">
                <Link :href="lr('/')" class="btn btn-primary">{{ t('error.back_home') }}</Link>
                <Link :href="lr('/tim-kiem')" class="btn"><Icon name="search" :size="18" />{{ t('nav.search') }}</Link>
                <button v-if="code >= 500 || code === 419" type="button" class="btn" @click="reload"><Icon name="refresh" :size="18" />{{ t('error.reload') }}</button>
            </div>
        </div>
    </div>
</template>
