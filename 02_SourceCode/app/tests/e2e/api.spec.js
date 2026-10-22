// API mở v1, healthz, dịch vụ Python (UC-06, UC-12), tuyến tham quan (UC-07) và thời gian phản hồi.
import { test, expect, request as pwRequest } from '@playwright/test';
import { actual, shot, getPage, showText } from './helpers.js';

const PY = process.env.E2E_PY_URL || 'http://localhost:8011';
const TOKEN = process.env.E2E_ANALYTICS_TOKEN || '';
const pretty = (o) => JSON.stringify(o, null, 2).slice(0, 2500);

test.describe('API mở', () => {
    test('TC-API-001 GET /api/v1/sites trả JSON phân trang có CORS', async ({ page, request }, ti) => {
        const r = await request.get('/api/v1/sites?province=&type=di_tich&page=1', { headers: { Origin: 'https://example.org' } });
        const j = await r.json();
        actual(ti, `HTTP ${r.status()}, ${j.data.length} mục, meta.total=${j.meta.total}, per_page=${j.meta.per_page}; Access-Control-Allow-Origin=${r.headers()['access-control-allow-origin']}; X-RateLimit-Limit=${r.headers()['x-ratelimit-limit']}.`);
        await showText(page, 'TC-API-001', 'GET /api/v1/sites?type=di_tich', `HTTP ${r.status()}\naccess-control-allow-origin: ${r.headers()['access-control-allow-origin']}\nx-ratelimit-limit: ${r.headers()['x-ratelimit-limit']}\n\n${pretty({ meta: j.meta, first: j.data[0] })}`);
        expect(r.status()).toBe(200);
        expect(j.data.every((s) => s.site_type === 'di_tich')).toBe(true);
        expect(r.headers()['access-control-allow-origin']).toBe('*');
    });

    test('TC-API-002 GET /api/v1/sites/{slug}?locale=en có JSON-LD TouristAttraction', async ({ page, request }, ti) => {
        const r = await request.get('/api/v1/sites/hoang-thanh-thang-long?locale=en&depth=3');
        const j = await r.json();
        actual(ti, `HTTP ${r.status()}, title="${j.data.title}", depth=${j.data.interpretation.depth_level}, jsonld @type=${j.data.jsonld?.['@type']}, geo=${JSON.stringify(j.data.jsonld?.geo)}.`);
        await showText(page, 'TC-API-002', 'GET /api/v1/sites/hoang-thanh-thang-long?locale=en&depth=3', `HTTP ${r.status()}\n${pretty({ title: j.data.title, interpretation: { ...j.data.interpretation, body_html: '…' }, jsonld: j.data.jsonld })}`);
        expect(j.data.jsonld['@type']).toContain('TouristAttraction');
    });

    test('TC-API-003 slug không tồn tại trả 404 JSON, không lộ stack trace', async ({ page, request }, ti) => {
        const r = await request.get('/api/v1/sites/khong-ton-tai', { headers: { Accept: 'application/json' } });
        const t = await r.text();
        actual(ti, `HTTP ${r.status()}, thân: ${t.slice(0, 120)}`);
        await showText(page, 'TC-API-003', 'GET /api/v1/sites/khong-ton-tai', `HTTP ${r.status()}\n${t}`);
        expect(r.status()).toBe(404);
        expect(t).not.toMatch(/trace|vendor|Exception/i);
    });

    test('TC-API-004 GeoJSON FeatureCollection hợp lệ, tọa độ [lng, lat] trong lãnh thổ VN', async ({ page, request }, ti) => {
        const r = await request.get('/api/v1/geojson/sites');
        const j = await r.json();
        const okCoords = j.features.every((f) => f.geometry.type === 'Point' && f.geometry.coordinates[0] > 102 && f.geometry.coordinates[0] < 110 && f.geometry.coordinates[1] > 8 && f.geometry.coordinates[1] < 24);
        actual(ti, `type=${j.type}, ${j.features.length} feature, mọi tọa độ hợp lệ: ${okCoords}; Content-Type=${r.headers()['content-type']}.`);
        await showText(page, 'TC-API-004', 'GET /api/v1/geojson/sites', `HTTP ${r.status()} ${r.headers()['content-type']}\n${pretty({ type: j.type, count: j.features.length, first: j.features[0] })}`);
        expect(j.type).toBe('FeatureCollection');
        expect(okCoords).toBe(true);
    });

    test('TC-API-005 đặc tả OpenAPI 3.1 truy cập được và mô tả các đường dẫn', async ({ page, request }, ti) => {
        const r = await request.get('/api/v1/openapi.json');
        const j = await r.json();
        const paths = Object.keys(j.paths || {});
        actual(ti, `openapi=${j.openapi}, title="${j.info?.title}", ${paths.length} path: ${paths.join(', ')}.`);
        await showText(page, 'TC-API-005', 'GET /api/v1/openapi.json', `HTTP ${r.status()}\nopenapi: ${j.openapi}\ninfo: ${JSON.stringify(j.info)}\npaths:\n${paths.join('\n')}`);
        expect(j.openapi).toMatch(/^3\.1/);
        expect(paths.length).toBeGreaterThanOrEqual(8);
    });

    test('TC-API-006 iCal khung giờ tuyến tải được, đúng chuẩn VCALENDAR', async ({ page, request }, ti) => {
        const r = await request.get('/api/v1/ical/routes/dau-an-thang-long.ics');
        const t = await r.text();
        const n = (t.match(/BEGIN:VEVENT/g) || []).length;
        actual(ti, `HTTP ${r.status()}, Content-Type=${r.headers()['content-type']}, ${n} VEVENT, có BEGIN:VCALENDAR: ${t.includes('BEGIN:VCALENDAR')}.`);
        await showText(page, 'TC-API-006', 'GET /api/v1/ical/routes/dau-an-thang-long.ics', `HTTP ${r.status()} ${r.headers()['content-type']}\n\n${t.slice(0, 1500)}`);
        expect(r.headers()['content-type']).toContain('text/calendar');
        expect(n).toBeGreaterThan(0);
    });

    test('TC-API-007 /healthz báo trạng thái db và analytics', async ({ page, request }, ti) => {
        const r = await request.get('/healthz');
        const j = await r.json();
        actual(ti, `HTTP ${r.status()} ${JSON.stringify(j)}`);
        await showText(page, 'TC-API-007', 'GET /healthz', `HTTP ${r.status()}\n${pretty(j)}`);
        expect(j.status).toBe('ok');
        expect(j.db).toBe('ok');
    });

    test('TC-API-008 API tìm kiếm và dòng thời gian lọc theo khoảng năm', async ({ page, request }, ti) => {
        const s = await (await request.get('/api/v1/search?q=hue&locale=vi')).json();
        const tl = await (await request.get('/api/v1/timeline?from=1000&to=1500')).json();
        const items = Array.isArray(tl) ? tl : tl.data;
        const inRange = items.every((e) => e.year >= 1000 && e.year <= 1500);
        actual(ti, `search q=hue: engine=${s.engine}, ${s.data.length} kết quả; timeline 1000–1500: ${items.length} sự kiện, tất cả trong khoảng: ${inRange}.`);
        await showText(page, 'TC-API-008', 'GET /api/v1/search?q=hue, /api/v1/timeline?from=1000&to=1500', pretty({ engine: s.engine, top: s.data.slice(0, 3).map((h) => h.slug), timeline: items.slice(0, 5).map((e) => `${e.year} ${e.title}`) }));
        expect(inRange).toBe(true);
    });

    test('TC-API-009 mọi lỗi dưới /api/* trả JSON gọn kể cả khi không gửi Accept: application/json', async ({ page, request }, ti) => {
        // Vòng 2: hồi quy docs_found_1 mục 1 (ForceJsonResponse + ApiErrorResponse)
        const probes = [
            ['GET', '/api/v1/sites/khong-ton-tai'],
            ['GET', '/api/v1/khong-co-route'],
            ['DELETE', '/api/v1/sites'],
            ['GET', '/api/v1/sites?page=abc&per_page=-5'],
        ];
        const out = [];
        for (const [m, u] of probes) {
            const r = await request.fetch(u, { method: m, headers: { Accept: 'text/html' } });
            const t = await r.text();
            let json = null;
            try { json = JSON.parse(t); } catch { /* không phải JSON */ }
            const leak = /SQLSTATE|App\\Models|vendor\/|Exception|trace/i.test(t);
            out.push({ m, u, status: r.status(), ct: r.headers()['content-type'], json: !!json, body: t.slice(0, 100), leak });
        }
        const text = out.map((o) => `${o.m} ${o.u} → ${o.status} ${o.ct}; JSON=${o.json}; lộ thông tin=${o.leak}; ${o.body}`).join('\n');
        actual(ti, text.replace(/\n/g, ' ; '));
        await showText(page, 'TC-API-009', 'Lỗi API khi gửi Accept: text/html', text);
        expect(out.every((o) => o.json && (o.ct || '').includes('application/json') && !o.leak)).toBe(true);
        expect(out[0].status).toBe(404);
        expect(out[1].status).toBe(404);
        expect(out[2].status).toBe(405);
    });
});

test.describe('PY – dịch vụ phân tích', () => {
    test('TC-PY-001 dịch vụ Python /healthz có chỉ mục tìm kiếm', async ({ page }, ti) => {
        const api = await pwRequest.newContext();
        const r = await api.get(`${PY}/healthz`);
        const j = await r.json();
        await api.dispose();
        actual(ti, `HTTP ${r.status()} ${JSON.stringify(j)}`);
        await showText(page, 'TC-PY-001', `GET ${PY}/healthz`, `HTTP ${r.status()}\n${pretty(j)}`);
        expect(j.index_size).toBeGreaterThan(0);
    });

    test('TC-PY-002 gọi endpoint nội bộ thiếu/sai token bị 401', async ({ page }, ti) => {
        const api = await pwRequest.newContext();
        const a = await api.post(`${PY}/search`, { data: { q: 'hue', locale: 'vi' } });
        const b = await api.get(`${PY}/quality-report`, { headers: { 'X-Analytics-Token': 'sai-token' } });
        const [ta, tb] = [await a.text(), await b.text()];
        await api.dispose();
        actual(ti, `POST /search không token → ${a.status()}; GET /quality-report token sai → ${b.status()}.`);
        await showText(page, 'TC-PY-002', 'Endpoint nội bộ không có token hợp lệ', `POST /search (không token) → ${a.status()} ${ta}\nGET /quality-report (token sai) → ${b.status()} ${tb}`);
        expect(a.status()).toBe(401);
        expect(b.status()).toBe(401);
    });

    test('TC-PY-003 tìm kiếm TF-IDF và gợi ý thẻ với token hợp lệ', async ({ page }, ti) => {
        test.skip(!TOKEN, 'Cần E2E_ANALYTICS_TOKEN');
        const api = await pwRequest.newContext({ extraHTTPHeaders: { 'X-Analytics-Token': TOKEN } });
        const s = await (await api.post(`${PY}/search`, { data: { q: 'chùa cổ Hà Nội', locale: 'vi', limit: 5 } })).json();
        const g = await (await api.post(`${PY}/suggest-tags`, { data: { text: 'Lễ hội đền thờ vua Hùng, tín ngưỡng thờ cúng tổ tiên', locale: 'vi', limit: 5 } })).json();
        await api.dispose();
        actual(ti, `search: engine=${s.engine}, took_ms=${s.took_ms}, top=${s.results.slice(0, 3).map((r) => `${r.slug}(${r.score.toFixed?.(3) ?? r.score})`).join(', ')}; suggest-tags: ${g.suggestions.map((x) => x.slug).join(', ')}.`);
        await showText(page, 'TC-PY-003', 'POST /search, /suggest-tags (có token)', pretty({ search: { ...s, results: s.results.slice(0, 5) }, suggest: g }));
        expect(s.results.length).toBeGreaterThan(0);
        expect(g.suggestions.length).toBeGreaterThan(0);
    });

    test('TC-PY-004 báo cáo chất lượng dữ liệu có cấu trúc checks', async ({ page }, ti) => {
        test.skip(!TOKEN, 'Cần E2E_ANALYTICS_TOKEN');
        const api = await pwRequest.newContext({ extraHTTPHeaders: { 'X-Analytics-Token': TOKEN } });
        const q = await (await api.get(`${PY}/quality-report`)).json();
        await api.dispose();
        actual(ti, `summary=${JSON.stringify(q.summary)}; checks: ${q.checks.map((c) => `${c.code}:${c.severity}:${c.count}`).join(', ')}.`);
        await showText(page, 'TC-PY-004', 'GET /quality-report', pretty({ summary: q.summary, checks: q.checks.map(({ samples, ...c }) => c) }));
        expect(q.checks.length).toBeGreaterThan(0);
    });
});

test.describe('TOUR – công khai', () => {
    test('TC-TOUR-001 danh sách tuyến lọc theo độ khó', async ({ page }, ti) => {
        const { page: p } = await getPage(page.request, '/vi/tuyen-tham-quan?difficulty=de');
        const d = [...new Set(p.props.routes.data.map((r) => r.difficulty))];
        await page.goto('/vi/tuyen-tham-quan?difficulty=de');
        actual(ti, `${p.props.routes.total} tuyến, difficulty = ${d.join(',')}; mỗi thẻ có next_slot: ${p.props.routes.data.every((r) => r.next_slot)}.`);
        await shot(page, 'TC-TOUR-001');
        expect(d).toEqual(['de']);
    });

    test('TC-TOUR-002 chi tiết tuyến có điểm dừng, bản đồ, khung giờ còn chỗ', async ({ page }, ti) => {
        const { page: p } = await getPage(page.request, '/vi/tuyen-tham-quan/dau-an-thang-long');
        await page.goto('/vi/tuyen-tham-quan/dau-an-thang-long');
        // Vòng 2: bản đồ khởi tạo lười bằng IntersectionObserver (sửa qa_3) → cuộn dần cho tới khi bản đồ vào khung nhìn
        const map = page.locator('.leaflet-container').first();
        for (let i = 0; i < 12 && !(await map.count()); i++) {
            await page.mouse.wheel(0, 500);
            await page.waitForTimeout(300);
        }
        await expect(map).toBeVisible();
        await map.scrollIntoViewIfNeeded();
        const r = p.props.route;
        actual(ti, `"${r.name}": ${r.stops.length} điểm dừng, ${r.slots.length} khung giờ, giá ${r.price_vnd} VND, holdMinutes=${p.props.holdMinutes}, canBook (khách)=${p.props.canBook}.`);
        await shot(page, 'TC-TOUR-002');
        expect(r.stops.length).toBeGreaterThan(0);
        expect(r.slots.length).toBeGreaterThan(0);
    });

    test('TC-TOUR-004 khách vãng lai thấy yêu cầu đăng nhập thay vì form đặt lịch', async ({ page }, ti) => {
        await page.goto('/vi/tuyen-tham-quan/dau-an-thang-long');
        await page.locator('input[name="slot"]:not([disabled])').first().check();
        const box = page.locator('#booking');
        const hasForm = await box.locator('form').count();
        const login = await box.getByRole('link', { name: 'Đăng nhập' }).count();
        await box.scrollIntoViewIfNeeded();
        actual(ti, `Khung đặt lịch: ${hasForm} form, ${login} nút Đăng nhập.`);
        await shot(page, 'TC-TOUR-004');
        expect(hasForm).toBe(0);
        expect(login).toBe(1);
    });
});

test.describe('PERF', () => {
    test('TC-PERF-001 thời gian phản hồi máy chủ (TTFB) 5 trang trọng yếu < 500 ms', async ({ page, request }, ti) => {
        const pages = ['/vi', '/vi/di-san', '/vi/di-san/hoang-thanh-thang-long', '/vi/ban-do', '/vi/tuyen-tham-quan'];
        const rows = [];
        for (const u of pages) {
            const times = [];
            for (let i = 0; i < 5; i++) {
                const t0 = Date.now();
                await request.get(u);
                times.push(Date.now() - t0);
            }
            times.sort((a, b) => a - b);
            rows.push({ u, median: times[2], max: times[4] });
        }
        const text = rows.map((r) => `${r.u}: trung vị ${r.median} ms, max ${r.max} ms`).join('\n');
        actual(ti, text.replace(/\n/g, '; '));
        await showText(page, 'TC-PERF-001', 'Thời gian phản hồi HTML (5 lần/trang)', text);
        expect(rows.every((r) => r.median < 500)).toBe(true);
    });
});
