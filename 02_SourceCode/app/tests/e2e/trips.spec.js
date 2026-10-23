// F1 – Đăng ký hành trình: wizard 3 bước (giao diện), đoàn khách của đơn vị lữ hành, sửa, hủy, iCal; hợp lệ dữ liệu hai phía.
import { test, expect } from '@playwright/test';
import { authFile, writerFile, shot, actual, send, createTrip, tripDetail, getPage, localDate, showText } from './helpers.js';

const D1 = localDate(3);
const D2 = localDate(4);

test.describe('Du khách – hành trình (tài khoản ghi theo trình duyệt)', () => {
    test.use({ storageState: async ({}, use, info) => use(writerFile(info)) });

    test('TC-TRIP-001 wizard 3 bước tạo hành trình Đà Nẵng qua giao diện', async ({ page }, info) => {
        await page.goto('/vi/hanh-trinh/tao');
        await page.getByLabel('Tên hành trình').fill(`E2E wizard ${info.project.name}`);
        await page.getByLabel('Ngày đi').fill(D1);
        await page.getByLabel('Ngày về').fill(D2);
        await page.getByLabel('Họ tên người liên hệ').fill('Du khách E2E');
        await page.getByLabel('Điện thoại người liên hệ').fill('0901234567');
        await page.getByRole('button', { name: 'Sau', exact: true }).click();
        await expect(page.locator('#step-legs')).toBeVisible();
        await page.getByLabel(/^Vùng/).first().selectOption('da-nang');
        await page.getByLabel(/^Bắt đầu/).first().fill(`${D1}T09:00`);
        await page.getByLabel(/^Kết thúc/).first().fill(`${D1}T20:00`);
        await page.getByRole('button', { name: 'Sau', exact: true }).click();
        await expect(page.locator('#step-members')).toBeVisible();
        await Promise.all([
            page.waitForURL(/\/hanh-trinh\/TR-[A-Z0-9]+$/, { timeout: 20_000 }),
            page.getByRole('button', { name: 'Tạo hành trình' }).click(),
        ]);
        const flash = (await page.locator('[role=status], [role=alert]').allInnerTexts()).join(' | ').slice(0, 160);
        const code = page.url().split('/').pop();
        const { trip } = await tripDetail(page.request, code);
        await shot(page, 'TC-TRIP-001', info);
        actual(info, `Tạo ${code} (${info.project.name}); flash "${flash.trim()}"; status=${trip.status}, legs=${trip.legs.length} (${trip.legs[0].zone.name}), share_url có token ${trip.share_url ? 'có' : 'không'}`);
        expect(trip.legs).toHaveLength(1);
        expect(trip.legs[0].zone.code).toBe('da-nang');
    });

    test('TC-TRIP-003 sửa hành trình: đổi tên, thêm chặng thứ 2', async ({ page, request }, info) => {
        const c = await createTrip(request, { title: 'E2E sửa – gốc', start: D1, end: D2, legs: [{ zone_code: 'hue', starts_at: `${D1}T08:00`, ends_at: `${D1}T18:00` }] });
        expect(c.code).toBeTruthy();
        const { trip } = await tripDetail(request, c.code);
        const legs = [
            { id: trip.legs[0].id, zone_code: 'hue', starts_at: `${D1}T08:00`, ends_at: `${D1}T18:00` },
            { zone_code: 'pho-co-hoi-an', starts_at: `${D2}T08:00`, ends_at: `${D2}T17:00` },
        ];
        const u = await send(request, 'PUT', `/vi/hanh-trinh/${c.code}/sua`, { ...trip, title: 'E2E sửa – đã đổi', start_date: D1, end_date: D2, legs, members: [] }, { headers: { 'X-Inertia': 'true' } });
        const after = (await tripDetail(request, c.code)).trip;
        await page.goto(`/vi/hanh-trinh/${c.code}`);
        await shot(page, 'TC-TRIP-003', info);
        actual(info, `${c.code}: PUT /sua → ${u.status} ${u.location.replace(/^https?:\/\/[^/]+/, '')}; tiêu đề "${after.title}", ${after.legs.length} chặng (${after.legs.map((l) => l.zone.code).join(' → ')})`);
        expect(after.title).toBe('E2E sửa – đã đổi');
        expect(after.legs).toHaveLength(2);
    });

    test('TC-TRIP-004 hủy hành trình → trạng thái cancelled, thông báo đang xếp hàng bị hủy', async ({ page, request }, info) => {
        const c = await createTrip(request, { title: 'E2E hủy', start: D1, end: D2, legs: [{ zone_code: 'ha-noi', starts_at: `${D1}T08:00`, ends_at: `${D1}T18:00` }] });
        const before = (await tripDetail(request, c.code)).trip;
        const r = await send(request, 'POST', `/vi/hanh-trinh/${c.code}/huy`, {}, { headers: { 'X-Inertia': 'true', Referer: `/vi/hanh-trinh/${c.code}` } });
        const after = (await tripDetail(request, c.code)).trip;
        const queued = after.notifications.filter((n) => n.status === 'queued').length;
        const edit = await request.get(`/vi/hanh-trinh/${c.code}/sua`, { maxRedirects: 0 });
        await page.goto(`/vi/hanh-trinh/${c.code}`);
        await shot(page, 'TC-TRIP-004', info);
        actual(info, `${c.code}: trước ${before.status} (${before.notifications.length} thông báo); POST /huy → ${r.status}; sau: status=${after.status}, can_edit=${after.can_edit}, queued còn ${queued}, trạng thái thông báo: ${[...new Set(after.notifications.map((n) => n.status))].join(',') || '(không có)'}; GET /sua → ${edit.status()}`);
        expect(after.status).toBe('cancelled');
        expect(queued).toBe(0);
        expect(edit.status()).toBe(403);
    });

});

test.describe('Du khách – dữ liệu mẫu của traveler@', () => {
    test.use({ storageState: authFile('traveler') });

    test('TC-TRIP-005 xuất iCalendar các chặng (lich.ics)', async ({ page, request }, info) => {
        const res = await request.get('/vi/hanh-trinh/TR-SEED-01/lich.ics');
        const body = await res.text();
        const events = (body.match(/BEGIN:VEVENT/g) || []).length;
        const longest = Math.max(...body.split('\r\n').map((l) => Buffer.byteLength(l)));
        await showText(page, 'TC-TRIP-005 GET /vi/hanh-trinh/TR-SEED-01/lich.ics', `${res.status()} ${res.headers()['content-type']}\n${res.headers()['content-disposition']}\n\n${body.slice(0, 1800)}`);
        await shot(page, 'TC-TRIP-005', info);
        actual(info, `${res.status()} ${res.headers()['content-type']}; ${events} VEVENT; CRLF ${body.includes('\r\n') ? 'có' : 'không'}; dòng dài nhất ${longest} octet; ${/^BEGIN:VCALENDAR/.test(body) && /END:VCALENDAR\s*$/.test(body) ? 'VCALENDAR đầy đủ' : 'thiếu VCALENDAR'}`);
        expect(res.status()).toBe(200);
        expect(res.headers()['content-type']).toContain('text/calendar');
        expect(events).toBe(4);
        expect(longest).toBeLessThanOrEqual(75);
    });

    test('TC-TRIP-006 danh sách Hành trình của tôi: lọc trạng thái và từ khóa', async ({ page, request }, info) => {
        const all = (await getPage(request, '/vi/hanh-trinh')).page.props.trips;
        const act = (await getPage(request, '/vi/hanh-trinh?status=completed')).page.props.trips.data;
        const q = (await getPage(request, '/vi/hanh-trinh?q=Sa%20Pa')).page.props.trips.data;
        await page.goto('/vi/hanh-trinh?status=completed');
        await shot(page, 'TC-TRIP-006', info);
        actual(info, `Tổng ${(all.total ?? all.meta?.total) ?? all.data.length} hành trình; status=completed → ${act.map((t) => `${t.code}:${t.status}`).join(', ')}; q="Sa Pa" → ${q.map((t) => t.title).join(' | ')}`);
        expect(act.length).toBeGreaterThan(0);
        expect(act.every((t) => t.status === 'completed')).toBeTruthy();
        expect(q.every((t) => /sa pa/i.test(t.title))).toBeTruthy();
    });
});

test.describe('Đơn vị lữ hành – đoàn khách', () => {
    test.use({ storageState: authFile('partner') });

    test('TC-TRIP-002 partner đăng ký hành trình cho đoàn 3 thành viên; SĐT thành viên được che', async ({ page, request }, info) => {
        const members = [
            { full_name: 'Nguyễn Văn Trưởng', phone: '0912345678', nationality: 'VN', age_group: 'adult', is_leader: true },
            { full_name: 'John Smith', phone: '+447700900123', nationality: 'GB', age_group: 'senior', special_needs: 'Đi lại chậm' },
            { full_name: 'Lê Thị Bé', nationality: 'VN', age_group: 'child' },
        ];
        const c = await createTrip(request, { title: 'E2E đoàn 3 khách Hội An', start: D1, end: D2, legs: [{ zone_code: 'pho-co-hoi-an', starts_at: `${D1}T08:00`, ends_at: `${D2}T12:00` }], extra: { group_size: 3, members } });
        expect(c.code, JSON.stringify(c.body).slice(0, 300)).toBeTruthy();
        const { trip } = await tripDetail(request, c.code);
        await page.goto(`/vi/hanh-trinh/${c.code}`);
        await shot(page, 'TC-TRIP-002', info);
        actual(info, `${c.code}: partner=${trip.partner?.name}, owner=${trip.owner?.name}, ${trip.members.length} thành viên, SĐT hiển thị: ${trip.members.map((m) => m.phone_masked ?? '—').join(', ')}`);
        expect(trip.partner?.name).toBeTruthy();
        expect(trip.members).toHaveLength(3);
        expect(trip.members[0].phone_masked).not.toContain('2345');
    });
});

test.describe('Hợp lệ dữ liệu hành trình', () => {
    test.use({ storageState: async ({}, use, info) => use(writerFile(info)) });

    test('TC-VALID-001 [máy khách] bước 1 bỏ trống trường bắt buộc → không sang bước 2, không gửi yêu cầu', async ({ page }, info) => {
        await page.goto('/vi/hanh-trinh/tao');
        let posted = 0;
        page.on('request', (r) => r.method() === 'POST' && r.url().includes('/hanh-trinh/tao') && posted++);
        await page.getByRole('button', { name: 'Sau', exact: true }).click();
        await page.waitForTimeout(300);
        const invalid = await page.locator('[aria-invalid=true]').count();
        const msgs = (await page.locator('.field-error').allInnerTexts()).slice(0, 4);
        await shot(page, 'TC-VALID-001', info);
        actual(info, `${invalid} ô aria-invalid; bước 2 hiển thị: ${await page.locator('#step-legs').isVisible()}; lỗi: ${msgs.join(' | ')}; số POST gửi đi: ${posted}`);
        expect(invalid).toBeGreaterThanOrEqual(3);
        expect(await page.locator('#step-legs').isVisible()).toBeFalsy();
        expect(posted).toBe(0);
    });

    test('TC-VALID-002 [máy khách] ngày về trước ngày đi; chặng chồng giờ ở bước 2', async ({ page }, info) => {
        await page.goto('/vi/hanh-trinh/tao');
        await page.getByLabel('Tên hành trình').fill('E2E ngày sai');
        await page.getByLabel('Ngày đi').fill(D2);
        await page.getByLabel('Ngày về').fill(D1);
        await page.getByLabel('Họ tên người liên hệ').fill('QA');
        await page.getByLabel('Điện thoại người liên hệ').fill('0901234567');
        await page.getByRole('button', { name: 'Sau', exact: true }).click();
        const e1 = (await page.locator('.field-error').allInnerTexts()).join(' | ');
        await page.getByLabel('Ngày đi').fill(D1);
        await page.getByLabel('Ngày về').fill(D2);
        await page.getByRole('button', { name: 'Sau', exact: true }).click();
        await page.getByLabel(/^Vùng/).first().selectOption('hue');
        await page.getByLabel(/^Bắt đầu/).first().fill(`${D1}T08:00`);
        await page.getByLabel(/^Kết thúc/).first().fill(`${D1}T18:00`);
        await page.getByRole('button', { name: 'Thêm chặng' }).click();
        await page.getByLabel(/^Vùng/).nth(1).selectOption('pho-co-hoi-an');
        await page.getByLabel(/^Bắt đầu/).nth(1).fill(`${D1}T12:00`);
        await page.getByLabel(/^Kết thúc/).nth(1).fill(`${D1}T20:00`);
        await page.getByRole('button', { name: 'Sau', exact: true }).click();
        await page.waitForTimeout(300);
        const e2 = (await page.locator('.field-error').allInnerTexts()).join(' | ');
        await shot(page, 'TC-VALID-002', info);
        actual(info, `Ngày về < ngày đi: "${e1}"; 2 chặng chồng 12:00–18:00: "${e2}"; vẫn ở bước 2: ${await page.locator('#step-legs').isVisible()}`);
        expect(e1).toContain('Ngày về phải bằng hoặc sau ngày đi');
        expect(e2).toContain('chồng thời gian');
    });

    test('TC-VALID-003 [máy chủ] chặng chồng thời gian / ngoài khoảng ngày / kết thúc trước bắt đầu → 422', async ({ page, request }, info) => {
        const base = { title: 'E2E 422', start: D1, end: D2 };
        const overlap = await createTrip(request, { ...base, legs: [{ zone_code: 'hue', starts_at: `${D1}T08:00`, ends_at: `${D1}T18:00` }, { zone_code: 'pho-co-hoi-an', starts_at: `${D1}T12:00`, ends_at: `${D1}T20:00` }] });
        const outside = await createTrip(request, { ...base, legs: [{ zone_code: 'hue', starts_at: `${localDate(8)}T08:00`, ends_at: `${localDate(8)}T18:00` }] });
        const reversed = await createTrip(request, { ...base, legs: [{ zone_code: 'hue', starts_at: `${D1}T18:00`, ends_at: `${D1}T08:00` }] });
        const txt = [overlap, outside, reversed].map((r) => `${r.status} ${JSON.stringify(r.body.errors)}`).join('\n');
        await showText(page, 'TC-VALID-003 POST /vi/hanh-trinh/tao (Accept: application/json)', txt);
        await shot(page, 'TC-VALID-003', info);
        actual(info, txt.replace(/\n/g, ' ; '));
        expect(overlap.status).toBe(422);
        expect(overlap.body.errors['legs.1.starts_at']).toBeTruthy();
        expect(outside.status).toBe(422);
        expect(reversed.status).toBe(422);
        expect(reversed.body.errors['legs.0.ends_at']).toBeTruthy();
    });

    test('TC-VALID-004 [máy chủ] thiếu chặng, ngày về trước ngày đi, SĐT/email sai, group_size 0 và 501, vùng không tồn tại → 422', async ({ page, request }, info) => {
        const r1 = await createTrip(request, { title: '', start: D2, end: D1, legs: [], extra: { contact_phone: 'abc', contact_email: 'khong-phai-email', group_size: 0 } });
        const r2 = await createTrip(request, { title: 'x', start: D1, end: D2, legs: [{ zone_code: 'khong-ton-tai', starts_at: `${D1}T08:00`, ends_at: `${D1}T09:00` }], extra: { group_size: 501 } });
        const txt = `${r1.status} ${Object.keys(r1.body.errors || {}).join(', ')}\n${r2.status} ${Object.keys(r2.body.errors || {}).join(', ')}`;
        await showText(page, 'TC-VALID-004 POST /vi/hanh-trinh/tao', `${txt}\n\n${JSON.stringify(r1.body.errors, null, 1)}`);
        await shot(page, 'TC-VALID-004', info);
        actual(info, txt.replace('\n', ' ; '));
        expect(r1.status).toBe(422);
        for (const k of ['title', 'end_date', 'legs', 'contact_phone', 'contact_email', 'group_size']) expect(Object.keys(r1.body.errors)).toContain(k);
        expect(r2.status).toBe(422);
        expect(Object.keys(r2.body.errors)).toEqual(expect.arrayContaining(['group_size', 'legs.0.zone_code']));
    });

    test('TC-VALID-005 [Biên] số khách 201: máy khách chặn (tối đa 200) nhưng máy chủ nhận (tối đa 500) – hai phía phải thống nhất', async ({ page, request }, info) => {
        await page.goto('/vi/hanh-trinh/tao');
        await page.getByLabel('Tên hành trình').fill('E2E biên số khách');
        await page.getByLabel('Ngày đi').fill(D1);
        await page.getByLabel('Ngày về').fill(D2);
        await page.getByLabel(/^Số khách/).fill('201');
        await page.getByLabel('Họ tên người liên hệ').fill('QA');
        await page.getByLabel('Điện thoại người liên hệ').fill('0901234567');
        await page.getByRole('button', { name: 'Sau', exact: true }).click();
        const clientErr = (await page.locator('.field-error').allInnerTexts()).join(' | ');
        await shot(page, 'TC-VALID-005', info);
        const s = await createTrip(request, { title: 'E2E biên 201 khách', start: D1, end: D2, legs: [{ zone_code: 'hue', starts_at: `${D1}T08:00`, ends_at: `${D1}T10:00` }], extra: { group_size: 201 } });
        if (s.code) await send(request, 'POST', `/vi/hanh-trinh/${s.code}/huy`, {}, { headers: { 'X-Inertia': 'true' } });
        actual(info, `Máy khách group_size=201: "${clientErr}"; máy chủ POST group_size=201 → ${s.status} ${s.code || JSON.stringify(s.body.errors)} (đã hủy hành trình thử)`);
        expect(clientErr).toBeTruthy();
        expect(s.status, 'máy chủ phải từ chối giống máy khách').toBe(422);
    });
});
