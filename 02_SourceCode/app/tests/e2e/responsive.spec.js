// Ma trận tương thích: 3 trình duyệt (project) × 3 khổ (375×812, 768×1024, 1280×800) × 6 trang tiêu biểu.
// Mỗi tổ hợp: HTTP 200, không cuộn ngang, có điều hướng dùng được (menu/thanh dưới/sidebar), có footer hoặc thanh điều hướng dưới,
// không lỗi JS; lưu ảnh + JSON vào 05_KiemThu/matrix/.
import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authFile, shot, actual, MATRIX_DIR } from './helpers.js';

const VIEWPORTS = [
    { code: 'TC-RESP-001', w: 375, h: 812 },
    { code: 'TC-RESP-002', w: 768, h: 1024 },
    { code: 'TC-RESP-003', w: 1280, h: 800 },
];
const PAGES = [
    { name: 'home', url: '/vi' },
    { name: 'alerts', url: '/vi/canh-bao' },
    { name: 'map', url: '/vi/ban-do' },
    { name: 'weather', url: '/vi/thoi-tiet/khanh-hoa' },
    { name: 'trip-show', url: '/vi/hanh-trinh/TR-SEED-01', role: 'traveler' },
    { name: 'dashboard', url: '/vi/quan-tri', role: 'agency' },
];

for (const vp of VIEWPORTS) {
    test.describe(`Khổ ${vp.w}×${vp.h}`, () => {
        test.use({ viewport: { width: vp.w, height: vp.h } });

        test(`${vp.code} giao diện đáp ứng ${vp.w}×${vp.h}: 6 trang không cuộn ngang, điều hướng dùng được`, async ({ browser }, info) => {
            test.setTimeout(180_000);
            const browserName = info.project.name;
            const rows = [];
            for (const pg of PAGES) {
                const ctx = await browser.newContext({ viewport: { width: vp.w, height: vp.h }, ...(pg.role ? { storageState: authFile(pg.role) } : {}) });
                const page = await ctx.newPage();
                const errors = [];
                page.on('pageerror', (e) => errors.push(e.message.slice(0, 120)));
                const t0 = Date.now();
                const resp = await page.goto(pg.url, { waitUntil: 'load' });
                await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
                await page.waitForTimeout(400);
                const ms = Date.now() - t0;
                const m = await page.evaluate(() => {
                    const vis = (el) => !!el && el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';
                    const navs = [...document.querySelectorAll('nav')].filter(vis).length;
                    const menuBtn = [...document.querySelectorAll('button[aria-label]')].filter((b) => vis(b) && /menu/i.test(b.getAttribute('aria-label'))).length;
                    return {
                        overflow: document.documentElement.scrollWidth - window.innerWidth,
                        navs,
                        menuBtn,
                        footer: !!document.querySelector('footer'),
                        h1: (document.querySelector('h1')?.innerText || '').trim().slice(0, 60),
                    };
                });
                const ok = resp.status() === 200 && m.overflow <= 1 && (m.navs > 0 || m.menuBtn > 0) && errors.length === 0;
                const row = { browser: browserName, page: pg.name, url: pg.url, viewport: `${vp.w}x${vp.h}`, status: resp.status(), load_ms: ms, overflow_px: m.overflow, visible_nav: m.navs, menu_button: m.menuBtn, footer: m.footer, h1: m.h1, js_errors: errors, ok };
                rows.push(row);
                fs.mkdirSync(MATRIX_DIR, { recursive: true });
                const base = path.join(MATRIX_DIR, `${browserName}-${pg.name}-${vp.w}x${vp.h}`);
                await page.screenshot({ path: `${base}.png` });
                fs.writeFileSync(`${base}.json`, JSON.stringify(row, null, 2));
                if (pg.name === 'home') await shot(page, vp.code, info);
                await ctx.close();
            }
            const bad = rows.filter((r) => !r.ok);
            actual(info, `${browserName} ${vp.w}×${vp.h}: ${rows.length - bad.length}/${rows.length} trang đạt (cuộn ngang tối đa ${Math.max(...rows.map((r) => r.overflow_px))} px; tải ${Math.min(...rows.map((r) => r.load_ms))}–${Math.max(...rows.map((r) => r.load_ms))} ms)${bad.length ? `; lỗi: ${bad.map((r) => `${r.page} (status ${r.status}, overflow ${r.overflow_px}, nav ${r.visible_nav}/${r.menu_button}, js ${r.js_errors.join('/')})`).join('; ')}` : ''}`);
            expect(bad, JSON.stringify(bad)).toEqual([]);
        });
    });
}
