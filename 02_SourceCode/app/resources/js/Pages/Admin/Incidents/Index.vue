<script setup>
import { computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import ExportButton from '@/Components/Admin/ExportButton.vue';
import FilterBar from '@/Components/FilterBar.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import SkeletonList from '@/Components/SkeletonList.vue';
import Badge from '@/Components/Badge.vue';
import Button from '@/Components/Button.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useVisitLoading } from '@/Composables/useVisitLoading';
import { enumOptions, rowsOf } from '@/Utils/options';

/** Duyệt sự cố du khách báo: xác minh (có thể tạo cảnh báo từ sự cố) hoặc từ chối, kèm ghi chú duyệt. */
const props = defineProps({
    incidents: { type: [Object, Array], default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    hazards: { type: Array, default: () => [] },
});
const { t, te } = useT();
const { dateTime, ago, number } = useFormat();
const lr = useLocaleRoute();
const { loading } = useVisitLoading();
const fields = computed(() => [
    { name: 'status', type: 'select', label: t('common.status'), options: ['pending', 'verified', 'rejected'].map((s) => ({ value: s, label: te('status', s) })) },
    { name: 'hazard', type: 'select', label: t('alerts.hazard'), options: enumOptions(props.hazards, te, 'hazard') },
]);
const notes = reactive({});
const busy = reactive({});
function review(item, status) {
    busy[item.id] = status;
    router.post(lr(`/quan-tri/su-co/${item.id}/duyet`), { status, review_note: notes[item.id] || null }, {
        preserveScroll: true,
        onFinish: () => (busy[item.id] = null),
    });
}
const nameOf = (x) => (typeof x === 'object' && x !== null ? x.name : x);
const codeOf = (x) => (typeof x === 'object' && x !== null ? x.code : x);
const TONE = { pending: 'border-level-2', verified: 'border-level-0', rejected: 'border-line opacity-80' };
</script>

<template>
    <Head :title="t('admin.nav.incidents')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.nav.incidents')" :description="t('admin.incidents.intro')">
        <template #actions>
            <ExportButton dataset="su-co" />
        </template>
    </PageHeader>
    <FilterBar :filters="filters" :fields="fields" class="mb-4" />
    <SkeletonList v-if="loading" :count="3" :cols="1" />
    <ul v-else-if="rowsOf(incidents).length" class="space-y-4">
        <li v-for="it in rowsOf(incidents)" :key="it.id">
            <article class="clay-card-sm grid grid-cols-1 gap-4 border-l-4 p-4 md:grid-cols-[minmax(0,1fr)_16rem] md:p-5" :class="TONE[it.status]">
                <div class="min-w-0 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="pill text-xs">{{ te('status', it.status) }}</span>
                        <span v-if="it.hazard" class="pill text-xs"><Icon :name="it.hazard.icon || codeOf(it.hazard)" fallback="alert" :size="14" />{{ nameOf(it.hazard) || te('hazard', codeOf(it.hazard)) }}</span>
                        <span v-if="it.zone" class="pill text-xs"><Icon name="map-pin" :size="14" />{{ nameOf(it.zone) }}</span>
                        <Badge v-if="it.is_simulated" tone="outline" size="sm" icon="flask">{{ t('common.simulated') }}</Badge>
                    </div>
                    <h2 class="break-words font-display text-lg font-bold leading-snug">{{ it.title }}</h2>
                    <p class="whitespace-pre-line break-words text-sm">{{ it.description }}</p>
                    <p class="text-xs text-muted">
                        {{ it.reporter?.name || t('admin.incidents.anonymous') }} ·
                        <time :datetime="it.created_at" :title="dateTime(it.created_at)">{{ ago(it.created_at) }}</time>
                        <template v-if="it.lat != null && it.lng != null">
                            · <a :href="`https://www.openstreetmap.org/?mlat=${it.lat}&mlon=${it.lng}#map=15/${it.lat}/${it.lng}`" target="_blank" rel="noopener noreferrer" class="link">{{ number(it.lat, 4) }}, {{ number(it.lng, 4) }}</a>
                        </template>
                    </p>
                    <p v-if="it.review_note" class="rounded-xl bg-soft p-2 text-sm"><strong>{{ t('admin.incidents.review_note') }}:</strong> {{ it.review_note }}</p>
                </div>
                <div class="min-w-0 space-y-3">
                    <img v-if="it.photo_url" :src="it.photo_url" :alt="t('admin.incidents.photo_alt', { title: it.title })" class="max-h-40 w-full rounded-xl object-cover" loading="lazy" />
                    <template v-if="it.status === 'pending'">
                        <div>
                            <label :for="`note-${it.id}`" class="field-label">{{ t('admin.incidents.review_note') }}</label>
                            <textarea :id="`note-${it.id}`" v-model="notes[it.id]" rows="2" maxlength="1000" class="clay-input text-sm"></textarea>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button size="sm" variant="primary" icon="check" :loading="busy[it.id] === 'verified'" :disabled="!!busy[it.id]" @click="review(it, 'verified')">{{ t('admin.incidents.verify') }}</Button>
                            <Button size="sm" variant="ghost" icon="ban" :loading="busy[it.id] === 'rejected'" :disabled="!!busy[it.id]" @click="review(it, 'rejected')">{{ t('admin.incidents.reject') }}</Button>
                        </div>
                    </template>
                    <Button v-else-if="it.status === 'verified'" size="sm" icon="bell-ring" :href="lr(`/quan-tri/canh-bao/tao?incident=${it.id}`)">{{ t('admin.incidents.create_alert') }}</Button>
                </div>
            </article>
        </li>
    </ul>
    <EmptyState v-else :title="t('admin.incidents.empty')" />
    <Pagination v-if="!Array.isArray(incidents)" :paginator="incidents" />
</template>
