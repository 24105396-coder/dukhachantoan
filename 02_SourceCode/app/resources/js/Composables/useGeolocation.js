import { ref } from 'vue';

const GEO_TIMEOUT_MS = 10000;
const GEO_MAX_AGE_MS = 60000;
const COORD_DIGITS = 6;

/**
 * Lấy vị trí hiện tại qua Geolocation API – chỉ khi người dùng bấm nút (trình duyệt hỏi quyền).
 * status: idle | locating | ok | denied | unavailable | unsupported
 * Không lưu vị trí ở đâu ngoài biến trả về; trang tự quyết định có gửi kèm biểu mẫu hay không.
 */
export function useGeolocation() {
    const coords = ref(null); // { lat, lng, accuracy }
    const status = ref('idle');

    const round = (v) => Number(Number(v).toFixed(COORD_DIGITS));

    function locate() {
        if (typeof navigator === 'undefined' || !navigator.geolocation) {
            status.value = 'unsupported';
            return Promise.resolve(null);
        }
        status.value = 'locating';
        return new Promise((resolve) => {
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    coords.value = { lat: round(pos.coords.latitude), lng: round(pos.coords.longitude), accuracy: Math.round(pos.coords.accuracy) };
                    status.value = 'ok';
                    resolve(coords.value);
                },
                (err) => {
                    status.value = err?.code === 1 ? 'denied' : 'unavailable';
                    resolve(null);
                },
                { enableHighAccuracy: true, timeout: GEO_TIMEOUT_MS, maximumAge: GEO_MAX_AGE_MS },
            );
        });
    }

    function clear() {
        coords.value = null;
        status.value = 'idle';
    }

    return { coords, status, locate, clear };
}
