<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

const props = defineProps({
    contribution: { type: Object, required: true },
});
const { t, te } = useT();
const { dateTime, number } = useFormat();
const lr = useLocaleRoute();

const tags = computed(() => [...(props.contribution.suggested_tags || [])].sort((a, b) => (b.score ?? 0) - (a.score ?? 0)));
const isImage = computed(() => /\.(jpe?g|png)(\?|$)/i.test(props.contribution.attachment_url || ''));
</script>

<template>
    <Head :title="contribution.title" />
    <div class="container-page max-w-3xl py-8">
        <Breadcrumbs :items="[{ label: t('nav.dashboard'), href: lr('/tai-khoan') }, { label: contribution.title }]" />
        <div class="mt-4 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="eyebrow">{{ te('contribution_type', contribution.contribution_type) }}</p>
                <h1 class="text-3xl font-bold">{{ contribution.title }}</h1>
                <p class="mt-1 text-sm text-muted">
                    {{ contribution.user?.name }} · {{ dateTime(contribution.created_at) }} · {{ te('locale', contribution.locale) }}
                </p>
            </div>
            <StatusBadge :status="contribution.status" />
        </div>

        <p v-if="contribution.subject" class="mt-4 text-sm">
            {{ t('contribution.related_to') }}:
            <Link :href="contribution.subject.url" class="link">{{ te('subject_type', contribution.subject.type) }} – {{ contribution.subject.title }}</Link>
        </p>

        <section v-if="contribution.review_note || contribution.reviewed_at" class="mt-6 rounded-lg border p-4" :class="contribution.status === 'rejected' ? 'border-danger bg-danger-soft' : contribution.status === 'approved' ? 'border-success bg-success-soft' : 'border-warning bg-warning-soft'">
            <h2 class="font-body text-sm font-bold">{{ t('contribution.review_note') }}</h2>
            <p class="mt-1 whitespace-pre-line text-sm">{{ contribution.review_note || '—' }}</p>
            <p v-if="contribution.reviewed_at" class="mt-1 text-xs opacity-80">{{ dateTime(contribution.reviewed_at) }}</p>
        </section>

        <article class="card mt-6 p-5">
            <p class="whitespace-pre-line leading-relaxed">{{ contribution.body }}</p>
        </article>

        <section v-if="contribution.attachment_url" class="mt-6">
            <h2 class="font-body text-base font-bold">{{ t('contribution.attachment') }}</h2>
            <img v-if="isImage" :src="contribution.attachment_url" :alt="contribution.title" class="mt-2 max-h-96 rounded-lg border border-border" />
            <a :href="contribution.attachment_url" target="_blank" rel="noopener" class="btn btn-sm mt-2"><Icon name="download" :size="16" />{{ t('common.download') }}</a>
        </section>

        <section class="mt-6">
            <h2 class="font-body text-base font-bold">{{ t('contribution.suggested_tags') }}</h2>
            <p class="text-xs text-muted">{{ t('contribution.suggested_tags_hint') }}</p>
            <p v-if="!tags.length" class="mt-2 text-sm text-muted">{{ t('contribution.no_suggestions') }}</p>
            <ul v-else class="mt-3 space-y-2">
                <li v-for="tag in tags" :key="tag.slug" class="flex items-center gap-3">
                    <span class="w-40 shrink-0 truncate text-sm font-medium">#{{ tag.name || tag.slug }}</span>
                    <span class="h-2 flex-1 overflow-hidden rounded-full bg-surface-2" role="meter" :aria-valuenow="tag.score" aria-valuemin="0" aria-valuemax="1" :aria-label="tag.name || tag.slug">
                        <span class="block h-full rounded-full bg-accent" :style="{ width: `${Math.round(Math.min(1, tag.score ?? 0) * 100)}%` }"></span>
                    </span>
                    <span class="w-12 text-right text-xs tabular-nums text-muted">{{ number(tag.score, 2) }}</span>
                </li>
            </ul>
        </section>
    </div>
</template>
