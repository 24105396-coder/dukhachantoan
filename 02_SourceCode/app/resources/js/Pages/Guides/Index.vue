<script setup>
import { computed } from 'vue';
import PageMeta from '@/Components/Public/PageMeta.vue';
import PageHeader from '@/Components/PageHeader.vue';
import GuideCard from '@/Components/GuideCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

/**
 * Hướng dẫn ứng phó nhóm theo loại hiểm họa, có dải pill nhảy nhanh tới từng nhóm.
 * `guides` chấp nhận [GuideCard] phẳng, [{ hazard, guides }] hoặc { mã hiểm họa: [GuideCard] }.
 */
const props = defineProps({
    guides: { type: [Array, Object], default: () => [] },
    hazards: { type: Array, default: () => [] },
});
const { t, te } = useT();
const lr = useLocaleRoute();

const hazardName = (code) => props.hazards.find((h) => h.code === code)?.name || te('hazard', code);
const groups = computed(() => {
    const raw = props.guides;
    if (!raw) return [];
    if (!Array.isArray(raw)) {
        return Object.entries(raw).map(([code, list]) => ({ hazard: list?.[0]?.hazard || { code, name: hazardName(code) }, guides: list || [] }));
    }
    if (raw.length && Array.isArray(raw[0]?.guides)) return raw;
    const map = new Map();
    raw.forEach((g) => {
        const code = g.hazard?.code || 'khac';
        if (!map.has(code)) map.set(code, { hazard: g.hazard || { code, name: hazardName(code) }, guides: [] });
        map.get(code).guides.push(g);
    });
    return [...map.values()];
});
const anchor = (code) => `hz-${code}`;
</script>

<template>
    <PageMeta :title="t('nav.guides')" fallback-key="guides.intro" />
    <div class="container-page space-y-6 py-8 md:py-10">
        <PageHeader :breadcrumbs="[{ label: t('nav.home'), href: lr('/') }, { label: t('nav.guides') }]" :eyebrow="t('guides.eyebrow')" :title="t('nav.guides')" :description="t('guides.intro')" />

        <nav v-if="groups.length > 1" :aria-label="t('guides.jump')">
            <ul class="flex flex-wrap gap-2">
                <li v-for="g in groups" :key="g.hazard.code">
                    <a :href="`#${anchor(g.hazard.code)}`" class="pill bg-raised py-1.5 hover:border-brand-400">
                        <Icon :name="g.hazard.icon || g.hazard.code" fallback="life-buoy" :size="16" />{{ g.hazard.name }}
                        <span class="text-xs text-muted">({{ g.guides.length }})</span>
                    </a>
                </li>
            </ul>
        </nav>

        <template v-if="groups.length">
            <section v-for="g in groups" :id="anchor(g.hazard.code)" :key="g.hazard.code" class="scroll-mt-24 space-y-3" :aria-labelledby="`${anchor(g.hazard.code)}-t`">
                <h2 :id="`${anchor(g.hazard.code)}-t`" class="section-title text-xl">{{ g.hazard.name }}</h2>
                <ul class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <li v-for="guide in g.guides" :key="guide.slug"><GuideCard :guide="guide" /></li>
                </ul>
            </section>
        </template>
        <EmptyState v-else :title="t('home.no_guides')" :description="t('guides.empty_hint')" />
    </div>
</template>
