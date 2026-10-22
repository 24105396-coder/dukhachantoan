// Phân quyền: truy cập trái phép trang/API quản trị, sửa dữ liệu của người khác, IPN giả mạo.
import { test, expect, request as pwRequest } from '@playwright/test';
import { PASSWORD, authFile, actual, shot, send, getPage, showText } from './helpers.js';

const asRole = (role) => ({ storageState: authFile(role) });

test.describe('RBAC – khách vãng lai', () => {
    test('TC-RBAC-001 khách vào /vi/quan-tri bị chuyển 302 tới trang đăng nhập, đăng nhập xong quay lại', async ({ page }, ti) => {
        // Vòng 2 (sau sửa qa_2): nhóm quan-tri dùng ['auth', 'role:...'] → khách nhận 302, không còn 403.
        const res = await page.request.get('/vi/quan-tri', { maxRedirects: 0 });
        const resEn = await page.request.get('/en/quan-tri', { maxRedirects: 0 });
        await page.goto('/vi/quan-tri');
        const landed = new URL(page.url()).pathname;
        await shot(page, 'TC-RBAC-001');
        // Đăng nhập manager ngay trên trang được chuyển tới → phải quay về /vi/quan-tri (url.intended)
        await page.locator('input[autocomplete="username"]').fill('manager@disanso.test');
        await page.locator('input[autocomplete="current-password"]').fill(PASSWORD);
        await page.getByRole('button', { name: 'Đăng nhập', exact: true }).last().click();
        await page.waitForURL(/\/vi\/quan-tri/);
        const back = new URL(page.url()).pathname;
        actual(ti, `GET /vi/quan-tri → ${res.status()} Location ${res.headers()['location'] ?? '-'}; GET /en/quan-tri → ${resEn.status()} ${resEn.headers()['location'] ?? '-'}; trình duyệt dừng ở ${landed}; đăng nhập manager xong quay về ${back}.`);
        expect([302, 303]).toContain(res.status());
        expect(res.headers()['location']).toContain('/vi/dang-nhap');
        expect(resEn.headers()['location']).toContain('/en/dang-nhap');
        expect(landed).toBe('/vi/dang-nhap');
        expect(back).toBe('/vi/quan-tri');
    });

    test('TC-RBAC-002 khách POST đặt lịch (không đăng nhập) bị chặn', async ({ page }, ti) => {
        await page.goto('/vi');
        const r = await send(page.request, 'POST', '/vi/dat-lich/3', { quantity: 1, contact_name: 'Khách', contact_phone: '0912345678', contact_email: 'a@b.vn' });
        expect(r.status).toBe(401);
        actual(ti, `POST /vi/dat-lich/3 (Accept JSON) → ${r.status} ${JSON.stringify(r.body).slice(0, 80)}`);
        await showText(page, 'TC-RBAC-002', 'Khách POST /vi/dat-lich/3', `HTTP ${r.status}\n${JSON.stringify(r.body, null, 2)}`);
    });
});

test.describe('RBAC – thành viên', () => {
    test.use(asRole('member'));

    test('TC-RBAC-003 member vào /vi/quan-tri nhận 403 thân thiện', async ({ page }, ti) => {
        const res = await page.goto('/vi/quan-tri');
        expect(res.status()).toBe(403);
        await expect(page.getByText('Không có quyền truy cập')).toBeVisible();
        const leak = /(Exception|vendor\/|stack|\.php)/i.test(await page.content());
        actual(ti, `HTTP ${res.status()}, tiêu đề "Không có quyền truy cập", lộ thông tin kỹ thuật: ${leak ? 'có' : 'không'}.`);
        expect(leak).toBe(false);
        await shot(page, 'TC-RBAC-003');
    });

    test('TC-RBAC-004 member gọi PATCH kiểm duyệt đóng góp (API quản trị) nhận 403', async ({ page }, ti) => {
        await page.goto('/vi');
        const r = await send(page.request, 'PATCH', '/vi/quan-tri/kiem-duyet/1', { status: 'approved', review_note: 'tự duyệt' });
        const { page: c } = await getPage(page.request, '/vi/tai-khoan');
        expect(r.status).toBe(403);
        actual(ti, `PATCH /vi/quan-tri/kiem-duyet/1 → ${r.status}; đóng góp #1 không đổi trạng thái.`);
        await showText(page, 'TC-RBAC-004', 'member PATCH /vi/quan-tri/kiem-duyet/1', `HTTP ${r.status}\n${JSON.stringify(r.body, null, 2)}\n(user: ${c.props.auth.user.email})`);
    });

    test('TC-RBAC-005 member xem đặt lịch của người khác (đoán mã) bị chặn', async ({ page, browser }, ti) => {
        const other = await browser.newContext(asRole('member2'));
        await other.request.get('/vi');
        const created = await send(other.request, 'POST', '/vi/dat-lich/10', { quantity: 1, contact_name: 'Thành viên 1', contact_phone: '0912345678', contact_email: 'member1@disanso.test' });
        const code = created.location.split('/').pop();
        await other.close();
        const res = await page.goto(`/vi/dat-lich/${code}`);
        expect([403, 404]).toContain(res.status());
        actual(ti, `member1 tạo ${code}; user@ GET /vi/dat-lich/${code} → ${res.status()}.`);
        await shot(page, 'TC-RBAC-005');
    });
});

test.describe('RBAC – contributor', () => {
    test.use(asRole('contributor'));

    test('TC-RBAC-006 contributor vào /vi/quan-tri/di-san nhận 403', async ({ page }, ti) => {
        const res = await page.goto('/vi/quan-tri/di-san');
        expect(res.status()).toBe(403);
        actual(ti, `GET /vi/quan-tri/di-san → ${res.status()}, hiển thị trang 403.`);
        await shot(page, 'TC-RBAC-006');
    });

    test('TC-RBAC-007 contributor sửa tuyến của người khác bị 403 (GET form và PUT)', async ({ page }, ti) => {
        const res = await page.goto('/vi/quan-tri/tuyen-tham-quan/dau-an-thang-long/sua');
        const put = await send(page.request, 'PUT', '/vi/quan-tri/tuyen-tham-quan/dau-an-thang-long', { slug: 'dau-an-thang-long', duration_minutes: 60, difficulty: 'de', price_vnd: 1, default_capacity: 5, status: 'archived', translations: { vi: { name: 'Bị sửa' } }, stops: [{ site_id: 1 }] });
        const del = await send(page.request, 'DELETE', '/vi/quan-tri/tuyen-tham-quan/dau-an-thang-long');
        expect(res.status()).toBe(403);
        expect(put.status).toBe(403);
        expect(del.status).toBe(403);
        actual(ti, `GET form → ${res.status()}, PUT → ${put.status}, DELETE → ${del.status}.`);
        await shot(page, 'TC-RBAC-007');
    });

    test('TC-RBAC-008 contributor chỉ thấy tuyến và đặt lịch thuộc tuyến của mình', async ({ page }, ti) => {
        const { page: tours } = await getPage(page.request, '/vi/quan-tri/tuyen-tham-quan');
        const { page: bk } = await getPage(page.request, '/vi/quan-tri/dat-lich');
        const rows = tours.props.routes.data ?? tours.props.routes;
        const owners = [...new Set(rows.map((r) => r.owner?.name))];
        const bookingRoutes = [...new Set((bk.props.bookings.data ?? []).map((b) => b.slot?.route?.slug))];
        const own = ['phat-giao-kinh-bac', 'huong-ve-dat-to', 'con-duong-di-san-cham', 'sai-gon-ky-uc'];
        expect(rows.every((r) => own.includes(r.slug))).toBe(true);
        expect(bookingRoutes.every((s) => own.includes(s))).toBe(true);
        expect(bk.props.canChangeStatus).toBeFalsy();
        actual(ti, `Thấy ${rows.length} tuyến (${rows.map((r) => r.slug).join(', ')}), chủ: ${owners.join(', ')}; đặt lịch thuộc tuyến: ${bookingRoutes.join(', ') || '(không có)'}; canChangeStatus=${bk.props.canChangeStatus}.`);
        await page.goto('/vi/quan-tri/tuyen-tham-quan');
        await shot(page, 'TC-RBAC-008');
    });

    test('TC-RBAC-009 contributor xóa di sản qua API quản trị nhận 403', async ({ page }, ti) => {
        await page.goto('/vi');
        const r = await send(page.request, 'DELETE', '/vi/quan-tri/di-san/hoang-thanh-thang-long');
        const still = await page.request.get('/api/v1/sites/hoang-thanh-thang-long');
        expect(r.status).toBe(403);
        expect(still.status()).toBe(200);
        actual(ti, `DELETE /vi/quan-tri/di-san/hoang-thanh-thang-long → ${r.status}; di sản vẫn tồn tại (API ${still.status()}).`);
        await showText(page, 'TC-RBAC-009', 'contributor DELETE di sản', `HTTP ${r.status}\n${JSON.stringify(r.body)}\nGET /api/v1/sites/hoang-thanh-thang-long → ${still.status()}`);
    });
});

test.describe('RBAC – manager', () => {
    test.use(asRole('manager'));

    test('TC-RBAC-010 manager vào trang quản lý người dùng và nhật ký (chỉ admin) nhận 403', async ({ page }, ti) => {
        const u = await page.goto('/vi/quan-tri/nguoi-dung');
        const st1 = u.status();
        const a = await page.request.get('/vi/quan-tri/nhat-ky');
        const p = await send(page.request, 'PATCH', '/vi/quan-tri/nguoi-dung/4', { role_id: 1, is_active: true });
        expect(st1).toBe(403);
        expect(a.status()).toBe(403);
        expect(p.status).toBe(403);
        actual(ti, `GET /nguoi-dung → ${st1}, GET /nhat-ky → ${a.status()}, PATCH nâng quyền user#4 → ${p.status}.`);
        await shot(page, 'TC-RBAC-010');
    });
});

test('TC-RBAC-011 IPN thanh toán với chữ ký sai bị từ chối 400', async ({ page, baseURL }, ti) => {
    const api = await pwRequest.newContext({ baseURL });
    const r = await api.post('/api/v1/payments/sandbox/ipn', {
        data: { txn_ref: 'SB-KHONGTONTAI', amount: 450000, currency: 'VND', outcome: 'success', response_code: '00', signature: 'deadbeef' },
        headers: { Accept: 'application/json' },
    });
    const body = await r.text();
    await api.dispose();
    expect(r.status()).toBe(400);
    actual(ti, `POST /api/v1/payments/sandbox/ipn chữ ký "deadbeef" → ${r.status()} ${body.slice(0, 120)}`);
    await showText(page, 'TC-RBAC-011', 'IPN chữ ký sai', `HTTP ${r.status()}\n${body}`);
});
