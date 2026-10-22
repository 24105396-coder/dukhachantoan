<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import FavoriteButton from '@/Components/FavoriteButton.vue';
import MediaGallery from '@/Components/MediaGallery.vue';
import AudioNarration from '@/Components/AudioNarration.vue';
import TimelineView from '@/Components/TimelineView.vue';
import ArtifactCard from '@/Components/ArtifactCard.vue';
import SiteCard from '@/Components/SiteCard.vue';
import MapView from '@/Components/MapView.vue';
import TagList from '@/Components/TagList.vue';
import CoverImage from '@/Components/CoverImage.vue';
import ProvenanceBadge from '@/Components/ProvenanceBadge.vue';
import Icon from '@/Components/Icon.vue';
import InterpretationBlock from '@/Components/Public/InterpretationBlock.vue';
import InfoList from '@/Components/Public/InfoList.vue';
import JsonLd from '@/Components/Public/JsonLd.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useMapProvider } from '@/Composables/useMapProvider';
import { useFormat } from '@/Composables/useFormat';
import PageMeta from '@/Components/Public/PageMeta.vue';

const props = defineProps({
    site: { type: Object, required: true },
    depth: { type: Number, default: 1 },
    related: { type: Array, default: () => [] },
    isFavorite: { type: Boolean, default: false },
});
const { t, te, locale } = useT();
const lr = useLocaleRoute();
const { money, number, year, duration } = useFormat();

const images = computed(() => props.site.media?.images || []);
const audios = computed(() => props.site.media?.audios || []);
const coverMedia = computed(() => images.value.find((m) => m.is_cover) || null);

const info = computed(() => [
    { label: t('sites.address'), value: [props.site.address, props.site.province?.name].filter(Boolean).join(', ') },
    { label: t('sites.visiting_hours'), value: props.site.visiting_hours },
    { label: t('sites.ticket_price'), value: props.site.ticket_price_vnd != null ? money(props.site.ticket_price_vnd) : null },
    { label: t('sites.website'), value: props.site.website, href: props.site.website },
    { label: t('sites.year_built'), value: year(props.site.year_built) },
    { label: t('sites.year_recognized'), value: props.site.year_recognized },
    { label: t('common.period'), value: props.site.period?.name },
    { label: t('sites.view_count'), value: props.site.view_count != null ? number(props.site.view_count) : null },
]);

const point = computed(() =>
    props.site.lat != null && props.site.lng != null
        ? [{ lat: props.site.lat, lng: props.site.lng, title: props.site.title, subtitle: props.site.province?.name }]
        : [],
);
const { directionsUrl } = useMapProvider();
const directions = computed(() => (point.value.length ? directionsUrl(props.site.lat, props.site.lng) : null));
</script>

<template>
    <PageMeta :title="site.title" :description="site.summary || ''" fallback-key="sites.description" :image="site.cover?.url" />
    <JsonLd :data="site.jsonld" />

    <!-- Hero ảnh bìa: <1024px tiêu đề nằm dưới ảnh, >=1024px phủ lên đáy ảnh (xem .hero-cover trong app.css) -->
    <section class="hero-cover">
        <div class="hero-cover__media">
            <CoverImage :src="site.cover?.url" :alt="site.cover?.caption || site.title" ratio="aspect-[16/10] sm:aspect-[21/9] max-h-[70vh] w-full" eager />
            <div class="hero-cover__fade" aria-hidden="true"></div>
        </div>
        <div class="hero-cover__body">
            <div class="container-page">
                <div class="mb-3 flex flex-wrap gap-2">
                    <span class="badge border-white/40 bg-white/15 text-on-hero">{{ te('site_type', site.site_type) }}</span>
                    <span v-if="site.recognition_level" class="badge badge-accent">{{ te('recognition_level', site.recognition_level) }}</span>
                </div>
                <h1 class="max-w-4xl text-3xl font-bold md:text-5xl">{{ site.title }}</h1>
                <p v-if="site.province" class="mt-2 inline-flex items-center gap-1 text-sm"><Icon name="pin" :size="16" />{{ site.province.name }}</p>
            </div>
        </div>
    </section>
    <div v-if="site.cover?.license" class="container-page pt-2">
        <ProvenanceBadge :item="coverMedia || site.cover" compact />
    </div>

    <div class="container-page py-6">
        <Breadcrumbs :items="[{ label: t('nav.home'), href: lr('/') }, { label: t('nav.sites'), href: lr('/di-san') }, { label: site.title }]" />

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <FavoriteButton type="site" :id="site.id" :active="isFavorite" />
            <Link :href="lr(`/dong-gop/moi?type=site&id=${site.id}`)" class="btn"><Icon name="edit" :size="18" />{{ t('sites.suggest_edit') }}</Link>
            <a v-if="directions" :href="directions" target="_blank" rel="noopener noreferrer" class="btn"><Icon name="external" :size="18" />{{ t('sites.directions') }}</a>
        </div>

        <p v-if="site.summary" class="mt-6 max-w-3xl font-display text-xl leading-relaxed text-muted md:text-2xl">{{ site.summary }}</p>

        <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="min-w-0 space-y-10">
                <AudioNarration :audios="audios" :locale="locale" />

                <InterpretationBlock :interpretation="site.interpretation" :depth="depth" :available-depths="site.available_depths || [1]" />

                <TagList :tags="site.tags || []" />

                <section v-if="images.length">
                    <h2 class="section-title mb-4">{{ t('media.gallery') }}</h2>
                    <MediaGallery :images="images" :title="site.title" />
                </section>

                <section v-if="site.figures?.length">
                    <h2 class="section-title mb-4">{{ t('sites.related_figures') }}</h2>
                    <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <li v-for="f in site.figures" :key="f.slug" class="min-w-0">
                            <Link :href="f.url" class="card card-hover flex items-center gap-3 p-3">
                                <CoverImage :src="f.portrait" :alt="f.title" icon="user" ratio="aspect-square" class="w-14 shrink-0 rounded-full" />
                                <span class="min-w-0">
                                    <span class="block truncate font-semibold">{{ f.title }}</span>
                                    <span class="block text-xs text-muted">
                                        {{ te('relation_type', f.relation_type) }}
                                        <template v-if="f.birth_year || f.death_year"> · {{ year(f.birth_year) }} – {{ year(f.death_year) }}</template>
                                    </span>
                                </span>
                            </Link>
                        </li>
                    </ul>
                </section>

                <section v-if="site.artifacts?.length">
                    <h2 class="section-title mb-4">{{ t('sites.related_artifacts') }}</h2>
                    <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
                        <ArtifactCard v-for="a in site.artifacts" :key="a.id" :artifact="a" />
                    </div>
                </section>

                <section v-if="site.events?.length">
                    <h2 class="section-title mb-4">{{ t('sites.history') }}</h2>
                    <TimelineView :events="site.events" compact />
                </section>
            </div>

            <aside class="space-y-5 lg:sticky lg:top-20 lg:self-start">
                <InfoList :title="t('sites.visit_info')" :items="info" />
                <div v-if="point.length">
                    <MapView :points="point" height="240px" :cluster="false" :label="t('sites.location')" />
                </div>
                <div v-if="site.routes?.length" class="card p-4">
                    <h2 class="mb-3 font-body text-base font-bold">{{ t('sites.in_routes') }}</h2>
                    <ul class="space-y-2">
                        <li v-for="r in site.routes" :key="r.slug">
                            <Link :href="r.url" class="flex items-start justify-between gap-2 rounded-lg p-2 hover:bg-surface-2">
                                <span class="min-w-0">
                                    <span class="block font-medium">{{ r.name }}</span>
                                    <span class="text-xs text-muted">{{ duration(r.duration_minutes) }}</span>
                                </span>
                                <span class="shrink-0 text-sm font-bold text-primary">{{ money(r.price_vnd) }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </aside>
        </div>

        <section v-if="related.length" class="mt-14">
            <h2 class="section-title mb-4">{{ t('sites.related_sites') }}</h2>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <SiteCard v-for="s in related" :key="s.id" :site="s" />
            </div>
        </section>
    </div>
</template>
