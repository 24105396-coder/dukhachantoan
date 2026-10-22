// Kiểm thử bảo mật (OWASP Top 10) trên hệ thống của nhóm: CSRF, XSS, SQLi, header, cookie, lộ thông tin, gán hàng loạt.
import { test, expect, request as pwRequest } from '@playwright/test';
import { authFile, actual, shot, getPage, send, showText, rnd, xsrfHeader } from './helpers.js';

test.describe('SEC – thành viên', () => {
    test.use({ storageState: authFile('member') });

    test('TC-SEC-001 POST không kèm CSRF token bị từ chối 419', async ({ page }, ti) => {
        await page.goto('/vi');
        const r = await page.request.post('/vi/yeu-thich', { form: { type: 'site', id: '1' }, headers: { Accept: 'text/html' }, maxRedirects: 0 });
        const r2 = await page.request.post('/vi/dat-lich/3', { form: { quantity: '1', contact_name: 'CSRF', contact_phone: '0912345678', contact_email: 'a@b.vn' }, maxRedirects: 0 });
        const html = await r.text();
        await page.setContent(html);
        actual(ti, `POST /vi/yeu-thich không token → ${r.status()}; POST /vi/dat-lich/3 không token → ${r2.status()}.`);
        await shot(page, 'TC-SEC-001');
        expect(r.status()).toBe(419);
        expect(r2.status()).toBe(419);
    });

    test('TC-SEC-002 XSS lưu trữ qua đóng góp không thực thi ở trang kiểm duyệt', async ({ page, browser }, ti) => {
        await page.goto('/vi');
        const payload = `<img src=x onerror="window.__xss=1;alert(1)"><script>window.__xss=2</script> XSS ${rnd()}`;
        const c = await send(page.request, 'POST', '/vi/dong-gop', { contribution_type: 'story', locale: 'vi', title: payload, body: `${payload} – nội dung kiểm thử XSS đủ dài.` }, { accept: 'html' });
        const id = c.location.split('/').pop();
        const mgr = await browser.newContext({ storageState: authFile('manager') });
        const mp = await mgr.newPage();
        let dialogs = 0;
        mp.on('dialog', async (d) => { dialogs++; await d.dismiss(); });
        await mp.goto(`/vi/quan-tri/kiem-duyet/${id}`);
        await mp.waitForTimeout(1500);
        const flag = await mp.evaluate(() => window.__xss ?? null);
        const literal = await mp.getByText('<img src=x onerror', { exact: false }).count();
        await shot(mp, 'TC-SEC-002');
        // Từ chối để dọn dẹp hàng đợi
        await mgr.request.get('/vi');
        await send(mgr.request, 'PATCH', `/vi/quan-tri/kiem-duyet/${id}`, { status: 'rejected', review_note: 'Ca kiểm thử XSS' });
        await mgr.close();
        actual(ti, `Đóng góp #${id}; ở trang kiểm duyệt: hộp thoại alert=${dialogs}, window.__xss=${flag}, payload hiển thị dạng chữ ${literal} lần.`);
        expect(dialogs).toBe(0);
        expect(flag).toBeNull();
        expect(literal).toBeGreaterThan(0);
    });

    test('TC-SEC-003 gán hàng loạt: thành viên tự đổi vai trò qua PATCH hồ sơ không có tác dụng', async ({ page }, ti) => {
        await page.goto('/vi');
        const r = await send(page.request, 'PATCH', '/vi/tai-khoan/ho-so', { name: 'Người dùng mẫu', role_id: 1, role: 'admin', is_active: true }, { accept: 'html' });
        const u = (await getPage(page.request, '/vi/tai-khoan')).page.props.auth.user;
        const adm = await page.request.get('/vi/quan-tri', { maxRedirects: 0 });
        actual(ti, `PATCH hồ sơ kèm role_id=1 → ${r.status}; vai trò sau đó: ${u.role}; GET /vi/quan-tri → ${adm.status()}.`);
        await showText(page, 'TC-SEC-003', 'PATCH /vi/tai-khoan/ho-so {role_id:1, role:"admin"}', `HTTP ${r.status}\nauth.user.role = ${u.role}\nGET /vi/quan-tri → ${adm.status()}`);
        expect(u.role).toBe('member');
    });
});

test.describe('SEC – khách', () => {
    test('TC-SEC-004 SQL injection qua ô tìm kiếm và tham số lọc không có tác dụng', async ({ page, request }, ti) => {
        const probes = [
            `/vi/tim-kiem?q=${encodeURIComponent("' OR 1=1 -- ")}`,
            `/vi/di-san?province=${encodeURIComponent("' OR '1'='1")}`,
            `/vi/di-san?type=${encodeURIComponent("di_tich' UNION SELECT password FROM users -- ")}`,
            `/vi/di-san?q=${encodeURIComponent('") OR SLEEP(3) -- ')}`,
            `/vi/di-san?province[]=1&tag[]=x`,
            `/api/v1/sites?level=${encodeURIComponent("unesco' OR 1=1#")}`,
        ];
        const out = [];
        for (const u of probes) {
            const t0 = Date.now();
            const r = await request.get(u);
            const body = await r.text();
            const total = body.match(/&quot;total&quot;:(\d+)|"total":(\d+)/);
            out.push(`${decodeURIComponent(u)} → ${r.status()} (${Date.now() - t0} ms, total=${total ? total[1] ?? total[2] : '-'}, lỗi SQL: ${/SQLSTATE|syntax error|mysql/i.test(body)})`);
        }
        const text = out.join('\n');
        actual(ti, text.replace(/\n/g, ' ; '));
        await showText(page, 'TC-SEC-004', 'Thăm dò SQL injection', text);
        expect(out.every((l) => / → (200|404|422) /.test(l) && l.includes('lỗi SQL: false'))).toBe(true);
    });

    test('TC-SEC-005 header bảo mật có mặt trên trang HTML và API', async ({ page, request }, ti) => {
        const r = await request.get('/vi');
        const a = await request.get('/api/v1/sites');
        const h = r.headers();
        const need = ['x-frame-options', 'x-content-type-options', 'referrer-policy', 'permissions-policy', 'content-security-policy'];
        const text = need.map((k) => `${k}: ${h[k] ?? '(thiếu)'} | API: ${a.headers()[k] ?? '(thiếu)'}`).join('\n') + `\nstrict-transport-security: ${h['strict-transport-security'] ?? '(không có – HTTP localhost)'}\nserver: ${h['server']}\nx-powered-by: ${h['x-powered-by'] ?? '(không có)'}`;
        // Vòng 2: CSP mới không còn nguồn CDN (unpkg, Google Fonts, ajax.googleapis); HTML không nạp script ngoài
        const csp = h['content-security-policy'] || '';
        const html = await r.text();
        const self = new URL(r.url()).origin;
        const extScripts = [...html.matchAll(/<script[^>]+src="(https?:[^"]+)"/g)].map((m) => m[1]).filter((u) => new URL(u).origin !== self);
        const cdn = /unpkg\.com|fonts\.googleapis|fonts\.gstatic|ajax\.googleapis/.test(csp);
        actual(ti, need.map((k) => `${k}=${h[k] ? 'có' : 'thiếu'}`).join(', ') + `; x-powered-by=${h['x-powered-by'] ?? 'không'}; server=${h['server']}; CSP có nguồn CDN: ${cdn}; script ngoài trong HTML: ${extScripts.length}.`);
        await showText(page, 'TC-SEC-005', 'Header phản hồi GET /vi và /api/v1/sites', `${text}\n\nCSP đầy đủ:\n${csp.split(';').map((x) => x.trim()).join('\n')}\n\nscript src ngoài trong HTML: ${extScripts.join(', ') || '(không có)'}`);
        expect(need.every((k) => h[k])).toBe(true);
        expect(h['x-frame-options']).toBe('DENY');
        expect(cdn).toBe(false);
        expect(extScripts).toEqual([]);
    });

    test('TC-SEC-006 cookie phiên HttpOnly + SameSite=Lax, phiên đổi sau đăng nhập', async ({ page, baseURL }, ti) => {
        const ctx = await pwRequest.newContext({ baseURL });
        const r = await ctx.get('/vi/dang-nhap');
        const raw = r.headersArray().filter((x) => x.name.toLowerCase() === 'set-cookie').map((x) => x.value);
        const before = (await ctx.storageState()).cookies.find((c) => c.name === 'disanso-session')?.value;
        await ctx.post('/vi/dang-nhap', { form: { email: 'member3@disanso.test', password: 'DiSan@2026' }, headers: await xsrfHeader(ctx), maxRedirects: 0 });
        const after = (await ctx.storageState()).cookies.find((c) => c.name === 'disanso-session')?.value;
        await ctx.dispose();
        const sess = raw.find((c) => c.startsWith('disanso-session')) || '';
        const xsrf = raw.find((c) => c.startsWith('XSRF-TOKEN')) || '';
        const flags = (c) => c.split(';').slice(1).map((s) => s.trim()).join('; ');
        actual(ti, `disanso-session: ${flags(sess)}; XSRF-TOKEN: ${flags(xsrf)}; session đổi sau đăng nhập: ${before !== after}.`);
        await showText(page, 'TC-SEC-006', 'Cờ cookie', `disanso-session: ${flags(sess)}\nXSRF-TOKEN: ${flags(xsrf)}\nsession trước/sau đăng nhập khác nhau: ${before !== after}`);
        expect(sess).toMatch(/httponly/i);
        expect(sess).toMatch(/samesite=lax/i);
        expect(before).not.toBe(after);
    });

    test('TC-SEC-007 không lộ tệp nhạy cảm và thông tin lỗi', async ({ page, request }, ti) => {
        const probes = ['/.env', '/.git/config', '/composer.json', '/storage/logs/laravel.log', '/vendor/autoload.php', '/phpinfo.php', '/vi/dang-xuat'];
        const out = [];
        for (const u of probes) {
            const r = await request.get(u, { maxRedirects: 0 });
            const b = await r.text();
            out.push(`${u} → ${r.status()} ${/APP_KEY|DB_PASSWORD|\[core\]|"require"|Stack trace|Whoops|Symfony\\|vendor\//.test(b) ? 'LỘ THÔNG TIN' : 'an toàn'}`);
        }
        const text = out.join('\n');
        actual(ti, text.replace(/\n/g, ' ; '));
        await showText(page, 'TC-SEC-007', 'Thăm dò tệp nhạy cảm và trang lỗi', text);
        expect(out.every((l) => l.endsWith('an toàn') && !/ → 200 /.test(l))).toBe(true);
    });

    test('TC-SEC-008 XSS phản xạ qua tham số tìm kiếm không thực thi', async ({ page }, ti) => {
        let dialogs = 0;
        page.on('dialog', async (d) => { dialogs++; await d.dismiss(); });
        await page.goto(`/vi/tim-kiem?q=${encodeURIComponent('"><script>alert(1)</script><img src=x onerror=alert(2)>')}`);
        await page.waitForTimeout(1200);
        const input = await page.locator('input[type="search"], input[name="q"]').first().inputValue().catch(() => '');
        actual(ti, `Hộp thoại alert: ${dialogs}; ô tìm kiếm chứa chuỗi gốc dạng văn bản: ${input.includes('<script>')}.`);
        await shot(page, 'TC-SEC-008');
        expect(dialogs).toBe(0);
    });
});

test.describe('SEC – manager nhập liệu', () => {
    test.use({ storageState: authFile('manager') });
    test('TC-SEC-009 nội dung diễn giải chứa <script> được lọc khi hiển thị body_html', async ({ page }, ti) => {
        await page.goto('/vi');
        const slug = `qa-xss-${rnd()}`;
        const { page: form } = await getPage(page.request, '/vi/quan-tri/di-san/moi');
        await send(page.request, 'POST', '/vi/quan-tri/di-san', {
            slug, site_type: 'di_tich', province_id: form.props.provinces[0].id, lat: 21, lng: 105.8, recognition_level: 'cap_tinh', status: 'published',
            interpretations: [{ locale: 'vi', depth_level: 1, title: 'XSS qua diễn giải', body: '**Đậm** <script>window.__xss=1</script> <a href="javascript:alert(1)">liên kết</a> <img src=x onerror="window.__xss=2">' }],
        }, { accept: 'html' });
        let dialogs = 0;
        page.on('dialog', async (d) => { dialogs++; await d.dismiss(); });
        const { page: p } = await getPage(page.request, `/vi/di-san/${slug}`);
        await page.goto(`/vi/di-san/${slug}`);
        await page.waitForTimeout(1000);
        const flag = await page.evaluate(() => window.__xss ?? null);
        const html = p.props.site.interpretation.body_html;
        await shot(page, 'TC-SEC-009');
        await send(page.request, 'DELETE', `/vi/quan-tri/di-san/${slug}`, {}, { accept: 'html' });
        actual(ti, `body_html="${html.replace(/\n/g, ' ')}"; window.__xss=${flag}; alert=${dialogs}.`);
        expect(html).not.toMatch(/<script|onerror|javascript:/i);
        expect(flag).toBeNull();
    });
});
