<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

/** Danh sách báo "cần hỗ trợ" chưa phản hồi (CheckinItem) – ưu tiên xử lý trên dashboard. */
defineProps({
    items: { type: Array, default: () => [] },
});
const { t } = useT();
const { ago, dateTime } = useFormat();
const lr = useLocaleRoute();
</script>

<template>
    <section class="clay-card-sm flex flex-col p-4 md:p-5" aria-labelledby="need-help-title">
        <div class="mb-3 flex items-center justify-between gap-2">
            <h2 id="need-help-title" class="section-title text-lg">{{ t('dashboard.need_help_title') }}</h2>
            <Link :href="lr('/quan-tri/an-toan?status=need_help')" class="link text-sm">{{ t('common.view_all') }}</Link>
        </div>
        <ul v-if="items.length" class="space-y-2">
            <li v-for="c in items" :key="c.id" class="rounded-2xl border border-level-4 bg-level-4-soft p-3">
                <div class="flex items-start gap-2">
                    <Icon name="siren" :size="18" class="mt-0.5" />
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ c.user?.name || t('dashboard.anonymous') }}</p>
                        <p v-if="c.message" class="line-clamp-2 text-sm">{{ c.message }}</p>
                        <p class="mt-1 text-xs text-muted">
                            <time :datetime="c.checked_at" :title="dateTime(c.checked_at)">{{ ago(c.checked_at) }}</time>
                            <template v-if="c.alert"> · {{ c.alert.code }}</template>
                        </p>
                    </div>
                </div>
            </li>
        </ul>
        <EmptyState v-else :title="t('dashboard.need_help_empty')" compact />
    </section>
</template>
