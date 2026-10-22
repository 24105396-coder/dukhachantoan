// Chạy khi đã dừng dịch vụ analytics (docker compose stop analytics) với E2E_ANALYTICS_DOWN=1.
import { test, expect } from '@playwright/test';
import { authFile, actual, shot, getPage, showText, send } from './helpers.js';

test.skip(!process.env.E2E_ANALYTICS_DOWN, 'Chỉ chạy khi analytics đã dừng');

test('TC-SEARCH-006 tắt dịch vụ phân tích: tìm kiếm tự chuyển FULLTEXT, có thông báo', async ({ page }, ti) => {
    const t0 = Date.now();
    await page.goto('/vi/tim-kiem?q=ho%C3%A0ng%20th%C3%A0nh');
    const ms = Date.now() - t0;
    const { page: p } = await getPage(page.request, '/vi/tim-kiem?q=ho%C3%A0ng%20th%C3%A0nh');
    const notice = await page.getByText('tạm thời không khả dụng', { exact: false }).count();
    actual(ti, `engine=${p.props.engine}, ${p.props.results.length} kết quả (đầu: ${p.props.results[0]?.slug}), thông báo hiển thị: ${notice > 0}, tải trang ${ms} ms.`);
    await shot(page, 'TC-SEARCH-006');
    expect(p.props.engine).toBe('fallback');
    expect(p.props.results.length).toBeGreaterThan(0);
    expect(notice).toBeGreaterThan(0);
});

test('TC-PY-005 tắt dịch vụ phân tích: /healthz báo analytics down, trang công khai vẫn chạy', async ({ page, request }, ti) => {
    const h = await (await request.get('/healthz')).json();
    const api = await (await request.get('/api/v1/search?q=hue')).json();
    const site = await request.get('/vi/di-san/hoang-thanh-thang-long');
    actual(ti, `/healthz=${JSON.stringify(h)}; /api/v1/search engine=${api.engine} (${api.data.length} kết quả); trang chi tiết → ${site.status()}.`);
    await showText(page, 'TC-PY-005', 'Hệ thống khi analytics dừng', `GET /healthz\n${JSON.stringify(h, null, 2)}\n\nGET /api/v1/search?q=hue → engine=${api.engine}, ${api.data.length} kết quả\nGET /vi/di-san/hoang-thanh-thang-long → ${site.status()}`);
    expect(h.analytics).toBe('down');
    expect(api.engine).toBe('fallback');
    expect(site.status()).toBe(200);
});

test.describe('quản trị khi analytics dừng', () => {
    test.use({ storageState: authFile('manager') });
    test('TC-ADMIN-011 kiểm định chất lượng khi analytics dừng báo lỗi thân thiện, không 500', async ({ page }, ti) => {
        await page.goto('/vi/quan-tri/chat-luong-du-lieu');
        const r = await send(page.request, 'POST', '/vi/quan-tri/chat-luong-du-lieu/kiem-tra', {}, { accept: 'html', headers: { Referer: 'http://localhost:8080/vi/quan-tri/chat-luong-du-lieu' } });
        const { page: p } = await getPage(page.request, '/vi/quan-tri/chat-luong-du-lieu');
        await page.reload();
        await shot(page, 'TC-ADMIN-011');
        actual(ti, `POST kiểm tra → ${r.status}; analyticsUp=${p.props.analyticsUp}, lastError="${p.props.lastError}", flash.error="${p.props.flash?.error ?? ''}".`);
        expect(r.status).toBeLessThan(500);
        expect(p.props.analyticsUp).toBe(false);
    });
});
