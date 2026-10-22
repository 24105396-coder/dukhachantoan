<script setup>
import { computed, nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import FormField from '@/Components/FormField.vue';
import Icon from '@/Components/Icon.vue';
import ProvenanceBadge from '@/Components/ProvenanceBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import DeleteAction from '@/Components/Admin/DeleteAction.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { enumOptions } from '@/Utils/options';
import { useClientValidation } from '@/Composables/useClientValidation';

/** Danh sách media của một thực thể + form tải lên với đủ trường nguồn gốc / bản quyền. */
const props = defineProps({
    media: { type: Array, default: () => [] },
    subjectType: { type: String, required: true },
    subjectId: { type: Number, required: true },
    licenses: { type: [Array, Object], default: null },
});
const { t, te } = useT();
const lr = useLocaleRoute();

const MEDIA_TYPES = ['image', 'audio', 'video', 'model3d', 'document'];
const DEFAULT_LICENSES = ['public_domain', 'cc_by', 'cc_by_sa', 'cc_by_nc', 'all_rights_reserved', 'simulated'];
const typeOptions = MEDIA_TYPES.map((c) => ({ value: c, label: te('media_type', c) }));
const licenseOptions = computed(() => enumOptions(props.licenses || DEFAULT_LICENSES, te, 'license'));
const localeOptions = ['vi', 'en'].map((c) => ({ value: c, label: te('locale', c) }));
// Ngày hôm nay theo giờ máy khách, khớp luật before_or_equal:today ở MediaStoreRequest
const today = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);

const blank = () => ({
    subject_type: props.subjectType, subject_id: props.subjectId, media_type: 'image', file: null, external_url: '',
    locale: '', caption_vi: '', caption_en: '', transcript_vi: '', transcript_en: '', source_name: '', source_url: '',
    author: '', license: 'cc_by_sa', rights_holder: '', captured_at: '', provenance_note: '', is_cover: false,
});
const form = useForm(blank());
const isAudio = computed(() => form.media_type === 'audio');

/** Tư liệu đang được chọn để sửa; null = form ở chế độ tải lên mới. */
const editing = ref(null);
const fileInput = ref(null);
const formEl = ref(null);
// Laravel chỉ đọc multipart ở POST nên khi sửa gửi POST kèm _method=put.
form.transform((data) => (editing.value ? { ...data, _method: 'put' } : data));

function onFile(e) {
    form.file = e.target.files?.[0] || null;
}

/** Đổ dữ liệu của tư liệu vào form để sửa và cuộn tới form. */
function startEdit(m) {
    editing.value = m;
    form.clearErrors();
    Object.assign(form, blank(), {
        media_type: m.media_type, external_url: m.external_url || '', locale: m.locale || '',
        caption_vi: m.caption_vi || '', caption_en: m.caption_en || '', transcript_vi: m.transcript_vi || '', transcript_en: m.transcript_en || '',
        source_name: m.source_name || '', source_url: m.source_url || '', author: m.author || '', license: m.license || 'cc_by_sa',
        rights_holder: m.rights_holder || '', captured_at: m.captured_at || '', provenance_note: m.provenance_note || '', is_cover: !!m.is_cover,
    });
    if (fileInput.value) fileInput.value.value = '';
    nextTick(() => formEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
}

/** Về chế độ tải lên mới và xóa trắng form. */
function cancelEdit() {
    editing.value = null;
    form.clearErrors();
    Object.assign(form, blank());
    if (fileInput.value) fileInput.value.value = '';
}

// Tải lên mới cần tệp hoặc URL ngoài (required_without:file); khi sửa được giữ tệp cũ
const { validate } = useClientValidation(form, {
    media_type: { label: 'admin.media.media_type', required: true },
    external_url: { label: 'admin.media.external_url', required: () => !editing.value && !form.file, url: ['https:'], max: 500 },
    locale: { label: 'admin.media.locale', required: () => isAudio.value },
    caption_vi: { label: 'admin.media.caption_vi', required: true, max: 500 },
    caption_en: { label: 'admin.media.caption_en', max: 500 },
    license: { label: 'provenance.license', required: true },
    source_name: { label: 'admin.media.source_name', required: true, max: 190 },
    source_url: { label: 'admin.media.source_url', url: true, max: 500 },
});

function submit(e) {
    if (!validate(e)) return;
    const url = editing.value ? `/quan-tri/media/${editing.value.id}` : '/quan-tri/media';
    form.post(lr(url), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: cancelEdit,
    });
}

const thumbIcon = (m) => ({ audio: 'volume', video: 'play', model3d: 'cube', document: 'file' })[m.media_type] || 'image';
</script>

<template>
    <div class="space-y-6">
        <EmptyState v-if="!media.length" icon="image" :title="t('admin.media.empty_subject')" compact />
        <ul v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="m in media" :key="m.id" class="card overflow-hidden transition-shadow" :class="{ 'ring-2 ring-[var(--ds-primary)]': editing?.id === m.id }">
                <div class="relative aspect-video bg-surface-2">
                    <img v-if="m.media_type === 'image' && m.url" :src="m.url" :alt="m.caption || ''" class="h-full w-full object-cover" loading="lazy" />
                    <div v-else class="flex h-full items-center justify-center text-accent-strong"><Icon :name="thumbIcon(m)" :size="36" /></div>
                    <span v-if="m.is_cover" class="badge badge-accent absolute left-2 top-2">{{ t('admin.media.cover') }}</span>
                    <span class="badge absolute right-2 top-2">{{ te('media_type', m.media_type) }}<template v-if="m.locale"> · {{ m.locale }}</template></span>
                </div>
                <div class="space-y-2 p-3">
                    <div class="flex items-start justify-between gap-2">
                        <p class="line-clamp-2 text-sm font-medium">{{ m.caption || t('admin.media.no_caption') }}</p>
                        <div class="flex shrink-0 items-center gap-1">
                            <button type="button" class="btn btn-ghost btn-sm" :aria-pressed="editing?.id === m.id" @click="startEdit(m)">
                                <Icon name="edit" :size="16" />{{ t('common.edit') }}
                            </button>
                            <DeleteAction :url="lr(`/quan-tri/media/${m.id}`)" :name="m.caption || `#${m.id}`" />
                        </div>
                    </div>
                    <ProvenanceBadge :item="m" />
                </div>
            </li>
        </ul>

        <form ref="formEl" class="scroll-mt-20 rounded-xl border border-dashed p-4" :class="editing ? 'border-[var(--ds-primary)] bg-surface-2/40' : 'border-border'" novalidate @submit.prevent="submit">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-body text-base font-bold">
                    <template v-if="editing">{{ t('admin.media.edit_title') }}: <span class="font-normal">{{ editing.caption || `#${editing.id}` }}</span></template>
                    <template v-else>{{ t('admin.media.upload_title') }}</template>
                </h3>
                <span v-if="editing" class="badge badge-accent">{{ t('admin.media.editing') }}</span>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <FormField v-model="form.media_type" type="select" :label="t('admin.media.media_type')" :options="typeOptions" :error="form.errors.media_type" :disabled="!!editing" required />
                <FormField v-slot="{ id, describedby, invalid }" :label="t('admin.media.file')" :hint="editing ? t('admin.media.keep_file_hint') : t('admin.media.file_hint')" :error="form.errors.file">
                    <input :id="id" ref="fileInput" type="file" class="input" :aria-describedby="describedby" :aria-invalid="invalid" @change="onFile" />
                </FormField>
                <FormField v-model="form.external_url" type="url" maxlength="500" pattern="https://.*" :label="t('admin.media.external_url')" :hint="t('admin.media.external_url_hint')" :error="form.errors.external_url" />
                <FormField v-model="form.locale" type="select" :label="t('admin.media.locale')" :options="localeOptions" :placeholder="t('admin.media.locale_any')" :hint="isAudio ? t('admin.media.locale_audio_hint') : ''" :error="form.errors.locale" :required="isAudio" />
                <FormField v-model="form.caption_vi" maxlength="500" :label="t('admin.media.caption_vi')" :error="form.errors.caption_vi" required />
                <FormField v-model="form.caption_en" maxlength="500" :label="t('admin.media.caption_en')" :error="form.errors.caption_en" />
                <template v-if="isAudio">
                    <FormField v-model="form.transcript_vi" class="sm:col-span-2 lg:col-span-3" type="textarea" maxlength="20000" :rows="3" :label="t('admin.media.transcript_vi')" :hint="t('admin.media.transcript_hint')" :error="form.errors.transcript_vi" />
                    <FormField v-model="form.transcript_en" class="sm:col-span-2 lg:col-span-3" type="textarea" maxlength="20000" :rows="3" :label="t('admin.media.transcript_en')" :error="form.errors.transcript_en" />
                </template>
            </div>

            <h4 class="mb-2 mt-5 text-sm font-bold uppercase tracking-wider text-muted">{{ t('admin.media.provenance_title') }}</h4>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <FormField v-model="form.license" type="select" :label="t('provenance.license')" :options="licenseOptions" :error="form.errors.license" required />
                <FormField v-model="form.source_name" maxlength="190" :label="t('admin.media.source_name')" :error="form.errors.source_name" required />
                <FormField v-model="form.source_url" type="url" maxlength="500" :label="t('admin.media.source_url')" :error="form.errors.source_url" />
                <FormField v-model="form.author" maxlength="150" :label="t('provenance.author')" :error="form.errors.author" />
                <FormField v-model="form.rights_holder" maxlength="190" :label="t('provenance.rights_holder')" :error="form.errors.rights_holder" />
                <FormField v-model="form.captured_at" type="date" :max="today" :label="t('provenance.captured_at')" :error="form.errors.captured_at" />
                <FormField v-model="form.provenance_note" class="sm:col-span-2 lg:col-span-3" type="textarea" maxlength="2000" :rows="2" :label="t('provenance.note')" :error="form.errors.provenance_note" />
            </div>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input v-model="form.is_cover" type="checkbox" class="h-4 w-4 accent-[var(--ds-primary)]" />
                    {{ t('admin.media.set_cover') }}
                </label>
                <p v-if="form.errors.subject_id || form.errors.subject_type" class="field-error">{{ form.errors.subject_id || form.errors.subject_type }}</p>
                <div class="flex items-center gap-3">
                    <progress v-if="form.progress" :value="form.progress.percentage" max="100" class="w-32">{{ form.progress.percentage }}%</progress>
                    <button v-if="editing" type="button" class="btn btn-ghost" :disabled="form.processing" @click="cancelEdit">{{ t('common.cancel') }}</button>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">
                        <Icon :name="editing ? 'check' : 'upload'" :size="18" />{{ editing ? t('admin.media.save') : t('admin.media.upload') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>
