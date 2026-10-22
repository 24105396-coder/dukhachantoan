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
    artifact: { type: Object, default: null },
    sites: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
    enums: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const lr = useLocaleRoute();

const a = props.artifact || {};
const isEdit = computed(() => !!props.artifact?.id);
const ARTIFACT_TYPES = ['tuong', 'bia_ky', 'do_gom', 'do_dong', 'vu_khi', 'trang_phuc', 'kien_truc', 'tai_lieu', 'bao_vat', 'khac'];

const form = useForm({
    slug: a.slug ?? '',
    site_id: a.site_id ?? '',
    inventory_code: a.inventory_code ?? '',
    artifact_type: a.artifact_type ?? '',
    material: a.material ?? '',
    period_id: a.period_id ?? '',
    year_from: a.year_from ?? '',
    year_to: a.year_to ?? '',
    height_cm: a.height_cm ?? '',
    width_cm: a.width_cm ?? '',
    depth_cm: a.depth_cm ?? '',
    weight_kg: a.weight_kg ?? '',
    is_national_treasure: !!a.is_national_treasure,
    model3d_url: a.model3d_url ?? '',
    status: a.status ?? 'draft',
    interpretations: buildInterpretations(a.interpretations),
    tag_ids: [...(a.tag_ids || [])],
});

const opt = computed(() => ({
    types: enumOptions(props.enums.artifact_types || ARTIFACT_TYPES, te, 'artifact_type'),
    status: STATUS_CODES.map((c) => ({ value: c, label: te('status', c) })),
    sites: entityOptions(props.sites),
    periods: entityOptions(props.periods),
}));

const GENERAL_KEYS = ['slug', 'site_id', 'inventory_code', 'artifact_type', 'material', 'period_id', 'year_from', 'year_to', 'status', 'model3d_url'];
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
    site_id: { label: 'admin.artifacts.site', required: true },
    inventory_code: { label: 'admin.artifacts.inventory_code', max: 40 },
    artifact_type: { label: 'common.type', required: true },
    material: { label: 'admin.artifacts.material', max: 120 },
    status: { label: 'common.status', required: true },
    year_from: { label: 'admin.artifacts.year_from', number: { min: YEAR_MIN, max: YEAR_MAX, integer: true } },
    year_to: { label: 'admin.artifacts.year_to', number: { min: YEAR_MIN, max: YEAR_MAX, integer: true } },
    height_cm: { label: 'admin.artifacts.height_cm', number: { min: 0, max: 99999 } },
    width_cm: { label: 'admin.artifacts.width_cm', number: { min: 0, max: 99999 } },
    depth_cm: { label: 'admin.artifacts.depth_cm', number: { min: 0, max: 99999 } },
    weight_kg: { label: 'admin.artifacts.weight_kg', number: { min: 0, max: 99999 } },
    model3d_url: { label: 'admin.artifacts.model3d_url', url: ['https:'], max: 255 },
    'interpretations.0.title': { label: interpLabel('admin.form.interp_title'), required: true, max: 255 },
    'interpretations.0.body': { label: interpLabel('admin.form.interp_body'), required: true },
});

function submit(e) {
    if (!validate(e)) return;
    form.transform((data) => ({ ...data, interpretations: serializeInterpretations(data.interpretations) }));
    if (isEdit.value) form.put(lr(`/quan-tri/hien-vat/${a.slug}`), { preserveScroll: true });
    else form.post(lr('/quan-tri/hien-vat'), { preserveScroll: true });
}

const title = computed(() => (isEdit.value ? t('admin.artifacts.edit_title', { name: a.title_vi || a.slug }) : t('admin.artifacts.create_title')));
</script>

<template>
    <Head :title="title" />
    <Breadcrumbs :items="[{ label: t('admin.artifacts.title'), href: lr('/quan-tri/hien-vat') }, { label: title }]" class="mb-3" />
    <PageHeader :title="title" />
    <SectionNav :sections="sections" />

    <form class="space-y-6" novalidate @submit.prevent="submit">
        <FormSection id="general" :title="t('admin.form.section_general')" class="scroll-mt-20">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <FormField v-model="form.slug" :label="t('admin.form.slug')" :hint="t('admin.form.slug_hint')" :error="form.errors.slug" required maxlength="150" :pattern="SLUG_PATTERN" :pattern-hint="t('validation_hint.slug')" />
                <FormField v-model="form.site_id" type="select" :label="t('admin.artifacts.site')" :options="opt.sites" :placeholder="t('admin.form.choose')" :error="form.errors.site_id" required />
                <FormField v-model="form.inventory_code" maxlength="40" :label="t('admin.artifacts.inventory_code')" :error="form.errors.inventory_code" />
                <FormField v-model="form.artifact_type" type="select" :label="t('common.type')" :options="opt.types" :placeholder="t('admin.form.choose')" :error="form.errors.artifact_type" required />
                <FormField v-model="form.material" maxlength="120" :label="t('admin.artifacts.material')" :error="form.errors.material" />
                <FormField v-model="form.period_id" type="select" :label="t('common.period')" :options="opt.periods" :placeholder="t('common.none')" :error="form.errors.period_id" />
                <FormField v-model="form.year_from" type="number" :min="YEAR_MIN" :max="YEAR_MAX" :label="t('admin.artifacts.year_from')" :hint="t('admin.form.year_hint')" :error="form.errors.year_from" />
                <FormField v-model="form.year_to" type="number" :min="YEAR_MIN" :max="YEAR_MAX" :label="t('admin.artifacts.year_to')" :error="form.errors.year_to" />
                <FormField v-model="form.status" type="select" :label="t('common.status')" :options="opt.status" :error="form.errors.status" required />
                <FormField v-model="form.height_cm" type="number" step="any" min="0" max="99999" :label="t('admin.artifacts.height_cm')" :error="form.errors.height_cm" />
                <FormField v-model="form.width_cm" type="number" step="any" min="0" max="99999" :label="t('admin.artifacts.width_cm')" :error="form.errors.width_cm" />
                <FormField v-model="form.depth_cm" type="number" step="any" min="0" max="99999" :label="t('admin.artifacts.depth_cm')" :error="form.errors.depth_cm" />
                <FormField v-model="form.weight_kg" type="number" step="any" min="0" max="99999" :label="t('admin.artifacts.weight_kg')" :error="form.errors.weight_kg" />
                <FormField v-model="form.model3d_url" type="url" maxlength="255" pattern="https://.*" class="sm:col-span-2" :label="t('admin.artifacts.model3d_url')" :hint="t('admin.artifacts.model3d_hint')" :error="form.errors.model3d_url" />
                <div class="flex items-end pb-2 sm:col-span-2 lg:col-span-3">
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input v-model="form.is_national_treasure" type="checkbox" class="h-4 w-4 accent-[var(--ds-primary)]" />
                        {{ t('artifacts.national_treasure') }}
                    </label>
                    <p v-if="form.errors.is_national_treasure" class="field-error ml-3">{{ form.errors.is_national_treasure }}</p>
                </div>
            </div>
        </FormSection>

        <FormSection id="interpretations" :title="t('admin.form.section_interpretations')" :description="t('admin.form.interp_description')" class="scroll-mt-20">
            <InterpretationEditor :items="form.interpretations" :errors="form.errors" />
        </FormSection>

        <FormSection id="tags" :title="t('admin.form.section_tags')" class="scroll-mt-20">
            <TagPicker v-model="form.tag_ids" :tags="tags" :error="form.errors.tag_ids" />
        </FormSection>

        <FormActions :cancel-href="lr('/quan-tri/hien-vat')" :processing="form.processing" :has-errors="form.hasErrors" />
    </form>

    <FormSection v-if="isEdit" id="media" :title="t('admin.form.section_media')" :description="t('admin.form.media_description')" class="mt-6 scroll-mt-20">
        <MediaManager :media="artifact.media || []" subject-type="artifact" :subject-id="artifact.id" :licenses="enums.licenses" />
    </FormSection>
    <p v-else class="mt-6 text-sm text-muted">{{ t('admin.form.media_after_create') }}</p>
</template>
