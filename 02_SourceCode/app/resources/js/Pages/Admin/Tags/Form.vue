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
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { enumOptions, rowsOf } from '@/Utils/options';

const props = defineProps({
    tag: { type: Object, default: null },
    parents: { type: Array, default: () => [] },
    tags: { type: [Array, Object], default: () => [] },
    enums: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const lr = useLocaleRoute();

const g = props.tag || {};
const isEdit = computed(() => !!props.tag?.id);
const TAG_GROUPS = ['chu_de', 'loai_hinh', 'ton_giao', 'phong_cach', 'vat_lieu', 'thoi_ky', 'vung_mien'];

const form = useForm({
    slug: g.slug ?? '',
    tag_group: g.tag_group ?? g.group ?? 'chu_de',
    parent_id: g.parent_id ?? (typeof g.parent === 'object' ? g.parent?.id : '') ?? '',
    name_vi: g.name_vi ?? '',
    name_en: g.name_en ?? '',
    description_vi: g.description_vi ?? '',
    description_en: g.description_en ?? '',
});

const groupOptions = computed(() => enumOptions(props.enums.groups || TAG_GROUPS, te, 'tag_group'));
// Thẻ cha: backend trả `parents` [{id, slug, name}]; nếu có nhóm thì chỉ lấy cùng nhóm, khác chính nó
const parentOptions = computed(() =>
    (props.parents.length ? props.parents : rowsOf(props.tags))
        .filter((x) => x.id !== g.id)
        .filter((x) => !(x.group || x.tag_group) || (x.group || x.tag_group) === form.tag_group)
        .map((x) => ({ value: x.id, label: x.name_vi || x.name })),
);

const { validate } = useClientValidation(form, {
    slug: { label: 'admin.form.slug', required: true, max: 120, pattern: { regex: SLUG_RE, message: 'slug' } },
    tag_group: { label: 'admin.tags.group', required: true },
    name_vi: { label: 'admin.tags.name_vi', required: true, max: 120 },
    name_en: { label: 'admin.tags.name_en', required: true, max: 120 },
    description_vi: { label: 'admin.form.description_vi', max: 500 },
    description_en: { label: 'admin.form.description_en', max: 500 },
});

function submit(e) {
    if (!validate(e)) return;
    if (isEdit.value) form.put(lr(`/quan-tri/the/${g.slug}`), { preserveScroll: true });
    else form.post(lr('/quan-tri/the'), { preserveScroll: true });
}

const title = computed(() => (isEdit.value ? t('admin.tags.edit_title', { name: g.name_vi || g.slug }) : t('admin.tags.create_title')));
</script>

<template>
    <Head :title="title" />
    <Breadcrumbs :items="[{ label: t('admin.tags.title'), href: lr('/quan-tri/the') }, { label: title }]" class="mb-3" />
    <PageHeader :title="title" />
    <form class="max-w-4xl space-y-6" novalidate @submit.prevent="submit">
        <FormSection :title="t('admin.form.section_general')">
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField v-model="form.slug" :label="t('admin.form.slug')" :hint="t('admin.form.slug_hint')" :error="form.errors.slug" required maxlength="120" :pattern="SLUG_PATTERN" :pattern-hint="t('validation_hint.slug')" />
                <FormField v-model="form.tag_group" type="select" :label="t('admin.tags.group')" :options="groupOptions" :error="form.errors.tag_group" required />
                <FormField v-model="form.parent_id" type="select" class="sm:col-span-2" :label="t('admin.tags.parent')" :options="parentOptions" :placeholder="t('common.none')" :error="form.errors.parent_id" />
                <FormField v-model="form.name_vi" maxlength="120" :label="t('admin.tags.name_vi')" :error="form.errors.name_vi" required />
                <FormField v-model="form.name_en" maxlength="120" :label="t('admin.tags.name_en')" :error="form.errors.name_en" required />
                <FormField v-model="form.description_vi" type="textarea" maxlength="500" :rows="3" :label="t('admin.form.description_vi')" :error="form.errors.description_vi" />
                <FormField v-model="form.description_en" type="textarea" maxlength="500" :rows="3" :label="t('admin.form.description_en')" :error="form.errors.description_en" />
            </div>
        </FormSection>
        <FormActions :cancel-href="lr('/quan-tri/the')" :processing="form.processing" :has-errors="form.hasErrors" />
    </form>
</template>
