<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Icon from '@/Components/Icon.vue';
import DeleteAction from '@/Components/Admin/DeleteAction.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { usePermissions } from '@/Composables/usePermissions';
import { STATUS_CODES, rowsOf } from '@/Utils/options';

const props = defineProps({
    routes: { type: [Array, Object], default: () => [] },
    filters: { type: Object, default: () => ({}) },
    canCreate: { type: Boolean, default: true },
});
const { t, te } = useT();
const { money, duration, dateTime } = useFormat();
const lr = useLocaleRoute();
const { isStaff } = usePermissions();

const rows = computed(() => rowsOf(props.routes));
const isPaginated = computed(() => !Array.isArray(props.routes) && !!props.routes?.links);
const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword') },
    { name: 'status', type: 'select', label: t('common.status'), options: STATUS_CODES.map((c) => ({ value: c, label: te('status', c) })) },
]);
const columns = computed(() => [
    { key: 'name', label: t('admin.common.name') },
    { key: 'duration_minutes', label: t('admin.tours.duration') },
    { key: 'price_vnd', label: t('admin.tours.price'), class: 'text-right' },
    { key: 'stops_count', label: t('admin.tours.stops'), class: 'text-right' },
    { key: 'next_slot', label: t('tours.next_slot') },
    { key: 'status', label: t('common.status') },
    ...(isStaff.value ? [{ key: 'owner', label: t('admin.tours.owner') }] : []),
]);
const ownerName = (o) => (o && typeof o === 'object' ? o.name : o);
</script>

<template>
    <Head :title="t('admin.tours.title')" />
    <PageHeader :title="t('admin.tours.title')" :description="isStaff ? t('admin.tours.description') : t('admin.tours.description_own')">
        <template #actions>
            <Link v-if="canCreate" :href="lr('/quan-tri/tuyen-tham-quan/moi')" class="btn btn-primary"><Icon name="plus" :size="18" />{{ t('admin.tours.create') }}</Link>
        </template>
    </PageHeader>
    <FilterBar :filters="filters" :fields="fields" class="mb-4" />
    <DataTable :columns="columns" :rows="rows" :caption="t('admin.tours.title')" :empty-text="t('admin.tours.empty')">
        <template #cell-name="{ row }">
            <Link :href="lr(`/quan-tri/tuyen-tham-quan/${row.slug}/sua`)" class="font-semibold hover:text-primary">{{ row.name || row.name_vi || row.slug }}</Link>
            <p v-if="row.province" class="text-xs text-muted">{{ row.province.name || row.province }}</p>
        </template>
        <template #cell-duration_minutes="{ value }"><span class="whitespace-nowrap">{{ duration(value) }}</span></template>
        <template #cell-price_vnd="{ value }"><span class="whitespace-nowrap">{{ money(value) }}</span></template>
        <template #cell-next_slot="{ value }"><span class="whitespace-nowrap">{{ value ? dateTime(value.starts_at) : '—' }}</span></template>
        <template #cell-status="{ value }"><StatusBadge v-if="value" :status="value" /></template>
        <template #cell-owner="{ value }">{{ ownerName(value) || '—' }}</template>
        <template #actions="{ row }">
            <Link :href="lr(`/quan-tri/tuyen-tham-quan/${row.slug}/sua`)" class="btn btn-ghost btn-sm btn-icon" :aria-label="`${t('common.edit')} ${row.name}`">
                <Icon name="edit" :size="18" />
            </Link>
            <DeleteAction :url="lr(`/quan-tri/tuyen-tham-quan/${row.slug}`)" :name="row.name || row.slug" />
        </template>
    </DataTable>
    <Pagination v-if="isPaginated" :paginator="routes" />
</template>
