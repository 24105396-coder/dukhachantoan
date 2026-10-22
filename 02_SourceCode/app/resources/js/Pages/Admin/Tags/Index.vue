<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import Pagination from '@/Components/Pagination.vue';
import Icon from '@/Components/Icon.vue';
import DeleteAction from '@/Components/Admin/DeleteAction.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { rowsOf, enumOptions } from '@/Utils/options';

const props = defineProps({
    tags: { type: [Array, Object], default: () => [] },
    filters: { type: Object, default: () => ({}) },
    enums: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const lr = useLocaleRoute();

const TAG_GROUPS = ['chu_de', 'loai_hinh', 'ton_giao', 'phong_cach', 'vat_lieu', 'thoi_ky', 'vung_mien'];
const rows = computed(() => rowsOf(props.tags));
const isPaginated = computed(() => !Array.isArray(props.tags) && !!props.tags?.links);
const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword') },
    { name: 'group', type: 'select', label: t('admin.tags.group'), options: enumOptions(props.enums.groups || TAG_GROUPS, te, 'tag_group') },
]);
const columns = computed(() => [
    { key: 'name_vi', label: t('admin.common.name') },
    { key: 'slug', label: t('admin.form.slug') },
    { key: 'group', label: t('admin.tags.group') },
    { key: 'parent', label: t('admin.tags.parent') },
    { key: 'usage_count', label: t('admin.tags.usage_count'), class: 'text-right' },
]);
const parentName = (p) => (p && typeof p === 'object' ? p.name || p.name_vi || p.slug : p);
</script>

<template>
    <Head :title="t('admin.tags.title')" />
    <PageHeader :title="t('admin.tags.title')" :description="t('admin.tags.description')">
        <template #actions>
            <Link :href="lr('/quan-tri/the/moi')" class="btn btn-primary"><Icon name="plus" :size="18" />{{ t('admin.tags.create') }}</Link>
        </template>
    </PageHeader>
    <FilterBar :filters="filters" :fields="fields" class="mb-4" />
    <DataTable :columns="columns" :rows="rows" :caption="t('admin.tags.title')" :empty-text="t('admin.tags.empty')">
        <template #cell-name_vi="{ row }">
            <Link :href="lr(`/quan-tri/the/${row.slug}/sua`)" class="font-semibold hover:text-primary">{{ row.name_vi || row.name }}</Link>
            <p v-if="row.name_en" class="text-xs text-muted">{{ row.name_en }}</p>
        </template>
        <template #cell-slug="{ value }"><code class="text-xs">{{ value }}</code></template>
        <template #cell-group="{ row }"><span class="badge">{{ te('tag_group', row.group || row.tag_group) }}</span></template>
        <template #cell-parent="{ value }">{{ parentName(value) || '—' }}</template>
        <template #actions="{ row }">
            <Link :href="lr(`/quan-tri/the/${row.slug}/sua`)" class="btn btn-ghost btn-sm btn-icon" :aria-label="`${t('common.edit')} ${row.name_vi || row.name}`">
                <Icon name="edit" :size="18" />
            </Link>
            <DeleteAction :url="lr(`/quan-tri/the/${row.slug}`)" :name="row.name_vi || row.name" />
        </template>
    </DataTable>
    <Pagination v-if="isPaginated" :paginator="tags" />
</template>
