<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import FavoriteButton from '@/Components/FavoriteButton.vue';
import MediaGallery from '@/Components/MediaGallery.vue';
import AudioNarration from '@/Components/AudioNarration.vue';
import TimelineView from '@/Components/TimelineView.vue';
import CoverImage from '@/Components/CoverImage.vue';
import TagList from '@/Components/TagList.vue';
import Icon from '@/Components/Icon.vue';
import InterpretationBlock from '@/Components/Public/InterpretationBlock.vue';
import InfoList from '@/Components/Public/InfoList.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useFormat } from '@/Composables/useFormat';
import PageMeta from '@/Components/Public/PageMeta.vue';

const props = defineProps({
    figure: { type: Object, required: true },
    depth: { type: Number, default: 1 },
    isFavorite: { type: Boolean, default: false },
});
const { t, te, locale } = useT();
const lr = useLocaleRoute();
const { year, yearRange, number } = useFormat();

const images = computed(() => props.figure.media?.images || []);
const audios = computed(() => props.figure.media?.audios || []);
const info = computed(() => [
    { label: t('common.type'), value: te('figure_type', props.figure.figure_type) },
    { label: t('figures.birth_year'), value: year(props.figure.birth_year) },
    { label: t('figures.death_year'), value: year(props.figure.death_year) },
    { label: t('common.period'), value: props.figure.period?.name },
    { label: t('figures.hometown'), value: props.figure.province?.name },
    { label: t('sites.view_count'), value: props.figure.view_count != null ? number(props.figure.view_count) : null },
]);
</script>

<template>
    <PageMeta :title="figure.title" :description="figure.summary || ''" fallback-key="figures.description" :image="figure.portrait || ''" />

    <div class="container-page py-6">
        <Breadcrumbs :items="[{ label: t('nav.home'), href: lr('/') }, { label: t('nav.figures'), href: lr('/nhan-vat') }, { label: figure.title }]" />

        <header class="mt-6 flex flex-col items-center gap-6 text-center md:flex-row md:items-end md:text-left">
            <CoverImage :src="figure.portrait" :alt="figure.title" icon="user" ratio="aspect-square" class="w-40 shrink-0 rounded-full ring-4 ring-accent md:w-48" eager />
            <div class="min-w-0">
                <p class="eyebrow">{{ te('figure_type', figure.figure_type) }}</p>
                <h1 class="mt-1 text-3xl font-bold md:text-5xl">{{ figure.title }}</h1>
                <p class="mt-2 font-display text-xl text-primary">{{ yearRange(figure.birth_year, figure.death_year) }}</p>
                <div class="mt-4 flex flex-wrap justify-center gap-2 md:justify-start">
                    <FavoriteButton type="figure" :id="figure.id" :active="isFavorite" />
                    <Link :href="lr(`/dong-gop/moi?type=figure&id=${figure.id}`)" class="btn"><Icon name="edit" :size="18" />{{ t('sites.suggest_edit') }}</Link>
                </div>
            </div>
        </header>

        <p v-if="figure.summary" class="mt-8 max-w-3xl font-display text-xl leading-relaxed text-muted">{{ figure.summary }}</p>

        <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="min-w-0 space-y-10">
                <AudioNarration :audios="audios" :locale="locale" />
                <InterpretationBlock :interpretation="figure.interpretation" :depth="depth" :available-depths="figure.available_depths || [1]" />
                <TagList :tags="figure.tags || []" />
                <section v-if="images.length">
                    <h2 class="section-title mb-4">{{ t('media.gallery') }}</h2>
                    <MediaGallery :images="images" :title="figure.title" />
                </section>
                <section v-if="figure.events?.length">
                    <h2 class="section-title mb-4">{{ t('figures.life_events') }}</h2>
                    <TimelineView :events="figure.events" compact />
                </section>
            </div>
            <aside class="space-y-5 lg:sticky lg:top-20 lg:self-start">
                <InfoList :title="t('figures.profile')" :items="info" />
                <div v-if="figure.sites?.length" class="card p-4">
                    <h2 class="mb-3 font-body text-base font-bold">{{ t('figures.related_sites') }}</h2>
                    <ul class="space-y-1">
                        <li v-for="s in figure.sites" :key="s.slug">
                            <Link :href="s.url" class="flex items-center justify-between gap-2 rounded-lg p-2 hover:bg-surface-2">
                                <span class="min-w-0 truncate font-medium">{{ s.title }}</span>
                                <span class="badge shrink-0">{{ te('relation_type', s.relation_type) }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </aside>
        </div>
    </div>
</template>
