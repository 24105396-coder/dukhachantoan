// F2 – Thu thập dữ liệu cảnh báo/thời tiết theo khu vực: trang thời tiết (Open-Meteo), danh sách và chi tiết cảnh báo có nguồn + thời điểm cập nhật.
import { test, expect } from '@playwright/test';
import { authFile, shot, actual, send, getPage } from './helpers.js';

test.describe('Cảnh báo và thời tiết công khai', () => {
    test('TC-WX-001 thời tiết Khánh Hòa: hiện tại + 7 ngày dự báo + điểm rủi ro, nguồn Open-Meteo', async ({ page, request }, info) => {
        const { status, page: p } = await getPage(request, '/vi/thoi-tiet/khanh-hoa');
        const { now, forecast, risk } = p.props;
        await page.goto('/vi/thoi-tiet/khanh-hoa');
        await expect(page.locator('h1')).toBeVisible();
        const text = await page.locator('main').innerText();
        await shot(page, 'TC-WX-001', info, { fullPage: true });
        const ageMin = Math.round((Date.now() - Date.parse(now.fetched_at || now.observed_at)) / 60000);
        actual(info, `${status}; now: ${now.temp_c} °C, gió giật ${now.gust_kph} km/h, ${now.weather_label}, nguồn ${now.source}, lấy cách đây ${ageMin} phút; forecast ${forecast.length} ngày (${forecast[0]?.date}…${forecast.at(-1)?.date}); risk ${risk?.score}/mức ${risk?.level}; trang có chữ "Open-Meteo": ${/Open-Meteo/.test(text)}`);
        expect(forecast).toHaveLength(7);
        expect(now.source).toBe('Open-Meteo');
        expect(/Open-Meteo/.test(text)).toBeTruthy();
    });

    test('TC-WX-002 mọi thẻ cảnh báo hiển thị "Nguồn … · Cập nhật …"', async ({ page }, info) => {
        await page.goto('/vi/canh-bao');
        const cards = page.locator('main article[data-severity]');
        const n = await cards.count();
        const texts = await cards.allInnerTexts();
        const withSource = texts.filter((t) => /Nguồn/.test(t) && /Cập nhật/.test(t)).length;
        await shot(page, 'TC-WX-002', info);
        actual(info, `${n} thẻ trên trang 1, ${withSource} thẻ có "Nguồn" và "Cập nhật"; ví dụ: "${(texts[0] || '').split('\n').filter((l) => /Nguồn|Cập nhật/.test(l)).join(' ').slice(0, 160)}"`);
        expect(n).toBeGreaterThan(0);
        expect(withSource).toBe(n);
    });

    test('TC-WX-003 lọc cảnh báo theo mức 4, loại hiểm họa và tỉnh', async ({ page, request }, info) => {
        const s4 = (await getPage(request, '/vi/canh-bao?severity=4')).page.props.alerts.data;
        const hz = s4[0]?.hazard?.code;
        const both = (await getPage(request, `/vi/canh-bao?severity=4&hazard=${hz}`)).page.props.alerts.data;
        const kh = (await getPage(request, '/vi/canh-bao?province=KH')).page.props;
        await page.goto('/vi/canh-bao?severity=4');
        await shot(page, 'TC-WX-003', info);
        actual(info, `severity=4 → ${s4.length} cảnh báo, mức: ${[...new Set(s4.map((a) => a.severity))]}; +hazard=${hz} → ${both.length}, hazard: ${[...new Set(both.map((a) => a.hazard.code))]}; province=KH → ${kh.alerts.data.length} (${kh.alerts.data.map((a) => a.zones.map((z) => z.name).join('/')).slice(0, 4).join('; ')})`);
        expect(s4.length).toBeGreaterThan(0);
        expect(s4.every((a) => a.severity === 4)).toBeTruthy();
        expect(both.every((a) => a.hazard.code === hz && a.severity === 4)).toBeTruthy();
    });

    test('TC-WX-004 chi tiết cảnh báo: nguồn, lịch sử, hướng dẫn, danh bạ gần; mã sai → 404', async ({ page, request }, info) => {
        const list = (await getPage(request, '/vi/canh-bao?severity=4')).page.props.alerts.data;
        const code = list[0].code;
        const { status, page: p } = await getPage(request, `/vi/canh-bao/${code}`);
        const a = p.props.alert;
        const bad = await request.get('/vi/canh-bao/AL-KHONGCO', { maxRedirects: 0 });
        await page.goto(`/vi/canh-bao/${code}`);
        await shot(page, 'TC-WX-004', info, { fullPage: true });
        actual(info, `${code} → ${status}: nguồn "${a.source.name.slice(0, 60)}…" (${a.source.url}), issued ${a.issued_at}, cập nhật ${a.updated_at_source}; history ${a.history.length} bước; guides ${a.guides.length}; nearbyContacts ${p.props.nearbyContacts.length}; affected_trips_count với khách: ${a.affected_trips_count ?? 'không có'}; /vi/canh-bao/AL-KHONGCO → ${bad.status()}`);
        expect(a.source.name).toBeTruthy();
        expect(a.history.length).toBeGreaterThan(0);
        expect(a.affected_trips_count).toBeUndefined();
        expect(bad.status()).toBe(404);
    });

    test('TC-WX-005 vùng thời tiết không tồn tại → 404', async ({ page, request }, info) => {
        const r = await request.get('/vi/thoi-tiet/khong-co-vung', { maxRedirects: 0 });
        const api = await request.get('/api/v1/zones/khong-co-vung/weather');
        await page.goto('/vi/thoi-tiet/khong-co-vung');
        await shot(page, 'TC-WX-005', info);
        actual(info, `/vi/thoi-tiet/khong-co-vung → ${r.status()}; /api/v1/zones/khong-co-vung/weather → ${api.status()} ${await api.text()}`);
        expect(r.status()).toBe(404);
        expect(api.status()).toBe(404);
    });
});

test.describe('Nguồn dữ liệu (admin)', () => {
    test.use({ storageState: authFile('admin') });

    test('TC-WX-006 trang Nguồn dữ liệu: Open-Meteo trạng thái ok, nút Lấy ngay chạy thật @chromium', async ({ page, request }, info) => {
        const before = (await getPage(request, '/vi/quan-tri/nguon')).page.props.sources;
        const om = before.find((s) => /open-?meteo/i.test(s.code + s.provider));
        await page.goto('/vi/quan-tri/nguon');
        const btn = page.locator('article', { hasText: om.name }).getByRole('button', { name: /Lấy ngay/ });
        await btn.click();
        await expect(page.locator('[role=status], [role=alert]').filter({ hasText: /\S/ }).first()).toBeVisible({ timeout: 20_000 });
        await page.waitForTimeout(1500);
        const flash = (await page.locator('[role=status], [role=alert]').allInnerTexts()).join(' | ').slice(0, 200);
        const after = (await getPage(request, '/vi/quan-tri/nguon')).page.props.sources.find((s) => s.code === om.code);
        await shot(page, 'TC-WX-006', info);
        actual(info, `${before.length} nguồn (${before.map((s) => `${s.code}:${s.last_status}`).join(', ')}); ${om.code} last_polled_at ${om.last_polled_at} → ${after.last_polled_at}, last_status ${after.last_status}; flash "${flash}"`);
        expect(after.last_status).toBe('ok');
        expect(Date.parse(after.last_polled_at)).toBeGreaterThanOrEqual(Date.parse(om.last_polled_at || 0));
    });
});
