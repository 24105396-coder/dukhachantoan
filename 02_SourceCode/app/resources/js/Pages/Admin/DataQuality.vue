<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import DataTable from '@/Components/DataTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

const props = defineProps({
    report: { type: Object, default: null },
    analyticsUp: { type: Boolean, default: true },
    lastError: { type: String, default: null },
});
const { t, te } = useT();
const { dateTime, number } = useFormat();
const lr = useLocaleRoute();
const form = useForm({});

const run = () => form.post(lr('/quan-tri/chat-luong-du-lieu/kiem-tra'), { preserveScroll: true });
const checks = computed(() =>
    [...(props.report?.checks || [])].sort((a, b) => (a.severity === b.severity ? b.count - a.count : a.severity === 'error' ? -1 : 1)),
);
const passed = computed(() => checks.value.filter((c) => !c.count).length);
const columns = computed(() => [
    { key: 'severity', label: t('admin.quality.severity') },
    { key: 'title', label: t('admin.quality.check') },
    { key: 'count', label: t('admin.quality.count'), class: 'text-right' },
    { key: 'samples', label: t('admin.quality.samples') },
]);
</script>

<template>
    <Head :title="t('admin.quality.title')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.quality.title')" :description="t('admin.quality.intro')">
        <template #actions>
            <button type="button" class="btn btn-primary" :disabled="form.processing || !analyticsUp" @click="run">
                <Icon name="refresh" :size="18" :class="form.processing ? 'animate-spin' : ''" />{{ form.processing ? t('admin.quality.running') : t('admin.quality.run') }}
            </button>
        </template>
    </PageHeader>

    <p v-if="!analyticsUp" class="mb-4 flex items-start gap-2 rounded-lg bg-warning-soft p-3 text-sm text-warning" role="alert">
        <Icon name="alert" :size="18" />{{ t('admin.quality.analytics_down') }}
    </p>
    <p v-if="lastError" class="mb-4 rounded-lg bg-danger-soft p-3 text-sm text-danger" role="alert">{{ t('admin.quality.last_error') }}: {{ lastError }}</p>

    <EmptyState v-if="!report" icon="activity" :title="t('admin.quality.no_report')" :description="t('admin.quality.no_report_hint')" />
    <template v-else>
        <p class="mb-4 text-sm text-muted">{{ t('admin.quality.generated_at', { time: dateTime(report.generated_at) }) }}</p>
        <div class="grid gap-4 sm:grid-cols-3">
            <StatCard :label="te('severity', 'error')" :value="number(report.summary?.errors ?? 0)" icon="alert" tone="primary" />
            <StatCard :label="te('severity', 'warning')" :value="number(report.summary?.warnings ?? 0)" icon="info" tone="accent" />
            <StatCard :label="t('admin.quality.passed')" :value="`${passed} / ${checks.length}`" icon="check" tone="success" />
        </div>
        <div class="mt-6">
            <DataTable :columns="columns" :rows="checks" row-key="code" :caption="t('admin.quality.title')">
                <template #cell-severity="{ row }">
                    <span class="badge" :class="!row.count ? 'badge-success' : row.severity === 'error' ? 'badge-danger' : 'badge-warning'">
                        {{ !row.count ? t('admin.quality.ok') : te('severity', row.severity) }}
                    </span>
                </template>
                <template #cell-title="{ row }">
                    <span class="block font-medium">{{ row.title }}</span>
                    <code class="text-xs text-muted">{{ row.code }}</code>
                </template>
                <template #cell-count="{ value }"><span class="font-semibold tabular-nums">{{ number(value) }}</span></template>
                <template #cell-samples="{ row }">
                    <details v-if="row.samples?.length" class="max-w-sm text-xs">
                        <summary class="cursor-pointer">{{ t('admin.quality.show_samples', { n: row.samples.length }) }}</summary>
                        <ul class="mt-1 list-disc space-y-0.5 pl-4">
                            <li v-for="(s, i) in row.samples" :key="i" class="break-all">{{ s }}</li>
                        </ul>
                    </details>
                    <span v-else class="text-xs text-muted">—</span>
                </template>
            </DataTable>
        </div>
    </template>
</template>
