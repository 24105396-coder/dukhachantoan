<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import TripCard from '@/Components/TripCard.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import SkeletonList from '@/Components/SkeletonList.vue';
import Button from '@/Components/Button.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { usePermissions } from '@/Composables/usePermissions';
import { useVisitLoading } from '@/Composables/useVisitLoading';
import { rowsOf } from '@/Utils/options';

/** Hành trình của tôi (du khách) hoặc của các đoàn tôi đăng ký (đơn vị lữ hành): thẻ có badge mức rủi ro, lọc theo trạng thái. */
const props = defineProps({
    trips: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const lr = useLocaleRoute();
const { isPartner } = usePermissions();
const { loading } = useVisitLoading();
const TRIP_STATUSES = ['draft', 'active', 'completed', 'cancelled'];
const rows = computed(() => rowsOf(props.trips));
const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword'), placeholder: t('trips.search_placeholder') },
    { name: 'status', type: 'select', label: t('common.status'), options: TRIP_STATUSES.map((s) => ({ value: s, label: te('status', s) })) },
]);
const hasFilters = computed(() => Object.values(props.filters || {}).some((v) => v !== null && v !== ''));
</script>

<template>
    <Head :title="t('nav.trips')" />
    <PageHeader :eyebrow="isPartner ? t('trips.eyebrow_partner') : t('trips.eyebrow')" :title="t('nav.trips')" :description="isPartner ? t('trips.intro_partner') : t('trips.intro')">
        <template #actions>
            <Button :href="lr('/hanh-trinh/tao')" variant="primary" icon="plus">{{ t('trips.create') }}</Button>
        </template>
    </PageHeader>

    <FilterBar :filters="filters" :fields="fields" class="mb-5" />

    <SkeletonList v-if="loading" :count="4" :cols="2" height="h-24" />
    <ul v-else-if="rows.length" class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
        <li v-for="trip in rows" :key="trip.code"><TripCard :trip="trip" :heading-level="2" /></li>
    </ul>
    <EmptyState v-else :title="hasFilters ? t('trips.empty_filtered') : t('trips.empty')" :description="t('trips.empty_hint')">
        <Button :href="lr('/hanh-trinh/tao')" variant="primary" icon="plus">{{ t('trips.create') }}</Button>
    </EmptyState>
    <Pagination :paginator="trips" />
</template>
