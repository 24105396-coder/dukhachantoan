// Mức rủi ro/cảnh báo 0–4: icon + khóa nhãn; màu lấy từ token CSS --level-N (không hard-code)
export const LEVELS = {
    0: { icon: 'shield-check', key: 'enums.levels.0' },
    1: { icon: 'info', key: 'enums.levels.1' },
    2: { icon: 'eye', key: 'enums.levels.2' },
    3: { icon: 'triangle-alert', key: 'enums.levels.3' },
    4: { icon: 'siren', key: 'enums.levels.4' },
};

/** Chuẩn hóa về số nguyên 0–4; giá trị lạ hoặc thiếu → null. */
export function normalizeLevel(value) {
    if (value === null || value === undefined || value === '') return null;
    const n = Math.round(Number(value));
    if (!Number.isFinite(n)) return null;
    return Math.min(4, Math.max(0, n));
}

export function levelMeta(value) {
    const n = normalizeLevel(value);
    return n === null ? null : { level: n, ...LEVELS[n] };
}

/** Màu chính của mức, đọc từ biến CSS (dùng cho Leaflet/Chart.js vốn cần giá trị màu thật). */
export function levelColor(value) {
    const n = normalizeLevel(value) ?? 0;
    if (typeof document === 'undefined') return '';
    return getComputedStyle(document.documentElement).getPropertyValue(`--level-${n}`).trim();
}
