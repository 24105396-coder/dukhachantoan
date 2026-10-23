<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, shallowRef, useId } from 'vue';
import { router } from '@inertiajs/vue3';
import EmptyState from '@/Components/EmptyState.vue';
import { useT } from '@/Composables/useT';
import { LEVELS, normalizeLevel, levelColor } from '@/Utils/levels';
import { normalizeGeometry, emptyBox, extendBoxGeoJson, extendBoxPoint, boxToBounds } from '@/Utils/geo';

/**
 * Bản đồ Leaflet + tile OpenStreetMap trong thẻ clay.
 * - geojson: FeatureCollection(ZoneGeo) – vùng tô màu theo properties.level (0–4), popup tên + mức + liên kết url.
 * - markers: [{ id, lat, lng, label, level?, url?, description? }] – điểm tròn màu theo mức.
 * - legend: [{ level, label, color? }] (mặc định 5 mức) hiển thị dạng pill có chữ (không chỉ dựa màu).
 * - overlays: [{ id, geometry, level, label, url?, description? }] – phạm vi cảnh báo (GeoJSON hoặc
 *   { type:'Circle', center, radius_km }) vẽ nét đứt theo mức.
 * - route: [{ lat, lng }] – đường nối các chặng theo thứ tự; userLocation: { lat, lng } – vị trí của tôi.
 * - focus: { lat, lng, zoom } – ưu tiên đặt khung nhìn vào điểm này thay vì tự khớp dữ liệu.
 * - selected: mã vùng đang chọn (viền đậm).
 * - priority: ảnh nền cục bộ (đường viền 34 tỉnh, /images/map-placeholder.svg) nạp ưu tiên cao – dùng ở trang
 *   mà bản đồ là nội dung chính trên màn hình đầu (BUG-11: ảnh này thành phần tử LCP thay cho ô tile OSM ngoài).
 * Leaflet nạp động (chunk vendor-leaflet) để trang không có bản đồ không phải tải.
 */
const props = defineProps({
    geojson: { type: Object, default: null },
    markers: { type: Array, default: () => [] },
    center: { type: Object, default: null }, // { lat, lng, zoom }
    legend: { type: Array, default: null },
    showLegend: { type: Boolean, default: true },
    fit: { type: Boolean, default: true },
    heightClass: { type: String, default: 'h-[320px] md:h-[420px]' },
    ariaLabel: { type: String, default: '' },
    bare: { type: Boolean, default: false },
    overlays: { type: Array, default: () => [] },
    route: { type: Array, default: () => [] },
    userLocation: { type: Object, default: null },
    focus: { type: Object, default: null },
    selected: { type: String, default: null },
    scrollZoom: { type: Boolean, default: false },
    priority: { type: Boolean, default: false },
});
const emit = defineEmits(['select', 'mapclick']);
const { t } = useT();
const uid = useId();

const VIETNAM = { lat: 16.2, lng: 106.5, zoom: 5 };
const FIT_PADDING = [24, 24];
const MAX_FIT_ZOOM = 11;
const TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const PLACEHOLDER_SRC = '/images/map-placeholder.svg';
const PLACEHOLDER_SIZE = 1600;
const TILE_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a>';

const el = ref(null);
const map = shallowRef(null);
const loading = ref(true);
const failed = ref(false);
let L = null;
let dataLayer = null;

const features = computed(() => (Array.isArray(props.geojson?.features) ? props.geojson.features : []));
const legendItems = computed(() => {
    if (props.legend?.length) return props.legend.map((x) => ({ ...x, level: normalizeLevel(x.level) ?? 0 }));
    return Object.keys(LEVELS).map((n) => ({ level: Number(n), label: t(LEVELS[n].key) }));
});
const DOTS = { 0: 'bg-level-0', 1: 'bg-level-1', 2: 'bg-level-2', 3: 'bg-level-3', 4: 'bg-level-4' };
const summary = computed(() =>
    features.value
        .map((f) => f.properties || {})
        .filter((p) => p.name)
        .map((p) => ({ name: p.name, level: normalizeLevel(p.level), url: p.url })),
);

const isSafeHref = (url) => typeof url === 'string' && (/^\/(?!\/)/.test(url) || /^https?:\/\//i.test(url));

/** Popup dựng bằng DOM + textContent (không chèn HTML từ dữ liệu). */
function popupContent({ name, level, url, description }) {
    const box = document.createElement('div');
    box.className = 'space-y-1';
    const title = document.createElement('strong');
    title.className = 'block font-display text-base';
    title.textContent = name || '';
    box.appendChild(title);
    const lv = normalizeLevel(level);
    if (lv !== null) {
        const tag = document.createElement('span');
        tag.className = 'block text-xs font-semibold';
        tag.textContent = `${t('map.level')}: ${t(LEVELS[lv].key)}`;
        box.appendChild(tag);
    }
    if (description) {
        const p = document.createElement('span');
        p.className = 'block text-xs';
        p.textContent = description;
        box.appendChild(p);
    }
    if (isSafeHref(url)) {
        const a = document.createElement('a');
        a.href = url;
        a.textContent = t('common.view_detail');
        a.addEventListener('click', (e) => {
            if (url.startsWith('/')) {
                e.preventDefault();
                router.visit(url);
            }
        });
        box.appendChild(a);
    }
    return box;
}

function styleFor(feature) {
    const color = levelColor(feature?.properties?.level);
    const isSelected = props.selected && feature?.properties?.code === props.selected;
    return { color, weight: isSelected ? 4 : 2, opacity: 0.9, fillColor: color, fillOpacity: isSelected ? 0.45 : 0.28 };
}

function overlayStyle(level) {
    const color = levelColor(level);
    return { color, weight: 3, opacity: 1, dashArray: '8 6', fillColor: color, fillOpacity: 0.18 };
}

const KM_TO_M = 1000;

/** Vẽ phạm vi cảnh báo: vòng tròn (L.circle) hoặc GeoJSON đa giác. */
function drawOverlay(o) {
    const g = normalizeGeometry(o.geometry);
    if (!g) return;
    const content = () => popupContent({ name: o.label, level: o.level, url: o.url, description: o.description });
    const layer = g.kind === 'circle'
        ? L.circle([g.center.lat, g.center.lng], { radius: g.radiusKm * KM_TO_M, ...overlayStyle(o.level) })
        : L.geoJSON(g.data, {
            style: () => overlayStyle(o.level),
            pointToLayer: (f, latlng) => L.circleMarker(latlng, pointStyle(o.level)),
        });
    layer.bindPopup(content).on('click', () => emit('select', { type: 'alert', ...o })).addTo(dataLayer);
}

function drawRoute() {
    const pts = props.route
        .filter((p) => Number.isFinite(Number(p?.lat)) && Number.isFinite(Number(p?.lng)))
        .map((p) => [Number(p.lat), Number(p.lng)]);
    if (pts.length < 2) return;
    L.polyline(pts, { color: levelColor(0), weight: 3, opacity: 0.85, dashArray: '2 8', lineCap: 'round' }).addTo(dataLayer);
}

function drawUser() {
    const u = props.userLocation;
    if (!u || !Number.isFinite(Number(u.lat)) || !Number.isFinite(Number(u.lng))) return;
    L.circleMarker([Number(u.lat), Number(u.lng)], { radius: 9, color: '#FFFFFF', weight: 3, fillColor: levelColor(1), fillOpacity: 1 })
        .bindPopup(() => popupContent({ name: t('map.my_location') }))
        .addTo(dataLayer);
}

function pointStyle(level) {
    const color = levelColor(level);
    return { radius: 8, color: '#FFFFFF', weight: 2, fillColor: color, fillOpacity: 0.95 };
}

/** Vẽ lại toàn bộ lớp dữ liệu; refit = đặt lại khung nhìn theo dữ liệu (lần đầu đã đặt trước trong onMounted). */
function draw(refit = true) {
    if (!map.value || !L) return;
    dataLayer?.remove();
    dataLayer = L.featureGroup();
    if (features.value.length) {
        L.geoJSON({ type: 'FeatureCollection', features: features.value }, {
            style: styleFor,
            pointToLayer: (f, latlng) => L.circleMarker(latlng, pointStyle(f?.properties?.level)),
            onEachFeature: (f, layer) => {
                layer.bindPopup(() => popupContent(f.properties || {}));
                layer.on('click', () => emit('select', f.properties || {}));
            },
        }).addTo(dataLayer);
    }
    props.markers
        .filter((m) => Number.isFinite(Number(m.lat)) && Number.isFinite(Number(m.lng)))
        .forEach((m) => {
            L.circleMarker([Number(m.lat), Number(m.lng)], pointStyle(m.level ?? 0))
                .bindPopup(() => popupContent({ name: m.label, level: m.level, url: m.url, description: m.description }))
                .on('click', () => emit('select', m))
                .addTo(dataLayer);
        });
    props.overlays.forEach(drawOverlay);
    drawRoute();
    drawUser();
    dataLayer.addTo(map.value);
    if (refit) focusView(dataLayer.getBounds());
}

/** Khung bao dữ liệu tính thẳng từ props (không dựng lớp Leaflet) để đặt khung nhìn cuối trước khi tải tile. */
function dataBounds() {
    const box = emptyBox();
    features.value.forEach((f) => extendBoxGeoJson(box, f));
    props.markers.forEach((m) => extendBoxPoint(box, m));
    props.route.forEach((p) => extendBoxPoint(box, p));
    extendBoxPoint(box, props.userLocation);
    props.overlays.forEach((o) => {
        const g = normalizeGeometry(o.geometry);
        if (g?.kind === 'circle') extendBoxPoint(box, g.center, g.radiusKm);
        else if (g) extendBoxGeoJson(box, g.data);
    });
    const b = boxToBounds(box);
    return b ? L.latLngBounds(b) : null;
}

function focusView(bounds) {
    const f = props.focus;
    if (f && Number.isFinite(Number(f.lat)) && Number.isFinite(Number(f.lng))) {
        map.value.setView([Number(f.lat), Number(f.lng)], f.zoom ?? MAX_FIT_ZOOM);
        return;
    }
    if (props.fit && bounds?.isValid()) {
        map.value.fitBounds(bounds, { padding: FIT_PADDING, maxZoom: MAX_FIT_ZOOM });
        return;
    }
    const c = props.center || VIETNAM;
    map.value.setView([c.lat, c.lng], c.zoom ?? VIETNAM.zoom);
}

/**
 * BUG-9 (WebKit/Safari): Leaflet gọi container.focus() khi mousedown rồi window.scrollTo() để giữ vị trí cuộn,
 * nhưng WebKit vẫn cuộn bản đồ vào khung nhìn sau đó, phần tử dưới con trỏ đổi giữa pointerdown (path) và
 * pointerup (div) nên lần bấm đầu không chọn được vùng. Lấy focus bằng preventScroll để trang không cuộn;
 * container vẫn nhận focus (tabindex của Leaflet) nên điều khiển bằng bàn phím giữ nguyên.
 */
function patchKeyboardFocus(leaflet) {
    const proto = leaflet?.Map?.Keyboard?.prototype;
    if (!proto || proto.dkaPreventScroll) return;
    proto._onMouseDown = function onMouseDown() {
        if (this._focused) return;
        this._map._container.focus({ preventScroll: true });
    };
    proto.dkaPreventScroll = true;
}

onMounted(async () => {
    try {
        // CSS nạp cùng JS để chunk vendor-leaflet không bị kéo vào bundle chung của mọi trang
        const [mod] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css')]);
        L = mod.default ?? mod;
        patchKeyboardFocus(L);
        map.value = L.map(el.value, { scrollWheelZoom: props.scrollZoom, zoomControl: true, attributionControl: true });
        // Đặt khung nhìn ban đầu trước khi vẽ: Leaflet hoãn thêm lớp (vd L.circle) khi bản đồ chưa có view
        const c = props.center || VIETNAM;
        map.value.setView([c.lat, c.lng], c.zoom ?? VIETNAM.zoom);
        map.value.on('click', (e) => emit('mapclick', { lat: e.latlng.lat, lng: e.latlng.lng }));
        // BUG-5: đặt khung nhìn cuối từ khung bao dữ liệu rồi mới thêm lớp tile → tile (phần tử LCP của trang bản đồ)
        // chỉ tải một lần đúng khung nhìn và được yêu cầu trước khi vẽ 60+ vùng; vẽ dời sang tác vụ sau để không
        // gộp thành một long task dài (giảm TBT)
        focusView(dataBounds());
        L.tileLayer(TILE_URL, { maxZoom: 18, attribution: TILE_ATTRIBUTION }).addTo(map.value);
        await new Promise((resolve) => window.setTimeout(resolve, 0));
        draw(false);
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
});

watch(() => [props.geojson, props.markers, props.overlays, props.route, props.userLocation, props.focus, props.selected], () => draw(), { deep: true });

/** Cho trang cha gọi lại khi khung chứa đổi kích thước (vd mở/đóng panel bên). */
function invalidate() {
    map.value?.invalidateSize();
}
defineExpose({ invalidate });

onBeforeUnmount(() => {
    map.value?.remove();
    map.value = null;
});
</script>

<template>
    <section :class="bare ? '' : 'clay-card p-3 md:p-4'" :aria-labelledby="ariaLabel ? undefined : `map-h-${uid}`" :aria-label="ariaLabel || undefined">
        <h2 v-if="!ariaLabel" :id="`map-h-${uid}`" class="sr-only">{{ t('map.title') }}</h2>
        <div class="map-shell relative overflow-hidden rounded-xl" :class="heightClass">
            <!-- Nền cục bộ hiển thị ngay khi trang gắn (trước Leaflet và tile); Leaflet trong suốt nên nó còn thấy tới khi tile phủ -->
            <img
                :src="PLACEHOLDER_SRC"
                alt=""
                aria-hidden="true"
                :width="PLACEHOLDER_SIZE"
                :height="PLACEHOLDER_SIZE"
                :loading="priority ? 'eager' : 'lazy'"
                :fetchpriority="priority ? 'high' : 'auto'"
                decoding="async"
                class="map-placeholder pointer-events-none absolute inset-0 h-full w-full object-cover"
            />
            <div ref="el" class="relative h-full w-full" :aria-label="t('map.canvas_label')" role="application"></div>
            <div v-if="loading" class="skeleton map-skeleton absolute inset-0" aria-hidden="true"></div>
            <div v-if="failed" class="absolute inset-0 flex items-center justify-center bg-soft p-4">
                <EmptyState variant="error" :title="t('map.load_error')" :description="t('map.load_error_hint')" compact />
            </div>
        </div>

        <ul v-if="showLegend" class="mt-3 flex flex-wrap gap-2" :aria-label="t('map.legend')">
            <li v-for="item in legendItems" :key="item.level" class="pill">
                <span class="h-3 w-3 rounded-full ring-2 ring-white/70" :class="DOTS[item.level]" aria-hidden="true"></span>
                {{ item.label }}
            </li>
        </ul>

        <!-- Bản tóm tắt dạng văn bản cho trình đọc màn hình (bản đồ canvas không đọc được) -->
        <div class="sr-only">
            <p>{{ t('map.summary', { n: summary.length }) }}</p>
            <ul>
                <li v-for="z in summary" :key="z.name">
                    {{ z.name }}<template v-if="z.level !== null">: {{ t(LEVELS[z.level].key) }}</template>
                </li>
            </ul>
        </div>
        <slot />
    </section>
</template>
