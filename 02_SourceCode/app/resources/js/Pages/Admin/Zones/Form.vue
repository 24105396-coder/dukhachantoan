<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FormField from '@/Components/FormField.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormActions from '@/Components/Admin/FormActions.vue';
import GeometryEditor from '@/Components/Admin/GeometryEditor.vue';
import MapBase from '@/Components/MapBase.vue';
import WeatherNowCard from '@/Components/WeatherNowCard.vue';
import RiskScore from '@/Components/RiskScore.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { LOCALES } from '@/Utils/options';

/**
 * Sửa vùng: tên vi/en, hình học (dán GeoJSON) hoặc tâm + bán kính; xem thời tiết hiện tại và điểm rủi ro.
 * PUT /quan-tri/vung/{code}.
 */
const props = defineProps({
    zone: { type: Object, required: true },
    weather: { type: Object, default: null },
    risk: { type: Object, default: null },
});
const { t, te } = useT();
const lr = useLocaleRoute();
const z = props.zone;
const form = useForm({
    translations: Object.fromEntries(LOCALES.map((l) => [l, { name: z.translations?.[l]?.name ?? (l === 'vi' ? z.name : '') }])),
    geometry: z.geometry || null,
    centroid_lat: z.centroid?.lat ?? '',
    centroid_lng: z.centroid?.lng ?? '',
    radius_km: z.radius_km ?? '',
});
const centroidPreview = computed(() => {
    if (form.geometry || form.centroid_lat === '' || form.centroid_lng === '' || !Number(form.radius_km)) return [];
    return [{ id: 'c', geometry: { type: 'Circle', center: { lat: Number(form.centroid_lat), lng: Number(form.centroid_lng) }, radius_km: Number(form.radius_km) }, level: props.risk?.level ?? z.risk?.level ?? 0, label: z.name }];
});
function submit() {
    const errs = {};
    if (!form.translations.vi.name.trim()) errs['translations.vi.name'] = t('validation.client.required', { field: t('admin.common.name') });
    if (!form.geometry && (form.centroid_lat === '' || form.centroid_lng === '' || !(Number(form.radius_km) > 0))) errs.radius_km = t('admin.zones.err_area');
    form.clearErrors();
    if (Object.keys(errs).length) {
        form.setError(errs);
        return;
    }
    const blank = (v) => (v === '' ? null : Number(v));
    form.transform((d) => ({ ...d, centroid_lat: blank(d.centroid_lat), centroid_lng: blank(d.centroid_lng), radius_km: blank(d.radius_km) }))
        .put(lr(`/quan-tri/vung/${z.code}`), { preserveScroll: true });
}
const title = computed(() => t('admin.zones.edit_title', { name: z.name }));
</script>

<template>
    <Head :title="title" />
    <PageHeader :breadcrumbs="[{ label: t('admin.nav.zones'), href: lr('/quan-tri/vung') }, { label: z.name }]" :eyebrow="`${te('zone_type', z.zone_type)} · ${z.code}`" :title="title" />
    <div class="mb-6 grid gap-4 lg:grid-cols-2">
        <WeatherNowCard v-if="weather" :weather="weather" />
        <EmptyState v-else :title="t('home.no_weather')" :description="t('home.no_weather_hint')" compact />
        <RiskScore :risk="risk || z.risk" />
    </div>
    <form class="space-y-5" novalidate @submit.prevent="submit">
        <FormSection :title="t('admin.zones.names')">
            <div class="grid gap-4 md:grid-cols-2">
                <FormField v-for="l in LOCALES" :key="l" v-model="form.translations[l].name" :label="`${t('admin.common.name')} (${te('locale', l)})`" :required="l === 'vi'" maxlength="150" :error="form.errors[`translations.${l}.name`]" />
            </div>
        </FormSection>
        <FormSection :title="t('admin.zones.geometry')" :description="t('admin.zones.geometry_hint')">
            <GeometryEditor v-model="form.geometry" :allow-circle="false" :none-label="t('admin.zones.use_centroid')" :level="Number(z.risk?.level ?? 0)" :error="form.errors.geometry" />
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <FormField v-model="form.centroid_lat" type="number" step="0.000001" :label="t('admin.zones.centroid_lat')" inputmode="decimal" :error="form.errors.centroid_lat" />
                <FormField v-model="form.centroid_lng" type="number" step="0.000001" :label="t('admin.zones.centroid_lng')" inputmode="decimal" :error="form.errors.centroid_lng" />
                <FormField v-model="form.radius_km" type="number" step="0.5" :min="0" :label="t('geometry.radius')" inputmode="decimal" :hint="t('admin.zones.radius_hint')" :error="form.errors.radius_km" />
            </div>
            <MapBase v-if="centroidPreview.length" class="mt-4" :overlays="centroidPreview" :show-legend="false" height-class="h-[260px]" :aria-label="t('geometry.preview')" />
        </FormSection>
        <FormActions :cancel-href="lr('/quan-tri/vung')" :processing="form.processing" :has-errors="form.hasErrors" />
    </form>
</template>
