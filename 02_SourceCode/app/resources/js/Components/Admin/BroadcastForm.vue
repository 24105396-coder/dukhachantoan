<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import FormField from '@/Components/FormField.vue';
import Checkbox from '@/Components/Form/Checkbox.vue';
import Button from '@/Components/Button.vue';
import Badge from '@/Components/Badge.vue';
import ZonePicker from '@/Components/Admin/ZonePicker.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { ALERT_LEVELS, CHANNELS } from '@/Utils/options';

/** Phát thông báo thủ công tới du khách có chặng trong các vùng chọn: mức, kênh, tiêu đề, nội dung → POST /quan-tri/thong-bao/phat. */
const props = defineProps({
    zones: { type: Array, default: () => [] },
});
const { t, te } = useT();
const lr = useLocaleRoute();
const form = useForm({ zone_codes: [], level: 2, title: '', body: '', channels: ['inapp'] });
const levelOptions = ALERT_LEVELS.map((l) => ({ value: l, label: t('level.with_number', { n: l, label: t(`enums.levels.${l}`) }) }));
const isLevel4 = computed(() => Number(form.level) === 4);

function submit() {
    const errs = {};
    if (!form.zone_codes.length) errs.zone_codes = t('admin.broadcast.err_zones');
    if (!form.title.trim()) errs.title = t('validation.client.required', { field: t('admin.common.title') });
    if (!form.body.trim()) errs.body = t('validation.client.required', { field: t('admin.broadcast.body') });
    if (!form.channels.length && !isLevel4.value) errs.channels = t('admin.broadcast.err_channels');
    form.clearErrors();
    if (Object.keys(errs).length) {
        form.setError(errs);
        return;
    }
    form.transform((d) => ({ ...d, level: Number(d.level), channels: Number(d.level) === 4 ? CHANNELS : d.channels }))
        .post(lr('/quan-tri/thong-bao/phat'), { preserveScroll: true, onSuccess: () => form.reset('title', 'body') });
}
</script>

<template>
    <form class="grid gap-4 lg:grid-cols-2" novalidate @submit.prevent="submit">
        <ZonePicker v-model="form.zone_codes" :zones="zones" :label="t('alert.zones')" required :error="form.errors.zone_codes" />
        <div class="grid content-start gap-4">
            <div>
                <FormField v-model.number="form.level" type="select" :label="t('alerts.severity')" :options="levelOptions" required :error="form.errors.level" />
                <Badge class="mt-2" :level="form.level" show-level-number />
            </div>
            <fieldset>
                <legend class="field-label">{{ t('admin.broadcast.channels') }}</legend>
                <div class="flex flex-wrap gap-4">
                    <Checkbox v-for="c in CHANNELS" :key="c" v-model="form.channels" :value="c" :label="te('channel', c)" :disabled="isLevel4" />
                </div>
                <p class="field-hint">{{ isLevel4 ? t('admin.broadcast.level4_all') : t('admin.broadcast.channels_hint') }}</p>
                <p v-if="form.errors.channels" class="field-error" role="alert">{{ form.errors.channels }}</p>
            </fieldset>
            <FormField v-model="form.title" :label="t('admin.common.title')" required maxlength="160" :error="form.errors.title" />
            <FormField v-model="form.body" type="textarea" :rows="4" :label="t('admin.broadcast.body')" required maxlength="1000" :hint="t('admin.broadcast.body_hint')" :error="form.errors.body" />
            <div class="flex flex-wrap items-center gap-2">
                <Button type="submit" :variant="isLevel4 ? 'danger' : 'primary'" icon="megaphone" :loading="form.processing">{{ t('admin.broadcast.submit') }}</Button>
                <span v-if="form.recentlySuccessful" class="text-sm font-semibold text-success" role="status">{{ t('admin.broadcast.queued') }}</span>
            </div>
        </div>
    </form>
</template>
