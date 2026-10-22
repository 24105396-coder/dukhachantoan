// UC-01 Đăng ký/đăng nhập: luồng thuận, ngoại lệ, giá trị biên, hợp lệ dữ liệu.
import { test, expect } from '@playwright/test';
import { PASSWORD, actual, shot, rnd, send, getPage } from './helpers.js';

const err = (page) => page.locator('.field-error');

async function loginUi(page, email, password) {
    await page.goto('/vi/dang-nhap');
    await page.locator('input[autocomplete="username"]').fill(email);
    await page.locator('input[autocomplete="current-password"]').fill(password);
    await page.getByRole('button', { name: 'Đăng nhập', exact: true }).last().click();
}

async function registerUi(page, { name = 'Kiểm Thử V5', email, password, confirm = password }) {
    await page.goto('/vi/dang-ky');
    await page.locator('input[autocomplete="name"]').fill(name);
    await page.locator('input[autocomplete="email"]').fill(email);
    await page.locator('input[autocomplete="new-password"]').nth(0).fill(password);
    await page.locator('input[autocomplete="new-password"]').nth(1).fill(confirm);
    await page.locator('form button[type="submit"]').click();
}

test.describe('AUTH', () => {
    test('TC-AUTH-001 đăng nhập đúng thông tin chuyển tới trang tài khoản', async ({ page }, ti) => {
        await loginUi(page, 'member2@disanso.test', PASSWORD);
        await page.waitForURL(/\/vi\/tai-khoan/);
        await expect(page.locator('h1')).toBeVisible();
        actual(ti, `Chuyển tới ${new URL(page.url()).pathname}; trang tài khoản hiển thị.`);
        await shot(page, 'TC-AUTH-001');
    });

    test('TC-AUTH-002 đăng nhập sai mật khẩu báo lỗi, không tạo phiên', async ({ page }, ti) => {
        await loginUi(page, 'member3@disanso.test', 'SaiMatKhau1');
        await expect(err(page).first()).toBeVisible();
        const msg = await err(page).first().innerText();
        expect(page.url()).toContain('/vi/dang-nhap');
        actual(ti, `Ở lại /vi/dang-nhap, thông báo: "${msg}".`);
        await shot(page, 'TC-AUTH-002');
    });

    test('TC-AUTH-003 sai mật khẩu quá 5 lần/phút bị khóa tạm, kể cả khi nhập đúng', async ({ page }, ti) => {
        await page.goto('/vi/dang-nhap');
        const codes = [];
        for (let i = 0; i < 6; i++) {
            const r = await send(page.request, 'POST', '/vi/dang-nhap', { email: 'member4@disanso.test', password: `Sai${i}abc1` }, { accept: 'html', json: false });
            codes.push(r.status);
        }
        // Lần thứ 7 dùng mật khẩu ĐÚNG qua giao diện: vẫn phải bị chặn
        await loginUi(page, 'member4@disanso.test', PASSWORD);
        await page.waitForTimeout(1500);
        const msg = (await err(page).allInnerTexts()).join(' | ');
        const blocked = page.url().includes('/dang-nhap');
        actual(ti, `6 lần POST sai → ${codes.join(', ')}; lần 7 mật khẩu đúng: ${blocked ? 'vẫn ở trang đăng nhập' : 'ĐĂNG NHẬP ĐƯỢC'}, thông báo "${msg}".`);
        await shot(page, 'TC-AUTH-003');
        expect(blocked).toBe(true);
        expect(msg).toMatch(/giây|thử lại/i);
    });

    test('TC-AUTH-004 đăng ký tài khoản mới thành công, vai trò member', async ({ page }, ti) => {
        const email = `qa_${rnd()}@disanso.test`;
        await registerUi(page, { email, password: 'KiemThu2026' });
        await page.waitForURL((u) => !u.pathname.endsWith('/dang-ky'));
        const { page: p } = await getPage(page.request, '/vi/tai-khoan');
        expect(p.props.auth.user.email).toBe(email);
        expect(p.props.auth.user.role).toBe('member');
        actual(ti, `Tạo ${email}, chuyển tới ${new URL(page.url()).pathname}, role=${p.props.auth.user.role}.`);
        await shot(page, 'TC-AUTH-004');
    });

    test('TC-AUTH-005 đăng ký với email sai định dạng bị từ chối', async ({ page }, ti) => {
        await registerUi(page, { email: 'khong-phai-email', password: 'KiemThu2026' });
        await expect(err(page).first()).toBeVisible();
        const msg = await err(page).allInnerTexts();
        expect(page.url()).toContain('/dang-ky');
        actual(ti, `Ở lại /vi/dang-ky, lỗi: ${msg.join(' | ')}`);
        await shot(page, 'TC-AUTH-005');
    });

    test('TC-AUTH-006 đăng ký mật khẩu 7 ký tự (dưới biên 8) bị từ chối', async ({ page }, ti) => {
        await registerUi(page, { email: `qa_${rnd()}@disanso.test`, password: 'Abc1234' });
        await expect(err(page).first()).toBeVisible();
        const msg = await err(page).allInnerTexts();
        actual(ti, `Lỗi hiển thị: ${msg.join(' | ')}`);
        expect(msg.join(' ')).toMatch(/8/);
        await shot(page, 'TC-AUTH-006');
    });

    test('TC-AUTH-007 đăng ký email đã tồn tại bị từ chối', async ({ page }, ti) => {
        await registerUi(page, { email: 'user@disanso.test', password: 'KiemThu2026' });
        await expect(err(page).first()).toBeVisible();
        const msg = await err(page).allInnerTexts();
        actual(ti, `Lỗi hiển thị: ${msg.join(' | ')}`);
        await shot(page, 'TC-AUTH-007');
    });

    test('TC-AUTH-008 mật khẩu thiếu chữ hoa (chỉ chữ thường + số) bị từ chối 422, khớp gợi ý giao diện', async ({ page }, ti) => {
        // Vòng 2 (sau sửa qa_1): quy tắc máy chủ Password::min(8)->mixedCase()->numbers().
        const email = `qa_${rnd()}@disanso.test`;
        await registerUi(page, { email, password: 'kiemthu2026' });
        await expect(err(page).first()).toBeVisible();
        const uiErr = (await err(page).allInnerTexts()).join(' | ');
        const hint = (await page.locator('.field-hint').allInnerTexts().catch(() => [])).join(' ');
        const stayed = page.url().includes('/dang-ky');
        // Gửi thẳng (Accept JSON) để kiểm mã HTTP của máy chủ
        const r = await send(page.request, 'POST', '/vi/dang-ky', { name: 'Kiểm Thử V5', email: `qa_${rnd()}@disanso.test`, password: 'kiemthu2026', password_confirmation: 'kiemthu2026' });
        const pwErr = r.body?.errors?.password ?? [];
        actual(ti, `Giao diện: ${stayed ? 'ở lại /vi/dang-ky' : 'RỜI trang đăng ký'}, lỗi "${uiErr}" (gợi ý: "${hint}"); POST JSON → HTTP ${r.status}, lỗi password: ${JSON.stringify(pwErr)}.`);
        await shot(page, 'TC-AUTH-008');
        expect(stayed).toBe(true);
        expect(r.status).toBe(422);
        expect(pwErr.length).toBeGreaterThan(0);
    });

    test('TC-AUTH-009 đăng xuất hủy phiên, vào lại trang tài khoản bị chuyển tới đăng nhập', async ({ page }, ti) => {
        await loginUi(page, 'member5@disanso.test', PASSWORD);
        await page.waitForURL(/\/vi\/tai-khoan/);
        const r = await send(page.request, 'POST', '/vi/dang-xuat', {}, { accept: 'html', json: false });
        await page.goto('/vi/tai-khoan');
        expect(page.url()).toContain('/dang-nhap');
        actual(ti, `POST /vi/dang-xuat → ${r.status} ${r.location}; GET /vi/tai-khoan → chuyển tới ${new URL(page.url()).pathname}.`);
        await shot(page, 'TC-AUTH-009');
    });
});
