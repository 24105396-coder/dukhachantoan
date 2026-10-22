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
import { STATUS_CODES, rowsOf } from '@/Utils/options';

const props = defineProps({
    sites: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    provinces: { type: Array, default: () => [] },
});
const { t, te } = useT();
const { date } = useFormat();
const lr = useLocaleRoute();

const rows = computed(() => rowsOf(props.sites));
const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword') },
    { name: 'status', type: 'select', label: t('common.status'), options: STATUS_CODES.map((c) => ({ value: c, label: te('status', c) })) },
    { name: 'province', type: 'select', label: t('common.province'), options: props.provinces.map((p) => ({ value: p.code ?? p.id, label: p.name })) },
]);
const columns = computed(() => [
    { key: 'title_vi', label: t('admin.common.title') },
    { key: 'site_type', label: t('common.type') },
    { key: 'province', label: t('common.province') },
    { key: 'status', label: t('common.status') },
    { key: 'depths', label: t('admin.common.depths') },
    { key: 'media_count', label: t('admin.common.media'), class: 'text-right' },
    { key: 'updated_at', label: t('common.updated_at') },
]);
const provinceName = (p) => (p && typeof p === 'object' ? p.name : p);
</script>

<template>
    <Head :title="t('admin.sites.title')" />
    <PageHeader :title="t('admin.sites.title')" :description="t('admin.sites.description')">
        <template #actions>
            <Link :href="lr('/quan-tri/di-san/moi')" class="btn btn-primary"><Icon name="plus" :size="18" />{{ t('admin.sites.create') }}</Link>
        </template>
    </PageHeader>
    <FilterBar :filters="filters" :fields="fields" class="mb-4" />
    <DataTable :columns="columns" :rows="rows" :caption="t('admin.sites.title')" :empty-text="t('admin.sites.empty')">
        <template #cell-title_vi="{ row }">
            <Link :href="lr(`/quan-tri/di-san/${row.slug}/sua`)" class="font-semibold hover:text-primary">{{ row.title_vi || row.slug }}</Link>
            <p v-if="row.title_en" class="text-xs text-muted">{{ row.title_en }}</p>
        </template>
        <template #cell-site_type="{ value }">{{ te('site_type', value) }}</template>
        <template #cell-province="{ value }">{{ provinceName(value) || '—' }}</template>
        <template #cell-status="{ value }"><StatusBadge v-if="value" :status="value" /></template>
        <template #cell-depths="{ row }">
            <div class="flex flex-col gap-1">
                <DepthDots :depths="row.depths_vi" locale="vi" />
                <DepthDots :depths="row.depths_en" locale="en" />
            </div>
        </template>
        <template #cell-updated_at="{ value }"><span class="whitespace-nowrap">{{ date(value) }}</span></template>
        <template #actions="{ row }">
            <a v-if="row.slug" :href="lr(`/di-san/${row.slug}`)" class="btn btn-ghost btn-sm btn-icon" :aria-label="`${t('admin.common.view_public')} ${row.title_vi}`" target="_blank" rel="noopener">
                <Icon name="eye" :size="18" />
            </a>
            <Link :href="lr(`/quan-tri/di-san/${row.slug}/sua`)" class="btn btn-ghost btn-sm btn-icon" :aria-label="`${t('common.edit')} ${row.title_vi}`">
                <Icon name="edit" :size="18" />
            </Link>
            <DeleteAction :url="lr(`/quan-tri/di-san/${row.slug}`)" :name="row.title_vi || row.slug" />
        </template>
    </DataTable>
    <Pagination :paginator="sites" />
</template>
