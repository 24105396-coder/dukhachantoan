import { nextTick, watch } from 'vue';
import { useT } from '@/Composables/useT';

// Kiểm tra phía máy khách trước khi gửi (máy chủ vẫn kiểm lại bằng FormRequest).
// Form giữ `novalidate` vì thông báo mặc định của trình duyệt không theo ngôn ngữ giao diện;
// lỗi được gán vào form.errors để hiển thị y như lỗi máy chủ.

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const DEFAULT_PROTOCOLS = ['http:', 'https:'];

const resolve = (v) => (typeof v === 'function' ? v() : v);

function getPath(obj, path) {
    return path.split('.').reduce((acc, key) => (acc === null || acc === undefined ? undefined : acc[key]), obj);
}

function isBlank(v) {
    if (v === null || v === undefined) return true;
    if (typeof v === 'string') return v.trim() === '';
    if (Array.isArray(v)) return v.length === 0;
    return false;
}

function isValidUrl(text, protocols) {
    try {
        return protocols.includes(new URL(text).protocol);
    } catch {
        return false;
    }
}

/** Trả về [khóa thông báo, tham số] của luật đầu tiên bị vi phạm, hoặc null. */
function firstViolation(value, rule, form) {
    if (isBlank(value)) return resolve(rule.required) ? ['required', {}] : null;
    const text = typeof value === 'string' ? value.trim() : value;
    if (rule.email && !EMAIL_RE.test(String(text))) return ['email', {}];
    if (rule.url) {
        const protocols = Array.isArray(rule.url) ? rule.url : DEFAULT_PROTOCOLS;
        if (!isValidUrl(String(text), protocols)) return ['url', {}];
    }
    if (rule.number) {
        const min = resolve(rule.number.min);
        const max = resolve(rule.number.max);
        const n = Number(text);
        const bad = !Number.isFinite(n) || (rule.number.integer && !Number.isInteger(n)) || n < min || n > max;
        if (bad) return ['number', { min, max }];
    }
    if (rule.min !== undefined && String(text).length < rule.min) return ['min', { min: rule.min }];
    if (rule.max !== undefined && String(text).length > rule.max) return ['max', { max: rule.max }];
    if (rule.pattern) {
        const normalized = rule.pattern.normalize ? rule.pattern.normalize(String(text)) : String(text);
        if (!rule.pattern.regex.test(normalized)) return [rule.pattern.message, {}];
    }
    if (rule.confirmed && value !== getPath(form, rule.confirmed)) return ['confirmed', {}];
    return null;
}

/**
 * Gắn kiểm tra phía máy khách cho một useForm của Inertia.
 *
 * rules: { [field]: {
 *   label: khóa i18n | () => string,
 *   required?: boolean | () => boolean,
 *   email?: true, url?: true | ['https:'],
 *   min?: số ký tự tối thiểu, max?: số ký tự tối đa,
 *   number?: { min, max, integer? }   (min/max có thể là hàm),
 *   confirmed?: 'tên trường gốc',
 *   pattern?: { regex, message: 'phone'|'password'|'slug', normalize?: (s) => s },
 * } }
 * Trường lồng nhau dùng đường dẫn chấm, ví dụ 'interpretations.0.title'.
 */
export function useClientValidation(form, rules) {
    const { t } = useT();
    // Chỉ xóa lỗi do phía máy khách gán; lỗi máy chủ giữ nguyên tới lần gửi sau
    const clientErrors = new Map();

    const labelOf = (rule) => {
        const raw = resolve(rule.label);
        return typeof rule.label === 'function' ? raw : t(raw);
    };

    const messageFor = (field) => {
        const rule = rules[field];
        const violation = firstViolation(getPath(form, field), rule, form);
        if (!violation) return null;
        const [key, params] = violation;
        return t(`validation.client.${key}`, { field: labelOf(rule), ...params });
    };

    Object.entries(rules).forEach(([field, rule]) => {
        const sources = [() => getPath(form, field)];
        if (rule.confirmed) sources.push(() => getPath(form, rule.confirmed));
        // deep: 1 để mảng (vd stops) báo thay đổi khi thêm/bớt phần tử
        watch(sources, () => {
            const msg = clientErrors.get(field);
            if (msg === undefined) return;
            clientErrors.delete(field);
            if (form.errors[field] === msg) form.clearErrors(field);
        }, { deep: 1 });
    });

    /**
     * Kiểm tra toàn bộ form; gán lỗi và đưa tiêu điểm tới trường lỗi đầu tiên.
     * @param {Event} [event] sự kiện submit, dùng để giới hạn phạm vi tìm trường lỗi
     * @returns {boolean} true nếu hợp lệ và có thể gửi
     */
    const validate = (event) => {
        form.clearErrors();
        clientErrors.clear();
        const found = {};
        Object.keys(rules).forEach((field) => {
            const msg = messageFor(field);
            if (msg) found[field] = msg;
        });
        const fields = Object.keys(found);
        if (!fields.length) return true;
        fields.forEach((f) => clientErrors.set(f, found[f]));
        form.setError(found);
        const root = event?.target instanceof HTMLElement ? event.target : document;
        nextTick(() => root.querySelector('[aria-invalid="true"]')?.focus());
        return false;
    };

    return { validate };
}
