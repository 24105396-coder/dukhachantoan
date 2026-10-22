<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import Button from '@/Components/Button.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

/** Sao lưu cơ sở dữ liệu (chỉ admin): danh sách bản sao lưu, lịch tự động, nút Sao lưu ngay (POST, giới hạn 3 lần/phút). */
defineProps({
    backups: { type: Array, default: () => [] },
    schedule: { type: String, default: '02:00' },
});
const { t } = useT();
const { dateTime, number } = useFormat();
const lr = useLocaleRoute();
const form = useForm({});
const columns = [
    { key: 'name', label: t('admin.backup.file') },
    { key: 'size_kb', label: t('admin.backup.size'), class: 'text-right' },
    { key: 'created_at', label: t('common.created_at') },
];
const run = () => form.post(lr('/quan-tri/sao-luu'), { preserveScroll: true });
</script>

<template>
    <Head :title="t('admin.backup.title')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.backup.title')" :description="t('admin.backup.intro')">
        <template #actions>
            <Button variant="primary" icon="hard-drive" :loading="form.processing" @click="run">{{ t('admin.backup.run') }}</Button>
        </template>
    </PageHeader>
    <p class="mb-4 flex items-center gap-2 text-sm text-muted"><Icon name="clock" :size="16" />{{ t('admin.backup.schedule', { time: schedule }) }}</p>
    <p v-if="form.errors && Object.keys(form.errors).length" class="mb-4 rounded-2xl border border-level-4 bg-level-4-soft p-3 text-sm" role="alert">{{ Object.values(form.errors)[0] }}</p>
    <DataTable :columns="columns" :rows="backups" row-key="name" :caption="t('admin.backup.title')" :empty-text="t('admin.backup.empty')">
        <template #cell-name="{ value }"><span class="break-all font-mono text-xs">{{ value }}</span></template>
        <template #cell-size_kb="{ value }">{{ number(value) }} KB</template>
        <template #cell-created_at="{ value }"><span class="whitespace-nowrap">{{ dateTime(value) }}</span></template>
    </DataTable>
</template>
