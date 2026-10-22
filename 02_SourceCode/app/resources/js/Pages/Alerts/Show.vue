<script setup>
import { computed } from 'vue';
import PageMeta from '@/Components/Public/PageMeta.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import Icon from '@/Components/Icon.vue';
import Badge from '@/Components/Badge.vue';
import Button from '@/Components/Button.vue';
import MapBase from '@/Components/MapBase.vue';
import SourceStamp from '@/Components/SourceStamp.vue';
import SimulatedBanner from '@/Components/SimulatedBanner.vue';
import StatusHistory from '@/Components/StatusHistory.vue';
import GuideCard from '@/Components/GuideCard.vue';
import ContactItem from '@/Components/ContactItem.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { normalizeLevel, LEVELS } from '@/Utils/levels';

/** Chi tiết cảnh báo (AlertDetail): mức to rõ, vùng, hiệu lực, hướng dẫn, danh bạ gần, bản đồ phạm vi, lịch sử trạng thái. */
const props = defineProps({
    alert: { type: Object, required: true },
    zonesGeo: { type: Object, default: null },
    nearbyContacts: { type: Array, default: () => [] },
});
const { t, te, locale } = useT();
const { dateTime, number } = useFormat();
const lr = useLocaleRoute();

const level = computed(() => normalizeLevel(props.alert.severity) ?? 1);
const HERO = {
    1: 'border-level-1 bg-level-1-soft', 2: 'border-level-2 bg-level-2-soft',
    3: 'border-level-3 bg-level-3-soft', 4: 'border-level-4 bg-level-4-soft',
};
const SOLID = { 1: 'bg-level-1 text-level-1-fg', 2: 'bg-level-2 text-level-2-fg', 3: 'bg-level-3 text-level-3-fg', 4: 'bg-level-4 text-level-4-fg' };
const isInactive = computed(() => ['expired', 'cancelled'].includes(props.alert.status));
const overlays = computed(() => (props.alert.geometry
    ? [{ id: props.alert.code, geometry: props.alert.geometry, level: level.value, label: props.alert.headline }]
    : []));
const missingTranslation = computed(() => Array.isArray(props.alert.translations_available)
    && !props.alert.translations_available.includes(locale.value));
const otherLang = computed(() => (props.alert.translations_available || []).find((l) => l !== locale.value));
const crumbs = computed(() => [
    { label: t('nav.home'), href: lr('/') },
    { label: t('alerts.title'), href: lr('/canh-bao') },
    { label: props.alert.code },
]);
</script>

<template>
    <PageMeta :title="alert.headline" :description="alert.description" />
    <div class="container-page space-y-6 py-8 md:py-10">
        <Breadcrumbs :items="crumbs" />
        <SimulatedBanner v-if="alert.is_simulated" :scenario="alert.scenario" />

        <article class="clay-card overflow-hidden" :data-severity="level">
            <div class="grid gap-0 md:grid-cols-[14rem_1fr]">
                <!-- Khối mức: to, có số, icon và chữ (không chỉ dựa màu) -->
                <div class="flex flex-row items-center gap-4 p-5 md:flex-col md:justify-center md:p-6" :class="SOLID[level]">
                    <Icon :name="LEVELS[level].icon" :size="48" :stroke-width="2" />
                    <div class="md:text-center">
                        <p class="text-sm font-bold uppercase tracking-[0.18em]">{{ t('map.level') }}</p>
                        <p class="font-display text-5xl font-extrabold leading-none">{{ level }}</p>
                        <p class="font-display text-xl font-bold">{{ alert.severity_label || t(LEVELS[level].key) }}</p>
                    </div>
                </div>
                <div class="space-y-4 border-l-0 p-5 md:p-7" :class="HERO[level]">
                    <div class="flex flex-wrap items-center gap-2">
                        <span v-if="alert.hazard" class="pill bg-raised"><Icon :name="alert.hazard.icon || alert.hazard.code || 'alert'" fallback="alert" :size="16" />{{ alert.hazard.name }}</span>
                        <Badge :tone="isInactive ? 'outline' : 'brand'" size="sm">{{ te('status', alert.status) }}</Badge>
                        <span class="font-mono text-xs text-muted">{{ alert.code }}</span>
                    </div>
                    <h1 class="leading-tight">{{ alert.headline }}</h1>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div class="rounded-2xl bg-surface p-3">
                            <dt class="flex items-center gap-1.5 text-muted"><Icon name="map-pin" :size="16" />{{ t('alert.zones') }}</dt>
                            <dd class="mt-1 font-semibold">{{ (alert.zones || []).map((z) => z.name).join(', ') || '—' }}</dd>
                        </div>
                        <div class="rounded-2xl bg-surface p-3">
                            <dt class="flex items-center gap-1.5 text-muted"><Icon name="calendar-clock" :size="16" />{{ t('alert.valid_time') }}</dt>
                            <dd class="mt-1 font-semibold">
                                <time :datetime="alert.starts_at">{{ dateTime(alert.starts_at) }}</time> –
                                <time v-if="alert.ends_at" :datetime="alert.ends_at">{{ dateTime(alert.ends_at) }}</time>
                                <span v-else>{{ t('alert.until_further_notice') }}</span>
                            </dd>
                        </div>
                    </dl>
                    <p v-if="alert.issued_at" class="text-xs text-muted">{{ t('alert.issued_at', { time: dateTime(alert.issued_at) }) }}</p>
                    <SourceStamp :source="alert.source" :updated-at="alert.updated_at_source || alert.issued_at" :simulated="!!alert.is_simulated" />
                    <p v-if="alert.affected_trips_count !== undefined && alert.affected_trips_count !== null" class="pill bg-raised">
                        <Icon name="route" :size="14" />{{ t('alert.affected_trips', { n: number(alert.affected_trips_count) }) }}
                    </p>
                </div>
            </div>
        </article>

        <p v-if="missingTranslation" class="rounded-2xl border border-line bg-soft p-3 text-sm" role="note">
            {{ t('common.fallback_locale_notice', { lang: te('locale', otherLang) }) }}
        </p>

        <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
            <div class="space-y-6">
                <section v-if="alert.description" class="clay-card-sm p-5 md:p-6" aria-labelledby="al-desc">
                    <h2 id="al-desc" class="section-title text-xl">{{ t('alert.description') }}</h2>
                    <p class="mt-3 whitespace-pre-line">{{ alert.description }}</p>
                </section>
                <section v-if="alert.instructions" class="clay-card-sm border-2 p-5 md:p-6" :class="HERO[level]" aria-labelledby="al-inst">
                    <h2 id="al-inst" class="section-title text-xl">{{ t('alert.instructions') }}</h2>
                    <p class="mt-3 whitespace-pre-line font-medium">{{ alert.instructions }}</p>
                </section>
                <section aria-labelledby="al-map" class="space-y-3">
                    <h2 id="al-map" class="section-title text-xl">{{ t('alert.map_title') }}</h2>
                    <MapBase :geojson="zonesGeo" :overlays="overlays" :aria-label="t('alert.map_title')" />
                    <p v-if="!alert.geometry" class="text-xs text-muted">{{ t('alert.map_by_zones') }}</p>
                </section>
                <section aria-labelledby="al-guides" class="space-y-3">
                    <h2 id="al-guides" class="section-title text-xl">{{ t('alert.related_guides') }}</h2>
                    <div v-if="alert.guides?.length" class="grid gap-4 sm:grid-cols-2">
                        <GuideCard v-for="g in alert.guides" :key="g.slug" :guide="g" />
                    </div>
                    <EmptyState v-else :title="t('home.no_guides')" compact>
                        <Button :href="lr('/huong-dan-ung-pho')" size="sm">{{ t('nav.guides') }}</Button>
                    </EmptyState>
                </section>
            </div>

            <aside class="space-y-6">
                <section aria-labelledby="al-contacts" class="space-y-3">
                    <h2 id="al-contacts" class="section-title text-xl">{{ t('alert.nearby_contacts') }}</h2>
                    <ul v-if="nearbyContacts.length || alert.contacts?.length" class="grid gap-3">
                        <li v-for="c in (nearbyContacts.length ? nearbyContacts : alert.contacts)" :key="c.id"><ContactItem :contact="c" compact /></li>
                    </ul>
                    <EmptyState v-else :title="t('contacts.empty')" compact>
                        <Button :href="lr('/danh-ba-khan-cap')" size="sm">{{ t('nav.contacts') }}</Button>
                    </EmptyState>
                </section>
                <section class="clay-card-sm p-5" aria-labelledby="al-history">
                    <h2 id="al-history" class="section-title mb-4 text-lg">{{ t('alert.history') }}</h2>
                    <StatusHistory :items="alert.history || []" />
                </section>
                <details v-if="alert.raw_payload_excerpt" class="clay-card-sm p-5 text-sm">
                    <summary class="cursor-pointer font-semibold">{{ t('alert.raw_payload') }}</summary>
                    <pre class="mt-3 max-h-64 overflow-auto whitespace-pre-wrap break-all rounded-xl bg-soft p-3 text-xs">{{ typeof alert.raw_payload_excerpt === 'string' ? alert.raw_payload_excerpt : JSON.stringify(alert.raw_payload_excerpt, null, 2) }}</pre>
                </details>
                <Button :href="lr('/canh-bao')" icon="arrow-left" variant="ghost">{{ t('alert.back_to_list') }}</Button>
            </aside>
        </div>
    </div>
</template>
