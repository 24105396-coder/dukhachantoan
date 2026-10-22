<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FormField from '@/Components/FormField.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormActions from '@/Components/Admin/FormActions.vue';
import TranslationTabs from '@/Components/Admin/TranslationTabs.vue';
import Checkbox from '@/Components/Form/Checkbox.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useClientValidation } from '@/Composables/useClientValidation';
import { enumOptions, LOCALES } from '@/Utils/options';
import { PHONE_PATTERN } from '@/Utils/rules';

/** Thêm/sửa đầu mối khẩn cấp: tỉnh, vùng, loại, số chính/phụ, giờ, ngôn ngữ, tọa độ, nguồn, xác minh, tên + ghi chú vi/en. */
const props = defineProps({
    contact: { type: Object, default: null },
    provinces: { type: Array, default: () => [] },
    zones: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
});
const { t, te } = useT();
const lr = useLocaleRoute();
const isEdit = computed(() => !!props.contact?.id);
const c = props.contact || {};
const tr = c.translations || {};
const SERVICE_PHONE_RE = /^\+?[0-9]{3,15}$/; // cho phép số ngắn 112/113/114/115/1900…

const form = useForm({
    province_code: c.province?.code || c.province_code || '',
    zone_code: c.zone?.code || c.zone_code || '',
    contact_type: c.contact_type || '',
    phone: c.phone || '',
    phone_alt: c.phone_alt || '',
    address: c.address || '',
    lat: c.lat ?? '',
    lng: c.lng ?? '',
    hours: c.hours || '',
    languages: c.languages || ['vi'],
    is_verified: !!c.is_verified,
    source_name: c.source?.name || c.source_name || '',
    source_url: c.source?.url || c.source_url || '',
    translations: Object.fromEntries(LOCALES.map((l) => [l, { name: tr[l]?.name ?? (l === 'vi' ? c.name || '' : ''), note: tr[l]?.note ?? (l === 'vi' ? c.note || '' : '') }])),
});
const { validate } = useClientValidation(form, {
    province_code: { label: 'common.province', required: true },
    contact_type: { label: 'contacts.type', required: true },
    phone: { label: 'common.phone', required: true, pattern: { regex: SERVICE_PHONE_RE, message: 'phone', normalize: (s) => s.replace(/[\s.-]/g, '') } },
    'translations.vi.name': { label: 'admin.common.name', required: true, max: 200 },
    lat: { label: 'geometry.lat', number: { min: -90, max: 90 } },
    lng: { label: 'geometry.lng', number: { min: -180, max: 180 } },
    source_url: { label: 'admin.alerts.source_url', url: true },
});
const zoneOptions = computed(() => enumOptions(props.zones.filter((z) => !form.province_code || !z.province?.code || z.province.code === form.province_code)));
const errorLocales = computed(() => LOCALES.filter((l) => Object.keys(form.errors).some((k) => k.startsWith(`translations.${l}.`))));
function submit(e) {
    if (!validate(e)) return;
    const blank = (v) => (v === '' ? null : v);
    form.transform((d) => ({ ...d, zone_code: blank(d.zone_code), phone_alt: blank(d.phone_alt), lat: blank(d.lat), lng: blank(d.lng), source_url: blank(d.source_url) }));
    if (isEdit.value) form.put(lr(`/quan-tri/danh-ba/${props.contact.id}`), { preserveScroll: true });
    else form.post(lr('/quan-tri/danh-ba'), { preserveScroll: true });
}
const title = computed(() => (isEdit.value ? t('admin.contacts.edit_title') : t('admin.contacts.create')));
</script>

<template>
    <Head :title="title" />
    <PageHeader :breadcrumbs="[{ label: t('admin.nav.contacts'), href: lr('/quan-tri/danh-ba') }, { label: title }]" :title="title" />
    <form class="space-y-5" novalidate @submit.prevent="submit">
        <FormSection :title="t('admin.form.section_general')">
            <div class="grid gap-4 md:grid-cols-3">
                <FormField v-model="form.province_code" type="select" :label="t('common.province')" :options="enumOptions(provinces)" :placeholder="t('admin.form.choose')" required :error="form.errors.province_code" />
                <FormField v-model="form.zone_code" type="select" :label="t('trip_form.zone')" :options="zoneOptions" :placeholder="t('common.none')" :error="form.errors.zone_code" />
                <FormField v-model="form.contact_type" type="select" :label="t('contacts.type')" :options="enumOptions(types, te, 'contact_type')" :placeholder="t('admin.form.choose')" required :error="form.errors.contact_type" />
                <FormField v-model="form.phone" type="tel" :label="t('common.phone')" required inputmode="tel" :hint="t('admin.contacts.phone_hint')" :error="form.errors.phone" />
                <FormField v-model="form.phone_alt" type="tel" :label="t('admin.contacts.phone_alt')" :pattern="PHONE_PATTERN" inputmode="tel" :error="form.errors.phone_alt" />
                <FormField v-model="form.hours" :label="t('contact.hours')" placeholder="24/7" maxlength="100" :error="form.errors.hours" />
                <FormField v-model="form.address" :label="t('contact.address')" maxlength="255" class="md:col-span-3" :error="form.errors.address" />
                <FormField v-model="form.lat" type="number" step="0.000001" :label="t('geometry.lat')" inputmode="decimal" :error="form.errors.lat" />
                <FormField v-model="form.lng" type="number" step="0.000001" :label="t('geometry.lng')" inputmode="decimal" :error="form.errors.lng" />
                <fieldset>
                    <legend class="field-label">{{ t('contact.languages') }}</legend>
                    <div class="flex flex-wrap gap-4 pt-2">
                        <Checkbox v-for="l in LOCALES" :key="l" v-model="form.languages" :value="l" :label="te('locale', l)" />
                    </div>
                </fieldset>
            </div>
        </FormSection>
        <FormSection :title="t('admin.contacts.name_note')">
            <TranslationTabs :error-locales="errorLocales">
                <template #default="{ locale: l }">
                    <div class="grid gap-4">
                        <FormField v-model="form.translations[l].name" :label="t('admin.common.name')" :required="l === 'vi'" maxlength="200" :error="form.errors[`translations.${l}.name`]" />
                        <FormField v-model="form.translations[l].note" type="textarea" :rows="3" :label="t('common.note')" maxlength="1000" :error="form.errors[`translations.${l}.note`]" />
                    </div>
                </template>
            </TranslationTabs>
        </FormSection>
        <FormSection :title="t('admin.contacts.source_verify')">
            <div class="grid gap-4 md:grid-cols-2">
                <FormField v-model="form.source_name" :label="t('admin.alerts.source_name')" maxlength="255" :error="form.errors.source_name" />
                <FormField v-model="form.source_url" type="url" :label="t('admin.alerts.source_url')" maxlength="500" placeholder="https://" :error="form.errors.source_url" />
                <Checkbox v-model="form.is_verified" :label="t('admin.contacts.mark_verified')" :hint="t('admin.contacts.mark_verified_hint')" class="md:col-span-2" />
            </div>
        </FormSection>
        <FormActions :cancel-href="lr('/quan-tri/danh-ba')" :processing="form.processing" :has-errors="form.hasErrors" />
    </form>
</template>
