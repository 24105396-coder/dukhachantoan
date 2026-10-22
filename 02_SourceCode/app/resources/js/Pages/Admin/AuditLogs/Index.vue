<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import Pagination from '@/Components/Pagination.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

const props = defineProps({
    logs: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    actions: { type: Array, default: () => [] },
});
const { t, te } = useT();
const { dateTime } = useFormat();

const META_PREVIEW = 120;
const fields = computed(() => [
    props.actions.length
        ? { name: 'action', type: 'select', label: t('admin.audit.action'), options: props.actions.map((a) => ({ value: a, label: a })) }
        : { name: 'action', type: 'search', label: t('admin.audit.action'), placeholder: t('admin.audit.action_placeholder') },
    { name: 'user', type: 'search', label: t('admin.audit.user'), placeholder: t('admin.audit.user_placeholder') },
    { name: 'from', type: 'date', label: t('common.from') },
    { name: 'to', type: 'date', label: t('common.to') },
]);
const columns = computed(() => [
    { key: 'created_at', label: t('admin.audit.time') },
    { key: 'user', label: t('admin.audit.user') },
    { key: 'action', label: t('admin.audit.action') },
    { key: 'subject_type', label: t('admin.audit.subject') },
    { key: 'ip', label: 'IP' },
    { key: 'meta', label: t('admin.audit.meta') },
]);

const userName = (u) => (u && typeof u === 'object' ? u.name || u.email : u) || t('admin.audit.system');
function metaText(meta) {
    if (meta == null || meta === '') return '';
    const s = typeof meta === 'string' ? meta : JSON.stringify(meta);
    return s === '{}' || s === '[]' ? '' : s;
}
</script>

<template>
    <Head :title="t('admin.audit.title')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.audit.title')" :description="t('admin.audit.intro')" />
    <FilterBar :filters="filters" :fields="fields" />
    <div class="mt-6">
        <DataTable :columns="columns" :rows="logs?.data || []" :empty-text="t('admin.audit.empty')" :caption="t('admin.audit.title')">
            <template #cell-created_at="{ value }"><span class="whitespace-nowrap">{{ dateTime(value) }}</span></template>
            <template #cell-user="{ value }">{{ userName(value) }}</template>
            <template #cell-action="{ value }"><code class="text-xs">{{ value }}</code></template>
            <template #cell-subject_type="{ row }">
                <span v-if="row.subject_type">{{ te('subject_type', row.subject_type) }} #{{ row.subject_id }}</span>
                <span v-else>—</span>
            </template>
            <template #cell-ip="{ value }"><span class="whitespace-nowrap text-xs">{{ value || '—' }}</span></template>
            <template #cell-meta="{ value }">
                <details v-if="metaText(value).length > META_PREVIEW" class="max-w-xs text-xs">
                    <summary class="cursor-pointer"><code>{{ metaText(value).slice(0, META_PREVIEW) }}…</code></summary>
                    <pre class="mt-1 max-h-60 overflow-auto whitespace-pre-wrap break-all rounded bg-surface-2 p-2">{{ JSON.stringify(value, null, 2) }}</pre>
                </details>
                <code v-else class="block max-w-xs break-all text-xs">{{ metaText(value) || '—' }}</code>
            </template>
        </DataTable>
        <Pagination :paginator="logs" />
    </div>
</template>
