// Ma trận tương thích: 3 trình duyệt (project) × 3 khổ màn hình × 3 trang chính.
// Mỗi ô kiểm: HTTP 200, h1 hiển thị, không cuộn ngang, không lỗi JS/console, phần tử chính hoạt động.
import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { MATRIX_DIR, authFile, actual } from './helpers.js';

const VIEWPORTS = [
    { name: 'mobile', width: 375, height: 812 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'desktop', width: 1280, height: 800 },
];
const PAGES = [
    { key: 'home', url: '/vi', check: async (page) => (await page.locator('main a[href*="/vi/di-san/"]').filter({ visible: true }).count()) > 0 },
// Vòng 2: DepthTabs đổi sang mẫu ARIA tablist/tab (sửa qa_7), không còn <nav>
    { key: 'site-detail', url: '/vi/di-san/hoang-thanh-thang-long', check: async (page) => page.locator('[role="tablist"] [role="tab"][href*="depth="]').first().isVisible() },
    {
        key: 'booking', url: '/vi/tuyen-tham-quan/dau-an-thang-long', auth: 'member',
        check: async (page) => {
            await page.locator('input[name="slot"]:not([disabled])').first().check();
            return page.locator('#booking form button[type="submit"]').isVisible();
        },
    },
];

for (const vp of VIEWPORTS) {
    for (const pg of PAGES) {
        test.describe(`${pg.key} @ ${vp.name}`, () => {
            test.use({ viewport: { width: vp.width, height: vp.height }, ...(pg.auth ? { storageState: authFile(pg.auth) } : {}) });
            test(`COMPAT ${pg.key} ${vp.width}x${vp.height}`, async ({ page, browserName }, ti) => {
                const errors = [];
                page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`));
                page.on('console', (m) => m.type() === 'error' && errors.push(`console: ${m.text()}`));
                const t0 = Date.now();
                const res = await page.goto(pg.url, { waitUntil: 'load' });
                const loadMs = Date.now() - t0;
                await page.waitForTimeout(800);
                const h1 = await page.locator('h1').first().isVisible();
                const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
                const ok = await pg.check(page).catch(() => false);
                const menuBtn = vp.width < 768 ? await page.locator('header button[aria-expanded]').first().isVisible().catch(() => false) : null;
                fs.mkdirSync(MATRIX_DIR, { recursive: true });
                await page.screenshot({ path: path.join(MATRIX_DIR, `${browserName}-${pg.key}-${vp.width}x${vp.height}.png`) });
                const row = { browser: browserName, page: pg.key, viewport: `${vp.width}x${vp.height}`, status: res.status(), h1, overflow, mainOk: ok, menuBtn, loadMs, errors };
                fs.writeFileSync(path.join(MATRIX_DIR, `${browserName}-${pg.key}-${vp.width}x${vp.height}.json`), JSON.stringify(row, null, 2));
                actual(ti, JSON.stringify(row));
                expect(res.status()).toBe(200);
                expect(h1).toBe(true);
                expect(overflow).toBeLessThanOrEqual(1);
                expect(ok).toBe(true);
                expect(errors).toEqual([]);
            });
        });
    }
}

// Vòng 3 – qa_r2_2: chữ in sẵn trong ảnh bìa SVG không được chồng lên h1. Dưới 1024px h1 phải nằm hẳn dưới ảnh;
// từ 1024px h1 phủ lên đáy ảnh nhưng phải nằm dưới dải chữ của SVG (chữ ở 40–56 % chiều cao ảnh).
test('TC-SITE-009 hero trang chi tiết: h1 không chồng chữ trong ảnh bìa ở 375/768/1280 px', async ({ page, browserName }, ti) => {
    const IMG = path.resolve(MATRIX_DIR, '../img');
    const rows = [];
    for (const vp of VIEWPORTS) {
        await page.setViewportSize({ width: vp.width, height: vp.height });
        await page.goto('/vi/di-san/hoang-thanh-thang-long', { waitUntil: 'load' });
        await page.waitForTimeout(600);
        const m = await page.locator('.hero-cover__media').boundingBox();
        const h = await page.locator('.hero-cover h1').boundingBox();
        const textBandBottom = m.y + m.height * 0.56;
        const ok = vp.width < 1024 ? h.y >= m.y + m.height - 1 : h.y >= textBandBottom;
        const file = `TC-SITE-009_${browserName}_${vp.width}x${vp.height}.png`;
        await page.screenshot({ path: path.join(IMG, file), clip: { x: 0, y: Math.max(0, m.y), width: vp.width, height: Math.min(vp.height, h.y + h.height + 40 - Math.max(0, m.y)) } });
        if (browserName === 'chromium' && vp.width === 375) await page.screenshot({ path: path.join(IMG, 'TC-SITE-009.png') });
        rows.push({ viewport: `${vp.width}x${vp.height}`, imgTop: Math.round(m.y), imgBottom: Math.round(m.y + m.height), h1Top: Math.round(h.y), textBandBottom: Math.round(textBandBottom), ok });
    }
    actual(ti, `${browserName}: ` + rows.map((r) => `${r.viewport} ảnh ${r.imgTop}–${r.imgBottom}px, h1 top ${r.h1Top}px (${r.ok ? 'không chồng' : 'CHỒNG'})`).join('; '));
    expect(rows.every((r) => r.ok)).toBe(true);
});
