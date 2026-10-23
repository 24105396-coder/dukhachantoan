// Đa ngữ vi/en và 2 theme (Ban ngày / Trực ban đêm).
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect } from '@playwright/test';
import { authFile, writerFile, shot, actual, send, getPage } from './helpers.js';

const here = path.dirname(fileURLToPath(import.meta.url));
const KEYS = Object.keys(JSON.parse(fs.readFileSync(path.resolve(here, '../../lang/en.json'), 'utf8')));

test.describe('Đa ngữ', () => {
    test('TC-I18N-001 nút EN trên trang cảnh báo → /en/canh-bao, <html lang="en">, giao diện tiếng Anh', async ({ page }, info) => {
        await page.goto('/vi/canh-bao');
        await page.locator('a[hreflang=en]').locator('visible=true').first().click();
        await page.waitForURL(/\/en\/canh-bao/);
        const h1 = (await page.locator('h1').first().innerText()).trim();
        await shot(page, 'TC-I18N-001', info);
        actual(info, `${info.project.name}: URL ${new URL(page.url()).pathname}, lang=${await page.getAttribute('html', 'lang')}, h1 "${h1}"`);
        expect(await page.getAttribute('html', 'lang')).toBe('en');
        expect(h1).toBe('Risk alerts');
    });

    test('TC-I18N-002 nội dung cảnh báo bản tiếng Anh (bản dịch dữ liệu), có nhãn nguồn/cập nhật tiếng Anh', async ({ page }, info) => {
        await page.goto('/en/canh-bao/AL-SIM-0006');
        const h1 = (await page.locator('h1').first().innerText()).trim();
        const text = await page.locator('main').innerText();
        await shot(page, 'TC-I18N-002', info);
        actual(info, `h1 "${h1}"; có "Source": ${/Source/.test(text)}, có "Updated": ${/Updated/.test(text)}; còn chữ "Nguồn:" tiếng Việt: ${/Nguồn:/.test(text)}`);
        expect(h1).toMatch(/SIMULATION|Flood/i);
        expect(/Nguồn:/.test(text)).toBeFalsy();
    });

    test('TC-I18N-003 10 trang tiếng Anh không lộ khóa dịch thô hoặc chữ undefined/NaN', async ({ page }, info) => {
        const urls = ['/en', '/en/canh-bao', '/en/ban-do', '/en/thoi-tiet/khanh-hoa', '/en/danh-ba-khan-cap', '/en/huong-dan-ung-pho', '/en/huong-dan-ung-pho/ung-pho-bao', '/en/gioi-thieu', '/en/dang-nhap', '/en/dang-ky'];
        const issues = [];
        for (const u of urls) {
            await page.goto(u);
            await page.waitForTimeout(300);
            const t = await page.locator('body').innerText();
            const raw = KEYS.filter((k) => k.includes('.') && t.includes(k)).slice(0, 3);
            if (raw.length || /\bundefined\b|\bNaN\b|\[object Object\]/.test(t)) issues.push(`${u}: ${raw.join(',')} ${(t.match(/\bundefined\b|\bNaN\b|\[object Object\]/) || [''])[0]}`);
        }
        await page.goto('/en/thoi-tiet/khanh-hoa');
        await shot(page, 'TC-I18N-003', info);
        actual(info, `${urls.length} trang /en kiểm tra; trang có vấn đề: ${issues.length ? issues.join(' | ') : 'không'}`);
        expect(issues).toEqual([]);
    });
});

test.describe('Theme', () => {
    test('TC-THEME-001 khách bật "Trực ban đêm" → data-theme=dark, lưu localStorage, giữ sau tải lại', async ({ page }, info) => {
        await page.emulateMedia({ colorScheme: 'light' });
        await page.goto('/vi');
        const before = await page.getAttribute('html', 'data-theme');
        await page.locator('button[aria-pressed]').locator('visible=true').first().click();
        await page.waitForTimeout(300);
        const after = await page.getAttribute('html', 'data-theme');
        const stored = await page.evaluate(() => localStorage.getItem('dka-theme'));
        await page.reload();
        const reload = await page.getAttribute('html', 'data-theme');
        const bg = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
        await shot(page, 'TC-THEME-001', info);
        actual(info, `${info.project.name}: ${before} → ${after}; localStorage dka-theme=${stored}; sau tải lại ${reload}; màu nền body ${bg}`);
        expect(after).toBe(before === 'dark' ? 'light' : 'dark');
        expect(reload).toBe(after);
    });

    test('TC-THEME-002 du khách đổi theme → lưu vào tài khoản (users.theme), thiết bị khác cũng thấy @chromium', async ({ browser }, info) => {
        const ctx = await browser.newContext({ storageState: authFile('traveler1'), colorScheme: 'light' });
        const page = await ctx.newPage();
        await page.goto('/vi/hanh-trinh');
        const before = await page.getAttribute('html', 'data-theme');
        const req = page.waitForRequest((r) => r.url().includes('/tai-khoan/giao-dien'), { timeout: 5000 }).then((r) => r.method()).catch(() => 'không gửi');
        await page.locator('button[aria-pressed]').locator('visible=true').first().click();
        const method = await req;
        await page.waitForTimeout(800);
        const other = await browser.newContext({ storageState: authFile('traveler1'), colorScheme: 'light' });
        const p2 = await other.newPage();
        await p2.goto('/vi/hanh-trinh');
        const server = (await getPage(other.request, '/vi/hanh-trinh')).page.props.auth.user.theme;
        const onOther = await p2.getAttribute('html', 'data-theme');
        await shot(p2, 'TC-THEME-002', info);
        actual(info, `trước ${before}; yêu cầu ${method} /tai-khoan/giao-dien; auth.user.theme trên máy chủ = ${server}; context mới (không localStorage) hiển thị ${onOther}`);
        expect(server).toBe(before === 'dark' ? 'light' : 'dark');
        expect(onOther).toBe(server);
        await send(ctx.request, 'PATCH', '/vi/tai-khoan/giao-dien', { theme: 'light' }, { headers: { 'X-Inertia': 'true' } });
        await other.close();
        await ctx.close();
    });
});

test.describe('Thông điệp lỗi máy chủ', () => {
    test.use({ storageState: async ({}, use, info) => use(writerFile(info)) });

    test('TC-I18N-004 [Biên] thông điệp lỗi hợp lệ từ máy chủ dùng tên trường dễ hiểu (không lộ khóa kỹ thuật như legs.0.zone_code)', async ({ page, request }, info) => {
        const d = new Date(Date.now() + 10 * 86400_000 + 7 * 3600_000).toISOString().slice(0, 10);
        const r = await send(request, 'POST', '/vi/hanh-trinh/tao', {
            title: 'E2E thông điệp lỗi', start_date: d, end_date: d, group_size: 1, contact_name: 'QA', contact_phone: '0901234567',
            legs: [{ zone_code: 'khong-co-vung', starts_at: `${d}T08:00`, ends_at: `${d}T10:00` }], members: [{ full_name: '', age_group: 'xyz' }],
        });
        const msgs = Object.values(r.body.errors || {}).flat();
        const raw = msgs.filter((m) => /\b[a-z]+(\.\d+)?\.[a-z_]+\b|_[a-z]/.test(m));
        await page.goto('/vi/hanh-trinh/tao');
        await page.evaluate((list) => { document.querySelector('main').insertAdjacentHTML('afterbegin', `<pre id="qa-msgs" style="white-space:pre-wrap;background:#fff3f3;padding:12px;border-radius:12px">${list.join('\n').replace(/</g, '&lt;')}</pre>`); }, msgs);
        await shot(page, 'TC-I18N-004', info);
        actual(info, `${r.status}; ${msgs.length} thông điệp; chứa khóa kỹ thuật: ${raw.length} – ví dụ: ${raw.slice(0, 3).join(' | ')}`);
        expect(r.status).toBe(422);
        expect(raw, raw.join(' | ')).toEqual([]);
    });
});
