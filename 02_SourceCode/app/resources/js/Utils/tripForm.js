// Dựng dữ liệu biểu mẫu hành trình và kiểm tra chặng phía máy khách (máy chủ vẫn kiểm bằng FormRequest).
import { DISPLAY_TIME_ZONE } from '@/Composables/useFormat';
import { PHONE_RE, stripPhone } from '@/Utils/rules';

export const AGE_GROUPS = ['adult', 'child', 'senior'];
export const MAX_LEGS = 30;
export const MAX_MEMBERS = 50;

/** ISO có múi giờ → 'YYYY-MM-DDTHH:mm' theo giờ Việt Nam (giá trị cho input datetime-local). */
export function toLocalInput(iso) {
    if (!iso) return '';
    if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(iso)) return iso;
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    const p = Object.fromEntries(
        new Intl.DateTimeFormat('en-GB', {
            timeZone: DISPLAY_TIME_ZONE, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false,
        }).formatToParts(d).map((x) => [x.type, x.value]),
    );
    const hour = p.hour === '24' ? '00' : p.hour;
    return `${p.year}-${p.month}-${p.day}T${hour}:${p.minute}`;
}

/** ISO/ngày → 'YYYY-MM-DD'. */
export const toDateInput = (v) => (v ? String(v).slice(0, 10) : '');

export function emptyLeg(defaultStart = '') {
    return { zone_code: '', destination_slug: '', starts_at: defaultStart ? `${defaultStart}T08:00` : '', ends_at: defaultStart ? `${defaultStart}T18:00` : '', lodging: '', transport: '', note: '' };
}

export function emptyMember() {
    return { id: null, full_name: '', phone: '', nationality: '', age_group: 'adult', special_needs: '' };
}

/** Giá trị khởi tạo cho useForm từ `defaults` (tạo mới) hoặc TripDetail (sửa). */
export function initialTripForm(trip, defaults = {}) {
    const src = trip || defaults || {};
    return {
        title: src.title || '',
        start_date: toDateInput(src.start_date),
        end_date: toDateInput(src.end_date),
        group_size: src.group_size ?? 1,
        nationality: src.nationality || '',
        preferred_locale: src.preferred_locale || 'vi',
        contact_name: src.contact_name || '',
        contact_phone: src.contact_phone || '',
        contact_email: src.contact_email || '',
        legs: (trip?.legs || []).length
            ? [...trip.legs].sort((a, b) => a.leg_order - b.leg_order).map((l) => ({
                zone_code: l.zone?.code || '',
                destination_slug: l.destination?.slug || '',
                starts_at: toLocalInput(l.starts_at),
                ends_at: toLocalInput(l.ends_at),
                lodging: l.lodging || '',
                transport: l.transport || '',
                note: l.note || '',
            }))
            : [emptyLeg(toDateInput(src.start_date))],
        members: (trip?.members || []).map((m) => ({
            id: m.id ?? null,
            full_name: m.full_name || '',
            phone: '',
            phone_masked: m.phone_masked || '',
            nationality: m.nationality || '',
            age_group: m.age_group || 'adult',
            special_needs: m.special_needs || '',
        })),
    };
}

/**
 * Kiểm tra chặng: ≥ 1 chặng; mỗi chặng có vùng, giờ bắt đầu < giờ kết thúc, nằm trong khoảng ngày hành trình;
 * các chặng không chồng thời gian. Trả { 'legs': msg, 'legs.0.zone_code': msg, … } (khóa giống lỗi máy chủ).
 */
export function validateLegs(form, t) {
    const errors = {};
    const legs = form.legs || [];
    if (!legs.length) {
        errors.legs = t('trip_form.err_no_legs');
        return errors;
    }
    legs.forEach((leg, i) => {
        const key = (f) => `legs.${i}.${f}`;
        if (!leg.zone_code) errors[key('zone_code')] = t('validation.client.required', { field: t('trip_form.zone') });
        if (!leg.starts_at) errors[key('starts_at')] = t('validation.client.required', { field: t('trip_form.starts_at') });
        if (!leg.ends_at) errors[key('ends_at')] = t('validation.client.required', { field: t('trip_form.ends_at') });
        if (leg.starts_at && leg.ends_at && leg.ends_at <= leg.starts_at) errors[key('ends_at')] = t('trip_form.err_end_before_start');
        if (form.start_date && leg.starts_at && leg.starts_at.slice(0, 10) < form.start_date) errors[key('starts_at')] = t('trip_form.err_out_of_range');
        if (form.end_date && leg.ends_at && leg.ends_at.slice(0, 10) > form.end_date) errors[key('ends_at')] = t('trip_form.err_out_of_range');
    });
    // Chồng thời gian: so từng cặp (chuỗi 'YYYY-MM-DDTHH:mm' so sánh được theo thứ tự từ điển)
    legs.forEach((a, i) => {
        legs.slice(i + 1).forEach((b, k) => {
            const j = i + 1 + k;
            if (a.starts_at && a.ends_at && b.starts_at && b.ends_at && a.starts_at < b.ends_at && b.starts_at < a.ends_at) {
                errors[`legs.${j}.starts_at`] ??= t('trip_form.err_overlap', { n: i + 1 });
            }
        });
    });
    return errors;
}

/** Kiểm tra thành viên: họ tên bắt buộc; số điện thoại (nếu nhập) 9–15 chữ số. */
export function validateMembers(form, t) {
    const errors = {};
    (form.members || []).forEach((m, i) => {
        if (!m.full_name?.trim()) errors[`members.${i}.full_name`] = t('validation.client.required', { field: t('trip_form.member_name') });
        if (m.phone && !PHONE_RE.test(stripPhone(m.phone))) errors[`members.${i}.phone`] = t('validation.client.phone', { field: t('common.phone') });
    });
    return errors;
}

/** Bước (0,1,2) chứa lỗi đầu tiên – để mở đúng bước khi máy chủ trả lỗi. */
export function stepOfError(keys) {
    if (keys.some((k) => !k.startsWith('legs') && !k.startsWith('members'))) return 0;
    if (keys.some((k) => k.startsWith('legs'))) return 1;
    if (keys.some((k) => k.startsWith('members'))) return 2;
    return null;
}

/** Dữ liệu gửi đi: bỏ trường rỗng tùy chọn, bỏ phone_masked (chỉ để hiển thị). */
export function tripPayload(data) {
    const blankToNull = (v) => (v === '' || v === undefined ? null : v);
    return {
        ...data,
        nationality: blankToNull(data.nationality),
        contact_email: blankToNull(data.contact_email),
        legs: data.legs.map((l) => ({ ...l, destination_slug: blankToNull(l.destination_slug), lodging: blankToNull(l.lodging), transport: blankToNull(l.transport), note: blankToNull(l.note) })),
        members: data.members.map(({ phone_masked: _masked, ...m }) => ({ ...m, phone: blankToNull(m.phone ? stripPhone(m.phone) : ''), nationality: blankToNull(m.nationality), special_needs: blankToNull(m.special_needs) })),
    };
}
