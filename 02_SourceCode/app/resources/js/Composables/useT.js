import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Tra một khóa trong bảng dịch: hỗ trợ khóa phẳng ("nav.sites") của lang/*.json
 * và cả cấu trúc lồng nhau nếu backend trả về dạng object.
 */
function lookup(dict, key) {
    if (!dict) return undefined;
    if (typeof dict[key] === 'string') return dict[key];
    return key.split('.').reduce((acc, part) => (acc && typeof acc === 'object' ? acc[part] : undefined), dict);
}

/** Thay tham số kiểu Laravel `:name` (giữ nguyên kiểu chữ hoa :Name, :NAME). */
function replaceParams(text, params) {
    if (!params) return text;
    return Object.keys(params)
        .sort((a, b) => b.length - a.length)
        .reduce((out, k) => {
            const v = String(params[k] ?? '');
            return out
                .replaceAll(`:${k.toUpperCase()}`, v.toUpperCase())
                .replaceAll(`:${k.charAt(0).toUpperCase()}${k.slice(1)}`, v.charAt(0).toUpperCase() + v.slice(1))
                .replaceAll(`:${k}`, v);
        }, text);
}

export function translate(dict, key, params) {
    const found = lookup(dict, key);
    return replaceParams(typeof found === 'string' ? found : key, params);
}

export function useT() {
    const page = usePage();
    const dict = computed(() => page.props.translations || {});
    const locale = computed(() => page.props.locale || 'vi');

    const t = (key, params) => translate(dict.value, key, params);
    /** Nhãn enum: te('site_type', 'di_tich') → t('enums.site_type.di_tich'), fallback mã gốc. */
    const te = (group, code) => {
        if (code === null || code === undefined || code === '') return '';
        const key = `enums.${group}.${code}`;
        const found = lookup(dict.value, key);
        return typeof found === 'string' ? found : String(code);
    };
    const has = (key) => typeof lookup(dict.value, key) === 'string';

    return { t, te, has, locale };
}

export const i18nPlugin = {
    install(app) {
        app.config.globalProperties.$t = function (key, params) {
            return translate(this.$page?.props?.translations, key, params);
        };
        app.config.globalProperties.$te = function (group, code) {
            if (code === null || code === undefined || code === '') return '';
            const found = lookup(this.$page?.props?.translations, `enums.${group}.${code}`);
            return typeof found === 'string' ? found : String(code);
        };
    },
};
