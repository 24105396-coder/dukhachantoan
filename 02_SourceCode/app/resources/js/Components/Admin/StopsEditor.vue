<script setup>
import FormField from '@/Components/FormField.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';

/** Điểm dừng của tuyến: sắp xếp bằng nút lên/xuống, thêm/xóa. */
const props = defineProps({
    rows: { type: Array, required: true },
    siteOptions: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
});
const { t } = useT();

function move(i, delta) {
    const j = i + delta;
    if (j < 0 || j >= props.rows.length) return;
    const [item] = props.rows.splice(i, 1);
    props.rows.splice(j, 0, item);
}
const add = () => props.rows.push({ site_id: '', dwell_minutes: 30, note: '' });
const remove = (i) => props.rows.splice(i, 1);
const e = (i, f) => props.errors[`stops.${i}.${f}`];
</script>

<template>
    <div class="space-y-3">
        <p v-if="errors.stops" class="field-error" role="alert">{{ errors.stops }}</p>
        <p v-if="!rows.length" class="text-sm text-muted">{{ t('admin.tours.no_stops') }}</p>
        <ol class="space-y-3">
            <li v-for="(row, i) in rows" :key="`${row.site_id}-${i}`" class="flex gap-3 rounded-lg border border-border p-3">
                <div class="flex flex-col items-center gap-1">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-sm font-bold text-on-primary" :aria-label="t('admin.tours.stop_n', { n: i + 1 })">{{ i + 1 }}</span>
                    <button type="button" class="btn btn-ghost btn-sm btn-icon" :disabled="i === 0" :aria-label="t('admin.tours.move_up')" @click="move(i, -1)">
                        <Icon name="arrowUp" :size="16" />
                    </button>
                    <button type="button" class="btn btn-ghost btn-sm btn-icon" :disabled="i === rows.length - 1" :aria-label="t('admin.tours.move_down')" @click="move(i, 1)">
                        <Icon name="arrowDown" :size="16" />
                    </button>
                </div>
                <div class="grid min-w-0 flex-1 gap-3 sm:grid-cols-[2fr_1fr]">
                    <FormField v-model="row.site_id" type="select" :label="t('admin.tours.stop_site')" :options="siteOptions" :placeholder="t('admin.form.choose')" :error="e(i, 'site_id')" required />
                    <FormField v-model="row.dwell_minutes" type="number" min="5" max="600" :label="t('admin.tours.dwell_minutes')" :error="e(i, 'dwell_minutes')" />
                    <FormField v-model="row.note" class="sm:col-span-2" maxlength="255" :label="t('common.note')" :error="e(i, 'note')" />
                </div>
                <button type="button" class="btn btn-ghost btn-icon self-start text-danger" :aria-label="t('admin.form.remove_row')" @click="remove(i)">
                    <Icon name="trash" :size="18" />
                </button>
            </li>
        </ol>
        <button type="button" class="btn btn-sm" @click="add"><Icon name="plus" :size="16" />{{ t('admin.tours.add_stop') }}</button>
    </div>
</template>
