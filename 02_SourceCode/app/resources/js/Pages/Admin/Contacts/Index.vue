<script setup>
import { computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import ExportButton from '@/Components/Admin/ExportButton.vue';
import FilterBar from '@/Components/FilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import Pagination from '@/Components/Pagination.vue';
import Badge from '@/Components/Badge.vue';
import Button from '@/Components/Button.vue';
import DeleteAction from '@/Components/Admin/DeleteAction.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useVisitLoading } from '@/Composables/useVisitLoading';
import { enumOptions, rowsOf } from '@/Utils/options';

/** Danh bạ khẩn cấp: lọc, xác minh (ghi ngày xác minh), sửa, xóa. */
const props = defineProps({
    contacts: { type: [Object, Array], default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    provinces: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
});
const { t, te } = useT();
const { date } = useFormat();
const lr = useLocaleRoute();
const { loading } = useVisitLoading();
const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword'), placeholder: t('admin.contacts.search_placeholder') },
    { name: 'province', type: 'select', label: t('common.province'), options: enumOptions(props.provinces) },
    { name: 'type', type: 'select', label: t('contacts.type'), options: enumOptions(props.types, te, 'contact_type') },
    { name: 'verified', type: 'select', label: t('admin.contacts.verified'), options: [{ value: '1', label: te('status', 'verified') }, { value: '0', label: t('contact.unverified') }] },
]);
const columns = computed(() => [
    { key: 'name', label: t('common.name') },
    { key: 'phone', label: t('common.phone') },
    { key: 'province', label: t('common.province') },
    { key: 'verified', label: t('admin.contacts.verified') },
]);
const verifying = reactive({});
function verify(c) {
    verifying[c.id] = true;
    router.post(lr(`/quan-tri/danh-ba/${c.id}/xac-minh`), {}, { preserveScroll: true, onFinish: () => (verifying[c.id] = false) });
}
</script>

<template>
    <Head :title="t('admin.nav.contacts')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.nav.contacts')" :description="t('admin.contacts.intro')">
        <template #actions>
            <ExportButton dataset="danh-ba" />
            <Button :href="lr('/quan-tri/danh-ba/tao')" variant="primary" icon="plus">{{ t('admin.contacts.create') }}</Button>
        </template>
    </PageHeader>
    <FilterBar :filters="filters" :fields="fields" class="mb-4" />
    <DataTable :columns="columns" :rows="rowsOf(contacts)" :loading="loading" :caption="t('admin.nav.contacts')" :empty-text="t('contacts.empty')">
        <template #cell-name="{ row }">
            <span class="font-semibold">{{ row.name }}</span>
            <span class="block text-xs text-muted">{{ row.type_label || te('contact_type', row.contact_type) }}</span>
        </template>
        <template #cell-phone="{ row }"><span class="whitespace-nowrap font-mono">{{ row.phone }}</span><span v-if="row.phone_alt" class="block whitespace-nowrap font-mono text-xs text-muted">{{ row.phone_alt }}</span></template>
        <template #cell-province="{ row }">{{ row.province?.name || '—' }}<span v-if="row.zone?.name && row.zone.name !== row.province?.name" class="block text-xs text-muted">{{ row.zone.name }}</span></template>
        <template #cell-verified="{ row }">
            <Badge v-if="row.is_verified" tone="brand" size="sm" icon="check">{{ date(row.verified_at) || te('status', 'verified') }}</Badge>
            <Badge v-else tone="outline" size="sm" icon="circle-alert">{{ t('contact.unverified') }}</Badge>
        </template>
        <template #actions="{ row }">
            <Button v-if="!row.is_verified" size="sm" icon="check" :loading="!!verifying[row.id]" @click="verify(row)">{{ t('admin.contacts.verify') }}</Button>
            <Button :href="lr(`/quan-tri/danh-ba/${row.id}/sua`)" size="sm" variant="ghost" icon="edit" icon-only :label="`${t('common.edit')} ${row.name}`" />
            <DeleteAction :url="lr(`/quan-tri/danh-ba/${row.id}`)" :name="row.name" />
        </template>
    </DataTable>
    <Pagination v-if="!Array.isArray(contacts)" :paginator="contacts" />
</template>
