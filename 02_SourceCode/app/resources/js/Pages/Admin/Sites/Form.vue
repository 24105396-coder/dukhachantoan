<script setup>
import { SLUG_PATTERN, SLUG_RE, YEAR_MIN, YEAR_MAX } from '@/Utils/rules';
import { useClientValidation } from '@/Composables/useClientValidation';
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import FormField from '@/Components/FormField.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormActions from '@/Components/Admin/FormActions.vue';
import SectionNav from '@/Components/Admin/SectionNav.vue';
import InterpretationEditor from '@/Components/Admin/InterpretationEditor.vue';
import TagPicker from '@/Components/Admin/TagPicker.vue';
import FigureLinksEditor from '@/Components/Admin/FigureLinksEditor.vue';
import EventsEditor from '@/Components/Admin/EventsEditor.vue';
import MediaManager from '@/Components/Admin/MediaManager.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { STATUS_CODES, enumOptions, entityOptions } from '@/Utils/options';
import { buildInterpretations, serializeInterpretations, hasErrorWithPrefix } from '@/Utils/contentForm';

const props = defineProps({
    site: { type: Object, default: null },
    provinces: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
    figures: { type: Array, default: () => [] },
    enums: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const lr = useLocaleRoute();

const s = props.site || {};
const isEdit = computed(() => !!props.site?.id);

const EVENT_DEFAULTS = { id: null, event_type: '', event_year: '', event_month: '', event_day: '', end_year: '', importance: 2, title_vi: '', title_en: '', description_vi: '', description_en: '' };

const form = useForm({
    slug: s.slug ?? '',
    site_type: s.site_type ?? '',
    recognition_level: s.recognition_level ?? 'chua_xep_hang',
    province_id: s.province_id ?? '',
    period_id: s.period_id ?? '',
    address: s.address ?? '',
    lat: s.lat ?? '',
    lng: s.lng ?? '',
    year_built: s.year_built ?? '',
    year_recognized: s.year_recognized ?? '',
    visiting_hours: s.visiting_hours ?? '',
    ticket_price_vnd: s.ticket_price_vnd ?? 0,
    website: s.website ?? '',
    status: s.status ?? 'draft',
    interpretations: buildInterpretations(s.interpretations),
    tag_ids: [...(s.tag_ids || [])],
    figures: (s.figures || []).map((f) => ({ figure_id: f.figure_id, relation_type: f.relation_type })),
    events: (s.events || []).map((e) => ({ ...EVENT_DEFAULTS, ...e })),
});

const opt = computed(() => ({
    types: enumOptions(props.enums.site_types || ['di_tich', 'danh_thang', 'di_san_phi_vat_the', 'bao_tang', 'lang_nghe'], te, 'site_type'),
    levels: enumOptions(props.enums.levels || ['unesco', 'quoc_gia_dac_biet', 'quoc_gia', 'cap_tinh', 'chua_xep_hang'], te, 'recognition_level'),
    relations: enumOptions(props.enums.relation_types || ['sang_lap', 'duoc_tho', 'gan_lien', 'trung_tu', 'sinh_ra', 'an_tang'], te, 'relation_type'),
    events: enumOptions(props.enums.event_types || ['xay_dung', 'trung_tu', 'cong_nhan', 'su_kien', 'khac'], te, 'event_type'),
    status: STATUS_CODES.map((c) => ({ value: c, label: te('status', c) })),
    provinces: entityOptions(props.provinces),
    periods: entityOptions(props.periods),
    figures: entityOptions(props.figures),
}));

const sections = computed(() => [
    { id: 'general', label: t('admin.form.section_general'), error: ['slug', 'site_type', 'province_id', 'lat', 'lng', 'status'].some((k) => form.errors[k]) },
    { id: 'interpretations', label: t('admin.form.section_interpretations'), error: hasErrorWithPrefix(form.errors, 'interpretations') },
    { id: 'tags', label: t('admin.form.section_tags'), error: hasErrorWithPrefix(form.errors, 'tag_ids') },
    { id: 'figures', label: t('admin.form.section_figures'), error: hasErrorWithPrefix(form.errors, 'figures') },
    { id: 'events', label: t('admin.form.section_events'), error: hasErrorWithPrefix(form.errors, 'events') },
    ...(isEdit.value ? [{ id: 'media', label: t('admin.form.section_media') }] : []),
]);

// Diễn giải bắt buộc: ô vi – độ sâu 1 (chỉ số 0 trong buildInterpretations)
const interpLabel = (key) => () => `${t(key)} (vi, 1)`;
const { validate } = useClientValidation(form, {
    slug: { label: 'admin.form.slug', required: true, max: 150, pattern: { regex: SLUG_RE, message: 'slug' } },
    site_type: { label: 'admin.sites.site_type', required: true },
    recognition_level: { label: 'admin.sites.recognition_level', required: true },
    province_id: { label: 'common.province', required: true },
    status: { label: 'common.status', required: true },
    address: { label: 'admin.sites.address', max: 255 },
    lat: { label: 'admin.sites.lat', required: true, number: { min: 8, max: 24 } },
    lng: { label: 'admin.sites.lng', required: true, number: { min: 102, max: 110 } },
    year_built: { label: 'admin.sites.year_built', number: { min: YEAR_MIN, max: YEAR_MAX, integer: true } },
    year_recognized: { label: 'admin.sites.year_recognized', number: { min: 1900, max: YEAR_MAX, integer: true } },
    visiting_hours: { label: 'admin.sites.visiting_hours', max: 120 },
    ticket_price_vnd: { label: 'admin.sites.ticket_price_vnd', number: { min: 0, max: 100000000, integer: true } },
    website: { label: 'admin.sites.website', url: true, max: 255 },
    'interpretations.0.title': { label: interpLabel('admin.form.interp_title'), required: true, max: 255 },
    'interpretations.0.body': { label: interpLabel('admin.form.interp_body'), required: true },
});

function submit(e) {
    if (!validate(e)) return;
    form.transform((data) => ({ ...data, interpretations: serializeInterpretations(data.interpretations) }));
    const opts = { preserveScroll: true };
    if (isEdit.value) form.put(lr(`/quan-tri/di-san/${s.slug}`), opts);
    else form.post(lr('/quan-tri/di-san'), opts);
}

const title = computed(() => (isEdit.value ? t('admin.sites.edit_title', { name: s.title_vi || s.slug }) : t('admin.sites.create_title')));
</script>

<template>
    <Head :title="title" />
    <Breadcrumbs :items="[{ label: t('admin.sites.title'), href: lr('/quan-tri/di-san') }, { label: title }]" class="mb-3" />
    <PageHeader :title="title" />
    <SectionNav :sections="sections" />

    <form class="space-y-6" novalidate @submit.prevent="submit">
        <FormSection id="general" :title="t('admin.form.section_general')" class="scroll-mt-20">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <FormField v-model="form.slug" :label="t('admin.form.slug')" :hint="t('admin.form.slug_hint')" :error="form.errors.slug" required maxlength="150" :pattern="SLUG_PATTERN" :pattern-hint="t('validation_hint.slug')" />
                <FormField v-model="form.site_type" type="select" :label="t('admin.sites.site_type')" :options="opt.types" :placeholder="t('admin.form.choose')" :error="form.errors.site_type" required />
                <FormField v-model="form.recognition_level" type="select" :label="t('admin.sites.recognition_level')" :options="opt.levels" :error="form.errors.recognition_level" required />
                <FormField v-model="form.province_id" type="select" :label="t('common.province')" :options="opt.provinces" :placeholder="t('admin.form.choose')" :error="form.errors.province_id" required />
                <FormField v-model="form.period_id" type="select" :label="t('common.period')" :options="opt.periods" :placeholder="t('common.none')" :error="form.errors.period_id" />
                <FormField v-model="form.status" type="select" :label="t('common.status')" :options="opt.status" :error="form.errors.status" required />
                <FormField v-model="form.address" class="sm:col-span-2 lg:col-span-3" maxlength="255" :label="t('admin.sites.address')" :error="form.errors.address" />
                <FormField v-model="form.lat" type="number" step="any" min="8" max="24" :label="t('admin.sites.lat')" :error="form.errors.lat" required />
                <FormField v-model="form.lng" type="number" step="any" min="102" max="110" :label="t('admin.sites.lng')" :error="form.errors.lng" required />
                <FormField v-model="form.year_built" type="number" :min="YEAR_MIN" :max="YEAR_MAX" :label="t('admin.sites.year_built')" :hint="t('admin.form.year_hint')" :error="form.errors.year_built" />
                <FormField v-model="form.year_recognized" type="number" min="1900" :max="YEAR_MAX" :label="t('admin.sites.year_recognized')" :error="form.errors.year_recognized" />
                <FormField v-model="form.visiting_hours" maxlength="120" :label="t('admin.sites.visiting_hours')" :error="form.errors.visiting_hours" />
                <FormField v-model="form.ticket_price_vnd" type="number" min="0" max="100000000" :label="t('admin.sites.ticket_price_vnd')" :error="form.errors.ticket_price_vnd" />
                <FormField v-model="form.website" type="url" maxlength="255" class="sm:col-span-2 lg:col-span-3" :label="t('admin.sites.website')" :error="form.errors.website" />
            </div>
        </FormSection>

        <FormSection id="interpretations" :title="t('admin.form.section_interpretations')" :description="t('admin.form.interp_description')" class="scroll-mt-20">
            <InterpretationEditor :items="form.interpretations" :errors="form.errors" />
        </FormSection>

        <FormSection id="tags" :title="t('admin.form.section_tags')" class="scroll-mt-20">
            <TagPicker v-model="form.tag_ids" :tags="tags" :error="form.errors.tag_ids" />
        </FormSection>

        <FormSection id="figures" :title="t('admin.form.section_figures')" class="scroll-mt-20">
            <FigureLinksEditor :rows="form.figures" :figure-options="opt.figures" :relation-options="opt.relations" :errors="form.errors" />
        </FormSection>

        <FormSection id="events" :title="t('admin.form.section_events')" :description="t('admin.form.events_description')" class="scroll-mt-20">
            <EventsEditor :rows="form.events" :type-options="opt.events" :errors="form.errors" />
        </FormSection>

        <FormActions :cancel-href="lr('/quan-tri/di-san')" :processing="form.processing" :has-errors="form.hasErrors" />
    </form>

    <FormSection v-if="isEdit" id="media" :title="t('admin.form.section_media')" :description="t('admin.form.media_description')" class="mt-6 scroll-mt-20">
        <MediaManager :media="site.media || []" subject-type="site" :subject-id="site.id" :licenses="enums.licenses" />
    </FormSection>
    <p v-else class="mt-6 text-sm text-muted">{{ t('admin.form.media_after_create') }}</p>
</template>
