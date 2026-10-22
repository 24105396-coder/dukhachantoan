<script setup>
import { computed, ref } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import SiteCard from '@/Components/SiteCard.vue';
import MapView from '@/Components/MapView.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { enumOptions } from '@/Utils/options';
import PageMeta from '@/Components/Public/PageMeta.vue';

const props = defineProps({
    sites: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    provinces: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
    mapPoints: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    levels: { type: Array, default: () => [] },
});
const { t, te } = useT();

const SITE_TYPES = ['di_tich', 'danh_thang', 'di_san_phi_vat_the', 'bao_tang', 'lang_nghe'];
const LEVELS = ['unesco', 'quoc_gia_dac_biet', 'quoc_gia', 'cap_tinh', 'chua_xep_hang'];
const SORTS = ['newest', 'name', 'popular', 'oldest'];

const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword'), placeholder: t('sites.search_placeholder') },
    { name: 'province', type: 'select', label: t('common.province'), options: props.provinces.map((p) => ({ value: p.code, label: p.count != null ? `${p.name} (${p.count})` : p.name })) },
    { name: 'type', type: 'select', label: t('sites.type'), options: enumOptions(props.types.length ? props.types : SITE_TYPES, te, 'site_type') },
    { name: 'level', type: 'select', label: t('sites.level'), options: enumOptions(props.levels.length ? props.levels : LEVELS, te, 'recognition_level') },
    { name: 'period', type: 'select', label: t('common.period'), options: props.periods.map((p) => ({ value: p.code, label: p.name })) },
    { name: 'tag', type: 'select', label: t('sites.theme'), options: props.tags.map((tg) => ({ value: tg.slug, label: tg.name })) },
    { name: 'sort', type: 'select', label: t('common.sort'), placeholder: t('sites.sort_default'), options: SORTS.map((s) => ({ value: s, label: t(`sites.sort_${s}`) })) },
]);

const view = ref('grid'); // grid | map
const list = computed(() => props.sites?.data || []);
const total = computed(() => props.sites?.total ?? list.value.length);
</script>

<template>
    <PageMeta :title="t('sites.title')" fallback-key="sites.description" />
    <div class="container-page py-8">
        <PageHeader :title="t('sites.title')" :eyebrow="t('sites.eyebrow')" :description="t('sites.description')">
            <template #actions>
                <div class="inline-flex rounded-full border border-border p-1" role="group" :aria-label="t('sites.view_mode')">
                    <button type="button" class="tab border-0" :aria-pressed="view === 'grid'" @click="view = 'grid'">
                        {{ t('sites.view_grid') }}
                    </button>
                    <button type="button" class="tab border-0" :aria-pressed="view === 'map'" @click="view = 'map'">
                        <Icon name="map" :size="16" class="mr-1 inline" />{{ t('sites.view_map') }}
                    </button>
                </div>
            </template>
        </PageHeader>

        <FilterBar :filters="filters" :fields="fields" />

        <p class="mt-5 text-sm text-muted" aria-live="polite">{{ t('sites.result_count', { n: total }) }}</p>

        <div v-if="view === 'map'" class="mt-4">
            <MapView :points="mapPoints" height="520px" :label="t('sites.map_label')" />
            <p class="mt-2 text-xs text-muted">{{ t('sites.map_note') }}</p>
        </div>

        <div v-show="view === 'grid'" class="mt-4">
            <EmptyState v-if="!list.length" icon="landmark" :title="t('sites.empty')" :description="t('sites.empty_hint')" />
            <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <SiteCard v-for="(site, i) in list" :key="site.id" :site="site" :heading-level="2" :eager="i === 0" />
            </div>
            <Pagination :paginator="sites" />
        </div>
    </div>
</template>
