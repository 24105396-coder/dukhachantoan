import { usePage } from '@inertiajs/vue3';
import { translate } from '@/Composables/useT';

const intlLocale = (locale) => (locale === 'en' ? 'en-GB' : 'vi-VN');

/** Định dạng tiền VND, ngày giờ theo locale và năm âm (TCN/BCE). */
export function useFormat() {
    const page = usePage();
    const loc = () => page.props.locale || 'vi';
    const tr = (key, params) => translate(page.props.translations, key, params);

    const money = (value) => {
        if (value === null || value === undefined || value === '') return '';
        const n = Number(value);
        if (n === 0) return tr('common.free');
        return new Intl.NumberFormat(intlLocale(loc()), {
            style: 'currency',
            currency: 'VND',
            maximumFractionDigits: 0,
        }).format(n);
    };

    const number = (value, digits = 0) => {
        if (value === null || value === undefined || value === '') return '';
        return new Intl.NumberFormat(intlLocale(loc()), { maximumFractionDigits: digits }).format(Number(value));
    };

    const toDate = (iso) => {
        if (!iso) return null;
        const d = new Date(iso);
        return Number.isNaN(d.getTime()) ? null : d;
    };

    const date = (iso, opts = { day: '2-digit', month: '2-digit', year: 'numeric' }) => {
        const d = toDate(iso);
        return d ? new Intl.DateTimeFormat(intlLocale(loc()), opts).format(d) : '';
    };

    const dateTime = (iso) =>
        date(iso, { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });

    const time = (iso) => date(iso, { hour: '2-digit', minute: '2-digit' });

    const longDate = (iso) => date(iso, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

    const year = (y) => {
        if (y === null || y === undefined || y === '') return '';
        const n = Number(y);
        if (n < 0) return `${Math.abs(n)} ${tr('common.bce')}`;
        return String(n);
    };

    const yearRange = (from, to) => {
        if (from == null && to == null) return '';
        if (to == null || to === from) return year(from ?? to);
        if (from == null) return `– ${year(to)}`;
        return `${year(from)} – ${year(to)}`;
    };

    const duration = (minutes) => {
        if (!minutes) return '';
        const h = Math.floor(minutes / 60);
        const m = minutes % 60;
        if (h && m) return tr('common.duration_hm', { h, m });
        if (h) return tr('common.duration_h', { h });
        return tr('common.duration_m', { m });
    };

    return { money, number, date, dateTime, time, longDate, year, yearRange, duration };
}
