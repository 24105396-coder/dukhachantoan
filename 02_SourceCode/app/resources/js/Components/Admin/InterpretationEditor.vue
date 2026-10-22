<script setup>
import { ref, computed } from 'vue';
import FormField from '@/Components/FormField.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { LOCALES, DEPTHS } from '@/Utils/options';
import { interpretationIndex, isFilled, hasErrorWithPrefix } from '@/Utils/contentForm';

/** Soạn 6 bản diễn giải (vi/en × độ sâu 1–3); items là mảng 6 phần tử cố định. */
const props = defineProps({
    items: { type: Array, required: true },
    errors: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const locale = ref('vi');
const depth = ref(1);

const index = computed(() => interpretationIndex(locale.value, depth.value));
const current = computed(() => props.items[index.value]);
const err = (field) => props.errors[`interpretations.${index.value}.${field}`];
const tabFilled = (loc, d) => isFilled(props.items[interpretationIndex(loc, d)]);
const tabError = (loc, d) => hasErrorWithPrefix(props.errors, `interpretations.${interpretationIndex(loc, d)}.`);
// Backend chỉ bắt buộc bản tiếng Việt độ sâu 1 khi công bố
const missingRequired = computed(() => (tabFilled('vi', 1) ? [] : ['vi']));
</script>

<template>
    <div>
        <p v-if="missingRequired.length" class="mb-4 flex items-start gap-2 rounded-lg bg-warning-soft p-3 text-sm text-warning" role="status">
            <Icon name="alert" :size="18" />
            {{ t('admin.form.depth1_required', { locales: missingRequired.map((l) => te('locale', l)).join(', ') }) }}
        </p>
        <p v-if="errors.interpretations" class="field-error mb-3" role="alert">{{ errors.interpretations }}</p>

        <div class="mb-3 flex flex-wrap gap-2" role="tablist" :aria-label="t('admin.form.interp_locale')">
            <button
                v-for="loc in LOCALES"
                :key="loc"
                type="button"
                role="tab"
                class="tab"
                :aria-selected="locale === loc"
                @click="locale = loc"
            >
                {{ te('locale', loc) }}
            </button>
        </div>
        <div class="mb-4 flex flex-wrap gap-2" role="tablist" :aria-label="t('depth.label')">
            <button
                v-for="d in DEPTHS"
                :key="d"
                type="button"
                role="tab"
                class="tab inline-flex items-center gap-2"
                :aria-selected="depth === d"
                @click="depth = d"
            >
                {{ d }}. {{ t(`depth.${d}`) }}
                <span v-if="tabError(locale, d)" class="h-2 w-2 rounded-full bg-danger" :title="t('admin.form.tab_has_errors')"></span>
                <Icon v-else-if="tabFilled(locale, d)" name="check" :size="14" />
            </button>
        </div>

        <div v-if="current" :key="index" class="grid gap-4">
            <FormField v-model="current.title" maxlength="255" :label="t('admin.form.interp_title')" :error="err('title')" :required="depth === 1 && locale === 'vi'" />
            <FormField v-model="current.summary" type="textarea" :rows="2" maxlength="2000" :label="t('admin.form.interp_summary')" :error="err('summary')" />
            <FormField
                v-model="current.body"
                type="textarea"
                :rows="12"
                maxlength="100000"
                :label="t('admin.form.interp_body')"
                :hint="t('admin.form.interp_body_hint')"
                :error="err('body')"
                :required="depth === 1 && locale === 'vi'"
            />
            <FormField
                v-model="current.sources"
                type="textarea"
                :rows="3"
                :label="t('common.sources')"
                :hint="t('admin.form.sources_hint')"
                :error="err('sources') || errors[`interpretations.${index}.sources.0`]"
            />
        </div>
    </div>
</template>
