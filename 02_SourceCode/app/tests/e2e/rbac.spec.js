// RBAC: kiểm quyền ở máy chủ cho khách, du khách, đơn vị lữ hành, cơ quan hỗ trợ tỉnh (Khánh Hòa).
import { test, expect } from '@playwright/test';
import { authFile, writerFile, shot, actual, send, tripDetail, getPage } from './helpers.js';

test.describe('Khách vãng lai', () => {
    test('TC-RBAC-001 khách mở /vi/quan-tri và /vi/hanh-trinh → chuyển tới đăng nhập', async ({ page, request }, info) => {
        const a = await request.get('/vi/quan-tri', { maxRedirects: 0 });
        const b = await request.get('/vi/hanh-trinh/TR-SEED-01', { maxRedirects: 0 });
        await page.goto('/vi/quan-tri');
        await shot(page, 'TC-RBAC-001', info);
        actual(info, `GET /vi/quan-tri → ${a.status()} ${a.headers().location}; GET /vi/hanh-trinh/TR-SEED-01 → ${b.status()} ${b.headers().location}`);
        expect(a.status()).toBe(302);
        expect(a.headers().location).toContain('/dang-nhap');
        expect(b.headers().location).toContain('/dang-nhap');
    });
});

test.describe('Du khách', () => {
    test.use({ storageState: authFile('traveler') });

    test('TC-RBAC-002 du khách vào khu quản trị → 403', async ({ page, request }, info) => {
        const urls = ['/vi/quan-tri', '/vi/quan-tri/canh-bao', '/vi/quan-tri/an-toan', '/vi/quan-tri/nguoi-dung'];
        const codes = [];
        for (const u of urls) codes.push(`${u} ${(await request.get(u, { maxRedirects: 0 })).status()}`);
        const post = await send(request, 'POST', '/vi/quan-tri/canh-bao', { hazard_code: 'bao', severity: 2, zone_codes: ['ha-noi'], source_name: 'x', translations: { vi: { headline: 'x' } } });
        await page.goto('/vi/quan-tri');
        await shot(page, 'TC-RBAC-002', info);
        actual(info, `${codes.join('; ')}; POST /vi/quan-tri/canh-bao → ${post.status}`);
        for (const c of codes) expect(c.endsWith('403')).toBeTruthy();
        expect(post.status).toBe(403);
    });

    test('TC-RBAC-003 du khách xem/sửa/hủy/báo an toàn hành trình của người khác (TR-SEED-06) → 403', async ({ page, request, playwright }, info) => {
        const show = await request.get('/vi/hanh-trinh/TR-SEED-06', { maxRedirects: 0 });
        const edit = await request.get('/vi/hanh-trinh/TR-SEED-06/sua', { maxRedirects: 0 });
        const cancel = await send(request, 'POST', '/vi/hanh-trinh/TR-SEED-06/huy');
        // POST báo an toàn đi qua throttle:30,60 (bộ đếm chung) → dùng tài khoản ghi theo trình duyệt (cũng không phải chủ TR-SEED-06)
        const wr = await playwright.request.newContext({ baseURL: info.project.use.baseURL, storageState: writerFile(info) });
        const chk = await send(wr, 'POST', '/vi/hanh-trinh/TR-SEED-06/bao-an-toan', { status: 'safe' });
        await wr.dispose();
        const ics = await request.get('/vi/hanh-trinh/TR-SEED-06/lich.ics', { maxRedirects: 0 });
        await page.goto('/vi/hanh-trinh/TR-SEED-06');
        await shot(page, 'TC-RBAC-003', info);
        actual(info, `show ${show.status()}, sửa ${edit.status()}, hủy ${cancel.status}, báo an toàn ${chk.status}, iCal ${ics.status()}`);
        for (const s of [show.status(), edit.status(), cancel.status, chk.status, ics.status()]) expect(s).toBe(403);
    });
});

test.describe('Cơ quan hỗ trợ tỉnh Khánh Hòa', () => {
    test.use({ storageState: authFile('agency') });

    test('TC-RBAC-004 agency KH xem hành trình tỉnh khác (Hà Nội) → 403, hành trình trong tỉnh → 200', async ({ page, request }, info) => {
        const other = await request.get('/vi/quan-tri/hanh-trinh/TR-SEED-01', { maxRedirects: 0 });
        const own = await request.get('/vi/quan-tri/hanh-trinh/TR-SEED-14', { maxRedirects: 0 });
        const { page: idx } = await getPage(request, '/vi/quan-tri/hanh-trinh');
        const codes = (idx?.props?.trips?.data || []).map((t) => t.code);
        await page.goto('/vi/quan-tri/hanh-trinh/TR-SEED-01');
        await shot(page, 'TC-RBAC-004', info);
        actual(info, `TR-SEED-01 (Hà Nội) → ${other.status()}; TR-SEED-14 (Nha Trang) → ${own.status()}; danh sách hành trình của agency: ${codes.join(', ')}`);
        expect(other.status()).toBe(403);
        expect(own.status()).toBe(200);
        expect(codes).not.toContain('TR-SEED-01');
    });

    test('TC-RBAC-005 agency vào chức năng chỉ dành cho admin (người dùng, nhật ký, sao lưu, nguồn, mô phỏng) → 403', async ({ page, request }, info) => {
        const res = [];
        for (const u of ['/vi/quan-tri/nguoi-dung', '/vi/quan-tri/nhat-ky', '/vi/quan-tri/sao-luu', '/vi/quan-tri/nguon', '/vi/quan-tri/mo-phong']) {
            res.push([u, (await request.get(u, { maxRedirects: 0 })).status()]);
        }
        const run = await send(request, 'POST', '/vi/quan-tri/mo-phong/chay', { slug: 'su-co-an-ninh-le-hoi', mode: 'instant' });
        const bak = await send(request, 'POST', '/vi/quan-tri/sao-luu');
        await page.goto('/vi/quan-tri/mo-phong');
        await shot(page, 'TC-RBAC-005', info);
        actual(info, `${res.map(([u, s]) => `${u} ${s}`).join('; ')}; POST mo-phong/chay ${run.status}; POST sao-luu ${bak.status}`);
        for (const [, s] of res) expect(s).toBe(403);
        expect(run.status).toBe(403);
        // throttle:3,1 của route sao lưu chạy trước kiểm vai trò → lần gọi dồn có thể nhận 429 (vẫn bị từ chối)
        expect([403, 429]).toContain(bak.status);
    });

    test('TC-RBAC-007 agency KH phản hồi check-in của hành trình tỉnh khác → 403 @chromium', async ({ page, request, playwright }, info) => {
        // du khách tạo check-in trên TR-SEED-01 (Hà Nội) rồi agency Khánh Hòa thử phản hồi
        const trav = await playwright.request.newContext({ baseURL: info.project.use.baseURL, storageState: authFile('traveler') });
        const c = await send(trav, 'POST', '/vi/hanh-trinh/TR-SEED-01/bao-an-toan', { status: 'safe', message: 'RBAC-007 kiểm thử' }, { headers: { 'X-Inertia': 'true' } });
        const { trip } = await tripDetail(trav, 'TR-SEED-01');
        const id = trip.checkins[0].id;
        await trav.dispose();
        const r = await send(request, 'POST', `/vi/quan-tri/an-toan/${id}/phan-hoi`, { response_status: 'in_progress', response_note: 'thử vượt quyền' });
        await page.goto('/vi/quan-tri/an-toan');
        await shot(page, 'TC-RBAC-007', info);
        actual(info, `Du khách báo an toàn TR-SEED-01 → ${c.status}; check-in #${id}; agency KH POST /vi/quan-tri/an-toan/${id}/phan-hoi → ${r.status}`);
        expect(r.status).toBe(403);
    });
});

test.describe('Đơn vị lữ hành', () => {
    test.use({ storageState: authFile('partner') });

    test('TC-RBAC-006 partner chỉ thấy/sửa hành trình đoàn mình; hành trình cá nhân của du khách → 403', async ({ page, request }, info) => {
        const { page: idx } = await getPage(request, '/vi/hanh-trinh');
        const codes = (idx?.props?.trips?.data || []).map((t) => t.code);
        const own = await request.get('/vi/hanh-trinh/TR-SEED-30', { maxRedirects: 0 });
        const other = await request.get('/vi/hanh-trinh/TR-SEED-01', { maxRedirects: 0 });
        const upd = await send(request, 'PUT', '/vi/hanh-trinh/TR-SEED-01/sua', { title: 'x' });
        const admin = await request.get('/vi/quan-tri', { maxRedirects: 0 });
        await page.goto('/vi/hanh-trinh');
        await shot(page, 'TC-RBAC-006', info);
        actual(info, `Danh sách partner: ${codes.join(', ')}; TR-SEED-30 ${own.status()}; TR-SEED-01 ${other.status()}; PUT TR-SEED-01/sua ${upd.status}; /vi/quan-tri ${admin.status()}`);
        expect(codes.every((c) => /^TR-SEED-3[0-4]$/.test(c) || !/^TR-SEED-(0|1|2)/.test(c))).toBeTruthy();
        expect(own.status()).toBe(200);
        expect(other.status()).toBe(403);
        expect(upd.status).toBe(403);
        expect(admin.status()).toBe(403);
    });
});
