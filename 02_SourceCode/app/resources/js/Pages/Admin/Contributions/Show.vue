<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import FormField from '@/Components/FormField.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

const props = defineProps({
    contribution: { type: Object, required: true },
    tags: { type: Array, default: () => [] },
});
const { t, te } = useT();
const { dateTime, number } = useFormat();
const lr = useLocaleRoute();

const c = computed(() => props.contribution || {});
const form = useForm({ status: '', review_note: c.value.review_note || '' });
const isImage = computed(() => /\.(jpe?g|png|webp|gif)(\?|$)/i.test(c.value.attachment_url || ''));
const tagName = (s) => s.name || props.tags.find((x) => x.slug === s.slug)?.name || s.slug;
const percent = (score) => Math.round(Math.min(1, Math.max(0, Number(score) || 0)) * 100);

const ACTIONS = [
    { status: 'approved', icon: 'check', cls: 'btn-primary' },
    { status: 'needs_revision', icon: 'edit', cls: '' },
    { status: 'rejected', icon: 'close', cls: 'btn-danger' },
];

function review(status) {
    form.status = status;
    form.patch(lr(`/quan-tri/kiem-duyet/${c.value.id}`), { preserveScroll: true });
}
</script>

<template>
    <Head :title="c.title" />
    <Breadcrumbs :items="[{ label: t('admin.contributions.title'), href: lr('/quan-tri/kiem-duyet') }, { label: c.title }]" class="mb-3" />
    <PageHeader :title="c.title" :eyebrow="te('contribution_type', c.contribution_type)">
        <template #actions><StatusBadge v-if="c.status" :status="c.status" /></template>
    </PageHeader>

    <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <div class="space-y-6">
            <article class="card p-5">
                <dl class="mb-4 grid gap-2 text-sm sm:grid-cols-2">
                    <div><dt class="text-muted">{{ t('admin.contributions.author') }}</dt><dd class="font-medium">{{ c.user?.name || '—' }}<span v-if="c.user_email" class="block break-all text-xs font-normal text-muted">{{ c.user_email }}</span></dd></div>
                    <div><dt class="text-muted">{{ t('common.created_at') }}</dt><dd class="font-medium">{{ dateTime(c.created_at) }}</dd></div>
                    <div><dt class="text-muted">{{ t('admin.contributions.locale') }}</dt><dd class="font-medium">{{ te('locale', c.locale) }}</dd></div>
                    <div>
                        <dt class="text-muted">{{ t('admin.contributions.subject') }}</dt>
                        <dd class="font-medium">
                            <a v-if="c.subject?.url" :href="c.subject.url" class="link" target="_blank" rel="noopener">{{ te('subject_type', c.subject.type) }}: {{ c.subject.title }}</a>
                            <span v-else>{{ t('admin.contributions.no_subject') }}</span>
                        </dd>
                    </div>
                </dl>
                <h2 class="mb-2 font-body text-base font-bold">{{ t('admin.contributions.body') }}</h2>
                <p class="whitespace-pre-line leading-relaxed">{{ c.body }}</p>
            </article>

            <section v-if="c.attachment_url" class="card p-5">
                <h2 class="mb-3 font-body text-base font-bold">{{ t('admin.contributions.attachment') }}</h2>
                <img v-if="isImage" :src="c.attachment_url" :alt="c.title" class="max-h-96 rounded-lg object-contain" />
                <a :href="c.attachment_url" class="btn mt-3" target="_blank" rel="noopener"><Icon name="download" :size="18" />{{ t('common.download') }}</a>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="card p-5">
                <h2 class="mb-1 flex items-center gap-2 font-body text-base font-bold"><Icon name="sparkles" :size="18" />{{ t('admin.contributions.suggested_tags') }}</h2>
                <p class="mb-3 text-xs text-muted">{{ t('admin.contributions.suggested_hint') }}</p>
                <p v-if="!c.suggested_tags?.length" class="text-sm text-muted">{{ t('admin.contributions.no_suggestions') }}</p>
                <ul v-else class="space-y-2">
                    <li v-for="s in c.suggested_tags" :key="s.slug">
                        <div class="flex justify-between text-sm"><span>#{{ tagName(s) }}</span><span class="text-muted">{{ number(s.score, 2) }}</span></div>
                        <div class="mt-1 h-1.5 rounded-full bg-surface-2" aria-hidden="true">
                            <div class="h-1.5 rounded-full bg-accent" :style="{ width: `${percent(s.score)}%` }"></div>
                        </div>
                    </li>
                </ul>
            </section>

            <form class="card p-5" @submit.prevent>
                <h2 class="mb-3 font-body text-base font-bold">{{ t('admin.contributions.decision') }}</h2>
                <p v-if="c.reviewed_at" class="mb-3 text-xs text-muted">{{ t('admin.contributions.reviewed_at', { date: dateTime(c.reviewed_at) }) }}</p>
                <FormField v-model="form.review_note" type="textarea" :rows="4" :label="t('admin.contributions.review_note')" :hint="t('admin.contributions.review_note_hint')" :error="form.errors.review_note" />
                <p v-if="form.errors.status" class="field-error">{{ form.errors.status }}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        v-for="a in ACTIONS"
                        :key="a.status"
                        type="button"
                        class="btn"
                        :class="a.cls"
                        :disabled="form.processing"
                        @click="review(a.status)"
                    >
                        <Icon :name="a.icon" :size="18" />{{ t(`admin.contributions.action_${a.status}`) }}
                    </button>
                </div>
            </form>
        </aside>
    </div>
</template>
