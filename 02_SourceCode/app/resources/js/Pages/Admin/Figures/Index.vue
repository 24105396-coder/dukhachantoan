<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Icon from '@/Components/Icon.vue';
import DepthDots from '@/Components/Admin/DepthDots.vue';
import DeleteAction from '@/Components/Admin/DeleteAction.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { STATUS_CODES, rowsOf, enumOptions } from '@/Utils/options';

const props = defineProps({
    figures: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    enums: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const { date, yearRange } = useFormat();
const lr = useLocaleRoute();

const FIGURE_TYPES = ['vua_chua', 'tuong_linh', 'nha_van_hoa', 'anh_hung', 'nhan_vat_ton_giao', 'nghe_nhan', 'khac'];
const rows = computed(() => rowsOf(props.figures));
const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword') },
    { name: 'status', type: 'select', label: t('common.status'), options: STATUS_CODES.map((c) => ({ value: c, label: te('status', c) })) },
    { name: 'type', type: 'select', label: t('common.type'), options: enumOptions(props.enums.figure_types || FIGURE_TYPES, te, 'figure_type') },
]);
const columns = computed(() => [
    { key: 'title_vi', label: t('admin.common.title') },
    { key: 'figure_type', label: t('common.type') },
    { key: 'years', label: t('admin.figures.lifespan') },
    { key: 'status', label: t('common.status') },
    { key: 'depths', label: t('admin.common.depths') },
    { key: 'media_count', label: t('admin.common.media'), class: 'text-right' },
    { key: 'updated_at', label: t('common.updated_at') },
]);
</script>

<template>
    <Head :title="t('admin.figures.title')" />
    <PageHeader :title="t('admin.figures.title')" :description="t('admin.figures.description')">
        <template #actions>
            <Link :href="lr('/quan-tri/nhan-vat/moi')" class="btn btn-primary"><Icon name="plus" :size="18" />{{ t('admin.figures.create') }}</Link>
        </template>
    </PageHeader>
    <FilterBar :filters="filters" :fields="fields" class="mb-4" />
    <DataTable :columns="columns" :rows="rows" :caption="t('admin.figures.title')" :empty-text="t('admin.figures.empty')">
        <template #cell-title_vi="{ row }">
            <Link :href="lr(`/quan-tri/nhan-vat/${row.slug}/sua`)" class="font-semibold hover:text-primary">{{ row.title_vi || row.slug }}</Link>
            <p v-if="row.title_en" class="text-xs text-muted">{{ row.title_en }}</p>
        </template>
        <template #cell-figure_type="{ value }">{{ te('figure_type', value) }}</template>
        <template #cell-years="{ row }"><span class="whitespace-nowrap">{{ yearRange(row.birth_year, row.death_year) || '—' }}</span></template>
        <template #cell-status="{ value }"><StatusBadge v-if="value" :status="value" /></template>
        <template #cell-depths="{ row }">
            <div class="flex flex-col gap-1">
                <DepthDots :depths="row.depths_vi" locale="vi" />
                <DepthDots :depths="row.depths_en" locale="en" />
            </div>
        </template>
        <template #cell-updated_at="{ value }"><span class="whitespace-nowrap">{{ date(value) }}</span></template>
        <template #actions="{ row }">
            <Link :href="lr(`/quan-tri/nhan-vat/${row.slug}/sua`)" class="btn btn-ghost btn-sm btn-icon" :aria-label="`${t('common.edit')} ${row.title_vi}`">
                <Icon name="edit" :size="18" />
            </Link>
            <DeleteAction :url="lr(`/quan-tri/nhan-vat/${row.slug}`)" :name="row.title_vi || row.slug" />
        </template>
    </DataTable>
    <Pagination :paginator="figures" />
</template>
