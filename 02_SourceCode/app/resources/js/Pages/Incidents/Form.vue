<script setup>
import { computed, ref, onBeforeUnmount } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FormField from '@/Components/FormField.vue';
import Button from '@/Components/Button.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useGeolocation } from '@/Composables/useGeolocation';
import { useClientValidation } from '@/Composables/useClientValidation';
import { enumOptions } from '@/Utils/options';

/** Du khách báo sự cố thực địa (vùng, loại hiểm họa, mô tả, vị trí tùy chọn, ảnh tùy chọn) để cơ quan hỗ trợ duyệt. */
const props = defineProps({
    hazards: { type: Array, default: () => [] },
    zones: { type: Array, default: () => [] },
});
const { t, te } = useT();
const { number } = useFormat();
const lr = useLocaleRoute();
const geo = useGeolocation();
const MAX_PHOTO_MB = 5;
const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

const form = useForm({ zone_code: '', hazard_code: '', title: '', description: '', lat: null, lng: null, photo: null });
const { validate } = useClientValidation(form, {
    zone_code: { label: 'trip_form.zone', required: true },
    hazard_code: { label: 'alerts.hazard', required: true },
    title: { label: 'incident.title_field', required: true, min: 5, max: 160 },
    description: { label: 'incident.description', required: true, min: 10, max: 2000 },
});
const zoneOptions = computed(() => enumOptions(props.zones));
const hazardOptions = computed(() => enumOptions(props.hazards, te, 'hazard'));
const preview = ref(null);
const photoError = ref('');

function onPhoto(e) {
    const file = e.target.files?.[0] || null;
    photoError.value = '';
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = null;
    form.photo = null;
    if (!file) return;
    if (!PHOTO_TYPES.includes(file.type)) {
        photoError.value = t('incident.photo_type');
        e.target.value = '';
        return;
    }
    if (file.size > MAX_PHOTO_MB * 1024 * 1024) {
        photoError.value = t('incident.photo_size', { mb: MAX_PHOTO_MB });
        e.target.value = '';
        return;
    }
    form.photo = file;
    preview.value = URL.createObjectURL(file);
}
onBeforeUnmount(() => preview.value && URL.revokeObjectURL(preview.value));

async function locate() {
    const c = await geo.locate();
    if (c) {
        form.lat = c.lat;
        form.lng = c.lng;
    }
}
function submit(e) {
    if (!validate(e)) return;
    form.post(lr('/bao-su-co'), { forceFormData: true, preserveScroll: true });
}
const geoError = computed(() => ({ denied: t('checkin.geo_denied'), unavailable: t('checkin.geo_unavailable'), unsupported: t('checkin.geo_unsupported') }[geo.status.value] || ''));
</script>

<template>
    <Head :title="t('nav.report_incident')" />
    <PageHeader :title="t('nav.report_incident')" :description="t('incident.intro')" />
    <p class="mb-5 flex items-start gap-2 rounded-2xl border border-level-4 bg-level-4-soft p-3 text-sm font-semibold" role="note">
        <Icon name="siren" :size="18" class="mt-0.5" />{{ t('incident.emergency_note') }}
    </p>

    <form class="space-y-5" novalidate @submit.prevent="submit">
        <section class="clay-card-sm grid gap-4 p-4 md:grid-cols-2 md:p-6">
            <FormField v-model="form.zone_code" type="select" :label="t('trip_form.zone')" :options="zoneOptions" :placeholder="t('admin.form.choose')" required :error="form.errors.zone_code" />
            <FormField v-model="form.hazard_code" type="select" :label="t('alerts.hazard')" :options="hazardOptions" :placeholder="t('admin.form.choose')" required :error="form.errors.hazard_code" />
            <FormField v-model="form.title" :label="t('incident.title_field')" required maxlength="160" :placeholder="t('incident.title_placeholder')" class="md:col-span-2" :error="form.errors.title" />
            <FormField v-model="form.description" type="textarea" :rows="5" :label="t('incident.description')" required maxlength="2000" :hint="t('incident.description_hint')" class="md:col-span-2" :error="form.errors.description" />
        </section>

        <section class="clay-card-sm grid gap-4 p-4 md:grid-cols-2 md:p-6">
            <div>
                <p class="field-label">{{ t('incident.location') }} <span class="font-normal text-muted">({{ t('common.optional') }})</span></p>
                <div class="flex flex-wrap items-center gap-2">
                    <Button v-if="form.lat === null" size="sm" icon="locate" :loading="geo.status.value === 'locating'" @click="locate">{{ t('checkin.attach_location') }}</Button>
                    <template v-else>
                        <span class="pill"><Icon name="map-pin" :size="14" />{{ number(form.lat, 4) }}, {{ number(form.lng, 4) }}</span>
                        <Button size="sm" variant="ghost" icon="map-pin-off" @click="(form.lat = null), (form.lng = null), geo.clear()">{{ t('checkin.remove_location') }}</Button>
                    </template>
                </div>
                <p v-if="geoError" class="field-error" role="status">{{ geoError }}</p>
                <p v-else class="field-hint">{{ t('checkin.location_hint') }}</p>
                <p v-if="form.errors.lat || form.errors.lng" class="field-error" role="alert">{{ form.errors.lat || form.errors.lng }}</p>
            </div>
            <div>
                <label for="incident-photo" class="field-label">{{ t('incident.photo') }} <span class="font-normal text-muted">({{ t('common.optional') }})</span></label>
                <input
                    id="incident-photo"
                    type="file"
                    :accept="PHOTO_TYPES.join(',')"
                    class="clay-input file:mr-3 file:rounded-full file:border-0 file:bg-raised file:px-3 file:py-1 file:font-semibold"
                    aria-describedby="incident-photo-hint"
                    :aria-invalid="photoError || form.errors.photo ? 'true' : undefined"
                    @change="onPhoto"
                />
                <p id="incident-photo-hint" class="field-hint">{{ t('incident.photo_hint', { mb: MAX_PHOTO_MB }) }}</p>
                <p v-if="photoError || form.errors.photo" class="field-error" role="alert">{{ photoError || form.errors.photo }}</p>
                <img v-if="preview" :src="preview" :alt="t('incident.photo_preview')" class="mt-3 max-h-48 rounded-[20px] object-cover" />
                <progress v-if="form.progress" :value="form.progress.percentage" max="100" class="mt-2 w-full" :aria-label="t('incident.uploading')">{{ form.progress.percentage }}%</progress>
            </div>
        </section>

        <div class="flex flex-wrap justify-end gap-2">
            <p v-if="form.hasErrors" class="mr-auto text-sm text-danger" role="alert">{{ t('admin.form.has_errors') }}</p>
            <Button :href="lr('/hanh-trinh')" variant="ghost">{{ t('common.cancel') }}</Button>
            <Button type="submit" variant="primary" icon="send" :loading="form.processing">{{ t('incident.submit') }}</Button>
        </div>
    </form>
</template>
