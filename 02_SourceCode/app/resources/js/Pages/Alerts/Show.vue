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
import Figure from '@/Components/Figure.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useImages } from '@/Composables/useImages';
import { normalizeLevel } from '@/Utils/levels';

/** Chi tiết cảnh báo (AlertDetail): nhãn mức cạnh tiêu đề + viền trái màu mức, hàng meta vùng/hiệu lực, hướng dẫn, danh bạ gần, bản đồ phạm vi, lịch sử trạng thái. */
const props = defineProps({
    alert: { type: Object, required: true },
    zonesGeo: { type: Object, default: null },
    nearbyContacts: { type: Array, default: () => [] },
});
const { t, te, locale } = useT();
const { dateTime, number } = useFormat();
const lr = useLocaleRoute();
const images = useImages();
/** Ảnh minh họa loại hiểm họa (96px, chỉ từ 640px để khối cảnh báo trên điện thoại vẫn gọn). */
const hazardImage = computed(() => images.hazard(props.alert.hazard?.code));

const level = computed(() => normalizeLevel(props.alert.severity) ?? 1);
const CALLOUT = { 1: 'callout-1', 2: 'callout-2', 3: 'callout-3', 4: 'callout-4' };
const isInactive = computed(() => ['expired', 'cancelled'].includes(props.alert.status));
const overlays = computed(() => (props.alert.geometry
    ? [{
        id: props.alert.code, geometry: props.alert.geometry, level: level.value, label: props.alert.headline,
        source: props.alert.source?.name, updatedAt: props.alert.updated_at_source || props.alert.issued_at, simulated: !!props.alert.is_simulated,
    }]
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
    <div class="container-page space-y-5 py-6 md:py-8">
        <Breadcrumbs :items="crumbs" />
        <SimulatedBanner v-if="alert.is_simulated" :scenario="alert.scenario" />

        <!-- Khối đầu cảnh báo: nền trắng, viền trái màu mức, nhãn mức (màu + icon + chữ) cạnh tiêu đề -->
        <article class="callout p-5 md:p-6" :class="CALLOUT[level]" :data-severity="level">
            <Figure
                v-if="alert.hazard"
                :image="hazardImage"
                :alt="hazardImage?.alt || alert.hazard.name"
                class="float-right ml-4 hidden w-24 sm:block"
                ratio-class="aspect-square"
                rounded-class="rounded-[var(--radius-card-sm)]"
                credit="tooltip"
                :fallback-icon="alert.hazard.icon || alert.hazard.code || 'alert'"
                :width="96"
                :height="96"
            />
            <div class="flex flex-wrap items-center gap-2">
                <Badge :level="level" :label="alert.severity_label ? t('level.with_number', { n: level, label: alert.severity_label }) : undefined" show-level-number />
                <span v-if="alert.hazard" class="pill"><Icon :name="alert.hazard.icon || alert.hazard.code || 'alert'" fallback="alert" :size="14" />{{ alert.hazard.name }}</span>
                <Badge :tone="isInactive ? 'outline' : 'brand'" size="sm">{{ te('status', alert.status) }}</Badge>
                <span class="font-mono text-xs text-muted">{{ alert.code }}</span>
            </div>
            <h1 class="mt-3 leading-tight">{{ alert.headline }}</h1>
            <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                <div class="flex min-w-0 items-start gap-1.5">
                    <dt class="mt-0.5 shrink-0 text-muted"><Icon name="map-pin" :size="16" /><span class="sr-only">{{ t('alert.zones') }}</span></dt>
                    <dd class="font-semibold">{{ (alert.zones || []).map((z) => z.name).join(', ') || '—' }}</dd>
                </div>
                <div class="flex min-w-0 items-start gap-1.5">
                    <dt class="mt-0.5 shrink-0 text-muted"><Icon name="calendar-clock" :size="16" /><span class="sr-only">{{ t('alert.valid_time') }}</span></dt>
                    <dd class="font-semibold">
                        <time :datetime="alert.starts_at">{{ dateTime(alert.starts_at) }}</time> –
                        <time v-if="alert.ends_at" :datetime="alert.ends_at">{{ dateTime(alert.ends_at) }}</time>
                        <span v-else>{{ t('alert.until_further_notice') }}</span>
                    </dd>
                </div>
            </dl>
            <p v-if="alert.affected_trips_count !== undefined && alert.affected_trips_count !== null" class="mt-2 flex items-center gap-1.5 text-sm">
                <Icon name="route" :size="16" class="text-muted" />{{ t('alert.affected_trips', { n: number(alert.affected_trips_count) }) }}
            </p>
            <div class="mt-4 flex clear-both flex-wrap items-center gap-x-4 gap-y-1 border-t border-line pt-3">
                <SourceStamp :source="alert.source" :updated-at="alert.updated_at_source || alert.issued_at" :simulated="!!alert.is_simulated" />
                <p v-if="alert.issued_at" class="text-xs text-muted">{{ t('alert.issued_at', { time: dateTime(alert.issued_at) }) }}</p>
            </div>
        </article>

        <p v-if="missingTranslation" class="rounded-xl border border-line bg-soft p-3 text-sm" role="note">
            {{ t('common.fallback_locale_notice', { lang: te('locale', otherLang) }) }}
        </p>

        <div class="grid gap-5 lg:grid-cols-[1.6fr_1fr]">
            <div class="space-y-5">
                <section v-if="alert.description" class="clay-card-sm p-4 md:p-5" aria-labelledby="al-desc">
                    <h2 id="al-desc" class="section-title text-lg">{{ t('alert.description') }}</h2>
                    <p class="mt-2 whitespace-pre-line">{{ alert.description }}</p>
                </section>
                <section v-if="alert.instructions" class="callout p-4 md:p-5" :class="CALLOUT[level]" aria-labelledby="al-inst">
                    <h2 id="al-inst" class="section-title text-lg">{{ t('alert.instructions') }}</h2>
                    <p class="mt-2 whitespace-pre-line font-medium">{{ alert.instructions }}</p>
                </section>
                <section aria-labelledby="al-map" class="space-y-3">
                    <h2 id="al-map" class="section-title text-lg">{{ t('alert.map_title') }}</h2>
                    <MapBase :geojson="zonesGeo" :overlays="overlays" :aria-label="t('alert.map_title')" />
                    <p v-if="!alert.geometry" class="text-xs text-muted">{{ t('alert.map_by_zones') }}</p>
                </section>
                <section aria-labelledby="al-guides" class="space-y-3">
                    <h2 id="al-guides" class="section-title text-lg">{{ t('alert.related_guides') }}</h2>
                    <div v-if="alert.guides?.length" class="grid gap-4 sm:grid-cols-2">
                        <GuideCard v-for="g in alert.guides" :key="g.slug" :guide="g" />
                    </div>
                    <EmptyState v-else :title="t('home.no_guides')" compact>
                        <Button :href="lr('/huong-dan-ung-pho')" size="sm">{{ t('nav.guides') }}</Button>
                    </EmptyState>
                </section>
            </div>

            <aside class="space-y-5">
                <section aria-labelledby="al-contacts" class="space-y-3">
                    <h2 id="al-contacts" class="section-title text-lg">{{ t('alert.nearby_contacts') }}</h2>
                    <ul v-if="nearbyContacts.length || alert.contacts?.length" class="grid gap-2.5">
                        <li v-for="c in (nearbyContacts.length ? nearbyContacts : alert.contacts)" :key="c.id"><ContactItem :contact="c" compact /></li>
                    </ul>
                    <EmptyState v-else :title="t('contacts.empty')" compact>
                        <Button :href="lr('/danh-ba-khan-cap')" size="sm">{{ t('nav.contacts') }}</Button>
                    </EmptyState>
                </section>
                <section class="clay-card-sm p-4" aria-labelledby="al-history">
                    <h2 id="al-history" class="section-title mb-4 text-lg">{{ t('alert.history') }}</h2>
                    <StatusHistory :items="alert.history || []" />
                </section>
                <details v-if="alert.raw_payload_excerpt" class="clay-card-sm p-4 text-sm">
                    <summary class="cursor-pointer font-semibold">{{ t('alert.raw_payload') }}</summary>
                    <pre class="mt-3 max-h-64 overflow-auto whitespace-pre-wrap break-all rounded-xl bg-soft p-3 text-xs">{{ typeof alert.raw_payload_excerpt === 'string' ? alert.raw_payload_excerpt : JSON.stringify(alert.raw_payload_excerpt, null, 2) }}</pre>
                </details>
                <Button :href="lr('/canh-bao')" icon="arrow-left" variant="ghost">{{ t('alert.back_to_list') }}</Button>
            </aside>
        </div>
    </div>
</template>
