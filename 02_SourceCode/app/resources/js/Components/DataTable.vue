<script setup>
import EmptyState from '@/Components/EmptyState.vue';
import { useT } from '@/Composables/useT';

/**
 * Bảng dữ liệu: columns [{ key, label, class? }], rows [object].
 * Tùy biến ô bằng slot `cell-<key>` với { row, value }; slot `actions` cho cột thao tác.
 */
defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    rowKey: { type: String, default: 'id' },
    emptyText: { type: String, default: '' },
    caption: { type: String, default: '' },
});
const { t } = useT();
</script>

<template>
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-ds">
                <caption v-if="caption" class="sr-only">{{ caption }}</caption>
                <thead>
                    <tr>
                        <th v-for="col in columns" :key="col.key" scope="col" :class="col.class">{{ col.label }}</th>
                        <th v-if="$slots.actions" scope="col" class="text-right">{{ t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in rows" :key="row[rowKey] ?? i">
                        <td v-for="col in columns" :key="col.key" :class="col.class">
                            <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">{{ row[col.key] ?? '—' }}</slot>
                        </td>
                        <td v-if="$slots.actions" class="whitespace-nowrap text-right">
                            <slot name="actions" :row="row" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="!rows.length" class="p-4">
            <EmptyState :title="emptyText || t('common.no_data')" compact />
        </div>
    </div>
</template>
