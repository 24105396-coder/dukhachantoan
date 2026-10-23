<script setup>
import { computed, ref, nextTick, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import Stepper from '@/Components/Stepper.vue';
import FormField from '@/Components/FormField.vue';
import Button from '@/Components/Button.vue';
import LegsEditor from '@/Components/Trip/LegsEditor.vue';
import MembersEditor from '@/Components/Trip/MembersEditor.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useClientValidation } from '@/Composables/useClientValidation';
import { nationalityOptions } from '@/Utils/countries';
import { LOCALES } from '@/Utils/options';
import { PHONE_PATTERN, PHONE_RE, stripPhone } from '@/Utils/rules';
import { initialTripForm, validateLegs, validateMembers, stepOfError, tripPayload } from '@/Utils/tripForm';

/**
 * Wizard hành trình 3 bước: thông tin chung → chặng → thành viên. Mỗi bước kiểm tra phía máy khách trước khi sang bước sau
 * (≥ 1 chặng, không chồng giờ, trong khoảng ngày); lỗi máy chủ (legs.i.field…) tự mở đúng bước.
 * Tạo: POST /hanh-trinh/tao; sửa (có prop trip): PUT /hanh-trinh/{code}/sua.
 */
const props = defineProps({
    zones: { type: Array, default: () => [] },
    destinations: { type: Array, default: () => [] },
    defaults: { type: Object, default: () => ({}) },
    trip: { type: Object, default: null },
});
const { t, te, locale } = useT();
const { date } = useFormat();
const lr = useLocaleRoute();
const isEdit = computed(() => !!props.trip?.code);
// Phải khớp TripRequest (group_size max:200, BUG-3); thông điệp lỗi lấy từ khóa lang validation.client.number
const MAX_GROUP = 200;

const form = useForm(initialTripForm(props.trip, props.defaults));
const step = ref(0);
const root = ref(null);

const { validate: validateGeneral } = useClientValidation(form, {
    title: { label: 'trip_form.title', required: true, max: 160 },
    start_date: { label: 'trip_form.start_date', required: true },
    end_date: { label: 'trip_form.end_date', required: true },
    group_size: { label: 'trip.group_size', required: true, number: { min: 1, max: MAX_GROUP, integer: true } },
    contact_name: { label: 'trip_form.contact_name', required: true, max: 120 },
    contact_phone: { label: 'trip_form.contact_phone', required: true, pattern: { regex: PHONE_RE, message: 'phone', normalize: stripPhone } },
    contact_email: { label: 'common.email', email: true },
});

const natOptions = computed(() => nationalityOptions(locale.value));
const localeOptions = LOCALES.map((l) => ({ value: l, label: te('locale', l) }));
const errorKeys = computed(() => Object.keys(form.errors));
const steps = computed(() => [
    { key: 'general', label: t('trip_form.step_general'), hasError: stepOfError(errorKeys.value) === 0 },
    { key: 'legs', label: t('trip_form.step_legs'), hasError: errorKeys.value.some((k) => k.startsWith('legs')) },
    { key: 'members', label: t('trip_form.step_members'), hasError: errorKeys.value.some((k) => k.startsWith('members')) },
]);

function focusFirstError() {
    nextTick(() => (root.value?.querySelector('[aria-invalid="true"]') || root.value?.querySelector('h2'))?.focus());
}
function checkStep(i, event) {
    if (i === 0) {
        if (!validateGeneral(event)) return false;
        if (form.end_date < form.start_date) {
            form.setError('end_date', t('trip_form.err_end_date'));
            focusFirstError();
            return false;
        }
        return true;
    }
    const errs = i === 1 ? validateLegs(form, t) : validateMembers(form, t);
    form.clearErrors();
    if (Object.keys(errs).length) {
        form.setError(errs);
        focusFirstError();
        return false;
    }
    return true;
}
function next(event) {
    if (!checkStep(step.value, event)) return;
    step.value += 1;
    nextTick(() => root.value?.querySelector('h2')?.focus());
}
function back() {
    step.value = Math.max(0, step.value - 1);
}
function submit(event) {
    if (step.value < 2) {
        next(event);
        return;
    }
    // Kiểm lại cả 3 bước trước khi gửi (người dùng có thể quay lại sửa)
    for (const i of [0, 1, 2]) {
        if (!checkStep(i, event)) {
            step.value = i;
            return;
        }
    }
    const opts = {
        preserveScroll: true,
        onError: (errors) => {
            const s = stepOfError(Object.keys(errors));
            if (s !== null) step.value = s;
            focusFirstError();
        },
    };
    form.transform(tripPayload);
    if (isEdit.value) form.put(lr(`/hanh-trinh/${props.trip.code}/sua`), opts);
    else form.post(lr('/hanh-trinh/tao'), opts);
}
// Đổi ngày bắt đầu khi chặng đầu chưa có giờ: gợi ý ngày cho chặng đầu
watch(() => form.start_date, (d) => {
    const first = form.legs[0];
    if (d && first && !first.starts_at && !first.ends_at) {
        first.starts_at = `${d}T08:00`;
        first.ends_at = `${d}T18:00`;
    }
});
const cancelHref = computed(() => (isEdit.value ? lr(`/hanh-trinh/${props.trip.code}`) : lr('/hanh-trinh')));
const title = computed(() => (isEdit.value ? t('trip_form.edit_title', { code: props.trip.code }) : t('trips.create')));
const crumbs = computed(() => [{ label: t('nav.trips'), href: lr('/hanh-trinh') }, { label: title.value }]);
</script>

<template>
    <Head :title="title" />
    <PageHeader :breadcrumbs="crumbs" :title="title" :description="t('trip_form.intro')" />
    <Stepper :steps="steps" :current="step" @go="(i) => (step = i)" />

    <form ref="root" novalidate @submit.prevent="submit">
        <section v-show="step === 0" class="clay-card-sm p-4 md:p-6" aria-labelledby="step-general">
            <h2 id="step-general" class="section-title text-xl" tabindex="-1">{{ t('trip_form.step_general') }}</h2>
            <p class="mb-4 text-sm text-muted">{{ t('trip_form.general_hint') }}</p>
            <div class="grid gap-4 md:grid-cols-2">
                <FormField v-model="form.title" :label="t('trip_form.title')" required maxlength="160" :placeholder="t('trip_form.title_placeholder')" class="md:col-span-2" :error="form.errors.title" />
                <FormField v-model="form.start_date" type="date" :label="t('trip_form.start_date')" required :error="form.errors.start_date" />
                <FormField v-model="form.end_date" type="date" :label="t('trip_form.end_date')" :min="form.start_date || undefined" required :error="form.errors.end_date" />
                <FormField v-model="form.group_size" type="number" :label="t('trip.group_size')" :min="1" :max="MAX_GROUP" step="1" inputmode="numeric" required :error="form.errors.group_size" />
                <FormField v-model="form.nationality" type="select" :label="t('account.nationality')" :options="natOptions" :placeholder="t('auth.nationality_placeholder')" :error="form.errors.nationality" />
                <FormField v-model="form.preferred_locale" type="select" :label="t('auth.preferred_locale')" :options="localeOptions" :error="form.errors.preferred_locale" />
            </div>
            <h3 class="mt-6 mb-3 text-base font-bold">{{ t('trip_form.contact') }}</h3>
            <div class="grid gap-4 md:grid-cols-3">
                <FormField v-model="form.contact_name" :label="t('trip_form.contact_name')" required autocomplete="name" :error="form.errors.contact_name" />
                <FormField v-model="form.contact_phone" type="tel" :label="t('trip_form.contact_phone')" required :pattern="PHONE_PATTERN" inputmode="tel" autocomplete="tel" :hint="t('validation_hint.phone')" :error="form.errors.contact_phone" />
                <FormField v-model="form.contact_email" type="email" :label="t('common.email')" autocomplete="email" :error="form.errors.contact_email" />
            </div>
        </section>

        <section v-show="step === 1" aria-labelledby="step-legs">
            <h2 id="step-legs" class="section-title mb-1 text-xl" tabindex="-1">{{ t('trip_form.step_legs') }}</h2>
            <p class="mb-4 text-sm text-muted">{{ t('trip_form.legs_intro', { from: date(form.start_date), to: date(form.end_date) }) }}</p>
            <LegsEditor :form="form" :zones="zones" :destinations="destinations" />
        </section>

        <section v-show="step === 2" aria-labelledby="step-members">
            <h2 id="step-members" class="section-title mb-3 text-xl" tabindex="-1">{{ t('trip_form.step_members') }}</h2>
            <MembersEditor :form="form" />
        </section>

        <div class="clay-card-sm sticky bottom-20 z-20 mt-6 flex flex-wrap items-center gap-2 px-4 py-3 md:bottom-4">
            <p v-if="form.hasErrors" class="w-full text-sm text-danger" role="alert">{{ t('admin.form.has_errors') }}</p>
            <Button :href="cancelHref" variant="ghost" class="mr-auto">{{ t('common.cancel') }}</Button>
            <Button v-if="step > 0" icon="arrow-left" @click="back">{{ t('common.back') }}</Button>
            <Button v-if="step < 2" type="submit" variant="primary" icon-right="arrow-right">{{ t('common.next') }}</Button>
            <Button v-else type="submit" variant="primary" icon="check" :loading="form.processing">{{ isEdit ? t('common.save') : t('trip_form.submit') }}</Button>
        </div>
    </form>
</template>
