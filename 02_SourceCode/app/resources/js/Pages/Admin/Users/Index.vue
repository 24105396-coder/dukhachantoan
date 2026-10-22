<script setup>
import { computed, reactive } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import FilterBar from '@/Components/FilterBar.vue';
import Pagination from '@/Components/Pagination.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

const props = defineProps({
    users: { type: Object, default: () => ({ data: [] }) },
    roles: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});
const page = usePage();
const { t, te } = useT();
const { dateTime, date } = useFormat();
const lr = useLocaleRoute();

const meId = computed(() => page.props.auth?.user?.id);
// roles có thể là [{id, code, name}] hoặc chuỗi mã
const roleList = computed(() => props.roles.map((r) => (typeof r === 'object' ? r : { id: r, code: r, name: null })));
const roleIdOf = (code) => roleList.value.find((r) => r.code === code)?.id ?? code;
const saving = reactive({});
const filterFields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword'), placeholder: t('admin.users.search_placeholder') },
    { name: 'role', type: 'select', label: t('admin.users.role'), options: roleList.value.map((r) => ({ value: r.code, label: r.name || te('roles', r.code) })) },
]);
const errors = computed(() => page.props.errors || {});

const columns = computed(() => [
    { key: 'name', label: t('common.name') },
    { key: 'role', label: t('admin.users.role') },
    { key: 'is_active', label: t('admin.users.active') },
    { key: 'last_login_at', label: t('admin.users.last_login') },
    { key: 'created_at', label: t('common.created_at') },
]);

function update(user, changes) {
    const payload = { role_id: user.role_id ?? roleIdOf(user.role), is_active: !!user.is_active, ...changes };
    saving[user.id] = true;
    router.patch(lr(`/quan-tri/nguoi-dung/${user.id}`), payload, {
        preserveScroll: true,
        onFinish: () => (saving[user.id] = false),
    });
}
</script>

<template>
    <Head :title="t('admin.users.title')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.users.title')" :description="t('admin.users.intro')" />
    <p v-if="errors.role_id || errors.is_active" class="mb-4 rounded-lg bg-danger-soft p-3 text-sm text-danger" role="alert">{{ errors.role_id || errors.is_active }}</p>

    <FilterBar :filters="filters" :fields="filterFields" class="mb-4" />
    <DataTable :columns="columns" :rows="users?.data || []" :caption="t('admin.users.title')">
        <template #cell-name="{ row }">
            <span class="block font-semibold">{{ row.name }} <span v-if="row.id === meId" class="badge badge-info">{{ t('admin.users.you') }}</span></span>
            <span class="block break-all text-xs text-muted">{{ row.email }}</span>
        </template>
        <template #cell-role="{ row }">
            <label :for="`role-${row.id}`" class="sr-only">{{ t('admin.users.role_for', { name: row.name }) }}</label>
            <select
                :id="`role-${row.id}`"
                class="input min-w-40"
                :value="roleIdOf(row.role)"
                :disabled="row.id === meId || saving[row.id]"
                @change="update(row, { role_id: Number($event.target.value) })"
            >
                <option v-for="r in roleList" :key="r.id" :value="r.id">{{ te('roles', r.code) || r.name }}</option>
            </select>
        </template>
        <template #cell-is_active="{ row }">
            <label class="inline-flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    class="h-4 w-4 accent-[var(--ds-primary)]"
                    :checked="!!row.is_active"
                    :disabled="row.id === meId || saving[row.id]"
                    @change="update(row, { is_active: $event.target.checked })"
                />
                {{ row.is_active ? t('admin.users.active') : t('admin.users.locked') }}
            </label>
        </template>
        <template #cell-last_login_at="{ value }"><span class="whitespace-nowrap">{{ value ? dateTime(value) : t('admin.users.never') }}</span></template>
        <template #cell-created_at="{ value }">{{ date(value) }}</template>
    </DataTable>
    <p class="mt-2 text-xs text-muted">{{ t('admin.users.self_note') }}</p>
    <Pagination :paginator="users" />
</template>
