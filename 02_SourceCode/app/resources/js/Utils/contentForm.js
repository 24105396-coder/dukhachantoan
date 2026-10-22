import { LOCALES, DEPTHS } from '@/Utils/options';

// Diễn giải luôn có đủ 6 ô cố định (vi/en × độ sâu 1–3) để chỉ số lỗi ổn định
export function interpretationIndex(locale, depth) {
    return LOCALES.indexOf(locale) * DEPTHS.length + (depth - 1);
}

export function buildInterpretations(existing) {
    const list = Array.isArray(existing) ? existing : [];
    return LOCALES.flatMap((locale) =>
        DEPTHS.map((depth) => {
            const found = list.find((x) => x.locale === locale && Number(x.depth_level) === depth) || {};
            const sources = Array.isArray(found.sources) ? found.sources.join('\n') : found.sources || '';
            return {
                locale,
                depth_level: depth,
                title: found.title || '',
                summary: found.summary || '',
                body: found.body || '',
                sources,
            };
        }),
    );
}

/** Chuyển sources (mỗi dòng một nguồn) thành mảng khi gửi. */
export function serializeInterpretations(list) {
    return (list || []).map((x) => ({
        ...x,
        sources: String(x.sources || '')
            .split('\n')
            .map((s) => s.trim())
            .filter(Boolean),
    }));
}

export function isFilled(item) {
    return !!(item && (item.title || '').trim() && (item.body || item.summary || '').trim());
}

/** Lỗi đầu tiên của nhóm khóa có tiền tố (vd 'interpretations.3.'). */
export function hasErrorWithPrefix(errors, prefix) {
    return Object.keys(errors || {}).some((k) => k.startsWith(prefix));
}
