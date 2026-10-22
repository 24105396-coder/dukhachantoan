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
import MediaManager from '@/Components/Admin/MediaManager.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { STATUS_CODES, enumOptions, entityOptions } from '@/Utils/options';
import { buildInterpretations, serializeInterpretations, hasErrorWithPrefix } from '@/Utils/contentForm';

const props = defineProps({
    figure: { type: Object, default: null },
    provinces: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
    enums: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const lr = useLocaleRoute();

const f = props.figure || {};
const isEdit = computed(() => !!props.figure?.id);
const FIGURE_TYPES = ['vua_chua', 'tuong_linh', 'nha_van_hoa', 'anh_hung', 'nhan_vat_ton_giao', 'nghe_nhan', 'khac'];

const form = useForm({
    slug: f.slug ?? '',
    figure_type: f.figure_type ?? '',
    birth_year: f.birth_year ?? '',
    death_year: f.death_year ?? '',
    period_id: f.period_id ?? '',
    province_id: f.province_id ?? '',
    status: f.status ?? 'draft',
    interpretations: buildInterpretations(f.interpretations),
    tag_ids: [...(f.tag_ids || [])],
});

const opt = computed(() => ({
    types: enumOptions(props.enums.figure_types || FIGURE_TYPES, te, 'figure_type'),
    status: STATUS_CODES.map((c) => ({ value: c, label: te('status', c) })),
    provinces: entityOptions(props.provinces),
    periods: entityOptions(props.periods),
}));

const GENERAL_KEYS = ['slug', 'figure_type', 'birth_year', 'death_year', 'period_id', 'province_id', 'status'];
const sections = computed(() => [
    { id: 'general', label: t('admin.form.section_general'), error: GENERAL_KEYS.some((k) => form.errors[k]) },
    { id: 'interpretations', label: t('admin.form.section_interpretations'), error: hasErrorWithPrefix(form.errors, 'interpretations') },
    { id: 'tags', label: t('admin.form.section_tags'), error: hasErrorWithPrefix(form.errors, 'tag_ids') },
    ...(isEdit.value ? [{ id: 'media', label: t('admin.form.section_media') }] : []),
]);

// Diễn giải bắt buộc: ô vi – độ sâu 1 (chỉ số 0 trong buildInterpretations)
const interpLabel = (key) => () => `${t(key)} (vi, 1)`;
const { validate } = useClientValidation(form, {
    slug: { label: 'admin.form.slug', required: true, max: 150, pattern: { regex: SLUG_RE, message: 'slug' } },
    figure_type: { label: 'common.type', required: true },
    status: { label: 'common.status', required: true },
    birth_year: { label: 'admin.figures.birth_year', number: { min: YEAR_MIN, max: YEAR_MAX, integer: true } },
    death_year: { label: 'admin.figures.death_year', number: { min: YEAR_MIN, max: YEAR_MAX, integer: true } },
    'interpretations.0.title': { label: interpLabel('admin.form.interp_title'), required: true, max: 255 },
    'interpretations.0.body': { label: interpLabel('admin.form.interp_body'), required: true },
});

function submit(e) {
    if (!validate(e)) return;
    form.transform((data) => ({ ...data, interpretations: serializeInterpretations(data.interpretations) }));
    if (isEdit.value) form.put(lr(`/quan-tri/nhan-vat/${f.slug}`), { preserveScroll: true });
    else form.post(lr('/quan-tri/nhan-vat'), { preserveScroll: true });
}

const title = computed(() => (isEdit.value ? t('admin.figures.edit_title', { name: f.title_vi || f.slug }) : t('admin.figures.create_title')));
</script>

<template>
    <Head :title="title" />
    <Breadcrumbs :items="[{ label: t('admin.figures.title'), href: lr('/quan-tri/nhan-vat') }, { label: title }]" class="mb-3" />
    <PageHeader :title="title" />
    <SectionNav :sections="sections" />

    <form class="space-y-6" novalidate @submit.prevent="submit">
        <FormSection id="general" :title="t('admin.form.section_general')" class="scroll-mt-20">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <FormField v-model="form.slug" :label="t('admin.form.slug')" :hint="t('admin.form.slug_hint')" :error="form.errors.slug" required maxlength="150" :pattern="SLUG_PATTERN" :pattern-hint="t('validation_hint.slug')" />
                <FormField v-model="form.figure_type" type="select" :label="t('common.type')" :options="opt.types" :placeholder="t('admin.form.choose')" :error="form.errors.figure_type" required />
                <FormField v-model="form.status" type="select" :label="t('common.status')" :options="opt.status" :error="form.errors.status" required />
                <FormField v-model="form.birth_year" type="number" :min="YEAR_MIN" :max="YEAR_MAX" :label="t('admin.figures.birth_year')" :hint="t('admin.form.year_hint')" :error="form.errors.birth_year" />
                <FormField v-model="form.death_year" type="number" :min="YEAR_MIN" :max="YEAR_MAX" :label="t('admin.figures.death_year')" :error="form.errors.death_year" />
                <FormField v-model="form.period_id" type="select" :label="t('common.period')" :options="opt.periods" :placeholder="t('common.none')" :error="form.errors.period_id" />
                <FormField v-model="form.province_id" type="select" :label="t('admin.figures.province')" :options="opt.provinces" :placeholder="t('common.none')" :error="form.errors.province_id" />
            </div>
        </FormSection>

        <FormSection id="interpretations" :title="t('admin.form.section_interpretations')" :description="t('admin.form.interp_description')" class="scroll-mt-20">
            <InterpretationEditor :items="form.interpretations" :errors="form.errors" />
        </FormSection>

        <FormSection id="tags" :title="t('admin.form.section_tags')" class="scroll-mt-20">
            <TagPicker v-model="form.tag_ids" :tags="tags" :error="form.errors.tag_ids" />
        </FormSection>

        <FormActions :cancel-href="lr('/quan-tri/nhan-vat')" :processing="form.processing" :has-errors="form.hasErrors" />
    </form>

    <FormSection v-if="isEdit" id="media" :title="t('admin.form.section_media')" :description="t('admin.form.media_description')" class="mt-6 scroll-mt-20">
        <MediaManager :media="figure.media || []" subject-type="figure" :subject-id="figure.id" :licenses="enums.licenses" />
    </FormSection>
    <p v-else class="mt-6 text-sm text-muted">{{ t('admin.form.media_after_create') }}</p>
</template>
