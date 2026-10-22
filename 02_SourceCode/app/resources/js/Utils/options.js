// Chuẩn hóa danh sách từ backend thành options [{value,label}] cho <select>/checkbox

export const STATUS_CODES = ['draft', 'published', 'archived'];
export const LOCALES = ['vi', 'en'];
export const DEPTHS = [1, 2, 3];

/**
 * Enum: chấp nhận ['di_tich', ...], [{code,name}], [{value,label}] hoặc object {code: label}.
 * Khi chỉ có mã, nhãn lấy từ te(group, code).
 */
export function enumOptions(list, te, group) {
    if (!list) return [];
    const arr = Array.isArray(list) ? list : Object.entries(list).map(([code, name]) => ({ code, name }));
    return arr
        .map((item) => {
            if (item === null || item === undefined) return null;
            if (typeof item !== 'object') return { value: item, label: group ? te(group, item) : String(item) };
            const value = item.code ?? item.value ?? item.id;
            const label = item.name ?? item.label ?? (group ? te(group, value) : String(value));
            return { value, label };
        })
        .filter(Boolean);
}

/** Thực thể tham chiếu theo id: [{id, title|name}] → options; fallback code nếu thiếu id. */
export function entityOptions(list) {
    return (list || [])
        .filter(Boolean)
        .map((item) => ({ value: item.id ?? item.code, label: item.title ?? item.name ?? item.label ?? String(item.id) }));
}

/** Lấy mảng hàng từ prop có thể là mảng hoặc Paginated của Laravel. */
export function rowsOf(prop) {
    if (!prop) return [];
    if (Array.isArray(prop)) return prop;
    return prop.data || [];
}

/** Giá trị rỗng → null để backend validate nullable dễ hơn. */
export function nullIfEmpty(v) {
    return v === '' || v === undefined ? null : v;
}
