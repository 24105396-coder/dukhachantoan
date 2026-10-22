<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import PageMeta from '@/Components/Public/PageMeta.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Card from '@/Components/Card.vue';
import Icon from '@/Components/Icon.vue';
import Button from '@/Components/Button.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

/** Giới thiệu: người dùng mục tiêu, chức năng chính, nguồn dữ liệu (attribution) và thông tin học thuật. */
const props = defineProps({
    sources: { type: Array, default: () => [] }, // [{ name, url, attribution, provider? }]
    team: { type: [Object, String, Array], default: null },
});
const page = usePage();
const { t } = useT();
const lr = useLocaleRoute();
const app = computed(() => page.props.app || {});
const teamName = computed(() => {
    if (!props.team) return app.value.team || 'DuKhachAnToan';
    if (typeof props.team === 'string') return props.team;
    if (Array.isArray(props.team)) return props.team.map((m) => m.name || m).join(', ');
    return props.team.name || app.value.team;
});
const isSafeUrl = (u) => typeof u === 'string' && /^https?:\/\//i.test(u);

const personas = [
    { key: 'traveler', icon: 'luggage', tone: 'sky' },
    { key: 'partner', icon: 'users', tone: 'lemon' },
    { key: 'agency', icon: 'building', tone: 'peach' },
];
const features = [
    { key: 'alerts', icon: 'bell-ring' },
    { key: 'map', icon: 'map' },
    { key: 'weather', icon: 'cloud-sun' },
    { key: 'trips', icon: 'route' },
    { key: 'checkin', icon: 'hand' },
    { key: 'notify', icon: 'send' },
    { key: 'contacts', icon: 'phone' },
    { key: 'guides', icon: 'life-buoy' },
    { key: 'dashboard', icon: 'dashboard' },
];
</script>

<template>
    <PageMeta :title="t('about.title')" fallback-key="about.intro" />
    <div class="container-page space-y-14 py-8 md:py-12">
        <PageHeader :eyebrow="t('about.eyebrow')" :title="t('about.title')" :description="t('about.intro')" />

        <section aria-labelledby="about-personas" class="space-y-5">
            <h2 id="about-personas" class="section-title">{{ t('about.personas_title') }}</h2>
            <div class="grid gap-4 md:grid-cols-3">
                <Card v-for="p in personas" :key="p.key" as="article" :tone="p.tone" small>
                    <span class="icon-tile mb-3"><Icon :name="p.icon" /></span>
                    <h3 class="text-xl">{{ t(`about.persona_${p.key}_name`) }}</h3>
                    <p class="mt-2 text-sm text-muted">{{ t(`about.persona_${p.key}_text`) }}</p>
                </Card>
            </div>
        </section>

        <section aria-labelledby="about-features" class="space-y-5">
            <h2 id="about-features" class="section-title">{{ t('about.features_title') }}</h2>
            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="f in features" :key="f.key" class="clay-card-sm flex gap-3 p-4">
                    <span class="icon-tile"><Icon :name="f.icon" :size="20" /></span>
                    <div>
                        <h3 class="text-base">{{ t(`about.feature_${f.key}`) }}</h3>
                        <p class="text-sm text-muted">{{ t(`about.feature_${f.key}_text`) }}</p>
                    </div>
                </li>
            </ul>
        </section>

        <section aria-labelledby="about-sources" class="space-y-5">
            <h2 id="about-sources" class="section-title">{{ t('about.data_sources') }}</h2>
            <p class="max-w-3xl text-muted">{{ t('about.data_sources_text') }}</p>
            <ul v-if="sources.length" class="grid gap-3 md:grid-cols-2">
                <li v-for="s in sources" :key="s.code || s.name" class="clay-card-sm p-4">
                    <p class="font-semibold">
                        <a v-if="isSafeUrl(s.url)" :href="s.url" target="_blank" rel="noopener noreferrer" class="link">{{ s.name }}</a>
                        <span v-else>{{ s.name }}</span>
                    </p>
                    <p v-if="s.attribution" class="mt-1 text-sm text-muted">{{ s.attribution }}</p>
                </li>
            </ul>
            <EmptyState v-else :title="t('about.no_sources')" compact />
        </section>

        <Card as="section" tone="mint" padding="lg" class="grid gap-6 md:grid-cols-2" aria-labelledby="about-academic">
            <div>
                <h2 id="about-academic" class="section-title">{{ t('about.academic_title') }}</h2>
                <p class="mt-3 text-muted">{{ t('about.academic_text') }}</p>
                <p class="mt-4 inline-block rounded-2xl bg-lemon-100 px-3 py-2 text-sm font-bold">{{ app.notice || t('footer.academic_notice') }}</p>
            </div>
            <dl class="grid gap-3 text-sm">
                <div><dt class="eyebrow">{{ t('footer.course') }}</dt><dd class="font-semibold">{{ app.course || 'Lập trình ứng dụng web trong du lịch 2' }}</dd></div>
                <div><dt class="eyebrow">{{ t('footer.course_code') }}</dt><dd class="font-semibold">{{ app.courseCode || 'CSE703073' }}</dd></div>
                <div><dt class="eyebrow">{{ t('footer.team') }}</dt><dd class="font-semibold">{{ teamName }}</dd></div>
                <div><dt class="eyebrow">{{ t('about.simulation_title') }}</dt><dd>{{ t('about.simulation_text') }}</dd></div>
            </dl>
        </Card>

        <div class="flex flex-wrap justify-center gap-3">
            <Button :href="lr('/canh-bao')" variant="primary" icon="bell-ring">{{ t('home.cta_alerts') }}</Button>
            <Button :href="lr('/danh-ba-khan-cap')" icon="phone">{{ t('nav.contacts') }}</Button>
        </div>
    </div>
</template>
