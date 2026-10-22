<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import Badge from '@/Components/Badge.vue';
import Button from '@/Components/Button.vue';
import Icon from '@/Components/Icon.vue';
import DataTable from '@/Components/DataTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { usePermissions } from '@/Composables/usePermissions';

/**
 * Kịch bản mô phỏng: thẻ kịch bản (hiểm họa, mức, vùng, thời lượng, lần chạy cuối), nút Chạy / Kết thúc (chỉ admin, có xác nhận),
 * nhật ký các lần chạy. POST /quan-tri/mo-phong/chay {slug}; POST /quan-tri/mo-phong/ket-thuc {slug}.
 */
const props = defineProps({
    scenarios: { type: Array, default: () => [] },
    runs: { type: [Array, Object], default: () => [] },
});
const { t, te } = useT();
const { dateTime, number, duration } = useFormat();
const lr = useLocaleRoute();
const { isAdmin } = usePermissions();
const pending = ref(null); // { action: 'run'|'finish', scenario }
const processing = ref(false);

const runRows = computed(() => (Array.isArray(props.runs) ? props.runs : props.runs?.data || []));
const nameOf = (x) => (typeof x === 'object' && x !== null ? x.name : x);
const codeOf = (x) => (typeof x === 'object' && x !== null ? x.code : x);
const STATUS_TONE = { running: 'bg-level-3-soft border-level-3', ready: 'bg-mint-100', finished: 'bg-soft' };
const HOURS_TO_MIN = 60;

function confirmAction() {
    const { action, scenario } = pending.value || {};
    if (!action) return;
    processing.value = true;
    router.post(lr(`/quan-tri/mo-phong/${action === 'run' ? 'chay' : 'ket-thuc'}`), { slug: scenario.slug }, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            pending.value = null;
        },
    });
}
const columns = computed(() => [
    { key: 'scenario', label: t('admin.scenarios.scenario') },
    { key: 'started_at', label: t('admin.scenarios.started') },
    { key: 'finished_at', label: t('admin.scenarios.finished') },
    { key: 'alerts_created', label: t('admin.scenarios.alerts'), class: 'text-right' },
    { key: 'matches_created', label: t('admin.scenarios.matches'), class: 'text-right' },
    { key: 'notifications_created', label: t('admin.scenarios.notifications'), class: 'text-right' },
]);
</script>

<template>
    <Head :title="t('admin.nav.scenarios')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.nav.scenarios')" :description="t('admin.scenarios.intro')" />
    <p v-if="!isAdmin" class="mb-4 rounded-2xl border border-line bg-soft p-3 text-sm" role="note">{{ t('admin.scenarios.admin_only') }}</p>

    <ul v-if="scenarios.length" class="grid gap-4 lg:grid-cols-2">
        <li v-for="s in scenarios" :key="s.slug">
            <article class="clay-card-sm flex h-full flex-col gap-3 p-5" :data-status="s.status">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="icon-tile"><Icon name="flask" :size="22" /></span>
                    <Badge :level="s.severity" size="sm" show-level-number />
                    <span class="pill text-xs" :class="STATUS_TONE[s.status]">{{ te('status', s.status) }}</span>
                </div>
                <h2 class="font-display text-lg font-bold leading-snug">{{ s.name }}</h2>
                <p class="text-sm text-muted">{{ s.description }}</p>
                <dl class="grid gap-1 text-sm">
                    <div class="flex flex-wrap gap-x-2"><dt class="text-muted">{{ t('alerts.hazard') }}:</dt><dd>{{ nameOf(s.hazard) || te('hazard', codeOf(s.hazard)) }}</dd></div>
                    <div class="flex flex-wrap gap-x-2"><dt class="text-muted">{{ t('alert.zones') }}:</dt><dd>{{ (s.zone_codes || []).join(', ') }}</dd></div>
                    <div class="flex flex-wrap gap-x-2"><dt class="text-muted">{{ t('admin.scenarios.duration') }}:</dt><dd>{{ duration((s.duration_hours || 0) * HOURS_TO_MIN) }}</dd></div>
                </dl>
                <div v-if="s.last_run" class="rounded-xl bg-soft p-3 text-xs">
                    <p class="font-semibold">{{ t('admin.scenarios.last_run', { time: dateTime(s.last_run.started_at) }) }}</p>
                    <p class="text-muted">{{ t('admin.scenarios.last_run_stats', { a: number(s.last_run.alerts_created), m: number(s.last_run.matches_created), n: number(s.last_run.notifications_created) }) }}</p>
                </div>
                <div v-if="isAdmin" class="mt-auto flex flex-wrap gap-2 pt-1">
                    <Button v-if="s.status !== 'running'" variant="primary" icon="play" @click="pending = { action: 'run', scenario: s }">{{ t('admin.scenarios.run') }}</Button>
                    <Button v-else variant="danger" icon="stop" @click="pending = { action: 'finish', scenario: s }">{{ t('admin.scenarios.finish') }}</Button>
                </div>
            </article>
        </li>
    </ul>
    <EmptyState v-else :title="t('admin.scenarios.empty')" />

    <section class="mt-8 space-y-3" aria-labelledby="runs-title">
        <h2 id="runs-title" class="section-title text-xl">{{ t('admin.scenarios.runs') }}</h2>
        <DataTable :columns="columns" :rows="runRows" :caption="t('admin.scenarios.runs')" :empty-text="t('admin.scenarios.no_runs')">
            <template #cell-scenario="{ row }">
                <span class="font-semibold">{{ nameOf(row.scenario) || row.scenario_slug }}</span>
                <span v-if="row.started_by" class="block text-xs text-muted">{{ nameOf(row.started_by) }}</span>
            </template>
            <template #cell-started_at="{ value }"><span class="whitespace-nowrap">{{ dateTime(value) }}</span></template>
            <template #cell-finished_at="{ value }"><span class="whitespace-nowrap">{{ value ? dateTime(value) : te('status', 'running') }}</span></template>
        </DataTable>
    </section>

    <ConfirmDialog
        :open="!!pending"
        :title="pending ? t(`admin.scenarios.confirm_${pending.action}_title`) : ''"
        :message="pending ? t(`admin.scenarios.confirm_${pending.action}`, { name: pending.scenario.name }) : ''"
        :confirm-text="pending ? t(`admin.scenarios.${pending.action}`) : ''"
        :danger="pending?.action === 'finish'"
        :processing="processing"
        @confirm="confirmAction"
        @cancel="pending = null"
    />
</template>
