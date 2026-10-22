<script setup>
import FormField from '@/Components/FormField.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';

/** Danh sách nhân vật liên quan [{figure_id, relation_type}] thêm/xóa dòng. */
const props = defineProps({
    rows: { type: Array, required: true },
    figureOptions: { type: Array, default: () => [] },
    relationOptions: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
});
const { t } = useT();

const add = () => props.rows.push({ figure_id: '', relation_type: props.relationOptions[0]?.value ?? '' });
const remove = (i) => props.rows.splice(i, 1);
</script>

<template>
    <div class="space-y-3">
        <p v-if="!rows.length" class="text-sm text-muted">{{ t('admin.form.no_figures') }}</p>
        <div v-for="(row, i) in rows" :key="i" class="grid items-end gap-3 rounded-lg border border-border p-3 sm:grid-cols-[1fr_1fr_auto]">
            <FormField v-model="row.figure_id" type="select" :label="t('admin.form.figure')" :options="figureOptions" :placeholder="t('admin.form.choose')" :error="errors[`figures.${i}.figure_id`]" required />
            <FormField v-model="row.relation_type" type="select" :label="t('admin.form.relation_type')" :options="relationOptions" :error="errors[`figures.${i}.relation_type`]" required />
            <button type="button" class="btn btn-ghost btn-icon text-danger" :aria-label="t('admin.form.remove_row')" @click="remove(i)">
                <Icon name="trash" :size="18" />
            </button>
        </div>
        <button type="button" class="btn btn-sm" @click="add"><Icon name="plus" :size="16" />{{ t('admin.form.add_figure') }}</button>
    </div>
</template>
