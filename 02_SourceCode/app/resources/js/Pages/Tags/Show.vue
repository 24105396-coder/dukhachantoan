<script setup>
import { computed } from 'vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import SiteCard from '@/Components/SiteCard.vue';
import ArtifactCard from '@/Components/ArtifactCard.vue';
import FigureCard from '@/Components/FigureCard.vue';
import RouteCard from '@/Components/RouteCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import PageMeta from '@/Components/Public/PageMeta.vue';

const props = defineProps({
    tag: { type: Object, required: true },
    sites: { type: Array, default: () => [] },
    artifacts: { type: Array, default: () => [] },
    figures: { type: Array, default: () => [] },
    routes: { type: Array, default: () => [] },
});
const { t, te } = useT();
const lr = useLocaleRoute();
const isEmpty = computed(() => !props.sites.length && !props.artifacts.length && !props.figures.length && !props.routes.length);
</script>

<template>
    <PageMeta :title="`#${tag.name}`" :description="tag.description || ''" fallback-key="tags.description" />
    <div class="container-page py-8">
        <Breadcrumbs :items="[{ label: t('nav.home'), href: lr('/') }, { label: t('nav.tags'), href: lr('/the') }, { label: tag.name }]" />
        <header class="mt-5 mb-8">
            <p class="eyebrow">{{ te('tag_group', tag.group) }}</p>
            <h1 class="mt-1 text-3xl font-bold md:text-4xl">#{{ tag.name }}</h1>
            <p v-if="tag.description" class="mt-2 max-w-2xl text-muted">{{ tag.description }}</p>
        </header>

        <EmptyState v-if="isEmpty" icon="tag" :title="t('tags.no_items')" />

        <section v-if="sites.length" class="mb-12">
            <h2 class="section-title mb-4">{{ t('nav.sites') }} <span class="text-base font-normal text-muted">({{ sites.length }})</span></h2>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3"><SiteCard v-for="s in sites" :key="s.id" :site="s" /></div>
        </section>
        <section v-if="artifacts.length" class="mb-12">
            <h2 class="section-title mb-4">{{ t('nav.artifacts') }} <span class="text-base font-normal text-muted">({{ artifacts.length }})</span></h2>
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4"><ArtifactCard v-for="a in artifacts" :key="a.id" :artifact="a" /></div>
        </section>
        <section v-if="figures.length" class="mb-12">
            <h2 class="section-title mb-4">{{ t('nav.figures') }} <span class="text-base font-normal text-muted">({{ figures.length }})</span></h2>
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4"><FigureCard v-for="f in figures" :key="f.id" :figure="f" /></div>
        </section>
        <section v-if="routes.length" class="mb-12">
            <h2 class="section-title mb-4">{{ t('nav.tours') }} <span class="text-base font-normal text-muted">({{ routes.length }})</span></h2>
            <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3"><RouteCard v-for="r in routes" :key="r.id" :route="r" /></div>
        </section>
    </div>
</template>
