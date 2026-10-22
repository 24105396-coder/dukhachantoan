// Quốc tịch phổ biến của du khách đến Việt Nam (mã ISO 3166-1 alpha-2); tên hiển thị lấy từ Intl.DisplayNames
export const COMMON_NATIONALITIES = [
    'VN', 'CN', 'KR', 'JP', 'TW', 'US', 'AU', 'IN', 'TH', 'MY', 'SG', 'PH', 'ID', 'KH', 'LA',
    'GB', 'FR', 'DE', 'RU', 'CA', 'NL', 'IT', 'ES',
];

/** Options [{ value, label }] cho <select> quốc tịch, sắp theo tên trong ngôn ngữ giao diện (VN đứng đầu). */
export function nationalityOptions(locale) {
    let names = null;
    try {
        names = new Intl.DisplayNames([locale === 'en' ? 'en' : 'vi'], { type: 'region' });
    } catch {
        names = null;
    }
    const label = (code) => names?.of(code) || code;
    const [first, ...rest] = COMMON_NATIONALITIES;
    const sorted = rest.map((code) => ({ value: code, label: label(code) })).sort((a, b) => a.label.localeCompare(b.label, locale));
    return [{ value: first, label: label(first) }, ...sorted];
}
