<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, shallowRef } from 'vue';
import { useT } from '@/Composables/useT';
import { useMapProvider } from '@/Composables/useMapProvider';

// Leaflet (JS + CSS) nạp động khi bản đồ sắp vào khung nhìn, không nằm trong bundle đầu vào
let L = null;
async function loadLeaflet() {
    if (L) return L;
    const [mod] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css')]);
    L = mod.default;
    return L;
}

/**
 * Bản đồ Leaflet, nền Google Maps hoặc OSM theo shared prop `map.provider`. Nhận `points` [{lat,lng,title,url,cover,subtitle}]
 * hoặc `geojson` FeatureCollection (Point). Gom cụm đơn giản theo lưới pixel khi zoom nhỏ.
 */
const props = defineProps({
    points: { type: Array, default: () => [] },
    geojson: { type: Object, default: null },
    height: { type: String, default: '420px' },
    cluster: { type: Boolean, default: true },
    center: { type: Array, default: () => [16.0, 106.5] },
    zoom: { type: Number, default: 5 },
    label: { type: String, default: '' },
});
const { t } = useT();
const { tileLayer } = useMapProvider();

const el = ref(null);
const map = shallowRef(null);
const status = ref('idle'); // idle | loading | ready | error
const VIEWPORT_MARGIN = '200px';
const layer = shallowRef(null);
const CLUSTER_PX = 48;
const CLUSTER_MAX_ZOOM = 10;

const normalized = computed(() => {
    if (props.geojson?.features) {
        return props.geojson.features
            .filter((f) => f.geometry?.type === 'Point')
            .map((f) => ({
                lat: Number(f.geometry.coordinates[1]),
                lng: Number(f.geometry.coordinates[0]),
                title: f.properties?.title,
                url: f.properties?.url,
                cover: f.properties?.cover,
                subtitle: [f.properties?.province, f.properties?.site_type_label].filter(Boolean).join(' · '),
            }));
    }
    return props.points
        .filter((p) => p.lat != null && p.lng != null)
        .map((p) => ({ ...p, lat: Number(p.lat), lng: Number(p.lng) }));
});

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

function safeUrl(u) {
    const s = String(u ?? '');
    return s.startsWith('/') || /^https?:\/\//i.test(s) ? s : '#';
}

function popupHtml(p) {
    const cover = typeof p.cover === 'string' ? p.cover : p.cover?.url;
    const img = cover
        ? `<img src="${escapeHtml(safeUrl(cover))}" alt="${escapeHtml(p.title)}" style="width:100%;height:110px;object-fit:cover;border-radius:8px;margin-bottom:6px" loading="lazy">`
        : '';
    const sub = p.subtitle ? `<div style="font-size:12px;opacity:.8">${escapeHtml(p.subtitle)}</div>` : '';
    const link = p.url ? `<a href="${escapeHtml(safeUrl(p.url))}" style="display:inline-block;margin-top:6px;font-weight:600">${escapeHtml(t('common.view_detail'))} →</a>` : '';
    return `<div style="width:200px">${img}<strong>${escapeHtml(p.title)}</strong>${sub}${link}</div>`;
}

function markerIcon() {
    return L.divIcon({ className: '', html: '<div class="ds-marker"></div>', iconSize: [28, 28], iconAnchor: [14, 28], popupAnchor: [0, -26] });
}

function clusterIcon(n) {
    return L.divIcon({ className: '', html: `<div class="ds-cluster">${n}</div>`, iconSize: [40, 40], iconAnchor: [20, 20] });
}

function buildClusters(list) {
    if (!props.cluster || map.value.getZoom() > CLUSTER_MAX_ZOOM) return list.map((p) => ({ points: [p] }));
    const cells = new Map();
    list.forEach((p) => {
        const pt = map.value.project([p.lat, p.lng]);
        const key = `${Math.floor(pt.x / CLUSTER_PX)}:${Math.floor(pt.y / CLUSTER_PX)}`;
        if (!cells.has(key)) cells.set(key, { points: [] });
        cells.get(key).points.push(p);
    });
    return [...cells.values()];
}

function render() {
    if (!map.value) return;
    layer.value?.clearLayers();
    buildClusters(normalized.value).forEach((c) => {
        if (c.points.length === 1) {
            const p = c.points[0];
            L.marker([p.lat, p.lng], { icon: markerIcon(), title: p.title, alt: p.title, keyboard: true })
                .bindPopup(popupHtml(p))
                .addTo(layer.value);
            return;
        }
        const bounds = L.latLngBounds(c.points.map((p) => [p.lat, p.lng]));
        const m = L.marker(bounds.getCenter(), { icon: clusterIcon(c.points.length), title: t('map.cluster', { n: c.points.length }), keyboard: true });
        m.on('click', () => map.value.fitBounds(bounds.pad(0.3)));
        m.addTo(layer.value);
    });
}

function fit() {
    if (!map.value) return;
    const list = normalized.value;
    if (!list.length) {
        map.value.setView(props.center, props.zoom);
    } else if (list.length === 1) {
        map.value.setView([list[0].lat, list[0].lng], 13);
    } else {
        map.value.fitBounds(L.latLngBounds(list.map((p) => [p.lat, p.lng])).pad(0.1));
    }
}

let resizeObs = null;
let viewObs = null;

function createMap() {
    map.value = L.map(el.value, { scrollWheelZoom: false, zoomControl: true }).setView(props.center, props.zoom);
    L.tileLayer(tileLayer.value.url, tileLayer.value.options).addTo(map.value);
    layer.value = L.layerGroup().addTo(map.value);
    map.value.on('zoomend', render);
    // Bấm vào bản đồ mới bật cuộn chuột, tránh "kẹt" khi cuộn trang
    map.value.on('focus click', () => map.value.scrollWheelZoom.enable());
    map.value.on('blur', () => map.value.scrollWheelZoom.disable());
    fit();
    render();
    resizeObs = new ResizeObserver(() => map.value?.invalidateSize());
    resizeObs.observe(el.value);
}

async function init() {
    if (status.value !== 'idle') return;
    status.value = 'loading';
    try {
        await loadLeaflet();
        if (!el.value) return;
        createMap();
        status.value = 'ready';
    } catch {
        status.value = 'error';
    }
}

onMounted(() => {
    // Chỉ khởi tạo khi bản đồ gần vào khung nhìn (trang chủ: bản đồ nằm dưới màn hình đầu)
    if (!('IntersectionObserver' in window)) {
        init();
        return;
    }
    viewObs = new IntersectionObserver((entries) => {
        if (entries.some((e) => e.isIntersecting)) {
            viewObs.disconnect();
            init();
        }
    }, { rootMargin: VIEWPORT_MARGIN });
    viewObs.observe(el.value);
});

watch(normalized, () => {
    fit();
    render();
});

onBeforeUnmount(() => {
    viewObs?.disconnect();
    resizeObs?.disconnect();
    map.value?.remove();
});

defineExpose({ flyTo: (lat, lng, z = 12) => map.value?.flyTo([lat, lng], z) });
</script>

<template>
    <div class="relative z-0 overflow-hidden rounded-xl border border-border">
        <div ref="el" :style="{ height }" role="region" :aria-label="label || t('map.label')"></div>
        <div v-if="status === 'loading'" class="skeleton absolute inset-0 rounded-none" role="status" :aria-label="t('common.loading')"></div>
        <p v-else-if="status === 'error'" class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-muted" role="alert">{{ t('map.load_error') }}</p>
        <p v-else-if="status === 'ready' && !normalized.length" class="pointer-events-none absolute inset-x-0 top-4 mx-auto w-fit rounded-full bg-surface px-4 py-1 text-sm shadow">
            {{ t('map.no_points') }}
        </p>
    </div>
</template>
