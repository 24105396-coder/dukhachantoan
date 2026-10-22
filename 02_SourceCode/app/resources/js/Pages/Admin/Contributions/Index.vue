<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { rowsOf } from '@/Utils/options';

const props = defineProps({
    contributions: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const { dateTime } = useFormat();
const lr = useLocaleRoute();

const STATUSES = ['pending', 'approved', 'rejected', 'needs_revision'];
const TYPES = ['new_site', 'new_artifact', 'correction', 'story', 'media'];
const rows = computed(() => rowsOf(props.contributions));
const fields = computed(() => [
    { name: 'status', type: 'select', label: t('common.status'), options: STATUSES.map((c) => ({ value: c, label: te('status', c) })) },
    { name: 'type', type: 'select', label: t('common.type'), options: TYPES.map((c) => ({ value: c, label: te('contribution_type', c) })) },
]);
const columns = computed(() => [
    { key: 'title', label: t('admin.common.title') },
    { key: 'contribution_type', label: t('common.type') },
    { key: 'user', label: t('admin.contributions.author') },
    { key: 'subject', label: t('admin.contributions.subject') },
    { key: 'status', label: t('common.status') },
    { key: 'created_at', label: t('common.created_at') },
]);
const showUrl = (row) => lr(`/quan-tri/kiem-duyet/${row.id}`);
</script>

<template>
    <Head :title="t('admin.contributions.title')" />
    <PageHeader :title="t('admin.contributions.title')" :description="t('admin.contributions.description')" />
    <FilterBar :filters="filters" :fields="fields" class="mb-4" />
    <DataTable :columns="columns" :rows="rows" :caption="t('admin.contributions.title')" :empty-text="t('admin.contributions.empty')">
        <template #cell-title="{ row }">
            <Link :href="showUrl(row)" class="font-semibold hover:text-primary">{{ row.title }}</Link>
            <span v-if="row.locale" class="badge ml-2 uppercase">{{ row.locale }}</span>
            <span v-if="row.attachment_url" class="ml-1 inline-flex text-muted" :title="t('admin.contributions.has_attachment')"><Icon name="file" :size="14" /></span>
        </template>
        <template #cell-contribution_type="{ value }">{{ te('contribution_type', value) }}</template>
        <template #cell-user="{ value }">{{ value?.name || '—' }}</template>
        <template #cell-subject="{ value }">
            <template v-if="value">
                <span class="text-xs text-muted">{{ te('subject_type', value.type) }}:</span>
                <a v-if="value.url" :href="value.url" class="link" target="_blank" rel="noopener">{{ value.title }}</a>
                <span v-else>{{ value.title }}</span>
            </template>
            <span v-else>—</span>
        </template>
        <template #cell-status="{ value }"><StatusBadge v-if="value" :status="value" /></template>
        <template #cell-created_at="{ value }"><span class="whitespace-nowrap">{{ dateTime(value) }}</span></template>
        <template #actions="{ row }">
            <Link :href="showUrl(row)" class="btn btn-sm">{{ t('admin.contributions.review') }}</Link>
        </template>
    </DataTable>
    <Pagination :paginator="contributions" />
</template>
