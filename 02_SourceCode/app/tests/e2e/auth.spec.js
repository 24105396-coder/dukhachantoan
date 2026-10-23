// F-AUTH: đăng nhập, đăng ký, đăng xuất (giao diện thật, ba trình duyệt).
import { test, expect } from '@playwright/test';
import { UI_LOGIN, PASSWORD, loginUI, shot, actual, getPage, send } from './helpers.js';

test.describe('Xác thực', () => {
    test('TC-AUTH-001 đăng nhập du khách đúng thông tin → trang Hành trình của tôi', async ({ page }, info) => {
        const email = UI_LOGIN[info.project.name];
        const dest = await loginUI(page, email);
        await page.waitForLoadState('networkidle').catch(() => {});
        await shot(page, 'TC-AUTH-001', info);
        actual(info, `Đăng nhập ${email} → ${dest}; h1 "${(await page.locator('h1').first().innerText()).trim()}"`);
        // đích theo ngôn ngữ ưa thích của tài khoản (traveler2 dùng en)
        expect(dest).toMatch(/^\/(vi|en)\/hanh-trinh$/);
    });

    test('TC-AUTH-002 đăng nhập sai mật khẩu → báo lỗi, không tạo phiên', async ({ page }, info) => {
        const email = UI_LOGIN[info.project.name];
        await page.goto('/vi/dang-nhap');
        await page.fill('input[type=email]', email);
        await page.fill('input[type=password]', 'SaiMatKhau9');
        await page.click('button[type=submit]');
        const err = page.locator('.field-error, [role=alert]').filter({ hasText: /không đúng/ }).first();
        await expect(err).toBeVisible();
        await shot(page, 'TC-AUTH-002', info);
        const probe = await page.request.get('/vi/hanh-trinh', { maxRedirects: 0 });
        actual(info, `Ở lại ${new URL(page.url()).pathname}, thông báo "${(await err.innerText()).trim()}"; GET /vi/hanh-trinh → ${probe.status()} ${probe.headers().location || ''}`);
        expect(page.url()).toContain('/dang-nhap');
        expect(probe.status()).toBe(302);
    });

    test('TC-AUTH-003 đăng ký du khách mới qua giao diện → tự đăng nhập vai trò traveler', async ({ page }, info) => {
        const email = `qa_${info.project.name}_${Date.now().toString(36)}@dukhach.test`;
        await page.goto('/vi/dang-ky');
        await page.getByLabel(/^Tên/).fill('Du khách QA');
        await page.getByLabel(/^Email/).fill(email);
        await page.getByLabel(/^Điện thoại/).fill('0912345678');
        await page.getByLabel(/^Quốc tịch/).selectOption('VN');
        await page.getByLabel(/^Ngôn ngữ/).selectOption('vi').catch(() => {});
        await page.getByLabel(/^Mật khẩu/).fill('KiemThu2026');
        await page.getByLabel(/^Nhập lại mật khẩu/).fill('KiemThu2026');
        await Promise.all([page.waitForURL((u) => !u.pathname.endsWith('/dang-ky'), { timeout: 15_000 }), page.getByRole('button', { name: 'Đăng ký' }).last().click()]);
        const { page: p } = await getPage(page.request, '/vi/tai-khoan');
        await shot(page, 'TC-AUTH-003', info);
        actual(info, `Tạo ${email} → chuyển ${new URL(page.url()).pathname}; auth.user.role=${p?.props?.auth?.user?.role}`);
        expect(p.props.auth.user.role).toBe('traveler');
    });

    test('TC-AUTH-004 đăng xuất → hủy phiên, trang cần đăng nhập chuyển về /dang-nhap', async ({ page }, info) => {
        const email = UI_LOGIN[info.project.name];
        await loginUI(page, email);
        const r = await send(page.request, 'POST', '/vi/dang-xuat', {}, { accept: 'html' });
        await page.goto('/vi/hanh-trinh');
        await shot(page, 'TC-AUTH-004', info);
        actual(info, `POST /vi/dang-xuat → ${r.status} ${r.location}; mở /vi/hanh-trinh → ${new URL(page.url()).pathname}`);
        expect([302, 303, 409]).toContain(r.status);
        expect(page.url()).toContain('/dang-nhap');
    });

    test('TC-AUTH-005 đăng nhập cơ quan hỗ trợ → bảng điều khiển /vi/quan-tri', async ({ browser }, info) => {
        // tài khoản agency đã đăng nhập ở global-setup; ở đây đăng nhập lại bằng giao diện trong context mới (1 lần/trình duyệt)
        const ctx = await browser.newContext();
        const page = await ctx.newPage();
        const dest = await loginUI(page, 'agency@dukhach.test', PASSWORD);
        await page.waitForLoadState('networkidle').catch(() => {});
        await shot(page, 'TC-AUTH-005', info);
        actual(info, `agency@dukhach.test → ${dest}; h1 "${(await page.locator('h1').first().innerText()).trim()}"`);
        expect(dest).toBe('/vi/quan-tri');
        await ctx.close();
    });
});
