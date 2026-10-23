// API mở /api/v1 (JSON, OpenAPI 3.1): cảnh báo, vùng, thời tiết, danh bạ, hướng dẫn, chia sẻ hành trình, thống kê.
import { test, expect } from '@playwright/test';
import { authFile, shot, actual, send, tripDetail, tokenOf, showText } from './helpers.js';

async function j(request, url) {
    const r = await request.get(url);
    return { status: r.status(), headers: r.headers(), body: await r.json().catch(() => null) };
}

test.describe('API v1', () => {
    test('TC-API-001 GET /api/v1/alerts: data + meta + links, mỗi cảnh báo có nguồn và thời điểm cập nhật', async ({ page, request }, info) => {
        const r = await j(request, '/api/v1/alerts?active=1&severity=4');
        const d = r.body.data;
        const ok = d.every((a) => a.source?.name && a.source?.attribution !== undefined && a.issued_at && 'updated_at_source' in a);
        await page.goto('/api/v1/alerts?active=1&severity=4');
        await shot(page, 'TC-API-001', info);
        actual(info, `${r.status} ${r.headers['content-type']}; ${d.length} cảnh báo mức 4 (meta.total ${r.body.meta?.total}, links ${Object.keys(r.body.links || {}).join('/')}); đủ source+issued_at+updated_at_source: ${ok}; x-ratelimit-limit ${r.headers['x-ratelimit-limit']}`);
        expect(r.status).toBe(200);
        expect(r.body.meta).toBeTruthy();
        expect(ok).toBeTruthy();
        expect(d.every((a) => a.severity === 4)).toBeTruthy();
    });

    test('TC-API-002 GET /api/v1/alerts/{code}?lang=en: chi tiết tiếng Anh, không lộ affected_trips_count', async ({ page, request }, info) => {
        const vi = (await j(request, '/api/v1/alerts/AL-SIM-0006')).body;
        const en = await j(request, '/api/v1/alerts/AL-SIM-0006?lang=en');
        const a = en.body.data || en.body;
        const v = vi.data || vi;
        await showText(page, 'TC-API-002 GET /api/v1/alerts/AL-SIM-0006?lang=en', JSON.stringify(en.body, null, 1).slice(0, 2500));
        await shot(page, 'TC-API-002', info);
        actual(info, `${en.status}; vi "${v.headline}" / en "${a.headline}"; khóa: ${Object.keys(a).filter((k) => /guides|contacts|history|affected/.test(k)).join(', ')}; affected_trips_count: ${'affected_trips_count' in a ? 'có' : 'không có'}`);
        expect(en.status).toBe(200);
        expect(a.headline).not.toBe(v.headline);
        expect('affected_trips_count' in a).toBeFalsy();
    });

    test('TC-API-003 GET /api/v1/zones/{code}/weather: now + forecast 7 ngày + risk', async ({ page, request }, info) => {
        const r = await j(request, '/api/v1/zones/khanh-hoa/weather');
        const d = r.body.data;
        await showText(page, 'TC-API-003 GET /api/v1/zones/khanh-hoa/weather', JSON.stringify(r.body, null, 1).slice(0, 2500));
        await shot(page, 'TC-API-003', info);
        actual(info, `${r.status}; zone ${d.zone?.code}; now ${d.now?.temp_c} °C (${d.now?.source}); forecast ${d.forecast?.length} ngày; risk ${d.risk?.score}/${d.risk?.level}`);
        expect(d.forecast).toHaveLength(7);
        expect(d.now.source).toBe('Open-Meteo');
    });

    test('TC-API-004 danh bạ theo tỉnh và hướng dẫn theo loại hiểm họa (tiếng Anh)', async ({ page, request }, info) => {
        const c = await j(request, '/api/v1/contacts?province=KH');
        const g = await j(request, '/api/v1/guides?hazard=bao&lang=en');
        const gd = await j(request, `/api/v1/guides/${(g.body.data || g.body)[0].slug}?lang=en`);
        const cs = c.body.data || c.body;
        const gs = g.body.data || g.body;
        await showText(page, 'TC-API-004', `contacts?province=KH → ${c.status}\n${cs.map((x) => `${x.province?.code ?? 'toàn quốc'} ${x.contact_type} ${x.phone}`).join('\n')}\n\nguides?hazard=bao&lang=en → ${g.status}\n${gs.map((x) => x.title).join('\n')}`);
        await shot(page, 'TC-API-004', info);
        actual(info, `contacts?province=KH → ${c.status}, ${cs.length} liên hệ (tỉnh: ${[...new Set(cs.map((x) => x.province?.code ?? 'toàn quốc'))].join(',')}); guides?hazard=bao&lang=en → ${g.status}, ${gs.length}: ${gs.map((x) => x.title).join(' | ')}; chi tiết ${gd.status} có before_steps ${(gd.body.data || gd.body).before_steps?.length}`);
        expect(cs.every((x) => !x.province || x.province.code === 'KH')).toBeTruthy();
        expect(gs.every((x) => x.hazard.code === 'bao')).toBeTruthy();
    });

    test('TC-API-005 GET/POST /api/v1/trips/shared/{token}: xem rút gọn + báo an toàn 201', async ({ page, request, playwright }, info) => {
        const owner = await playwright.request.newContext({ baseURL: info.project.use.baseURL, storageState: authFile('traveler') });
        const token = tokenOf((await tripDetail(owner, 'TR-SEED-04')).trip);
        await owner.dispose();
        const g = await j(request, `/api/v1/trips/shared/${token}`);
        const p = await send(request, 'POST', `/api/v1/trips/shared/${token}/checkin`, { status: 'safe', message: `API ${info.project.name}`, lat: 22.336, lng: 103.844 }, { csrf: false });
        await showText(page, 'TC-API-005', `GET → ${g.status}\n${JSON.stringify(g.body, null, 1).slice(0, 1200)}\n\nPOST checkin → ${p.status}\n${JSON.stringify(p.body, null, 1)}`);
        await shot(page, 'TC-API-005', info);
        const t = g.body.data || g.body;
        actual(info, `GET → ${g.status}: ${t.trip?.code || t.code} "${t.trip?.title || t.title}", khóa: ${Object.keys(t.trip || t).slice(0, 14).join(',')}; POST checkin → ${p.status} {status ${(p.body.data || p.body).status}, source ${(p.body.data || p.body).source}, lat ${(p.body.data || p.body).lat}}`);
        expect(g.status).toBe(200);
        expect(p.status).toBe(201);
        expect(JSON.stringify(g.body)).not.toContain('contact_phone');
    });

    test('TC-API-006 OpenAPI 3.1 hợp lệ; mọi đường dẫn GET không tham số trả 200', async ({ page, request }, info) => {
        const o = (await j(request, '/api/v1/openapi.json')).body;
        const paths = Object.keys(o.paths);
        const res = [];
        for (const pth of paths.filter((x) => !x.includes('{') && o.paths[x].get)) {
            const url = pth.startsWith('/api') || pth.startsWith('/healthz') ? pth : `/api/v1${pth}`;
            res.push(`${url} ${(await request.get(url)).status()}`);
        }
        await showText(page, 'TC-API-006 /api/v1/openapi.json', `openapi ${o.openapi}, ${paths.length} path\n${res.join('\n')}`);
        await shot(page, 'TC-API-006', info);
        actual(info, `openapi ${o.openapi}, info.title "${o.info?.title}", ${paths.length} path; GET không tham số: ${res.join('; ')}`);
        expect(o.openapi).toMatch(/^3\.1/);
        expect(res.every((x) => x.endsWith(' 200'))).toBeTruthy();
    });

    test('TC-API-007 /api/v1/stats/overview không chứa dữ liệu cá nhân; có updated_at', async ({ page, request }, info) => {
        const r = await j(request, '/api/v1/stats/overview');
        const s = JSON.stringify(r.body);
        await showText(page, 'TC-API-007 GET /api/v1/stats/overview', JSON.stringify(r.body, null, 1));
        await shot(page, 'TC-API-007', info);
        const pii = /@|"phone"|"email"|"name":"[^"]*Doe/.test(s);
        actual(info, `${r.status}; khóa ${Object.keys(r.body.data || r.body).join(', ')}; có dữ liệu cá nhân: ${pii}`);
        expect(pii).toBeFalsy();
        expect(s).toContain('updated_at');
    });

    test('TC-API-008 lỗi API trả JSON: đường dẫn lạ 404, mã cảnh báo sai 404, tham số sai không gây 500', async ({ page, request }, info) => {
        const a = await request.get('/api/v1/khong-co');
        const b = await request.get('/api/v1/alerts/AL-KHONGCO');
        const c = await request.get('/api/v1/alerts?severity=abc&per_page=-5&page=x');
        const out = [`/api/v1/khong-co → ${a.status()} ${a.headers()['content-type']} ${await a.text()}`, `/api/v1/alerts/AL-KHONGCO → ${b.status()} ${await b.text()}`, `/api/v1/alerts?severity=abc&per_page=-5&page=x → ${c.status()} ${(await c.text()).slice(0, 120)}`];
        await showText(page, 'TC-API-008', out.join('\n'));
        await shot(page, 'TC-API-008', info);
        actual(info, out.join(' ; '));
        expect(a.status()).toBe(404);
        expect(a.headers()['content-type']).toContain('application/json');
        expect(b.status()).toBe(404);
        expect(c.status()).toBeLessThan(500);
    });
});
