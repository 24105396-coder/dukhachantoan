import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Nguồn bản đồ nền dùng chung cho MapView, trang bản đồ (preconnect), nút chỉ đường và chân trang.
 * Backend chia sẻ `map.provider` (google | osm) từ env MAP_PROVIDER; mặc định google theo yêu cầu giảng viên.
 */
const PROVIDERS = {
    google: {
        // lyrs=m: bản đồ đường; hl: ngôn ngữ nhãn theo locale hiện tại
        url: 'https://mt{s}.google.com/vt/lyrs=m&hl={hl}&x={x}&y={y}&z={z}',
        subdomains: ['0', '1', '2', '3'],
        maxZoom: 20,
        attribution: '&copy; <a href="https://www.google.com/intl/vi/help/terms_maps/" rel="noopener noreferrer" target="_blank">Google Maps</a>',
        preconnect: 'https://mt0.google.com',
        creditKey: 'footer.map_credit_google',
        directions: (lat, lng) => `https://www.google.com/maps/dir/?api=1&destination=${lat}%2C${lng}`,
    },
    osm: {
        // Một máy chủ tile duy nhất (khuyến nghị của OSM) để preconnect có tác dụng
        url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        subdomains: [],
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" rel="noopener noreferrer" target="_blank">OpenStreetMap</a>',
        preconnect: 'https://tile.openstreetmap.org',
        creditKey: 'footer.map_credit',
        directions: (lat, lng) => `https://www.openstreetmap.org/directions?to=${lat}%2C${lng}`,
    },
};

export function useMapProvider() {
    const page = usePage();
    const provider = computed(() => (page.props.map?.provider in PROVIDERS ? page.props.map.provider : 'google'));
    const config = computed(() => PROVIDERS[provider.value]);
    const locale = computed(() => page.props.locale || 'vi');

    /** Tham số cho L.tileLayer(url, options). */
    const tileLayer = computed(() => ({
        url: config.value.url,
        options: {
            subdomains: config.value.subdomains,
            maxZoom: config.value.maxZoom,
            attribution: config.value.attribution,
            hl: locale.value,
        },
    }));

    return {
        provider,
        tileLayer,
        preconnect: computed(() => config.value.preconnect),
        creditKey: computed(() => config.value.creditKey),
        directionsUrl: (lat, lng) => config.value.directions(encodeURIComponent(lat), encodeURIComponent(lng)),
    };
}
