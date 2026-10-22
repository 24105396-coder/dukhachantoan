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
    artifacts: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    sites: { type: Array, default: () => [] },
    enums: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const { date } = useFormat();
const lr = useLocaleRoute();

const ARTIFACT_TYPES = ['tuong', 'bia_ky', 'do_gom', 'do_dong', 'vu_khi', 'trang_phuc', 'kien_truc', 'tai_lieu', 'bao_vat', 'khac'];
const rows = computed(() => rowsOf(props.artifacts));
const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword') },
    { name: 'status', type: 'select', label: t('common.status'), options: STATUS_CODES.map((c) => ({ value: c, label: te('status', c) })) },
    { name: 'type', type: 'select', label: t('common.type'), options: enumOptions(props.enums.artifact_types || ARTIFACT_TYPES, te, 'artifact_type') },
]);
const columns = computed(() => [
    { key: 'title_vi', label: t('admin.common.title') },
    { key: 'artifact_type', label: t('common.type') },
    { key: 'site', label: t('admin.artifacts.site') },
    { key: 'status', label: t('common.status') },
    { key: 'depths', label: t('admin.common.depths') },
    { key: 'media_count', label: t('admin.common.media'), class: 'text-right' },
    { key: 'updated_at', label: t('common.updated_at') },
]);
const siteName = (v) => (v && typeof v === 'object' ? v.title : v);
</script>

<template>
    <Head :title="t('admin.artifacts.title')" />
    <PageHeader :title="t('admin.artifacts.title')" :description="t('admin.artifacts.description')">
        <template #actions>
            <Link :href="lr('/quan-tri/hien-vat/moi')" class="btn btn-primary"><Icon name="plus" :size="18" />{{ t('admin.artifacts.create') }}</Link>
        </template>
    </PageHeader>
    <FilterBar :filters="filters" :fields="fields" class="mb-4" />
    <DataTable :columns="columns" :rows="rows" :caption="t('admin.artifacts.title')" :empty-text="t('admin.artifacts.empty')">
        <template #cell-title_vi="{ row }">
            <Link :href="lr(`/quan-tri/hien-vat/${row.slug}/sua`)" class="font-semibold hover:text-primary">{{ row.title_vi || row.slug }}</Link>
            <p v-if="row.title_en" class="text-xs text-muted">{{ row.title_en }}</p>
            <span v-if="row.is_national_treasure" class="badge badge-primary mt-1">{{ t('artifacts.national_treasure') }}</span>
        </template>
        <template #cell-artifact_type="{ value }">{{ te('artifact_type', value) }}</template>
        <template #cell-site="{ value }">{{ siteName(value) || '—' }}</template>
        <template #cell-status="{ value }"><StatusBadge v-if="value" :status="value" /></template>
        <template #cell-depths="{ row }">
            <div class="flex flex-col gap-1">
                <DepthDots :depths="row.depths_vi" locale="vi" />
                <DepthDots :depths="row.depths_en" locale="en" />
            </div>
        </template>
        <template #cell-updated_at="{ value }"><span class="whitespace-nowrap">{{ date(value) }}</span></template>
        <template #actions="{ row }">
            <Link :href="lr(`/quan-tri/hien-vat/${row.slug}/sua`)" class="btn btn-ghost btn-sm btn-icon" :aria-label="`${t('common.edit')} ${row.title_vi}`">
                <Icon name="edit" :size="18" />
            </Link>
            <DeleteAction :url="lr(`/quan-tri/hien-vat/${row.slug}`)" :name="row.title_vi || row.slug" />
        </template>
    </DataTable>
    <Pagination :paginator="artifacts" />
</template>
