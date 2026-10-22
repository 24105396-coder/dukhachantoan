<script setup>
import EmptyState from '@/Components/EmptyState.vue';
import { useT } from '@/Composables/useT';

/**
 * Bảng dữ liệu trong thẻ clay, cuộn ngang bên trong thẻ (trang không cuộn ngang).
 * columns [{ key, label, class? }], rows [object]. Tùy biến ô bằng slot `cell-<key>` ({ row, value });
 * slot `actions` cho cột thao tác. `loading` → hàng skeleton; `error` → trạng thái lỗi.
 */
defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    rowKey: { type: String, default: 'id' },
    emptyText: { type: String, default: '' },
    emptyHint: { type: String, default: '' },
    caption: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
});
const { t } = useT();
const SKELETON_ROWS = 4;
</script>

<template>
    <div class="clay-card-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-clay">
                <caption v-if="caption" class="sr-only">{{ caption }}</caption>
                <thead>
                    <tr>
                        <th v-for="col in columns" :key="col.key" scope="col" :class="col.class">{{ col.label }}</th>
                        <th v-if="$slots.actions" scope="col" class="text-right">{{ t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody v-if="loading" aria-hidden="true">
                    <tr v-for="i in SKELETON_ROWS" :key="`sk-${i}`">
                        <td v-for="col in columns" :key="col.key"><div class="skeleton h-4 w-full"></div></td>
                        <td v-if="$slots.actions"><div class="skeleton h-4 w-12"></div></td>
                    </tr>
                </tbody>
                <tbody v-else>
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
        <div v-if="!loading && error" class="p-4">
            <EmptyState variant="error" :title="t('common.load_error')" :description="error" compact />
        </div>
        <div v-else-if="!loading && !rows.length" class="p-4">
            <EmptyState :title="emptyText || t('common.no_data')" :description="emptyHint" compact />
        </div>
    </div>
</template>
