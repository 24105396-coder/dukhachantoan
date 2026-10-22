<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import SiteCard from '@/Components/SiteCard.vue';
import ArtifactCard from '@/Components/ArtifactCard.vue';
import FigureCard from '@/Components/FigureCard.vue';
import RouteCard from '@/Components/RouteCard.vue';
import MapView from '@/Components/MapView.vue';
import EmptyState from '@/Components/EmptyState.vue';
import SectionHeading from '@/Components/Public/SectionHeading.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useFormat } from '@/Composables/useFormat';
import PageMeta from '@/Components/Public/PageMeta.vue';

const props = defineProps({
    featuredSites: { type: Array, default: () => [] },
    latestArtifacts: { type: Array, default: () => [] },
    upcomingRoutes: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    mapPoints: { type: Array, default: () => [] },
    featuredFigures: { type: Array, default: () => [] },
});
const { t } = useT();
const lr = useLocaleRoute();
const { number } = useFormat();

const q = ref('');
const search = () => router.get(lr('/tim-kiem'), q.value.trim() ? { q: q.value.trim() } : {});

const statItems = computed(() => [
    { key: 'sites', icon: 'landmark', value: props.stats?.sites },
    { key: 'artifacts', icon: 'vase', value: props.stats?.artifacts },
    { key: 'figures', icon: 'users', value: props.stats?.figures },
    { key: 'provinces', icon: 'pin', value: props.stats?.provinces },
    { key: 'intangible', icon: 'sparkles', value: props.stats?.intangible },
]);

const BLANK_IMG = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
const hero = computed(() => props.featuredSites.find((s) => s.cover?.url) || null);

const personas = ['visitor', 'student', 'researcher'];
const personaIcon = { visitor: 'map', student: 'book', researcher: 'file' };
</script>

<template>
    <PageMeta :title="t('home.meta_title')" fallback-key="home.hero_subtitle" :image="hero?.cover?.url" />

    <!-- Hero -->
    <section class="relative overflow-hidden border-b border-border bg-surface-2">
        <div class="container-page grid items-center gap-10 py-12 md:py-16 lg:grid-cols-[1.1fr_0.9fr] lg:py-20">
            <div>
                <p class="eyebrow mb-3">{{ t('home.eyebrow') }}</p>
                <h1 class="text-4xl font-bold leading-tight md:text-5xl lg:text-6xl">
                    {{ t('home.hero_title_1') }}
                    <span class="block text-primary italic">{{ t('home.hero_title_2') }}</span>
                </h1>
                <p class="mt-5 max-w-xl text-lg text-muted">{{ t('home.hero_subtitle') }}</p>

                <form class="mt-7 flex max-w-xl flex-col gap-2 sm:flex-row" role="search" @submit.prevent="search">
                    <label for="home-q" class="sr-only">{{ t('nav.search') }}</label>
                    <div class="relative flex-1">
                        <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-muted"><Icon name="search" /></span>
                        <input id="home-q" v-model="q" type="search" class="input h-12 rounded-full pl-12" :placeholder="t('home.search_placeholder')" />
                    </div>
                    <button type="submit" class="btn btn-primary h-12 px-6">{{ t('nav.search') }}</button>
                </form>

                <div class="mt-6 flex flex-wrap gap-2">
                    <Link :href="lr('/di-san')" class="btn">{{ t('home.cta_sites') }}</Link>
                    <Link :href="lr('/ban-do')" class="btn"><Icon name="map" :size="18" />{{ t('home.cta_map') }}</Link>
                    <Link :href="lr('/tuyen-tham-quan')" class="btn btn-accent"><Icon name="route" :size="18" />{{ t('home.cta_tours') }}</Link>
                </div>
            </div>

            <div class="relative hidden md:block">
                <div class="absolute -right-6 -top-6 h-40 w-40 rounded-full border-2 border-dashed border-accent opacity-60" aria-hidden="true"></div>
                <figure v-if="hero" class="card relative overflow-hidden rounded-[var(--radius-lg)]">
                    <!-- Khối này ẩn trên mobile: <picture> chỉ tải ảnh thật từ 768px, mobile nhận ảnh rỗng 1 px -->
                    <picture>
                        <source media="(min-width: 768px)" :srcset="hero.cover.url" />
                        <img :src="BLANK_IMG" :alt="hero.cover.caption || hero.title" width="640" height="800" fetchpriority="high" decoding="async" class="aspect-[4/5] w-full object-cover lg:aspect-[4/4.5]" />
                    </picture>
                    <figcaption class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent p-5 text-white">
                        <p class="text-xs uppercase tracking-widest opacity-80">{{ hero.province?.name }}</p>
                        <Link :href="hero.url" class="font-display text-2xl font-bold hover:underline">{{ hero.title }}</Link>
                    </figcaption>
                </figure>
                <div v-else class="card flex aspect-[4/5] items-center justify-center rounded-[var(--radius-lg)] text-accent">
                    <Icon name="landmark" :size="96" :stroke-width="1" />
                </div>
            </div>
        </div>
        <div class="ornament"></div>
    </section>

    <!-- Thống kê -->
    <section class="container-page -mt-px py-10" :aria-label="t('home.stats_label')">
        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <li v-for="s in statItems" :key="s.key" class="card flex flex-col items-center p-4 text-center">
                <span class="mb-2 text-accent-strong"><Icon :name="s.icon" :size="24" /></span>
                <span class="font-display text-3xl font-bold text-primary">{{ s.value != null ? number(s.value) : '—' }}</span>
                <span class="text-sm text-muted">{{ t(`home.stat_${s.key}`) }}</span>
            </li>
        </ul>
    </section>

    <!-- Di sản nổi bật -->
    <section class="container-page py-8">
        <SectionHeading :title="t('home.featured_sites')" :eyebrow="t('home.featured_sites_eyebrow')" :href="lr('/di-san')" :link-text="t('common.view_all')" />
        <EmptyState v-if="!featuredSites.length" icon="landmark" :title="t('sites.empty')" />
        <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <SiteCard v-for="site in featuredSites" :key="site.id" :site="site" />
        </div>
    </section>

    <!-- Ba tầng diễn giải -->
    <section class="bg-surface py-12">
        <div class="container-page">
            <SectionHeading :title="t('home.depth_title')" :eyebrow="t('home.depth_eyebrow')" />
            <p class="-mt-2 mb-6 max-w-2xl text-muted">{{ t('home.depth_intro') }}</p>
            <ol class="grid gap-4 md:grid-cols-3">
                <li v-for="(p, i) in personas" :key="p" class="card relative overflow-hidden p-5">
                    <span class="absolute -right-2 -top-6 font-display text-8xl font-bold text-accent opacity-15" aria-hidden="true">{{ i + 1 }}</span>
                    <span class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-primary-soft text-primary"><Icon :name="personaIcon[p]" /></span>
                    <h3 class="text-xl font-bold">{{ t(`depth.${i + 1}`) }}</h3>
                    <p class="text-sm font-semibold text-accent-strong">{{ t(`depth.${i + 1}_hint`) }}</p>
                    <p class="mt-2 text-sm text-muted">{{ t(`home.persona_${p}`) }}</p>
                </li>
            </ol>
        </div>
    </section>

    <!-- Bản đồ -->
    <section class="container-page py-12">
        <SectionHeading :title="t('home.map_title')" :eyebrow="t('home.map_eyebrow')" :href="lr('/ban-do')" :link-text="t('home.open_map')" />
        <MapView :points="mapPoints" height="420px" :label="t('home.map_title')" />
    </section>

    <!-- Hiện vật -->
    <section class="container-page py-8">
        <SectionHeading :title="t('home.latest_artifacts')" :eyebrow="t('home.latest_artifacts_eyebrow')" :href="lr('/hien-vat')" :link-text="t('common.view_all')" />
        <EmptyState v-if="!latestArtifacts.length" icon="vase" :title="t('artifacts.empty')" />
        <div v-else class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
            <ArtifactCard v-for="a in latestArtifacts" :key="a.id" :artifact="a" />
        </div>
    </section>

    <!-- Nhân vật -->
    <section class="container-page py-8">
        <SectionHeading :title="t('home.featured_figures')" :eyebrow="t('home.featured_figures_eyebrow')" :href="lr('/nhan-vat')" :link-text="t('common.view_all')" />
        <EmptyState v-if="!featuredFigures.length" icon="users" :title="t('figures.empty')" />
        <div v-else class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <FigureCard v-for="f in featuredFigures" :key="f.id" :figure="f" />
        </div>
    </section>

    <!-- Tuyến tham quan -->
    <section class="container-page py-8">
        <SectionHeading :title="t('home.upcoming_routes')" :eyebrow="t('home.upcoming_routes_eyebrow')" :href="lr('/tuyen-tham-quan')" :link-text="t('common.view_all')" />
        <EmptyState v-if="!upcomingRoutes.length" icon="route" :title="t('tours.empty')" />
        <div v-else class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            <RouteCard v-for="r in upcomingRoutes" :key="r.id" :route="r" />
        </div>
    </section>

    <!-- Kêu gọi đóng góp -->
    <section class="container-page py-10">
        <div class="card flex flex-col items-start gap-4 bg-primary-soft p-6 md:flex-row md:items-center md:justify-between md:p-8">
            <div>
                <h2 class="text-2xl font-bold">{{ t('home.contribute_title') }}</h2>
                <p class="mt-1 max-w-2xl text-muted">{{ t('home.contribute_text') }}</p>
            </div>
            <Link :href="lr('/dong-gop/moi')" class="btn btn-primary"><Icon name="upload" :size="18" />{{ t('nav.contribute') }}</Link>
        </div>
    </section>
</template>
