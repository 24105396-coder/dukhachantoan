// Tiện ích hình học phía giao diện: chuẩn hóa geometry cảnh báo (GeoJSON hoặc vòng tròn tâm + bán kính)
// để vẽ trên Leaflet và kiểm tra GeoJSON người quản trị dán vào. Máy chủ vẫn kiểm tra lại.

const GEOJSON_TYPES = ['Point', 'MultiPoint', 'LineString', 'MultiLineString', 'Polygon', 'MultiPolygon', 'GeometryCollection'];
const MAX_RADIUS_KM = 500;

const isNum = (v) => v !== null && v !== '' && Number.isFinite(Number(v));
const inLat = (v) => isNum(v) && Math.abs(Number(v)) <= 90;
const inLng = (v) => isNum(v) && Math.abs(Number(v)) <= 180;

/** Tâm vòng tròn: chấp nhận [lng, lat] (thứ tự GeoJSON) hoặc { lat, lng }. Trả { lat, lng } hoặc null. */
export function circleCenter(center) {
    if (Array.isArray(center) && center.length >= 2 && inLng(center[0]) && inLat(center[1])) {
        return { lat: Number(center[1]), lng: Number(center[0]) };
    }
    if (center && inLat(center.lat) && inLng(center.lng)) return { lat: Number(center.lat), lng: Number(center.lng) };
    return null;
}

/**
 * Chuẩn hóa geometry cảnh báo:
 * - { type: 'Circle', center, radius_km } → { kind: 'circle', center: {lat,lng}, radiusKm }
 * - GeoJSON geometry / Feature / FeatureCollection → { kind: 'geojson', data }
 * - còn lại → null
 */
export function normalizeGeometry(geometry) {
    if (!geometry || typeof geometry !== 'object') return null;
    if (geometry.type === 'Circle') {
        const center = circleCenter(geometry.center);
        const radiusKm = Number(geometry.radius_km);
        return center && radiusKm > 0 ? { kind: 'circle', center, radiusKm } : null;
    }
    if (geometry.type === 'Feature') return geometry.geometry ? normalizeGeometry(geometry.geometry) : null;
    if (geometry.type === 'FeatureCollection') {
        return Array.isArray(geometry.features) && geometry.features.length ? { kind: 'geojson', data: geometry } : null;
    }
    if (GEOJSON_TYPES.includes(geometry.type)) return { kind: 'geojson', data: geometry };
    return null;
}

/**
 * Phân tích chuỗi GeoJSON người dùng dán vào.
 * @returns {{ ok: true, geometry: object } | { ok: false, error: 'json'|'type'|'empty' }}
 */
export function parseGeoJson(text) {
    if (!text || !String(text).trim()) return { ok: false, error: 'empty' };
    let data;
    try {
        data = JSON.parse(text);
    } catch {
        return { ok: false, error: 'json' };
    }
    // Feature → lấy geometry; FeatureCollection một phần tử → lấy geometry của phần tử đó
    if (data?.type === 'Feature') data = data.geometry;
    if (data?.type === 'FeatureCollection' && data.features?.length === 1) data = data.features[0].geometry;
    if (!data || !['Polygon', 'MultiPolygon'].includes(data.type) || !Array.isArray(data.coordinates)) {
        return { ok: false, error: 'type' };
    }
    return { ok: true, geometry: data };
}

/** Vòng tròn hợp lệ cho form: tâm trong phạm vi tọa độ, bán kính 0 < r ≤ 500 km. */
export function circleGeometry(lat, lng, radiusKm) {
    if (!inLat(lat) || !inLng(lng) || !isNum(radiusKm)) return null;
    const r = Number(radiusKm);
    if (r <= 0 || r > MAX_RADIUS_KM) return null;
    return { type: 'Circle', center: [Number(lng), Number(lat)], radius_km: r };
}

export { MAX_RADIUS_KM };
