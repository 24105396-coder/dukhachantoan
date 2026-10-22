<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import CoverImage from '@/Components/CoverImage.vue';
import SkeletonList from '@/Components/SkeletonList.vue';
import HighlightText from '@/Components/Public/HighlightText.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useFormat } from '@/Composables/useFormat';
import PageMeta from '@/Components/Public/PageMeta.vue';

const props = defineProps({
    query: { type: String, default: '' },
    results: { type: Array, default: () => [] },
    engine: { type: String, default: null },
    durationMs: { type: Number, default: null },
    suggestedTags: { type: Array, default: () => [] },
    popularQueries: { type: Array, default: () => [] },
});
const { t, te } = useT();
const lr = useLocaleRoute();
const { number } = useFormat();

const q = ref(props.query || '');
const typeFilter = ref('');
const loading = ref(false);
watch(() => props.query, (v) => (q.value = v || ''));

function run(term) {
    const value = (term ?? q.value).trim();
    q.value = value;
    router.get(lr('/tim-kiem'), value ? { q: value } : {}, {
        preserveState: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
}

const TYPE_ICON = { site: 'landmark', artifact: 'vase', figure: 'users' };
const counts = computed(() =>
    props.results.reduce((acc, r) => ({ ...acc, [r.type]: (acc[r.type] || 0) + 1 }), {}),
);
const visible = computed(() => (typeFilter.value ? props.results.filter((r) => r.type === typeFilter.value) : props.results));
</script>

<template>
    <PageMeta :title="query ? t('search.title_with', { q: query }) : t('search.title')" fallback-key="search.subtitle" />
    <div class="container-page py-8">
        <div class="mx-auto max-w-3xl">
            <h1 class="text-center text-3xl font-bold md:text-4xl">{{ t('search.title') }}</h1>
            <p class="mt-2 text-center text-muted">{{ t('search.subtitle') }}</p>

            <form class="mt-6 flex flex-col gap-2 sm:flex-row" role="search" @submit.prevent="run()">
                <label for="search-q" class="sr-only">{{ t('search.label') }}</label>
                <div class="relative flex-1">
                    <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-muted"><Icon name="search" /></span>
                    <input id="search-q" v-model="q" type="search" class="input h-12 rounded-full pl-12" :placeholder="t('search.placeholder')" autofocus />
                </div>
                <button type="submit" class="btn btn-primary h-12 px-6" :disabled="loading">{{ t('nav.search') }}</button>
            </form>

            <div v-if="popularQueries.length" class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                <span class="text-muted">{{ t('search.popular') }}:</span>
                <button v-for="pq in popularQueries" :key="pq" type="button" class="badge hover:border-primary hover:text-primary" @click="run(pq)">{{ pq }}</button>
            </div>
        </div>

        <div v-if="query" class="mx-auto mt-8 max-w-4xl">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="badge" :class="engine === 'semantic' ? 'badge-success' : 'badge-warning'">
                    <Icon :name="engine === 'semantic' ? 'sparkles' : 'search'" :size="14" />
                    {{ te('engine', engine || 'fallback') }}
                </span>
                <span class="text-muted">{{ t('search.result_summary', { n: results.length, q: query }) }}</span>
                <span v-if="durationMs != null" class="text-muted">· {{ t('search.duration', { ms: number(durationMs) }) }}</span>
            </div>
            <p v-if="engine === 'fallback'" class="mt-3 flex items-start gap-2 rounded-lg bg-warning-soft p-3 text-sm text-warning" role="status">
                <Icon name="info" :size="18" class="mt-0.5" />{{ t('search.fallback_notice') }}
            </p>

            <div v-if="suggestedTags.length" class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                <span class="text-muted">{{ t('search.suggested_tags') }}:</span>
                <Link v-for="tag in suggestedTags" :key="tag.slug" :href="tag.url" class="badge badge-accent">#{{ tag.name }}</Link>
            </div>

            <div v-if="results.length" class="mt-6 flex flex-wrap gap-2" role="group" :aria-label="t('search.filter_type')">
                <button type="button" class="tab" :aria-pressed="typeFilter === ''" @click="typeFilter = ''">{{ t('common.all') }} ({{ results.length }})</button>
                <button v-for="tp in ['site', 'artifact', 'figure']" v-show="counts[tp]" :key="tp" type="button" class="tab" :aria-pressed="typeFilter === tp" @click="typeFilter = tp">
                    {{ te('subject_type', tp) }} ({{ counts[tp] || 0 }})
                </button>
            </div>

            <SkeletonList v-if="loading" class="mt-6" variant="row" :count="5" />
            <EmptyState v-else-if="!results.length" class="mt-6" icon="search" :title="t('search.no_results', { q: query })" :description="t('search.no_results_hint')" />
            <ol v-else class="mt-4 space-y-3">
                <li v-for="hit in visible" :key="`${hit.type}-${hit.slug}`">
                    <article class="card card-hover flex gap-4 p-3 sm:p-4">
                        <Link :href="hit.url" class="w-20 shrink-0 sm:w-28" tabindex="-1" aria-hidden="true">
                            <CoverImage :src="hit.cover" :alt="hit.title" :icon="TYPE_ICON[hit.type] || 'landmark'" ratio="aspect-square" class="rounded-lg" />
                        </Link>
                        <div class="min-w-0 flex-1">
                            <p class="eyebrow">{{ te('subject_type', hit.type) }}</p>
                            <h2 class="font-body text-lg font-bold">
                                <Link :href="hit.url" class="hover:text-primary"><HighlightText :text="hit.title" :terms="hit.matched_terms || []" /></Link>
                            </h2>
                            <p class="mt-1 line-clamp-2 text-sm text-muted"><HighlightText :text="hit.summary || ''" :terms="hit.matched_terms || []" /></p>
                            <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs">
                                <span v-if="hit.score != null" class="text-muted">{{ t('search.score') }} {{ number(hit.score, 3) }}</span>
                                <span v-if="hit.matched_terms?.length" class="text-muted">· {{ t('search.matched') }}:</span>
                                <span v-for="term in hit.matched_terms || []" :key="term" class="badge badge-accent">{{ term }}</span>
                            </div>
                        </div>
                    </article>
                </li>
            </ol>
        </div>

        <div v-else class="mx-auto mt-10 max-w-3xl">
            <EmptyState icon="search" :title="t('search.start_title')" :description="t('search.start_hint')" />
        </div>
    </div>
</template>
