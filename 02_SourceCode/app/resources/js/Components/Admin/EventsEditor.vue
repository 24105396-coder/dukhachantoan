<script setup>
import { YEAR_MIN, YEAR_MAX } from '@/Utils/rules';
import FormField from '@/Components/FormField.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';

/** Sự kiện dòng thời gian lặp (song ngữ), năm âm = TCN. */
const props = defineProps({
    rows: { type: Array, required: true },
    typeOptions: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
});
const { t } = useT();

const add = () =>
    props.rows.push({
        id: null, event_type: props.typeOptions[0]?.value ?? '', event_year: '', event_month: '', event_day: '',
        end_year: '', importance: 2, title_vi: '', title_en: '', description_vi: '', description_en: '',
    });
const remove = (i) => props.rows.splice(i, 1);
const e = (i, f) => props.errors[`events.${i}.${f}`];
const importance = [1, 2, 3].map((n) => ({ value: n, label: String(n) }));
</script>

<template>
    <div class="space-y-4">
        <p v-if="!rows.length" class="text-sm text-muted">{{ t('admin.form.no_events') }}</p>
        <fieldset v-for="(row, i) in rows" :key="row.id || `n${i}`" class="rounded-lg border border-border p-3">
            <legend class="flex items-center gap-2 px-1 text-sm font-semibold">
                {{ t('admin.form.event_n', { n: i + 1 }) }}
                <button type="button" class="btn btn-ghost btn-sm btn-icon text-danger" :aria-label="t('admin.form.remove_row')" @click="remove(i)">
                    <Icon name="trash" :size="16" />
                </button>
            </legend>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <FormField v-model="row.event_type" class="lg:col-span-2" type="select" :label="t('timeline.event_type')" :options="typeOptions" :error="e(i, 'event_type')" required />
                <FormField v-model="row.event_year" type="number" :min="YEAR_MIN" :max="YEAR_MAX" :label="t('admin.form.year')" :hint="t('admin.form.year_hint')" :error="e(i, 'event_year')" required />
                <FormField v-model="row.event_month" type="number" min="1" max="12" :label="t('admin.form.month')" :error="e(i, 'event_month')" />
                <FormField v-model="row.event_day" type="number" min="1" max="31" :label="t('admin.form.day')" :error="e(i, 'event_day')" />
                <FormField v-model="row.end_year" type="number" :min="YEAR_MIN" :max="YEAR_MAX" :label="t('admin.form.end_year')" :error="e(i, 'end_year')" />
                <FormField v-model="row.importance" type="select" :label="t('admin.form.importance')" :options="importance" :error="e(i, 'importance')" />
                <FormField v-model="row.title_vi" class="sm:col-span-2 lg:col-span-5" :label="t('admin.form.title_vi')" :error="e(i, 'title_vi')" required maxlength="255" />
                <FormField v-model="row.title_en" class="sm:col-span-2 lg:col-span-6" :label="t('admin.form.title_en')" :error="e(i, 'title_en')" maxlength="255" />
                <FormField v-model="row.description_vi" class="sm:col-span-2 lg:col-span-3" type="textarea" :rows="2" maxlength="5000" :label="t('admin.form.description_vi')" :error="e(i, 'description_vi')" />
                <FormField v-model="row.description_en" class="sm:col-span-2 lg:col-span-3" type="textarea" :rows="2" maxlength="5000" :label="t('admin.form.description_en')" :error="e(i, 'description_en')" />
            </div>
        </fieldset>
        <button type="button" class="btn btn-sm" @click="add"><Icon name="plus" :size="16" />{{ t('admin.form.add_event') }}</button>
    </div>
</template>
