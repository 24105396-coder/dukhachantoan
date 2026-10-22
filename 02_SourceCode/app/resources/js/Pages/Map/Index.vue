<script setup>
import { computed, ref } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import MapView from '@/Components/MapView.vue';
// Bản đồ là nội dung chính (phần tử LCP) của trang này: import tĩnh để Leaflet được modulepreload cùng trang,
// MapView vẫn import động nhưng sẽ nhận ngay module đã nạp
import 'leaflet';
import 'leaflet/dist/leaflet.css';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useMapProvider } from '@/Composables/useMapProvider';
import { useFormat } from '@/Composables/useFormat';
import { enumOptions } from '@/Utils/options';
import PageMeta from '@/Components/Public/PageMeta.vue';

const props = defineProps({
    geojson: { type: Object, default: () => ({ type: 'FeatureCollection', features: [] }) },
    provinces: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    types: { type: Array, default: () => [] },
    levels: { type: Array, default: () => [] },
});
const { t, te } = useT();
const { number } = useFormat();
const { preconnect } = useMapProvider();
const mapRef = ref(null);

const SITE_TYPES = ['di_tich', 'danh_thang', 'di_san_phi_vat_the', 'bao_tang', 'lang_nghe'];
const LEVELS = ['unesco', 'quoc_gia_dac_biet', 'quoc_gia', 'cap_tinh', 'chua_xep_hang'];

const fields = computed(() => [
    { name: 'type', type: 'select', label: t('sites.type'), options: enumOptions(props.types.length ? props.types : SITE_TYPES, te, 'site_type') },
    { name: 'level', type: 'select', label: t('sites.level'), options: enumOptions(props.levels.length ? props.levels : LEVELS, te, 'recognition_level') },
    { name: 'province', type: 'select', label: t('common.province'), options: props.provinces.map((p) => ({ value: p.code, label: p.name })) },
]);

const count = computed(() => props.geojson?.features?.length || 0);
const sortedProvinces = computed(() => [...props.provinces].filter((p) => p.count > 0).sort((a, b) => b.count - a.count));

const focus = (p) => {
    if (p.lat != null && p.lng != null) mapRef.value?.flyTo(Number(p.lat), Number(p.lng), 10);
};
</script>

<template>
    <PageMeta :title="t('map.title')" fallback-key="map.description">
        <!-- Tile bản đồ nền là phần tử LCP của trang: mở kết nối sớm tới máy chủ tile đang dùng -->
        <link head-key="preconnect-tiles" rel="preconnect" :href="preconnect" />
    </PageMeta>
    <div class="container-page py-8">
        <PageHeader :title="t('map.title')" :eyebrow="t('map.eyebrow')" :description="t('map.description')" />
        <FilterBar :filters="filters" :fields="fields" />
        <p class="mt-4 text-sm text-muted" aria-live="polite">{{ t('map.count', { n: count }) }}</p>

        <div class="mt-4 grid gap-5 lg:grid-cols-[minmax(0,1fr)_280px]">
            <MapView ref="mapRef" :geojson="geojson" height="min(70vh, 640px)" :label="t('map.title')" />
            <aside class="card flex max-h-[min(70vh,640px)] flex-col p-4">
                <h2 class="mb-3 font-body text-base font-bold">{{ t('map.by_province') }}</h2>
                <p v-if="!sortedProvinces.length" class="text-sm text-muted">{{ t('common.no_data') }}</p>
                <ul v-else class="-mx-2 flex-1 space-y-0.5 overflow-y-auto">
                    <li v-for="p in sortedProvinces" :key="p.code">
                        <button type="button" class="flex w-full items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-left text-sm hover:bg-surface-2" :title="t('map.focus_province', { name: p.name })" @click="focus(p)">
                            <span class="inline-flex min-w-0 items-center gap-2"><Icon name="pin" :size="14" class="text-primary" /><span class="truncate">{{ p.name }}</span></span>
                            <span class="badge shrink-0">{{ number(p.count) }}</span>
                        </button>
                    </li>
                </ul>
            </aside>
        </div>
    </div>
</template>
