<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import FavoriteButton from '@/Components/FavoriteButton.vue';
import MediaGallery from '@/Components/MediaGallery.vue';
import AudioNarration from '@/Components/AudioNarration.vue';
import TimelineView from '@/Components/TimelineView.vue';
import ArtifactCard from '@/Components/ArtifactCard.vue';
import ModelViewerAR from '@/Components/ModelViewerAR.vue';
import CoverImage from '@/Components/CoverImage.vue';
import TagList from '@/Components/TagList.vue';
import Icon from '@/Components/Icon.vue';
import InterpretationBlock from '@/Components/Public/InterpretationBlock.vue';
import InfoList from '@/Components/Public/InfoList.vue';
import JsonLd from '@/Components/Public/JsonLd.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useFormat } from '@/Composables/useFormat';
import PageMeta from '@/Components/Public/PageMeta.vue';

const props = defineProps({
    artifact: { type: Object, required: true },
    depth: { type: Number, default: 1 },
    related: { type: Array, default: () => [] },
    isFavorite: { type: Boolean, default: false },
});
const { t, te, locale } = useT();
const lr = useLocaleRoute();
const { number, yearRange } = useFormat();

const images = computed(() => props.artifact.media?.images || []);
const audios = computed(() => props.artifact.media?.audios || []);

const dims = computed(() => {
    const a = props.artifact;
    const parts = [
        a.height_cm != null ? `${t('artifacts.height')} ${number(a.height_cm, 1)}` : null,
        a.width_cm != null ? `${t('artifacts.width')} ${number(a.width_cm, 1)}` : null,
        a.depth_cm != null ? `${t('artifacts.depth')} ${number(a.depth_cm, 1)}` : null,
    ].filter(Boolean);
    return parts.length ? `${parts.join(' × ')} cm` : null;
});

const info = computed(() => [
    { label: t('artifacts.inventory_code'), value: props.artifact.inventory_code },
    { label: t('common.type'), value: te('artifact_type', props.artifact.artifact_type) },
    { label: t('artifacts.material'), value: props.artifact.material },
    { label: t('artifacts.dating'), value: yearRange(props.artifact.year_from, props.artifact.year_to) },
    { label: t('common.period'), value: props.artifact.period?.name },
    { label: t('artifacts.dimensions'), value: dims.value },
    { label: t('artifacts.weight'), value: props.artifact.weight_kg != null ? `${number(props.artifact.weight_kg, 2)} kg` : null },
    { label: t('sites.view_count'), value: props.artifact.view_count != null ? number(props.artifact.view_count) : null },
]);
</script>

<template>
    <PageMeta :title="artifact.title" :description="artifact.summary || ''" fallback-key="artifacts.description" :image="artifact.cover?.url" />
    <JsonLd :data="artifact.jsonld" />

    <div class="container-page py-6">
        <Breadcrumbs :items="[{ label: t('nav.home'), href: lr('/') }, { label: t('nav.artifacts'), href: lr('/hien-vat') }, { label: artifact.title }]" />

        <div class="mt-6 grid gap-8 lg:grid-cols-2">
            <div class="min-w-0 space-y-4">
                <ModelViewerAR v-if="artifact.model3d_url" :src="artifact.model3d_url" :alt="artifact.title" :poster="artifact.cover?.url" />
                <CoverImage v-else :src="artifact.cover?.url" :alt="artifact.cover?.caption || artifact.title" icon="vase" ratio="aspect-square" class="card rounded-[var(--radius-lg)]" eager />
            </div>
            <div class="min-w-0">
                <div class="flex flex-wrap gap-2">
                    <span class="badge">{{ te('artifact_type', artifact.artifact_type) }}</span>
                    <span v-if="artifact.is_national_treasure" class="badge badge-primary"><Icon name="star" :size="14" />{{ t('artifacts.national_treasure') }}</span>
                </div>
                <h1 class="mt-3 text-3xl font-bold md:text-4xl">{{ artifact.title }}</h1>
                <p v-if="artifact.site" class="mt-2 text-sm">
                    {{ t('artifacts.kept_at') }}
                    <Link :href="artifact.site.url || lr(`/di-san/${artifact.site.slug}`)" class="link font-semibold">{{ artifact.site.title }}</Link>
                </p>
                <p v-if="artifact.summary" class="mt-4 font-display text-lg leading-relaxed text-muted">{{ artifact.summary }}</p>
                <div class="mt-5 flex flex-wrap gap-2">
                    <FavoriteButton type="artifact" :id="artifact.id" :active="isFavorite" />
                    <Link :href="lr(`/dong-gop/moi?type=artifact&id=${artifact.id}`)" class="btn"><Icon name="edit" :size="18" />{{ t('sites.suggest_edit') }}</Link>
                </div>
                <InfoList class="mt-6" :title="t('artifacts.specs')" :items="info" />
            </div>
        </div>

        <div class="mt-10 max-w-4xl space-y-10">
            <AudioNarration :audios="audios" :locale="locale" />
            <InterpretationBlock :interpretation="artifact.interpretation" :depth="depth" :available-depths="artifact.available_depths || [1]" />
            <TagList :tags="artifact.tags || []" />
            <section v-if="images.length">
                <h2 class="section-title mb-4">{{ t('media.gallery') }}</h2>
                <MediaGallery :images="images" :title="artifact.title" />
            </section>
            <section v-if="artifact.events?.length">
                <h2 class="section-title mb-4">{{ t('sites.history') }}</h2>
                <TimelineView :events="artifact.events" compact />
            </section>
        </div>

        <section v-if="related.length" class="mt-14">
            <h2 class="section-title mb-4">{{ t('artifacts.related') }}</h2>
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <ArtifactCard v-for="a in related" :key="a.id" :artifact="a" />
            </div>
        </section>
    </div>
</template>
