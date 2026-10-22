<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FormField from '@/Components/FormField.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormActions from '@/Components/Admin/FormActions.vue';
import TranslationTabs from '@/Components/Admin/TranslationTabs.vue';
import StepsEditor from '@/Components/Admin/StepsEditor.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { enumOptions, ALERT_LEVELS, LOCALES } from '@/Utils/options';

/**
 * Soạn hướng dẫn ứng phó 2 ngôn ngữ: tiêu đề, tóm tắt, các bước trước/trong/sau (danh sách sửa được), nguồn tham khảo;
 * slug, hiểm họa, mức áp dụng, đối tượng, trạng thái (nháp/xuất bản).
 */
const props = defineProps({
    guide: { type: Object, default: null },
    hazards: { type: Array, default: () => [] },
});
const { t, te } = useT();
const lr = useLocaleRoute();
const isEdit = computed(() => !!props.guide?.id);
const AUDIENCES = ['all', 'international', 'elderly', 'family'];
const PHASES = ['before', 'during', 'after'];
const SLUG_RE = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
const g = props.guide || {};
const tr = g.translations || {};
const emptyTr = (l) => ({
    title: tr[l]?.title ?? (l === 'vi' ? g.title || '' : ''),
    summary: tr[l]?.summary ?? (l === 'vi' ? g.summary || '' : ''),
    before_steps: tr[l]?.before_steps ?? (l === 'vi' ? g.before_steps || [] : []),
    during_steps: tr[l]?.during_steps ?? (l === 'vi' ? g.during_steps || [] : []),
    after_steps: tr[l]?.after_steps ?? (l === 'vi' ? g.after_steps || [] : []),
    sources: tr[l]?.sources ?? (l === 'vi' ? g.sources || [] : []),
});
const form = useForm({
    slug: g.slug || '',
    hazard_code: g.hazard_code || g.hazard?.code || '',
    min_severity: g.min_severity ?? 2,
    audience: g.audience || 'all',
    sort_order: g.sort_order ?? 0,
    status: g.status || 'draft',
    translations: Object.fromEntries(LOCALES.map((l) => [l, emptyTr(l)])),
});
const errorLocales = computed(() => LOCALES.filter((l) => Object.keys(form.errors).some((k) => k.startsWith(`translations.${l}.`))));

function clientCheck() {
    const errs = {};
    const vi = form.translations.vi;
    if (!SLUG_RE.test(form.slug)) errs.slug = t('validation.client.slug', { field: t('admin.form.slug') });
    if (!form.hazard_code) errs.hazard_code = t('validation.client.required', { field: t('alerts.hazard') });
    if (!vi.title.trim()) errs['translations.vi.title'] = t('validation.client.required', { field: t('admin.common.title') });
    PHASES.forEach((p) => {
        if (!vi[`${p}_steps`].some((s) => s.trim())) errs[`translations.vi.${p}_steps`] = t('admin.guides.err_steps', { phase: t(`guides.phase.${p}`) });
    });
    form.clearErrors();
    if (Object.keys(errs).length) form.setError(errs);
    return !Object.keys(errs).length;
}
function submit() {
    if (!clientCheck()) return;
    // Bỏ dòng trống trong danh sách bước/nguồn trước khi gửi
    const clean = (arr) => arr.map((s) => s.trim()).filter(Boolean);
    form.transform((d) => ({
        ...d,
        translations: Object.fromEntries(LOCALES.map((l) => [l, {
            ...d.translations[l],
            before_steps: clean(d.translations[l].before_steps),
            during_steps: clean(d.translations[l].during_steps),
            after_steps: clean(d.translations[l].after_steps),
            sources: clean(d.translations[l].sources),
        }])),
    }));
    if (isEdit.value) form.put(lr(`/quan-tri/huong-dan/${props.guide.id}`), { preserveScroll: true });
    else form.post(lr('/quan-tri/huong-dan'), { preserveScroll: true });
}
const title = computed(() => (isEdit.value ? t('admin.guides.edit_title') : t('admin.guides.create')));
</script>

<template>
    <Head :title="title" />
    <PageHeader :breadcrumbs="[{ label: t('admin.nav.guides'), href: lr('/quan-tri/huong-dan') }, { label: title }]" :title="title" :description="t('admin.guides.form_intro')" />
    <form class="space-y-5" novalidate @submit.prevent="submit">
        <FormSection :title="t('admin.form.section_general')">
            <div class="grid gap-4 md:grid-cols-3">
                <FormField v-model="form.slug" :label="t('admin.form.slug')" required maxlength="120" :hint="t('validation_hint.slug')" :error="form.errors.slug" />
                <FormField v-model="form.hazard_code" type="select" :label="t('alerts.hazard')" :options="enumOptions(hazards, te, 'hazard')" :placeholder="t('admin.form.choose')" required :error="form.errors.hazard_code" />
                <FormField v-model.number="form.min_severity" type="select" :label="t('admin.guides.min_severity')" :options="ALERT_LEVELS.map((l) => ({ value: l, label: t(`enums.levels.${l}`) }))" :error="form.errors.min_severity" />
                <FormField v-model="form.audience" type="select" :label="t('admin.guides.audience')" :options="AUDIENCES.map((a) => ({ value: a, label: te('audience', a) }))" :error="form.errors.audience" />
                <FormField v-model.number="form.sort_order" type="number" :label="t('admin.guides.sort_order')" :min="0" :max="999" :error="form.errors.sort_order" />
                <FormField v-model="form.status" type="select" :label="t('common.status')" :options="['draft', 'published'].map((s) => ({ value: s, label: te('status', s) }))" :error="form.errors.status" />
            </div>
        </FormSection>
        <FormSection :title="t('admin.guides.content')" :description="t('admin.guides.content_hint')">
            <TranslationTabs :error-locales="errorLocales">
                <template #default="{ locale: l }">
                    <div class="grid gap-5">
                        <FormField v-model="form.translations[l].title" :label="t('admin.common.title')" :required="l === 'vi'" maxlength="200" :error="form.errors[`translations.${l}.title`]" />
                        <FormField v-model="form.translations[l].summary" type="textarea" :rows="3" :label="t('admin.guides.summary')" maxlength="1000" :error="form.errors[`translations.${l}.summary`]" />
                        <div class="grid gap-5 xl:grid-cols-3">
                            <StepsEditor
                                v-for="p in PHASES"
                                :key="p"
                                v-model="form.translations[l][`${p}_steps`]"
                                :label="t(`guides.phase.${p}`)"
                                :errors="form.errors"
                                :error-prefix="`translations.${l}.${p}_steps`"
                                :placeholder="t('admin.guides.step_placeholder')"
                            />
                        </div>
                        <StepsEditor v-model="form.translations[l].sources" :label="t('common.sources')" :errors="form.errors" :error-prefix="`translations.${l}.sources`" :multiline="false" :placeholder="t('admin.guides.source_placeholder')" />
                    </div>
                </template>
            </TranslationTabs>
        </FormSection>
        <FormActions :cancel-href="lr('/quan-tri/huong-dan')" :processing="form.processing" :has-errors="form.hasErrors" />
    </form>
</template>
