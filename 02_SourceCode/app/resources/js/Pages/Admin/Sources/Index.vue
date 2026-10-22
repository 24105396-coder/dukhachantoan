<script setup>
import { reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import Badge from '@/Components/Badge.vue';
import Button from '@/Components/Button.vue';
import Icon from '@/Components/Icon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

/** Trạng thái các nguồn dữ liệu cảnh báo/thời tiết: lần lấy gần nhất, kết quả, lỗi; nút "Lấy ngay" (POST /quan-tri/nguon/lay-ngay). */
defineProps({
    sources: { type: Array, default: () => [] },
});
const { t } = useT();
const { dateTime, ago } = useFormat();
const lr = useLocaleRoute();
const fetching = reactive({});
const PROVIDER_ICON = { openmeteo: 'cloud-sun', manual: 'edit', simulated: 'flask' };

function fetchNow(s) {
    fetching[s.code] = true;
    router.post(lr('/quan-tri/nguon/lay-ngay'), { code: s.code }, { preserveScroll: true, onFinish: () => (fetching[s.code] = false) });
}
</script>

<template>
    <Head :title="t('admin.nav.sources')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.nav.sources')" :description="t('admin.sources.intro')" />
    <ul v-if="sources.length" class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
        <li v-for="s in sources" :key="s.code">
            <article class="clay-card-sm flex h-full flex-col gap-3 p-5" :class="s.last_status === 'error' ? 'border-level-4' : ''">
                <div class="flex items-start gap-3">
                    <span class="icon-tile"><Icon :name="PROVIDER_ICON[s.provider] || 'radio'" :size="22" /></span>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-display text-lg font-bold leading-snug">{{ s.name }}</h2>
                        <p class="font-mono text-xs text-muted">{{ s.code }} · {{ s.provider }}</p>
                    </div>
                    <Badge v-if="!s.is_active" tone="outline" size="sm">{{ t('admin.sources.inactive') }}</Badge>
                    <Badge v-else-if="s.last_status === 'error'" tone="danger" size="sm" icon="circle-x">{{ t('admin.sources.error') }}</Badge>
                    <Badge v-else-if="s.last_status === 'ok'" tone="brand" size="sm" icon="check">{{ t('admin.sources.ok') }}</Badge>
                    <Badge v-else tone="neutral" size="sm">{{ t('admin.sources.never') }}</Badge>
                </div>
                <dl class="grid gap-1 text-sm">
                    <div class="flex flex-wrap gap-x-2"><dt class="text-muted">{{ t('admin.sources.last_polled') }}:</dt><dd><time v-if="s.last_polled_at" :datetime="s.last_polled_at" :title="dateTime(s.last_polled_at)">{{ ago(s.last_polled_at) }}</time><span v-else>—</span></dd></div>
                </dl>
                <p v-if="s.last_error" class="break-words rounded-xl bg-level-4-soft p-2 text-xs" role="status">{{ s.last_error }}</p>
                <div class="mt-auto pt-1">
                    <Button size="sm" icon="refresh" :loading="!!fetching[s.code]" :disabled="!s.is_active" @click="fetchNow(s)">{{ t('admin.sources.fetch_now') }}</Button>
                </div>
            </article>
        </li>
    </ul>
    <EmptyState v-else :title="t('about.no_sources')" />
</template>
