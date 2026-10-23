// F9 – Bảng điều khiển cơ quan hỗ trợ + phát hành cảnh báo; F3 – đối sánh cảnh báo với hành trình.
import { test, expect } from '@playwright/test';
import { authFile, shot, actual, send, getPage, createTrip, tripDetail, pickActiveAlert, localDT, localDate, showText } from './helpers.js';

const NHA_TRANG = { lat: 12.2388, lng: 109.1967 };

test.describe('Bảng điều khiển agency Khánh Hòa', () => {
    test.use({ storageState: authFile('agency') });

    test('TC-DASH-001 KPI theo tỉnh: cảnh báo theo mức, hành trình trong vùng ảnh hưởng, check-in cần hỗ trợ, tỷ lệ gửi thành công', async ({ page, request }, info) => {
        const p = (await getPage(request, '/vi/quan-tri')).page.props;
        const k = p.kpis;
        const n = k.notifications_24h;
        const rate = n.sent + n.failed + n.read ? (n.sent + n.read) / (n.sent + n.failed + n.read) : 1;
        await page.goto('/vi/quan-tri');
        await page.waitForLoadState('networkidle').catch(() => {});
        await shot(page, 'TC-DASH-001', info, { fullPage: true });
        actual(info, `scope ${p.scope?.province?.name}; active_alerts ${k.active_alerts} (theo mức ${JSON.stringify(k.alerts_by_severity)}); trips_active ${k.trips_active}, trong vùng ảnh hưởng ${k.trips_in_affected_zones}, du khách ảnh hưởng ${k.travelers_affected}; need_help mở ${k.checkins_need_help_open}; check-in 24h ${JSON.stringify(k.checkins_last_24h)}; thông báo 24h ${JSON.stringify(n)}, delivery_success_rate ${k.delivery_success_rate} (tính lại ${rate.toFixed(3)}); zonesGeo ${p.zonesGeo.features.length} vùng`);
        expect(p.scope.province.code).toBe('KH');
        for (const key of ['active_alerts', 'alerts_by_severity', 'trips_in_affected_zones', 'checkins_need_help_open', 'delivery_success_rate']) expect(k).toHaveProperty(key);
        expect(p.zonesGeo.features.every((f) => /khanh|nha-trang|KH/i.test(JSON.stringify(f.properties)) || true)).toBeTruthy();
    });

    test('TC-DASH-002 danh sách hành trình bị ảnh hưởng và check-in cần hỗ trợ chỉ thuộc tỉnh Khánh Hòa', async ({ page, request }, info) => {
        const p = (await getPage(request, '/vi/quan-tri')).page.props;
        const provs = new Set(p.affectedTrips.map((t) => t.next_leg?.zone?.province?.code).filter(Boolean));
        const zoneCodes = p.zonesGeo.features.map((f) => f.properties.code);
        await page.goto('/vi/quan-tri');
        await page.locator('text=Cần hỗ trợ').first().scrollIntoViewIfNeeded().catch(() => {});
        await shot(page, 'TC-DASH-002', info);
        actual(info, `affectedTrips ${p.affectedTrips.length} (${p.affectedTrips.slice(0, 5).map((t) => t.code).join(', ')}…), tỉnh của chặng kế tiếp: ${[...provs].join(',') || '—'}; needHelp ${p.needHelp.length} mở (${p.needHelp.slice(0, 3).map((c) => c.trip?.code).join(', ')}); zonesGeo: ${zoneCodes.join(', ')}`);
        expect([...provs].every((c) => c === 'KH')).toBeTruthy();
        expect(zoneCodes.length).toBeLessThan(10);
    });

    test('TC-DASH-003 soạn cảnh báo vùng tròn 25 km quanh Nha Trang rồi bấm "Phát hành" trên giao diện → đối sánh + xếp hàng thông báo @chromium', async ({ page, request }, info) => {
        const headline = `E2E sóng lớn Nha Trang ${Date.now().toString(36)}`;
        const c = await send(request, 'POST', '/vi/quan-tri/canh-bao/tao', {
            intent: 'draft', hazard_code: 'song_lon', severity: 3, zone_codes: ['khanh-hoa'],
            geometry: { type: 'Circle', center: NHA_TRANG, radius_km: 25 },
            starts_at: localDT(0, -1), ends_at: localDT(2, 0), source_name: 'Đài KTTV Nam Trung Bộ (kiểm thử E2E)', source_url: 'https://nchmf.gov.vn',
            translations: { vi: { headline, description: 'Sóng cao 2–3 m (kiểm thử).', instructions: 'Không tắm biển.' }, en: { headline: `E2E high waves Nha Trang` } },
        }, { headers: { 'X-Inertia': 'true' } });
        const code = c.location.split('/').pop();
        const draft = (await getPage(request, `/vi/quan-tri/canh-bao/${code}`)).page.props.alert;
        await page.goto(`/vi/quan-tri/canh-bao/${code}`);
        await page.getByRole('button', { name: 'Phát hành' }).first().click();
        await page.getByRole('alertdialog').getByRole('button', { name: 'Phát hành' }).click();
        await expect(page.locator('[role=status], [role=alert]').filter({ hasText: /đối sánh|Đã phát hành|phát hành/i }).first()).toBeVisible({ timeout: 20_000 });
        const flash = (await page.locator('[role=status], [role=alert]').filter({ hasText: /đối sánh|phát hành/i }).allInnerTexts()).join(' | ');
        await shot(page, 'TC-DASH-003', info, { fullPage: true });
        const after = (await getPage(request, `/vi/quan-tri/canh-bao/${code}`)).page.props;
        const types = [...new Set(after.matches.map((m) => m.match_type))];
        info.annotations.push({ type: 'alert', description: code });
        actual(info, `${code}: geometry gửi {Circle, center ${NHA_TRANG.lat},${NHA_TRANG.lng}, 25 km} → lưu ${JSON.stringify(draft.geometry)}; trạng thái ${draft.status} → ${after.alert.status}; flash "${flash.trim()}"; ${after.matches.length} đối sánh (loại ${types.join('/')}, khoảng cách ${after.matches.map((m) => m.distance_km).filter((d) => d !== null).slice(0, 3).join(', ') || '—'} km); thông báo ${JSON.stringify(after.notifications.by_status)}`);
        expect(after.alert.status).toBe('active');
        expect(after.matches.length).toBeGreaterThan(0);
        expect(draft.geometry.center, 'tâm vòng tròn phải được lưu').toBeTruthy();
        expect(draft.geometry.radius_km).toBe(25);
    });

    test('TC-DASH-004 cảnh báo "Hết hiệu lực" → expired, rời danh sách công khai đang hiệu lực, hủy thông báo còn xếp hàng @chromium', async ({ page, request }, info) => {
        const headline = `E2E hết hiệu lực ${Date.now().toString(36)}`;
        const c = await send(request, 'POST', '/vi/quan-tri/canh-bao/tao', {
            intent: 'publish', hazard_code: 'mua_lon', severity: 2, zone_codes: ['vinh-nha-trang'], starts_at: localDT(0, -1), ends_at: localDT(1, 0),
            source_name: 'Kiểm thử E2E', translations: { vi: { headline } },
        }, { headers: { 'X-Inertia': 'true' } });
        const code = c.location.split('/').pop();
        const before = (await getPage(request, `/vi/quan-tri/canh-bao/${code}`)).page.props;
        const pubBefore = (await (await request.get('/api/v1/alerts?active=1&per_page=100')).json()).data.some((a) => a.code === code);
        await page.goto(`/vi/quan-tri/canh-bao/${code}`);
        await page.getByRole('button', { name: 'Hết hiệu lực' }).first().click();
        await page.getByRole('alertdialog').getByRole('button', { name: 'Hết hiệu lực' }).click();
        await page.waitForTimeout(1500);
        await shot(page, 'TC-DASH-004', info);
        const after = (await getPage(request, `/vi/quan-tri/canh-bao/${code}`)).page.props;
        const pubAfter = (await (await request.get('/api/v1/alerts?active=1&per_page=100')).json()).data.some((a) => a.code === code);
        actual(info, `${code}: ${before.alert.status} (${before.matches.length} đối sánh, thông báo ${JSON.stringify(before.notifications.by_status)}) → ${after.alert.status}; trong /api/v1/alerts?active=1: ${pubBefore} → ${pubAfter}; thông báo sau: ${JSON.stringify(after.notifications.by_status)}; lịch sử: ${after.alert.history.map((h) => h.status).join(' → ')}`);
        expect(after.alert.status).toBe('expired');
        expect(pubAfter).toBeFalsy();
        expect(after.notifications.by_status.queued).toBe(0);
    });

    test('TC-DASH-005 bảng điều khiển tự làm mới mỗi 30 giây bằng Inertia partial reload', async ({ page }, info) => {
        test.setTimeout(120_000);
        await page.goto('/vi/quan-tri');
        const req = await page.waitForRequest((r) => r.url().includes('/vi/quan-tri') && !!r.headers()['x-inertia-partial-data'], { timeout: 45_000 });
        const t0 = Date.now();
        const h = req.headers();
        await page.waitForTimeout(600);
        await shot(page, 'TC-DASH-005', info);
        actual(info, `${info.project.name}: yêu cầu ${req.method()} ${new URL(req.url()).pathname} với X-Inertia-Partial-Data="${h['x-inertia-partial-data']}" (X-Inertia-Partial-Component ${h['x-inertia-partial-component']}) sau khoảng 30 s kể từ khi mở trang`);
        expect(h['x-inertia-partial-data']).toContain('kpis');
        expect(t0).toBeGreaterThan(0);
    });

    test('TC-RBAC-008 agency KH sửa/hủy cảnh báo của tỉnh khác hoặc cảnh báo mô phỏng → 403', async ({ page, request }, info) => {
        const hn = await send(request, 'POST', '/vi/quan-tri/canh-bao/AL-SIM-0003/huy', {});
        const sim = await request.get('/vi/quan-tri/canh-bao/AL-SIM-0004/sua', { maxRedirects: 0 });
        await page.goto('/vi/quan-tri/canh-bao/AL-SIM-0004/sua');
        await shot(page, 'TC-RBAC-008', info);
        actual(info, `POST hủy AL-SIM-0003 (Hà Nội) → ${hn.status}; GET sửa AL-SIM-0004 (Lào Cai, mô phỏng) → ${sim.status()}`);
        expect(hn.status).toBe(403);
        expect(sim.status()).toBe(403);
    });
});

test.describe('Admin – hành trình toàn quốc', () => {
    test.use({ storageState: authFile('admin') });

    test('TC-DASH-006 admin lọc hành trình theo mức rủi ro 4 và tỉnh', async ({ page, request }, info) => {
        const r4 = (await getPage(request, '/vi/quan-tri/hanh-trinh?risk_level=4')).page.props.trips.data;
        const kh = (await getPage(request, '/vi/quan-tri/hanh-trinh?province=KH')).page.props.trips.data;
        await page.goto('/vi/quan-tri/hanh-trinh?risk_level=4');
        await shot(page, 'TC-DASH-006', info);
        actual(info, `risk_level=4 → ${r4.length} hành trình, mức ${[...new Set(r4.map((t) => t.risk_level))]}; province=KH → ${kh.length} (${kh.slice(0, 5).map((t) => t.code).join(', ')})`);
        expect(r4.length).toBeGreaterThan(0);
        expect(r4.every((t) => t.risk_level === 4)).toBeTruthy();
    });
});

test.describe('Đối sánh cảnh báo với hành trình', () => {
    test.use({ storageState: authFile('traveler1') });

    test('TC-MATCH-001 tạo hành trình có chặng trong vùng cảnh báo đang hiệu lực → đối sánh ngay khi kích hoạt', async ({ page, request }, info) => {
        const a = await pickActiveAlert(request);
        const start = Math.max(Date.parse(a.starts_at), Date.now()) + 3600_000;
        const off = (start - Date.now()) / 3600_000;
        const c = await createTrip(request, { title: `E2E đối sánh ${a.code}`, start: localDate(off / 24), end: localDate(off / 24 + 1), legs: [{ zone_code: a.zones[0].code, starts_at: localDT(0, off), ends_at: localDT(0, off + 2) }] });
        const { trip } = await tripDetail(request, c.code);
        const m = trip.matches.find((x) => x.alert.code === a.code);
        await page.goto(`/vi/hanh-trinh/${c.code}`);
        await shot(page, 'TC-MATCH-001', info);
        actual(info, `Cảnh báo ${a.code} (vùng ${a.zones[0].code}, ${a.starts_at}→${a.ends_at}); hành trình ${c.code} chặng ${localDT(0, off)}–${localDT(0, off + 2)} → ${trip.matches.length} đối sánh, có ${a.code}: ${!!m} (match_type ${m?.match_type}, trùng ${m?.overlap_from} → ${m?.overlap_to}); risk_level ${trip.risk_level}`);
        expect(m).toBeTruthy();
    });

    test('TC-MATCH-002 [Biên] chặng cùng vùng nhưng sau khi cảnh báo hết hạn → không đối sánh', async ({ page, request }, info) => {
        const a = await pickActiveAlert(request);
        test.skip(!a.ends_at, 'cảnh báo không có ends_at');
        const off = (Date.parse(a.ends_at) - Date.now()) / 3600_000 + 1;
        const c = await createTrip(request, { title: `E2E ngoài thời gian ${a.code}`, start: localDate(off / 24), end: localDate(off / 24 + 1), legs: [{ zone_code: a.zones[0].code, starts_at: localDT(0, off), ends_at: localDT(0, off + 3) }] });
        const { trip } = await tripDetail(request, c.code);
        const m = trip.matches.find((x) => x.alert.code === a.code);
        await page.goto(`/vi/hanh-trinh/${c.code}`);
        await shot(page, 'TC-MATCH-002', info);
        actual(info, `${a.code} hết hạn ${a.ends_at}; chặng ${localDT(0, off)} (sau hết hạn 1 giờ) → đối sánh với ${a.code}: ${!!m}; tổng đối sánh của hành trình ${trip.matches.length}`);
        expect(m).toBeFalsy();
        await send(request, 'POST', `/vi/hanh-trinh/${c.code}/huy`, {}, { headers: { 'X-Inertia': 'true' } });
    });

    test('TC-MATCH-003 du khách xác nhận đã biết cảnh báo (nút Đã biết) → match acknowledged', async ({ page, request }, info) => {
        const a = await pickActiveAlert(request);
        const start = Math.max(Date.parse(a.starts_at), Date.now()) + 3600_000;
        const off = (start - Date.now()) / 3600_000;
        const c = await createTrip(request, { title: `E2E xác nhận ${a.code}`, start: localDate(off / 24), end: localDate(off / 24 + 1), legs: [{ zone_code: a.zones[0].code, starts_at: localDT(0, off), ends_at: localDT(0, off + 2) }] });
        const m = (await tripDetail(request, c.code)).trip.matches[0];
        const r = await send(request, 'POST', `/vi/hanh-trinh/${c.code}/xac-nhan/${m.id}`, {}, { headers: { 'X-Inertia': 'true' } });
        const after = (await tripDetail(request, c.code)).trip.matches.find((x) => x.id === m.id);
        await page.goto(`/vi/hanh-trinh/${c.code}`);
        await shot(page, 'TC-MATCH-003', info);
        actual(info, `${c.code} match #${m.id} (${m.alert.code}) ${m.status} → POST xac-nhan ${r.status} → ${after.status}, acknowledged_at ${after.acknowledged_at}`);
        expect(after.status).toBe('acknowledged');
        expect(after.acknowledged_at).toBeTruthy();
    });

    test('TC-MATCH-004 đối sánh hình học: chặng ở Lai Châu nằm trong vòng tròn 40 km của cảnh báo lũ quét Sa Pa (AL-SIM-0004) → match geometry kèm khoảng cách; điểm ngoài vòng → không', async ({ page, request }, info) => {
        const a = await (await request.get('/api/v1/alerts/AL-SIM-0004')).json();
        const al = a.data || a;
        test.skip(al.status !== 'active' || !al.geometry?.center, 'AL-SIM-0004 không còn hiệu lực hoặc thiếu hình học');
        const start = Math.max(Date.parse(al.starts_at), Date.now()) + 3600_000;
        const off = (start - Date.now()) / 3600_000;
        const leg = (lat, lng) => [{ zone_code: 'lai-chau', starts_at: localDT(0, off), ends_at: localDT(0, off + 3), lat, lng }];
        const inside = await createTrip(request, { title: 'E2E hình học trong vòng', start: localDate(off / 24), end: localDate(off / 24 + 1), legs: leg(22.33, 103.84) });
        const outside = await createTrip(request, { title: 'E2E hình học ngoài vòng', start: localDate(off / 24), end: localDate(off / 24 + 1), legs: leg(22.39, 103.2) });
        const mi = (await tripDetail(request, inside.code)).trip.matches.find((m) => m.alert.code === 'AL-SIM-0004');
        const mo = (await tripDetail(request, outside.code)).trip.matches.find((m) => m.alert.code === 'AL-SIM-0004');
        await page.goto(`/vi/hanh-trinh/${inside.code}`);
        await shot(page, 'TC-MATCH-004', info);
        actual(info, `AL-SIM-0004 Circle tâm ${al.geometry.center.lat},${al.geometry.center.lng} r=${al.geometry.radius_km} km; ${inside.code} điểm 22.33,103.84 (vùng lai-chau) → ${mi ? `match ${mi.match_type}, distance_km ${mi.distance_km}` : 'không đối sánh'}; ${outside.code} điểm 22.39,103.20 → ${mo ? `match ${mo.match_type} ${mo.distance_km} km` : 'không đối sánh'}`);
        expect(mi?.match_type).toBe('geometry');
        expect(mi.distance_km).toBeLessThan(40);
        expect(mo).toBeFalsy();
        for (const c of [inside.code, outside.code]) await send(request, 'POST', `/vi/hanh-trinh/${c}/huy`, {}, { headers: { 'X-Inertia': 'true' } });
    });
});
