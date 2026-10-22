<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FormField from '@/Components/FormField.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import TranslationTabs from '@/Components/Admin/TranslationTabs.vue';
import ZonePicker from '@/Components/Admin/ZonePicker.vue';
import GeometryEditor from '@/Components/Admin/GeometryEditor.vue';
import Button from '@/Components/Button.vue';
import Badge from '@/Components/Badge.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import AlertLifecycle from '@/Components/Admin/AlertLifecycle.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { enumOptions, ALERT_LEVELS, LOCALES } from '@/Utils/options';
import { toLocalInput } from '@/Utils/tripForm';

/**
 * Soạn/sửa cảnh báo song ngữ: loại hiểm họa, mức, vùng (chọn nhiều), phạm vi (GeoJSON/bán kính, xem trước bản đồ),
 * thời gian hiệu lực, nguồn, nội dung vi/en. Nút: Lưu nháp | Phát hành (nháp) | Cập nhật (đang hiệu lực) – có xác nhận.
 * Gửi kèm `intent`: draft | publish | update. Tạo: POST /quan-tri/canh-bao; sửa: PUT /quan-tri/canh-bao/{code}.
 */
const props = defineProps({
    alert: { type: Object, default: null },
    hazards: { type: Array, default: () => [] },
    zones: { type: Array, default: () => [] },
    defaults: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const lr = useLocaleRoute();
const isEdit = computed(() => !!props.alert?.code);
const status = computed(() => props.alert?.status || 'draft');
const isLive = computed(() => ['active', 'updated'].includes(status.value));

const emptyTr = () => ({ headline: '', description: '', instructions: '' });
function initial(a, d) {
    const src = a || d || {};
    const tr = a?.translations || {};
    return {
        hazard_code: src.hazard_code || src.hazard?.code || '',
        severity: src.severity ?? 3,
        zone_codes: src.zone_codes || (src.zones || []).map((z) => z.code),
        geometry: src.geometry || null,
        starts_at: toLocalInput(src.starts_at) || '',
        ends_at: toLocalInput(src.ends_at) || '',
        source_name: src.source_name || src.source?.name || '',
        source_url: src.source_url || src.source?.url || '',
        translations: Object.fromEntries(LOCALES.map((l) => [l, { ...emptyTr(), ...(tr[l] || {}) }])),
        intent: 'draft',
    };
}
const form = useForm(initial(props.alert, props.defaults));
const confirm = ref(null); // 'publish' | 'update'

const hazardOptions = computed(() => enumOptions(props.hazards, te, 'hazard'));
const severityOptions = ALERT_LEVELS.map((l) => ({ value: l, label: t('level.with_number', { n: l, label: t(`enums.levels.${l}`) }) }));
const trErr = (l, f) => form.errors[`translations.${l}.${f}`];
const errorLocales = computed(() => LOCALES.filter((l) => Object.keys(form.errors).some((k) => k.startsWith(`translations.${l}.`))));

// Kiểm tra tối thiểu phía máy khách: nội dung tiếng Việt bắt buộc, giờ kết thúc sau giờ bắt đầu
function clientCheck() {
    const errs = {};
    const req = (v, f) => (!String(v ?? '').trim() ? t('validation.client.required', { field: f }) : null);
    const pairs = [
        ['hazard_code', req(form.hazard_code, t('alerts.hazard'))],
        ['starts_at', req(form.starts_at, t('admin.alerts.starts_at'))],
        ['translations.vi.headline', req(form.translations.vi.headline, t('admin.alerts.headline'))],
        ['translations.vi.instructions', req(form.translations.vi.instructions, t('alert.instructions'))],
        ['source_name', req(form.source_name, t('admin.alerts.source_name'))],
    ];
    pairs.forEach(([k, v]) => v && (errs[k] = v));
    if (!form.zone_codes.length && !form.geometry) errs.zone_codes = t('admin.alerts.err_area');
    if (form.starts_at && form.ends_at && form.ends_at <= form.starts_at) errs.ends_at = t('trip_form.err_end_before_start');
    form.clearErrors();
    if (Object.keys(errs).length) form.setError(errs);
    return !Object.keys(errs).length;
}
function save(intent) {
    if (!clientCheck()) return;
    if (intent !== 'draft' && confirm.value !== intent) {
        confirm.value = intent;
        return;
    }
    form.intent = intent;
    const opts = { preserveScroll: true, onFinish: () => (confirm.value = null) };
    form.transform((d) => ({ ...d, ends_at: d.ends_at || null, source_url: d.source_url || null }));
    if (isEdit.value) form.put(lr(`/quan-tri/canh-bao/${props.alert.code}`), opts);
    else form.post(lr('/quan-tri/canh-bao'), opts);
}
const title = computed(() => (isEdit.value ? t('admin.alerts.edit_title', { code: props.alert.code }) : t('admin.alerts.create')));
const crumbs = computed(() => [{ label: t('admin.nav.alerts'), href: lr('/quan-tri/canh-bao') }, { label: title.value }]);
</script>

<template>
    <Head :title="title" />
    <PageHeader :breadcrumbs="crumbs" :title="title" :description="t('admin.alerts.form_intro')">
        <template v-if="isEdit" #actions>
            <span class="pill">{{ te('status', status) }}</span>
        </template>
    </PageHeader>

    <form class="space-y-5" novalidate @submit.prevent="save(isLive ? 'update' : 'draft')">
        <FormSection :title="t('admin.form.section_general')">
            <div class="grid gap-4 md:grid-cols-2">
                <FormField v-model="form.hazard_code" type="select" :label="t('alerts.hazard')" :options="hazardOptions" :placeholder="t('admin.form.choose')" required :error="form.errors.hazard_code" />
                <div>
                    <FormField v-model.number="form.severity" type="select" :label="t('alerts.severity')" :options="severityOptions" required :error="form.errors.severity" />
                    <Badge class="mt-2" :level="form.severity" show-level-number />
                </div>
                <FormField v-model="form.starts_at" type="datetime-local" :label="t('admin.alerts.starts_at')" required :error="form.errors.starts_at" />
                <FormField v-model="form.ends_at" type="datetime-local" :label="t('admin.alerts.ends_at')" :min="form.starts_at || undefined" :hint="t('admin.alerts.ends_hint')" :error="form.errors.ends_at" />
                <FormField v-model="form.source_name" :label="t('admin.alerts.source_name')" required maxlength="255" :placeholder="t('admin.alerts.source_placeholder')" :error="form.errors.source_name" />
                <FormField v-model="form.source_url" type="url" :label="t('admin.alerts.source_url')" maxlength="500" placeholder="https://" :error="form.errors.source_url" />
            </div>
        </FormSection>

        <FormSection :title="t('admin.alerts.area')" :description="t('admin.alerts.area_hint')">
            <div class="grid gap-6 lg:grid-cols-2">
                <ZonePicker v-model="form.zone_codes" :zones="zones" :label="t('alert.zones')" :error="form.errors.zone_codes" />
                <GeometryEditor v-model="form.geometry" :level="Number(form.severity)" :error="form.errors.geometry" />
            </div>
        </FormSection>

        <FormSection :title="t('admin.alerts.content')" :description="t('admin.alerts.content_hint')">
            <TranslationTabs :error-locales="errorLocales">
                <template #default="{ locale: l }">
                    <div class="grid gap-4">
                        <FormField v-model="form.translations[l].headline" :label="t('admin.alerts.headline')" :required="l === 'vi'" maxlength="200" :error="trErr(l, 'headline')" />
                        <FormField v-model="form.translations[l].description" type="textarea" :rows="4" :label="t('alert.description')" maxlength="4000" :error="trErr(l, 'description')" />
                        <FormField v-model="form.translations[l].instructions" type="textarea" :rows="4" :label="t('alert.instructions')" :required="l === 'vi'" maxlength="4000" :error="trErr(l, 'instructions')" />
                    </div>
                </template>
            </TranslationTabs>
            <p class="field-hint">{{ t('admin.alerts.en_hint') }}</p>
        </FormSection>

        <div class="clay-card-sm sticky bottom-20 z-20 flex flex-wrap items-center gap-2 px-4 py-3 md:bottom-4">
            <p v-if="form.hasErrors" class="w-full text-sm text-danger" role="alert">{{ t('admin.form.has_errors') }}</p>
            <Button :href="isEdit ? lr(`/quan-tri/canh-bao/${alert.code}`) : lr('/quan-tri/canh-bao')" variant="ghost" class="mr-auto">{{ t('common.cancel') }}</Button>
            <template v-if="!isLive">
                <Button type="submit" icon="file" :loading="form.processing && form.intent === 'draft'">{{ t('admin.alerts.save_draft') }}</Button>
                <Button variant="primary" icon="send" :loading="form.processing && form.intent === 'publish'" @click="save('publish')">{{ t('admin.alerts.publish') }}</Button>
            </template>
            <Button v-else type="submit" variant="primary" icon="refresh" :loading="form.processing">{{ t('admin.alerts.update') }}</Button>
        </div>
    </form>

    <section v-if="isEdit" class="mt-6 space-y-2" aria-labelledby="al-life">
        <h2 id="al-life" class="section-title text-lg">{{ t('admin.alerts.lifecycle') }}</h2>
        <AlertLifecycle :alert="alert" :show-edit="false" size="sm" />
    </section>

    <ConfirmDialog
        :open="!!confirm"
        :title="confirm ? t(`admin.alerts.confirm_${confirm}_title`) : ''"
        :message="confirm ? t(`admin.alerts.confirm_${confirm}`, { code: alert?.code || t('admin.alerts.new_code') }) : ''"
        :confirm-text="confirm ? t(`admin.alerts.${confirm}`) : ''"
        :processing="form.processing"
        @confirm="save(confirm)"
        @cancel="confirm = null"
    />
</template>
