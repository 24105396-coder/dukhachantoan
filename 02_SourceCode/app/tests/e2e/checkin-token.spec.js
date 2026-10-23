// F8 – Ghi nhận tình trạng an toàn: du khách báo trên trang hành trình (kèm vị trí), khách/người thân báo qua liên kết chia sẻ (token),
// cơ quan hỗ trợ phản hồi; hợp lệ dữ liệu check-in; giới hạn 10 lần/giờ/token.
import { test, expect } from '@playwright/test';
import { authFile, writerFile, shot, actual, send, createTrip, tripDetail, tokenOf, getPage, localDate, localDT, showText } from './helpers.js';

/** Hành trình mới ở Vịnh Nha Trang (thuộc tỉnh Khánh Hòa của tài khoản agency), đang diễn ra. */
async function nhaTrangTrip(playwright, baseURL, title) {
    const req = await playwright.request.newContext({ baseURL, storageState: authFile('traveler3') });
    const c = await createTrip(req, { title, start: localDate(0), end: localDate(2), legs: [{ zone_code: 'vinh-nha-trang', starts_at: localDT(0, -1), ends_at: localDT(1, 0) }] });
    const { trip } = await tripDetail(req, c.code);
    return { req, code: c.code, token: tokenOf(trip), trip };
}

test.describe('Báo an toàn', () => {
    test('TC-CHK-001 du khách bấm "Tôi an toàn" kèm vị trí trên trang hành trình', async ({ browser, playwright }, info) => {
        const t = await nhaTrangTrip(playwright, info.project.use.baseURL, `E2E check-in UI ${info.project.name}`);
        const ctx = await browser.newContext({ storageState: authFile('traveler3'), geolocation: { latitude: 12.2388, longitude: 109.1967 }, permissions: ['geolocation'] });
        const page = await ctx.newPage();
        await page.goto(`/vi/hanh-trinh/${t.code}`);
        await page.getByRole('button', { name: /Gắn vị trí/ }).locator('visible=true').first().click({ timeout: 8_000 });
        await expect(page.getByText(/Vị trí: /).locator('visible=true').first()).toBeVisible({ timeout: 8_000 }).catch(() => {});
        await page.waitForTimeout(800);
        await page.getByRole('button', { name: /Tôi an toàn/ }).locator('visible=true').first().click();
        await expect(page.locator('[role=status], [role=alert]').filter({ hasText: /Đã|ghi nhận|Cảm ơn/i }).first()).toBeVisible({ timeout: 10_000 });
        await page.waitForTimeout(800);
        const { trip } = await tripDetail(t.req, t.code);
        await shot(page, 'TC-CHK-001', info);
        const c = trip.checkins[0];
        actual(info, `${t.code} (${info.project.name}): check-in mới status=${c?.status}, source=${c?.source}, lat/lng=${c?.lat},${c?.lng}, lúc ${c?.checked_at}; last_checkin trên thẻ: ${trip.last_checkin?.status}`);
        expect(c.status).toBe('safe');
        expect(Number(c.lat)).toBeCloseTo(12.2388, 2);
        await ctx.close();
        await t.req.dispose();
    });

    test('TC-CHK-002 khách không tài khoản báo "Cần hỗ trợ" qua liên kết chia sẻ → agency tỉnh nhận check-in mở + thông báo mức 4', async ({ page, playwright }, info) => {
        const t = await nhaTrangTrip(playwright, info.project.use.baseURL, `E2E need_help token ${info.project.name}`);
        const msg = `E2E ${info.project.name}: cần hỗ trợ đưa đón ${Date.now().toString(36)}`;
        await page.goto(`/vi/hanh-trinh/chia-se/${t.token}/bao-an-toan`);
        await page.getByLabel(/Lời nhắn/).fill(msg);
        await Promise.all([
            page.waitForURL((u) => !u.pathname.endsWith('bao-an-toan'), { timeout: 10_000 }).catch(() => null),
            page.getByRole('button', { name: /Cần hỗ trợ/ }).first().click(),
        ]);
        await page.waitForTimeout(800);
        const flash = (await page.locator('[role=status], [role=alert]').allInnerTexts()).filter((x) => /Đã|ghi nhận|Cảm ơn/i.test(x) && !/Mức rủi ro/.test(x)).join(' | ').slice(0, 160);
        await shot(page, 'TC-CHK-002', info);
        const agency = await playwright.request.newContext({ baseURL: info.project.use.baseURL, storageState: authFile('agency') });
        const dash = (await getPage(agency, '/vi/quan-tri')).page.props;
        const open = dash.needHelp.find((c) => c.message === msg);
        const notifs = (await getPage(agency, '/vi/thong-bao')).page.props.notifications.data.filter((n) => n.level === 4 && (n.body || '').includes(t.code) || (n.trip?.code === t.code));
        actual(info, `Token ${t.token.slice(0, 6)}… (${t.token.length} ký tự) → chuyển ${new URL(page.url()).pathname.slice(0, 40)}…; flash "${flash.trim()}"; dashboard agency KH needHelp có check-in: ${!!open} (source=${open?.source}, response_status=${open?.response_status}); thông báo mức 4 cho agency về ${t.code}: ${notifs.length} (kênh ${[...new Set(notifs.map((n) => n.channel))].join('/')})`);
        expect(open).toBeTruthy();
        expect(open.response_status).toBe('open');
        await agency.dispose();
        await t.req.dispose();
    });

    test('TC-CHK-004 trang chia sẻ công khai không lộ SĐT/email liên hệ và thành viên', async ({ page, playwright }, info) => {
        const req = await playwright.request.newContext({ baseURL: info.project.use.baseURL, storageState: authFile('traveler') });
        const { trip } = await tripDetail(req, 'TR-SEED-01');
        const token = tokenOf(trip);
        await req.dispose();
        const html = await (await page.request.get(`/vi/hanh-trinh/chia-se/${token}`)).text();
        const api = await (await page.request.get(`/api/v1/trips/shared/${token}`)).text();
        // dữ liệu liên hệ: SĐT, email người liên hệ và SĐT thành viên (tên người báo check-in hiển thị theo thiết kế CheckinItem.user.name)
        const digits = (x) => (x || '').replace(/\D/g, '');
        const leaks = [trip.contact_phone, trip.contact_email].filter(Boolean).filter((s) => html.includes(s) || api.includes(s));
        const phoneLeak = digits(trip.contact_phone) && (digits(html).includes(digits(trip.contact_phone)) || digits(api).includes(digits(trip.contact_phone)));
        if (phoneLeak) leaks.push(`SĐT dạng số ${digits(trip.contact_phone)}`);
        const memberNames = trip.members.map((m) => m.full_name).filter((n) => html.includes(n) || api.includes(n));
        await page.goto(`/vi/hanh-trinh/chia-se/${token}`);
        await shot(page, 'TC-CHK-004', info, { fullPage: true });
        actual(info, `TR-SEED-01 chia sẻ: HTML ${html.length} byte, API ${api.length} byte; chuỗi nhạy cảm bị lộ: ${leaks.length ? leaks.join(', ') : 'không có'} (đã dò SĐT ${trip.contact_phone}, email ${trip.contact_email}); tên xuất hiện: ${memberNames.join(', ') || 'không'} (chỉ là tên người đã báo check-in, theo thiết kế)`);
        expect(leaks).toEqual([]);
    });
});

test.describe('Trang chia sẻ hành trình', () => {
    test('TC-CHK-005 người thân mở liên kết chia sẻ: thấy chặng, cảnh báo đang ảnh hưởng, 3 lần báo an toàn gần nhất, nút báo an toàn', async ({ page, playwright }, info) => {
        const req = await playwright.request.newContext({ baseURL: info.project.use.baseURL, storageState: authFile('traveler') });
        const token = tokenOf((await tripDetail(req, 'TR-SEED-01')).trip);
        await req.dispose();
        const p = (await getPage(page.request, `/vi/hanh-trinh/chia-se/${token}`)).page.props;
        await page.goto(`/vi/hanh-trinh/chia-se/${token}`);
        const robots = await page.locator('meta[name=robots]').getAttribute('content').catch(() => null);
        const btn = await page.getByRole('link', { name: /Báo an toàn/ }).count() + await page.getByRole('button', { name: /Báo an toàn/ }).count();
        await shot(page, 'TC-CHK-005', info, { fullPage: true });
        actual(info, `${info.project.name}: ${p.trip.code} "${p.trip.title}", ${p.trip.legs.length} chặng, ${p.alerts.length} cảnh báo, ${p.checkins.length} check-in gần nhất (≤3), nút/liên kết báo an toàn: ${btn}, meta robots "${robots}"`);
        expect(p.trip.legs.length).toBeGreaterThan(0);
        expect(p.checkins.length).toBeLessThanOrEqual(3);
        expect(btn).toBeGreaterThan(0);
    });
});

test.describe('Cơ quan hỗ trợ phản hồi', () => {
    test.use({ storageState: authFile('agency') });

    test('TC-CHK-003 agency phản hồi check-in: in_progress rồi closed (closed bắt buộc ghi chú)', async ({ page, request, playwright }, info) => {
        const t = await nhaTrangTrip(playwright, info.project.use.baseURL, `E2E phản hồi ${info.project.name}`);
        const guest = await playwright.request.newContext({ baseURL: info.project.use.baseURL });
        await send(guest, 'POST', `/api/v1/trips/shared/${t.token}/checkin`, { status: 'need_help', message: `E2E phản hồi ${info.project.name}` }, { csrf: false });
        await guest.dispose();
        const { trip } = await tripDetail(t.req, t.code);
        const id = trip.checkins[0].id;
        const r1 = await send(request, 'POST', `/vi/quan-tri/an-toan/${id}/phan-hoi`, { response_status: 'in_progress', response_note: 'Đã gọi điện, xe cứu hộ đang tới' }, { headers: { 'X-Inertia': 'true' } });
        const bad = await send(request, 'POST', `/vi/quan-tri/an-toan/${id}/phan-hoi`, { response_status: 'closed', response_note: '' });
        const r2 = await send(request, 'POST', `/vi/quan-tri/an-toan/${id}/phan-hoi`, { response_status: 'closed', response_note: 'Đã đưa đoàn về khách sạn an toàn' }, { headers: { 'X-Inertia': 'true' } });
        const c = (await tripDetail(t.req, t.code)).trip.checkins[0];
        await page.goto('/vi/quan-tri/an-toan');
        await shot(page, 'TC-CHK-003', info);
        actual(info, `check-in #${id}: in_progress → ${r1.status}; closed không ghi chú → ${bad.status} ${JSON.stringify(bad.body.errors || {})}; closed có ghi chú → ${r2.status}; du khách thấy response_status=${c.response_status}, responder=${c.responder?.name}, note="${c.response_note}"`);
        expect(bad.status).toBe(422);
        expect(c.response_status).toBe('closed');
        expect(c.responder?.name).toBeTruthy();
        await t.req.dispose();
    });
});

test.describe('Hợp lệ dữ liệu check-in và giới hạn tần suất', () => {
    test('TC-VALID-006 [máy chủ] check-in: trạng thái lạ, vĩ độ 91, có lat thiếu lng, lời nhắn > 1000 ký tự → 422', async ({ page, playwright }, info) => {
        const t = await nhaTrangTrip(playwright, info.project.use.baseURL, `E2E 422 check-in ${info.project.name}`);
        const g = page.request;
        const cases = [
            { status: 'ok_lam' },
            { status: 'safe', lat: 91, lng: 100 },
            { status: 'safe', lat: 12.2 },
            { status: 'safe', message: 'x'.repeat(1001) },
        ];
        const out = [];
        for (const body of cases) {
            const r = await send(g, 'POST', `/api/v1/trips/shared/${t.token}/checkin`, body, { csrf: false });
            out.push(`${JSON.stringify(body).slice(0, 50)} → ${r.status} ${Object.keys(r.body.errors || {}).join(',')}`);
        }
        await showText(page, 'TC-VALID-006 POST /api/v1/trips/shared/{token}/checkin', out.join('\n'));
        await shot(page, 'TC-VALID-006', info);
        actual(info, out.join(' ; '));
        expect(out.every((l) => / → 422 /.test(l))).toBeTruthy();
        await t.req.dispose();
    });

    test('TC-VALID-007 [máy khách + máy chủ] báo sự cố: tiêu đề < 5 ký tự, thiếu vùng/loại hiểm họa', async ({ browser }, info) => {
        const ctx = await browser.newContext({ storageState: writerFile(info) });
        const page = await ctx.newPage();
        await page.goto('/vi/bao-su-co');
        await page.getByLabel(/^Tiêu đề/).fill('abc');
        let posted = 0;
        page.on('request', (r) => r.method() === 'POST' && r.url().includes('/bao-su-co') && posted++);
        await page.getByRole('button', { name: /Gửi/ }).last().click();
        await page.waitForTimeout(300);
        const client = (await page.locator('.field-error').allInnerTexts()).join(' | ');
        await shot(page, 'TC-VALID-007', info);
        const r = await send(ctx.request, 'POST', '/vi/bao-su-co', { title: 'abc', zone_code: 'khong-co', hazard_code: '' });
        actual(info, `Máy khách: ${posted} POST gửi đi, lỗi "${client}"; máy chủ (gửi thẳng) → ${r.status} ${Object.keys(r.body.errors || {}).join(', ')}`);
        expect(posted).toBe(0);
        expect(r.status).toBe(422);
        expect(Object.keys(r.body.errors)).toEqual(expect.arrayContaining(['title', 'zone_code', 'hazard_code']));
        await ctx.close();
    });

    test('TC-SEC-008 giới hạn báo an toàn qua token 10 lần/giờ → lần 11 nhận 429 @chromium', async ({ page, playwright }, info) => {
        const t = await nhaTrangTrip(playwright, info.project.use.baseURL, 'E2E throttle token');
        const codes = [];
        let retry = '';
        for (let i = 1; i <= 11; i++) {
            const r = await send(page.request, 'POST', `/api/v1/trips/shared/${t.token}/checkin`, { status: 'safe', message: `lần ${i}` }, { csrf: false });
            codes.push(r.status);
            if (r.status === 429) retry = `${r.headers['retry-after']} s; thân ${JSON.stringify(r.body)}`;
        }
        const web = await send(page.request, 'POST', `/vi/hanh-trinh/chia-se/${t.token}/bao-an-toan`, { status: 'safe' });
        await showText(page, 'TC-SEC-008 11 lần POST check-in cùng token', `Mã trả về: ${codes.join(', ')}\nRetry-After: ${retry}\nForm web cùng token: ${web.status}`);
        await shot(page, 'TC-SEC-008', info);
        actual(info, `${t.code}: 11 lần POST /api/v1/trips/shared/{token}/checkin → ${codes.join(', ')}; lần 429 Retry-After ${retry}; form web cùng token → ${web.status}`);
        expect(codes.slice(0, 10).every((c) => c === 201)).toBeTruthy();
        expect(codes[10]).toBe(429);
        expect(web.status).toBe(429);
        await t.req.dispose();
    });
});
