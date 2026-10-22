<script setup>
import { SLUG_PATTERN, SLUG_RE } from '@/Utils/rules';
import { useClientValidation } from '@/Composables/useClientValidation';
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import FormField from '@/Components/FormField.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormActions from '@/Components/Admin/FormActions.vue';
import StopsEditor from '@/Components/Admin/StopsEditor.vue';
import SlotsManager from '@/Components/Admin/SlotsManager.vue';
import MediaManager from '@/Components/Admin/MediaManager.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { usePermissions } from '@/Composables/usePermissions';
import { STATUS_CODES, LOCALES, entityOptions } from '@/Utils/options';

const props = defineProps({
    route: { type: Object, default: null },
    sites: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
    provinces: { type: Array, default: () => [] },
    enums: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const lr = useLocaleRoute();
// Chỉ admin/manager được tải và sửa tư liệu (máy chủ kiểm tra lại); đối tác chỉ thấy khung giờ.
const { isStaff } = usePermissions();

const r = props.route || {};
const isEdit = computed(() => !!props.route?.id);

// translations có thể là {vi:{...}, en:{...}} hoặc [{locale, ...}]
function normalizeTranslations(src) {
    const pick = (loc) => (Array.isArray(src) ? src.find((x) => x.locale === loc) : src?.[loc]) || {};
    return Object.fromEntries(
        LOCALES.map((loc) => {
            const x = pick(loc);
            return [loc, { name: x.name ?? '', description: x.description ?? '', highlights: x.highlights ?? '' }];
        }),
    );
}

const form = useForm({
    slug: r.slug ?? '',
    province_id: r.province_id ?? '',
    theme_tag_id: r.theme_tag_id ?? '',
    duration_minutes: r.duration_minutes ?? 120,
    distance_km: r.distance_km ?? '',
    difficulty: r.difficulty ?? 'de',
    price_vnd: r.price_vnd ?? 0,
    default_capacity: r.default_capacity ?? 20,
    status: r.status ?? 'draft',
    translations: normalizeTranslations(r.translations),
    stops: [...(r.stops || [])]
        .sort((x, y) => (x.stop_order ?? 0) - (y.stop_order ?? 0))
        .map((s) => ({ site_id: s.site_id ?? s.site?.id ?? '', dwell_minutes: s.dwell_minutes ?? 30, note: s.note ?? '' })),
});

const opt = computed(() => ({
    status: STATUS_CODES.map((c) => ({ value: c, label: te('status', c) })),
    difficulty: ['de', 'vua', 'kho'].map((c) => ({ value: c, label: te('difficulty', c) })),
    provinces: entityOptions(props.provinces),
    tags: entityOptions(props.tags),
    sites: entityOptions(props.sites),
}));

const { validate } = useClientValidation(form, {
    slug: { label: 'admin.form.slug', required: true, max: 150, pattern: { regex: SLUG_RE, message: 'slug' } },
    duration_minutes: { label: 'admin.tours.duration_minutes', required: true, number: { min: 30, max: 1440, integer: true } },
    distance_km: { label: 'admin.tours.distance_km', number: { min: 0, max: 1000 } },
    difficulty: { label: 'admin.tours.difficulty', required: true },
    price_vnd: { label: 'admin.tours.price_vnd', required: true, number: { min: 0, max: 100000000, integer: true } },
    default_capacity: { label: 'admin.tours.default_capacity', required: true, number: { min: 1, max: 500, integer: true } },
    status: { label: 'common.status', required: true },
    'translations.vi.name': { label: () => `${t('admin.tours.name')} (vi)`, required: true, max: 200 },
    stops: { label: 'admin.tours.stops', required: true },
});

function submit(e) {
    if (!validate(e)) return;
    form.transform((data) => ({ ...data, stops: data.stops.map((s, i) => ({ ...s, stop_order: i + 1 })) }));
    if (isEdit.value) form.put(lr(`/quan-tri/tuyen-tham-quan/${r.slug}`), { preserveScroll: true });
    else form.post(lr('/quan-tri/tuyen-tham-quan'), { preserveScroll: true });
}

const trErr = (loc, f) => form.errors[`translations.${loc}.${f}`];
const title = computed(() => (isEdit.value ? t('admin.tours.edit_title', { name: form.translations.vi.name || r.slug }) : t('admin.tours.create_title')));
</script>

<template>
    <Head :title="title" />
    <Breadcrumbs :items="[{ label: t('admin.tours.title'), href: lr('/quan-tri/tuyen-tham-quan') }, { label: title }]" class="mb-3" />
    <PageHeader :title="title" />

    <form class="space-y-6" novalidate @submit.prevent="submit">
        <FormSection :title="t('admin.form.section_general')">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <FormField v-model="form.slug" :label="t('admin.form.slug')" :hint="t('admin.form.slug_hint')" :error="form.errors.slug" required maxlength="150" :pattern="SLUG_PATTERN" :pattern-hint="t('validation_hint.slug')" />
                <FormField v-model="form.province_id" type="select" :label="t('common.province')" :options="opt.provinces" :placeholder="t('admin.form.choose')" :error="form.errors.province_id" />
                <FormField v-model="form.theme_tag_id" type="select" :label="t('admin.tours.theme')" :options="opt.tags" :placeholder="t('common.none')" :error="form.errors.theme_tag_id" />
                <FormField v-model="form.duration_minutes" type="number" min="30" max="1440" :label="t('admin.tours.duration_minutes')" :error="form.errors.duration_minutes" required />
                <FormField v-model="form.distance_km" type="number" min="0" max="1000" step="any" :label="t('admin.tours.distance_km')" :error="form.errors.distance_km" />
                <FormField v-model="form.difficulty" type="select" :label="t('admin.tours.difficulty')" :options="opt.difficulty" :error="form.errors.difficulty" required />
                <FormField v-model="form.price_vnd" type="number" min="0" max="100000000" :label="t('admin.tours.price_vnd')" :error="form.errors.price_vnd" required />
                <FormField v-model="form.default_capacity" type="number" min="1" max="500" :label="t('admin.tours.default_capacity')" :error="form.errors.default_capacity" required />
                <FormField v-model="form.status" type="select" :label="t('common.status')" :options="opt.status" :error="form.errors.status" required />
            </div>
        </FormSection>

        <FormSection :title="t('admin.tours.section_translations')">
            <div class="grid gap-6 lg:grid-cols-2">
                <fieldset v-for="loc in LOCALES" :key="loc" class="grid gap-3">
                    <legend class="mb-2 text-sm font-bold uppercase tracking-wider text-muted">{{ te('locale', loc) }}</legend>
                    <FormField v-model="form.translations[loc].name" :label="t('admin.tours.name')" :error="trErr(loc, 'name')" :required="loc === 'vi'" maxlength="200" />
                    <FormField v-model="form.translations[loc].description" type="textarea" :rows="4" maxlength="10000" :label="t('admin.tours.description_field')" :error="trErr(loc, 'description')" />
                    <FormField v-model="form.translations[loc].highlights" type="textarea" :rows="3" maxlength="5000" :label="t('admin.tours.highlights')" :hint="t('admin.tours.highlights_hint')" :error="trErr(loc, 'highlights')" />
                </fieldset>
            </div>
        </FormSection>

        <FormSection :title="t('admin.tours.section_stops')" :description="t('admin.tours.stops_description')">
            <StopsEditor :rows="form.stops" :site-options="opt.sites" :errors="form.errors" />
        </FormSection>

        <FormActions :cancel-href="lr('/quan-tri/tuyen-tham-quan')" :processing="form.processing" :has-errors="form.hasErrors" />
    </form>

    <FormSection v-if="isEdit" :title="t('admin.tours.slots')" :description="t('admin.tours.slots_description')" class="mt-6">
        <SlotsManager :route-slug="route.slug" :slots="route.slots || []" :default-capacity="form.default_capacity" :default-duration="form.duration_minutes" />
    </FormSection>
    <p v-else class="mt-6 text-sm text-muted">{{ t('admin.tours.slots_after_create') }}</p>

    <FormSection v-if="isEdit && isStaff" id="media" :title="t('admin.form.section_media')" :description="t('admin.form.media_description')" class="mt-6 scroll-mt-20">
        <MediaManager :media="route.media || []" subject-type="route" :subject-id="route.id" :licenses="enums.licenses" />
    </FormSection>
</template>
