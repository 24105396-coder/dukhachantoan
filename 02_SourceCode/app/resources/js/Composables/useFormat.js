import { usePage } from '@inertiajs/vue3';
import { translate } from '@/Composables/useT';

const intlLocale = (locale) => (locale === 'en' ? 'en-GB' : 'vi-VN');
// Cảnh báo phát hành theo giờ Việt Nam: hiển thị cố định múi giờ này để du khách nước ngoài không nhầm
export const DISPLAY_TIME_ZONE = 'Asia/Ho_Chi_Minh';

const MINUTE = 60 * 1000;
const HOUR = 60 * MINUTE;
const DAY = 24 * HOUR;

/** Định dạng số, ngày giờ, dấu thời gian nguồn, khoảng cách, nhiệt độ theo locale. */
export function useFormat() {
    const page = usePage();
    const loc = () => page.props.locale || 'vi';
    const tr = (key, params) => translate(page.props.translations, key, params);

    const toDate = (iso) => {
        if (!iso) return null;
        const d = iso instanceof Date ? iso : new Date(iso);
        return Number.isNaN(d.getTime()) ? null : d;
    };

    const number = (value, digits = 0) => {
        if (value === null || value === undefined || value === '') return '';
        return new Intl.NumberFormat(intlLocale(loc()), { maximumFractionDigits: digits }).format(Number(value));
    };

    const percent = (value, digits = 0) => {
        if (value === null || value === undefined || value === '') return '';
        const n = Number(value);
        // Chấp nhận cả tỉ lệ 0–1 và phần trăm 0–100
        const ratio = n > 1 ? n / 100 : n;
        return new Intl.NumberFormat(intlLocale(loc()), { style: 'percent', maximumFractionDigits: digits }).format(ratio);
    };

    const date = (iso, opts = { day: '2-digit', month: '2-digit', year: 'numeric' }) => {
        const d = toDate(iso);
        return d ? new Intl.DateTimeFormat(intlLocale(loc()), { timeZone: DISPLAY_TIME_ZONE, ...opts }).format(d) : '';
    };

    const dateTime = (iso) => date(iso, { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false });
    const time = (iso) => date(iso, { hour: '2-digit', minute: '2-digit', hour12: false });
    const longDate = (iso) => date(iso, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const weekday = (iso) => date(iso, { weekday: 'short', day: '2-digit', month: '2-digit' });

    /** "hh:mm dd/mm" – định dạng bắt buộc của dòng "Nguồn · Cập nhật" trên thẻ cảnh báo. */
    const stamp = (iso) => {
        const d = toDate(iso);
        if (!d) return '';
        const parts = Object.fromEntries(
            new Intl.DateTimeFormat('en-GB', {
                timeZone: DISPLAY_TIME_ZONE, hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', hour12: false,
            }).formatToParts(d).map((p) => [p.type, p.value]),
        );
        return `${parts.hour}:${parts.minute} ${parts.day}/${parts.month}`;
    };

    /** Khoảng thời gian tương đối ngắn: "5 phút trước". */
    const ago = (iso) => {
        const d = toDate(iso);
        if (!d) return '';
        const diff = Date.now() - d.getTime();
        if (diff < MINUTE) return tr('time.just_now');
        if (diff < HOUR) return tr('time.minutes_ago', { n: Math.floor(diff / MINUTE) });
        if (diff < DAY) return tr('time.hours_ago', { n: Math.floor(diff / HOUR) });
        return tr('time.days_ago', { n: Math.floor(diff / DAY) });
    };

    const km = (value) => (value === null || value === undefined || value === '' ? '' : `${number(value, 1)} km`);
    const temp = (value) => (value === null || value === undefined || value === '' ? '—' : `${number(value, 0)}°C`);
    const mm = (value) => (value === null || value === undefined || value === '' ? '—' : `${number(value, 1)} mm`);
    const kph = (value) => (value === null || value === undefined || value === '' ? '—' : `${number(value, 0)} km/h`);

    const duration = (minutes) => {
        if (!minutes) return '';
        const h = Math.floor(minutes / 60);
        const m = minutes % 60;
        if (h && m) return tr('common.duration_hm', { h, m });
        if (h) return tr('common.duration_h', { h });
        return tr('common.duration_m', { m });
    };

    return { number, percent, date, dateTime, time, longDate, weekday, stamp, ago, km, temp, mm, kph, duration };
}
