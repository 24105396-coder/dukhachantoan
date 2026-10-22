<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FormField from '@/Components/FormField.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import PageMeta from '@/Components/Public/PageMeta.vue';
import { useClientValidation } from '@/Composables/useClientValidation';

const props = defineProps({
    types: { type: Array, default: () => [] },
    subject: { type: Object, default: null },
});
const page = usePage();
const { t, te } = useT();
const lr = useLocaleRoute();

const MAX_BYTES = 5 * 1024 * 1024;
const ALLOWED_MIME = ['image/jpeg', 'image/png', 'application/pdf'];
const ALLOWED_EXT = /\.(jpe?g|png|pdf)$/i;

const form = useForm({
    contribution_type: props.types[0]?.code || '',
    locale: page.props.locale || 'vi',
    title: '',
    body: '',
    subject_type: props.subject?.type || '',
    subject_id: props.subject?.id || '',
    attachment: null,
});
const fileError = ref('');
const fileInput = ref(null);

const typeOptions = computed(() => props.types.map((x) => ({ value: x.code, label: x.name || te('contribution_type', x.code) })));
const localeOptions = (page.props.locales || [{ code: 'vi' }, { code: 'en' }]).map((l) => ({ value: l.code, label: l.label || te('locale', l.code) }));

function onFile(e) {
    const file = e.target.files?.[0] || null;
    fileError.value = '';
    if (!file) {
        form.attachment = null;
        return;
    }
    if (!(ALLOWED_MIME.includes(file.type) || ALLOWED_EXT.test(file.name))) {
        fileError.value = t('contribution.file_type_error');
    } else if (file.size > MAX_BYTES) {
        fileError.value = t('contribution.file_size_error');
    }
    if (fileError.value) {
        form.attachment = null;
        e.target.value = '';
        return;
    }
    form.attachment = file;
}

function clearFile() {
    form.attachment = null;
    if (fileInput.value) fileInput.value.value = '';
}

const { validate } = useClientValidation(form, {
    contribution_type: { label: 'contribution.type', required: true },
    locale: { label: 'contribution.locale', required: true },
    title: { label: 'contribution.title', required: true, min: 5, max: 255 },
    body: { label: 'contribution.body', required: true, min: 20, max: 20000 },
});

function submit(e) {
    if (!validate(e) || fileError.value) return;
    form.transform((data) => {
        const out = { ...data };
        if (!out.subject_type) {
            delete out.subject_type;
            delete out.subject_id;
        }
        if (!out.attachment) delete out.attachment;
        return out;
    }).post(lr('/dong-gop'), { forceFormData: true, preserveScroll: true });
}
</script>

<template>
    <PageMeta :title="t('contribution.create_title')" fallback-key="contribution.create_intro" />
    <div class="container-page max-w-3xl py-8">
        <PageHeader :eyebrow="t('nav.contribute')" :title="t('contribution.create_title')" :description="t('contribution.create_intro')" />

        <p v-if="subject" class="mb-4 rounded-lg bg-info-soft p-3 text-sm text-info">
            {{ t('contribution.about_subject', { type: te('subject_type', subject.type), title: subject.title }) }}
        </p>

        <form class="card grid gap-4 p-5 sm:grid-cols-2 md:p-6" novalidate @submit.prevent="submit">
            <FormField v-model="form.contribution_type" :label="t('contribution.type')" type="select" :options="typeOptions" required :error="form.errors.contribution_type" />
            <FormField v-model="form.locale" :label="t('contribution.locale')" type="select" :options="localeOptions" required :error="form.errors.locale" />
            <FormField v-model="form.title" class="sm:col-span-2" :label="t('contribution.title')" required minlength="5" maxlength="255" :error="form.errors.title" />
            <FormField v-model="form.body" class="sm:col-span-2" :label="t('contribution.body')" type="textarea" :rows="8" required minlength="20" maxlength="20000" :hint="t('contribution.body_hint')" :error="form.errors.body" />

            <FormField class="sm:col-span-2" :label="`${t('contribution.attachment')} (${t('common.optional')})`" :hint="t('contribution.attachment_hint')" :error="fileError || form.errors.attachment">
                <template #default="{ id, invalid, describedby }">
                    <input
                        :id="id"
                        ref="fileInput"
                        type="file"
                        accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                        class="input file:mr-3 file:rounded-full file:border-0 file:bg-primary-soft file:px-3 file:py-1 file:font-semibold file:text-primary"
                        :aria-invalid="invalid"
                        :aria-describedby="describedby"
                        @change="onFile"
                    />
                </template>
            </FormField>
            <div v-if="form.attachment" class="flex items-center gap-2 text-sm sm:col-span-2">
                <Icon name="file" :size="18" />
                <span class="truncate">{{ form.attachment.name }}</span>
                <button type="button" class="btn btn-ghost btn-sm" :aria-label="t('contribution.remove_file')" @click="clearFile"><Icon name="close" :size="16" /></button>
            </div>
            <progress v-if="form.progress" :value="form.progress.percentage" max="100" class="w-full sm:col-span-2" :aria-label="t('contribution.uploading')">{{ form.progress.percentage }}%</progress>

            <p class="text-xs text-muted sm:col-span-2">{{ t('contribution.ai_tag_note') }}</p>
            <div class="sm:col-span-2">
                <button type="submit" class="btn btn-primary" :disabled="form.processing"><Icon name="upload" :size="18" />{{ form.processing ? t('common.saving') : t('contribution.submit') }}</button>
            </div>
        </form>
    </div>
</template>
