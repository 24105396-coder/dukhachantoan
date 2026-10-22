<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import Pagination from '@/Components/Pagination.vue';
import Badge from '@/Components/Badge.vue';
import Button from '@/Components/Button.vue';
import Icon from '@/Components/Icon.vue';
import DeleteAction from '@/Components/Admin/DeleteAction.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useVisitLoading } from '@/Composables/useVisitLoading';
import { enumOptions, rowsOf } from '@/Utils/options';

/** Hướng dẫn ứng phó: lọc theo hiểm họa/trạng thái, xuất bản bản nháp, sửa, xóa. */
const props = defineProps({
    guides: { type: [Object, Array], default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    hazards: { type: Array, default: () => [] },
});
const { t, te } = useT();
const lr = useLocaleRoute();
const { loading } = useVisitLoading();
const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword') },
    { name: 'hazard', type: 'select', label: t('alerts.hazard'), options: enumOptions(props.hazards, te, 'hazard') },
    { name: 'status', type: 'select', label: t('common.status'), options: ['draft', 'published'].map((s) => ({ value: s, label: te('status', s) })) },
]);
const columns = computed(() => [
    { key: 'title', label: t('admin.common.title') },
    { key: 'min_severity', label: t('admin.guides.min_severity') },
    { key: 'audience', label: t('admin.guides.audience') },
    { key: 'status', label: t('common.status') },
]);
const publishing = reactive({});
function publish(g) {
    publishing[g.id] = true;
    router.post(lr(`/quan-tri/huong-dan/${g.id}/xuat-ban`), {}, { preserveScroll: true, onFinish: () => (publishing[g.id] = false) });
}
</script>

<template>
    <Head :title="t('admin.nav.guides')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.nav.guides')" :description="t('admin.guides.intro')">
        <template #actions>
            <Button :href="lr('/quan-tri/huong-dan/tao')" variant="primary" icon="plus">{{ t('admin.guides.create') }}</Button>
        </template>
    </PageHeader>
    <FilterBar :filters="filters" :fields="fields" class="mb-4" />
    <DataTable :columns="columns" :rows="rowsOf(guides)" :loading="loading" :caption="t('admin.nav.guides')" :empty-text="t('home.no_guides')">
        <template #cell-title="{ row }">
            <Link v-if="row.url" :href="row.url" class="link">{{ row.title }}</Link>
            <span v-else class="font-semibold">{{ row.title }}</span>
            <span class="block text-xs text-muted"><Icon :name="row.hazard?.icon || row.hazard?.code || 'life-buoy'" fallback="life-buoy" :size="12" class="inline align-[-1px]" /> {{ row.hazard?.name }} · {{ row.slug }}</span>
            <Badge v-if="row.is_machine_translated" tone="outline" size="sm" class="mt-1">{{ t('admin.guides.machine') }}</Badge>
        </template>
        <template #cell-min_severity="{ row }"><Badge v-if="row.min_severity" :level="row.min_severity" size="sm" soft /></template>
        <template #cell-audience="{ row }">{{ te('audience', row.audience) }}</template>
        <template #cell-status="{ row }"><span class="pill text-xs">{{ te('status', row.status || 'published') }}</span></template>
        <template #actions="{ row }">
            <Button v-if="row.status === 'draft'" size="sm" icon="send" :loading="!!publishing[row.id]" @click="publish(row)">{{ t('admin.guides.publish') }}</Button>
            <Button :href="lr(`/quan-tri/huong-dan/${row.id}/sua`)" size="sm" variant="ghost" icon="edit" icon-only :label="`${t('common.edit')} ${row.title}`" />
            <DeleteAction :url="lr(`/quan-tri/huong-dan/${row.id}`)" :name="row.title" />
        </template>
    </DataTable>
    <Pagination v-if="!Array.isArray(guides)" :paginator="guides" />
</template>
