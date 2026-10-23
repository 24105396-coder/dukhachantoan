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

const KM_PER_DEG_LAT = 111.32;

/** Khung bao rỗng để cộng dồn tọa độ; `extendBox` nhận một điểm hoặc mảng tọa độ GeoJSON lồng nhau. */
export function emptyBox() {
    return { south: Infinity, west: Infinity, north: -Infinity, east: -Infinity };
}

function extendPoint(box, lat, lng) {
    if (!inLat(lat) || !inLng(lng)) return;
    const la = Number(lat);
    const ln = Number(lng);
    box.south = Math.min(box.south, la);
    box.north = Math.max(box.north, la);
    box.west = Math.min(box.west, ln);
    box.east = Math.max(box.east, ln);
}

function extendCoords(box, coords) {
    if (!Array.isArray(coords)) return;
    if (typeof coords[0] === 'number') {
        extendPoint(box, coords[1], coords[0]);
        return;
    }
    coords.forEach((c) => extendCoords(box, c));
}

/** Cộng dồn GeoJSON (geometry / Feature / FeatureCollection) vào khung bao. */
export function extendBoxGeoJson(box, data) {
    if (!data || typeof data !== 'object') return box;
    if (data.type === 'FeatureCollection') (data.features || []).forEach((f) => extendBoxGeoJson(box, f));
    else if (data.type === 'Feature') extendBoxGeoJson(box, data.geometry);
    else if (data.type === 'GeometryCollection') (data.geometries || []).forEach((g) => extendBoxGeoJson(box, g));
    else extendCoords(box, data.coordinates);
    return box;
}

/** Cộng dồn điểm { lat, lng }; nếu có radiusKm thì cộng cả hình vuông bao vòng tròn (xấp xỉ theo độ). */
export function extendBoxPoint(box, point, radiusKm = 0) {
    if (!point || !inLat(point.lat) || !inLng(point.lng)) return box;
    const lat = Number(point.lat);
    const lng = Number(point.lng);
    const dLat = radiusKm / KM_PER_DEG_LAT;
    const dLng = radiusKm / (KM_PER_DEG_LAT * Math.max(Math.cos((lat * Math.PI) / 180), 0.01));
    extendPoint(box, Math.max(-90, lat - dLat), Math.max(-180, lng - dLng));
    extendPoint(box, Math.min(90, lat + dLat), Math.min(180, lng + dLng));
    return box;
}

/** Khung bao hợp lệ → [[south, west], [north, east]] (dạng Leaflet nhận), ngược lại null. */
export function boxToBounds(box) {
    return Number.isFinite(box.south) && Number.isFinite(box.west) ? [[box.south, box.west], [box.north, box.east]] : null;
}

export { MAX_RADIUS_KM };
