// F5 – Bản đồ vùng rủi ro (Leaflet + GeoJSON): lớp vùng tô màu theo mức, lớp cảnh báo có hình học, chặng của tôi, chú giải, chọn vùng.
import { test, expect } from '@playwright/test';
import { authFile, shot, actual, getPage, showText } from './helpers.js';

async function openMap(page) {
    await page.goto('/vi/ban-do');
    await expect(page.locator('.leaflet-container')).toBeVisible({ timeout: 20_000 });
    await page.waitForFunction(() => document.querySelectorAll('.leaflet-overlay-pane path').length > 10, null, { timeout: 20_000 });
    await page.waitForTimeout(500);
}

test.describe('Bản đồ – khách', () => {
    test('TC-MAP-001 bản đồ vẽ 61 vùng tô màu theo mức rủi ro + chú giải 5 mức', async ({ page, request }, info) => {
        const { page: p } = await getPage(request, '/vi/ban-do');
        const feats = p.props.zonesGeo.features;
        await openMap(page);
        const paths = await page.locator('.leaflet-overlay-pane path').count();
        const fills = await page.locator('.leaflet-overlay-pane path').evaluateAll((els) => [...new Set(els.map((e) => e.getAttribute('fill')))].filter(Boolean));
        const legend = p.props.legend;
        await shot(page, 'TC-MAP-001', info);
        actual(info, `zonesGeo ${feats.length} feature (${feats.filter((f) => f.properties.level !== undefined).length} có level); ${paths} path SVG trong lớp phủ; ${fills.length} màu tô khác nhau; legend ${legend.length} mức: ${legend.map((l) => `${l.level}:${l.label}`).join(', ')}`);
        expect(feats.length).toBe(61);
        expect(paths).toBeGreaterThanOrEqual(30);
        expect(legend.length).toBe(5);
    });

    test('TC-MAP-002 lớp cảnh báo có hình học (Circle/Polygon) bật/tắt được', async ({ page, request }, info) => {
        const { page: p } = await getPage(request, '/vi/ban-do');
        const geoAlerts = p.props.alerts.filter((a) => a.geometry);
        await openMap(page);
        const on = await page.locator('.leaflet-overlay-pane path').count();
        await page.locator('fieldset label', { hasText: /Phạm vi cảnh báo/ }).locator('input').uncheck();
        await page.waitForTimeout(400);
        const off = await page.locator('.leaflet-overlay-pane path').count();
        await page.locator('fieldset label', { hasText: /Phạm vi cảnh báo/ }).locator('input').check();
        await page.waitForTimeout(400);
        await shot(page, 'TC-MAP-002', info);
        actual(info, `${p.props.alerts.length} cảnh báo active trong props, ${geoAlerts.length} có geometry (${geoAlerts.map((a) => `${a.code}:${a.geometry.type}`).join(', ')}); path khi bật ${on}, tắt lớp cảnh báo ${off}`);
        expect(geoAlerts.length).toBeGreaterThan(0);
        expect(off).toBeLessThan(on);
    });

    test('TC-MAP-003 bấm một vùng → bảng bên hiện tên vùng, mức, liên kết thời tiết', async ({ page }, info) => {
        await openMap(page);
        // chọn vùng có nhiều cảnh báo nhất bằng cách bấm path đầu tiên có fill
        // bấm vào điểm giữa hình bao của một vùng (WebKit không nhận click ép buộc lên phần tử SVG bị che)
        const boxes = await page.locator('.leaflet-overlay-pane path.leaflet-interactive').evaluateAll((els) => els.map((e) => { const r = e.getBoundingClientRect(); return { x: r.x + r.width / 2, y: r.y + r.height / 2, w: r.width, h: r.height }; }));
        const vp = page.viewportSize();
        const target = boxes.filter((b) => b.w > 20 && b.h > 20 && b.x > 0 && b.y > 0 && b.x < vp.width * 0.6 && b.y < vp.height).sort((a, b) => b.w * b.h - a.w * a.h)[0] || boxes[0];
        await page.mouse.click(target.x, target.y);
        await page.waitForTimeout(600);
        const title = (await page.locator('#map-panel-title').innerText()).trim();
        const link = await page.locator('aside a', { hasText: /Thời tiết/ }).first().getAttribute('href').catch(() => null);
        await shot(page, 'TC-MAP-003', info);
        actual(info, `Sau khi bấm vùng: tiêu đề bảng "${title}", liên kết thời tiết ${link}`);
        expect(title).not.toBe('Cảnh báo đang hiệu lực');
        expect(link).toContain('/thoi-tiet/');
    });
});

test.describe('Bản đồ – du khách đăng nhập', () => {
    test.use({ storageState: authFile('traveler') });

    test('TC-MAP-004 lớp "Chặng của tôi" hiển thị điểm chặng của du khách', async ({ page, request }, info) => {
        const { page: p } = await getPage(request, '/vi/ban-do');
        const legs = p.props.myLegs;
        await openMap(page);
        const box = page.locator('fieldset label', { hasText: /Chặng của tôi/ }).locator('input');
        if (!(await box.isChecked())) await box.check();
        await page.waitForTimeout(400);
        const on = await page.locator('.leaflet-overlay-pane path').count();
        await box.uncheck();
        await page.waitForTimeout(400);
        const off = await page.locator('.leaflet-overlay-pane path').count();
        await box.check();
        await page.waitForTimeout(400);
        const markers = on - off;
        await shot(page, 'TC-MAP-004', info);
        actual(info, `myLegs ${legs.length} chặng (${legs.slice(0, 4).map((l) => l.zone.name).join(', ')}…); path SVG khi bật lớp "Chặng của tôi" ${on}, khi tắt ${off} → ${markers} đối tượng (điểm chặng + tuyến nối)`);
        expect(legs.length).toBeGreaterThan(0);
        expect(markers).toBeGreaterThan(0);
    });
});

test.describe('Bản đồ – dữ liệu GeoJSON', () => {
    test('TC-MAP-005 GeoJSON vùng và cảnh báo hợp lệ (FeatureCollection, geometry, properties.level)', async ({ page, request }, info) => {
        const z = await (await request.get('/api/v1/zones.geojson')).json();
        const a = await (await request.get('/api/v1/alerts.geojson')).json();
        const types = [...new Set(z.features.map((f) => f.geometry?.type))];
        const ok = z.features.every((f) => f.type === 'Feature' && f.geometry && f.properties.code && f.properties.level !== undefined);
        await showText(page, 'TC-MAP-005 /api/v1/zones.geojson, /api/v1/alerts.geojson', `zones.geojson: ${z.type}, ${z.features.length} feature, kiểu ${types.join('/')}\n${JSON.stringify(z.features[0], null, 1).slice(0, 1200)}\n\nalerts.geojson: ${a.type}, ${a.features.length} feature`);
        await shot(page, 'TC-MAP-005', info);
        actual(info, `zones.geojson ${z.type} ${z.features.length} feature, kiểu ${types.join('/')}, đủ code+level: ${ok}; alerts.geojson ${a.type} ${a.features.length} feature`);
        expect(z.type).toBe('FeatureCollection');
        expect(ok).toBeTruthy();
        expect(a.type).toBe('FeatureCollection');
    });
});
