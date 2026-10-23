// Bảo mật (OWASP): SQLi qua ô lọc, XSS trong tiêu đề hành trình/sự cố, CSRF, token chia sẻ sai, throttle đăng nhập,
// header bảo mật + CSP nonce, cookie, tải ảnh sai MIME, không lộ lỗi, nhật ký kiểm toán.
import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authFile, writerFile, shot, actual, send, getPage, createTrip, tripDetail, localDate, showText, FIXTURES, PASSWORD, xsrfHeader } from './helpers.js';

const XSS = `<img src=x onerror="window.__xss=1"><script>window.__xss=2</script>"'><svg onload=window.__xss=3>`;

test.describe('Tiêm SQL qua ô lọc', () => {
    test('TC-SEC-001 SQLi qua ô từ khóa trang cảnh báo không làm lộ dữ liệu, không lỗi 500', async ({ page, request }, info) => {
        const payloads = [`' OR '1'='1`, `%' UNION SELECT email,password FROM users -- `, `1; DROP TABLE alerts; --`, `\\' OR 1=1#`];
        const base = (await getPage(request, '/vi/canh-bao')).page.props.alerts.total;
        const out = [];
        for (const q of payloads) {
            const r = await getPage(request, `/vi/canh-bao?q=${encodeURIComponent(q)}`);
            const total = (r.page?.props?.alerts?.total ?? r.page?.props?.alerts?.meta?.total);
            out.push(`q=${q} → ${r.status}, total ${total}, lộ "$2y$": ${r.html.includes('$2y$')}, lỗi SQL: ${/SQLSTATE|syntax error/i.test(r.html)}`);
        }
        const after = (await getPage(request, '/vi/canh-bao')).page.props.alerts.total;
        await page.goto(`/vi/canh-bao?q=${encodeURIComponent(payloads[1])}`);
        await shot(page, 'TC-SEC-001', info);
        actual(info, `Tổng cảnh báo trước ${base}, sau ${after}. ${out.join(' ; ')}`);
        expect(out.every((l) => / → 200, total 0, lộ "\$2y\$": false, lỗi SQL: false/.test(l))).toBeTruthy();
        expect(after).toBe(base);
    });

    test('TC-SEC-002 SQLi qua tham số lọc mức/tỉnh/hiểm họa (web + API) bị bỏ qua hoặc từ chối', async ({ page, request }, info) => {
        const urls = [
            `/vi/canh-bao?severity=${encodeURIComponent('4 OR 1=1')}`,
            `/vi/canh-bao?province=${encodeURIComponent("KH' OR '1'='1")}`,
            `/vi/canh-bao?hazard=${encodeURIComponent("bao') OR ('a'='a")}`,
            `/api/v1/alerts?province=${encodeURIComponent("KH' OR 1=1 -- ")}`,
            `/api/v1/contacts?province=${encodeURIComponent("' UNION SELECT 1,2,3 -- ")}`,
            `/api/v1/guides?hazard=${encodeURIComponent("bao' OR '1'='1")}`,
        ];
        const out = [];
        for (const u of urls) {
            const r = await request.get(u, { maxRedirects: 0 });
            const t = await r.text();
            const n = u.startsWith('/api') ? (JSON.parse(t).data || JSON.parse(t)).length : null;
            out.push(`${decodeURIComponent(u)} → ${r.status()}${n !== null ? `, ${n} bản ghi` : ''}${/SQLSTATE/.test(t) ? ' LỘ LỖI SQL' : ''}`);
        }
        await showText(page, 'TC-SEC-002 SQLi qua tham số lọc', out.join('\n'));
        await shot(page, 'TC-SEC-002', info);
        actual(info, out.join(' ; '));
        expect(out.every((l) => !/LỘ LỖI SQL| → 5\d\d/.test(l))).toBeTruthy();
        expect(out[3]).toMatch(/, 0 bản ghi/);
    });
});

test.describe('XSS lưu trữ', () => {
    test('TC-SEC-003 XSS trong tiêu đề hành trình hiển thị dạng văn bản, không chạy script', async ({ browser }, info) => {
        const ctx = await browser.newContext({ storageState: writerFile(info) });
        const page = await ctx.newPage();
        let dialog = false;
        page.on('dialog', async (d) => { dialog = true; await d.dismiss(); });
        const c = await createTrip(ctx.request, { title: `XSS ${XSS}`, start: localDate(5), end: localDate(6), legs: [{ zone_code: 'hue', starts_at: `${localDate(5)}T08:00`, ends_at: `${localDate(5)}T10:00`, note: XSS }] });
        await page.goto(`/vi/hanh-trinh/${c.code}`);
        await page.waitForTimeout(800);
        const flag = await page.evaluate(() => window.__xss);
        const h1 = await page.locator('h1').first().innerText();
        const injected = await page.locator('main img[src="x"], main svg[onload]').count();
        await page.goto('/vi/hanh-trinh');
        await page.waitForTimeout(500);
        const flag2 = await page.evaluate(() => window.__xss);
        await page.goto(`/vi/hanh-trinh/${c.code}`);
        await shot(page, 'TC-SEC-003', info);
        actual(info, `${c.code} (${info.project.name}): h1 = "${h1.slice(0, 90)}"; phần tử chèn được: ${injected}; window.__xss trang chi tiết = ${flag}, trang danh sách = ${flag2}; hộp thoại: ${dialog}`);
        expect(flag).toBeUndefined();
        expect(flag2).toBeUndefined();
        expect(injected).toBe(0);
        expect(h1).toContain('<img');
        await send(ctx.request, 'POST', `/vi/hanh-trinh/${c.code}/huy`, {}, { headers: { 'X-Inertia': 'true' } });
        await ctx.close();
    });

    test('TC-SEC-004 XSS trong tiêu đề/mô tả sự cố → trang duyệt của agency hiển thị văn bản @chromium', async ({ browser, playwright }, info) => {
        const trav = await playwright.request.newContext({ baseURL: info.project.use.baseURL, storageState: authFile('traveler3') });
        const title = `XSS sự cố ${Date.now().toString(36)} ${XSS}`;
        const r = await send(trav, 'POST', '/vi/bao-su-co', { zone_code: 'vinh-nha-trang', hazard_code: 'song_lon', title, description: `Mô tả ${XSS}` }, { headers: { 'X-Inertia': 'true' } });
        await trav.dispose();
        const ctx = await browser.newContext({ storageState: authFile('agency') });
        const page = await ctx.newPage();
        await page.goto('/vi/quan-tri/su-co');
        await page.waitForTimeout(800);
        const flag = await page.evaluate(() => window.__xss);
        const txt = await page.locator('main').innerText();
        await shot(page, 'TC-SEC-004', info);
        actual(info, `POST /vi/bao-su-co → ${r.status}; trang /vi/quan-tri/su-co có tiêu đề dạng chữ: ${txt.includes('<script>window.__xss=2</script>')}; window.__xss = ${flag}`);
        expect(flag).toBeUndefined();
        expect(txt).toContain('<img src=x');
        await ctx.close();
    });
});

test.describe('CSRF, token, phiên', () => {
    test('TC-SEC-005 POST không có token CSRF → 419 (đăng nhập, tạo hành trình, báo an toàn qua form)', async ({ page, playwright }, info) => {
        const anon = await playwright.request.newContext({ baseURL: info.project.use.baseURL });
        await anon.get('/vi/dang-nhap');
        const a = await anon.post('/vi/dang-nhap', { form: { email: 'traveler@dukhach.test', password: PASSWORD }, maxRedirects: 0 });
        const trav = await playwright.request.newContext({ baseURL: info.project.use.baseURL, storageState: authFile('traveler') });
        const b = await trav.post('/vi/hanh-trinh/tao', { data: { title: 'csrf' }, headers: { Accept: 'application/json' }, maxRedirects: 0 });
        const tok = (await tripDetail(trav, 'TR-SEED-02')).trip.share_url.split('/chia-se/')[1];
        const cc = await anon.post(`/vi/hanh-trinh/chia-se/${tok}/bao-an-toan`, { form: { status: 'safe' }, maxRedirects: 0 });
        const forged = await trav.post('/vi/hanh-trinh/TR-SEED-02/huy', { headers: { 'X-XSRF-TOKEN': 'gia-mao' }, maxRedirects: 0 });
        const still = (await tripDetail(trav, 'TR-SEED-02')).trip.status;
        const wr = await playwright.request.newContext({ baseURL: info.project.use.baseURL, storageState: writerFile(info) });
        const ok = await send(wr, 'POST', '/vi/hanh-trinh/tao', { title: '' });
        await wr.dispose();
        const out = `đăng nhập không token → ${a.status()}\ntạo hành trình không token → ${b.status()}\nbáo an toàn qua form không token → ${cc.status()}\nhủy hành trình với X-XSRF-TOKEN giả → ${forged.status()} (TR-SEED-02 vẫn ${still})\nđối chứng có token hợp lệ → ${ok.status} (422 hợp lệ dữ liệu)`;
        await showText(page, 'TC-SEC-005 CSRF', out);
        await shot(page, 'TC-SEC-005', info);
        actual(info, out.replace(/\n/g, ' ; '));
        for (const s of [a.status(), b.status(), cc.status(), forged.status()]) expect(s).toBe(419);
        expect(still).toBe('active');
        expect(ok.status).toBe(422);
        await anon.dispose();
        await trav.dispose();
    });

    test('TC-SEC-006 token chia sẻ sai / thiếu 1 ký tự / dò id số → 404, không lộ thông tin', async ({ page, request, playwright }, info) => {
        const trav = await playwright.request.newContext({ baseURL: info.project.use.baseURL, storageState: authFile('traveler') });
        const tok = (await tripDetail(trav, 'TR-SEED-01')).trip.share_url.split('/chia-se/')[1];
        await trav.dispose();
        const fake = 'A'.repeat(40);
        const urls = [`/vi/hanh-trinh/chia-se/${fake}`, `/vi/hanh-trinh/chia-se/${tok.slice(0, 39)}`, `/api/v1/trips/shared/${fake}`, `/api/v1/trips/shared/1`, `/vi/hanh-trinh/1`];
        const out = [];
        for (const u of urls) out.push(`${u.replace(fake, 'AAAA…(40)')} → ${(await request.get(u, { maxRedirects: 0 })).status()}`);
        const post = await send(request, 'POST', `/api/v1/trips/shared/${fake}/checkin`, { status: 'safe' }, { csrf: false });
        out.push(`POST /api/v1/trips/shared/AAAA…/checkin → ${post.status}`);
        await page.goto(`/vi/hanh-trinh/chia-se/${fake}`);
        await shot(page, 'TC-SEC-006', info);
        actual(info, `token thật dài ${tok.length} ký tự [A-Za-z0-9]: ${/^[A-Za-z0-9]{40}$/.test(tok)}; ${out.join(' ; ')}`);
        expect(out.slice(0, 4).every((l) => l.endsWith('404'))).toBeTruthy();
        expect(post.status).toBe(404);
    });

    test('TC-SEC-007 đăng nhập sai 5 lần/phút → lần 6 (kể cả đúng mật khẩu) bị chặn, báo thời gian chờ @chromium', async ({ page, playwright }, info) => {
        const ctx = await playwright.request.newContext({ baseURL: info.project.use.baseURL });
        await ctx.get('/vi/dang-nhap');
        const codes = [];
        for (let i = 0; i < 5; i++) {
            const r = await ctx.post('/vi/dang-nhap', { form: { email: 'traveler6@dukhach.test', password: `Sai${i}Matkhau` }, headers: { ...(await xsrfHeader(ctx)), Accept: 'application/json' }, maxRedirects: 0 });
            codes.push(r.status());
        }
        const sixth = await ctx.post('/vi/dang-nhap', { form: { email: 'traveler6@dukhach.test', password: PASSWORD }, headers: { ...(await xsrfHeader(ctx)), Accept: 'application/json' }, maxRedirects: 0 });
        const body = await sixth.text();
        await page.goto('/vi/dang-nhap');
        await page.fill('input[type=email]', 'traveler6@dukhach.test');
        await page.fill('input[type=password]', PASSWORD);
        await page.click('button[type=submit]');
        await page.waitForTimeout(1500);
        const msg = (await page.locator('.field-error, [role=alert]').allInnerTexts()).join(' | ');
        await shot(page, 'TC-SEC-007', info);
        actual(info, `5 lần sai → ${codes.join(', ')}; lần 6 mật khẩu đúng (HTTP) → ${sixth.status()} ${body.slice(0, 140)}; giao diện: ở ${new URL(page.url()).pathname}, "${msg}"`);
        expect(codes.every((c) => c === 422)).toBeTruthy();
        expect(sixth.status()).toBe(429);
        expect(msg).toMatch(/quá nhiều|thử lại sau/i);
        expect(page.url()).toContain('/dang-nhap');
        await ctx.dispose();
    });

    test('TC-SEC-010 cookie phiên HttpOnly + SameSite=Lax; phiên được tạo mới khi đăng nhập', async ({ page, playwright }, info) => {
        const r = await page.request.get('/vi');
        const sc = r.headersArray().filter((h) => h.name.toLowerCase() === 'set-cookie').map((h) => h.value);
        const sess = sc.find((c) => /session=/.test(c)) || '';
        const xsrf = sc.find((c) => /^XSRF-TOKEN=/.test(c)) || '';
        const ctx = await playwright.request.newContext({ baseURL: info.project.use.baseURL });
        await ctx.get('/vi/dang-nhap');
        const before = (await ctx.storageState()).cookies.find((c) => /session/.test(c.name))?.value;
        await ctx.post('/vi/dang-nhap', { form: { email: 'traveler1@dukhach.test', password: PASSWORD }, headers: await xsrfHeader(ctx), maxRedirects: 0 });
        const after = (await ctx.storageState()).cookies.find((c) => /session/.test(c.name))?.value;
        await ctx.dispose();
        const attrs = (c) => c.split(';').slice(1).map((s) => s.trim()).join('; ');
        await showText(page, 'TC-SEC-010 Set-Cookie', `${sess.split('=')[0]}: ${attrs(sess)}\nXSRF-TOKEN: ${attrs(xsrf)}\nCookie phiên trước/sau đăng nhập khác nhau: ${before !== after}`);
        await shot(page, 'TC-SEC-010', info);
        actual(info, `${sess.split('=')[0]}: ${attrs(sess)}; XSRF-TOKEN: ${attrs(xsrf)}; Secure: ${/secure/i.test(sess) ? 'có' : 'không (HTTP cục bộ, SESSION_SECURE_COOKIE=false; bật khi HTTPS)'}; cookie phiên đổi sau đăng nhập: ${before !== after}`);
        expect(sess).toMatch(/httponly/i);
        expect(sess).toMatch(/samesite=lax/i);
        expect(xsrf).not.toMatch(/httponly/i);
        expect(before).not.toBe(after);
    });
});

test.describe('Header bảo mật và lỗi', () => {
    test('TC-SEC-009 header bảo mật + CSP nonce trên trang, API và healthz; mọi <script> inline mang nonce', async ({ page, request }, info) => {
        const out = [];
        let html = '';
        let csp = '';
        for (const u of ['/vi', '/api/v1/alerts', '/healthz']) {
            const r = await request.get(u);
            const h = r.headers();
            if (u === '/vi') {
                html = await r.text();
                csp = h['content-security-policy'] || '';
            }
            out.push(`${u}: XFO=${h['x-frame-options']}; XCTO=${h['x-content-type-options']}; Referrer=${h['referrer-policy']}; Permissions=${h['permissions-policy'] ? 'có' : 'không'}; CSP=${h['content-security-policy'] ? 'có' : 'không'}; X-Powered-By=${h['x-powered-by'] || 'không'}`);
        }
        const nonce = (csp.match(/'nonce-([^']+)'/) || [])[1];
        const scripts = [...html.matchAll(/<script\b([^>]*)>/g)].map((m) => m[1]);
        const inlineNoNonce = scripts.filter((a) => !/src=/.test(a) && !/nonce=/.test(a)).length;
        const nonceOk = scripts.filter((a) => /nonce=/.test(a)).every((a) => a.includes(`nonce="${nonce}"`));
        const r2 = await request.get('/vi');
        const nonce2 = ((r2.headers()['content-security-policy'] || '').match(/'nonce-([^']+)'/) || [])[1];
        await showText(page, 'TC-SEC-009 Header bảo mật', `${out.join('\n')}\n\nCSP: ${csp}\n\n${scripts.length} thẻ script, inline thiếu nonce: ${inlineNoNonce}; nonce khớp: ${nonceOk}; nonce đổi mỗi yêu cầu: ${nonce !== nonce2}`);
        await shot(page, 'TC-SEC-009', info);
        actual(info, `${out.join(' | ')}; CSP có frame-ancestors 'none', object-src 'none'; ${scripts.length} script, inline thiếu nonce ${inlineNoNonce}, nonce khớp ${nonceOk}, nonce đổi mỗi yêu cầu ${nonce !== nonce2}; img-src: ${(csp.match(/img-src([^;]*)/) || [])[1]?.trim()}`);
        // trang HTML: đủ 5 header; phản hồi JSON (API, healthz): tối thiểu X-Content-Type-Options: nosniff
        expect(out[0]).toMatch(/XFO=DENY; XCTO=nosniff; Referrer=strict-origin-when-cross-origin; Permissions=có; CSP=có/);
        expect(out.slice(1).every((l) => /XCTO=nosniff/.test(l)), 'API/healthz thiếu nosniff').toBeTruthy();
        expect(inlineNoNonce).toBe(0);
        expect(nonceOk).toBeTruthy();
        expect(nonce).not.toBe(nonce2);
        // img-src chỉ liệt kê nguồn tile cụ thể, không cho mọi https:
        const imgSrc = (csp.match(/img-src([^;]*)/) || [])[1] || '';
        expect(imgSrc.split(/\s+/)).not.toContain('https:');
    });

    test('TC-SEC-011 tải ảnh sự cố sai MIME (văn bản đổi đuôi .jpg) và > 5 MB → 422; ảnh PNG thật → nhận @chromium', async ({ page, playwright }, info) => {
        const ctx = await playwright.request.newContext({ baseURL: info.project.use.baseURL, storageState: authFile('traveler1') });
        const base = { zone_code: 'hue', hazard_code: 'lu', title: 'E2E ảnh sự cố', description: 'Kiểm thử tải ảnh sự cố' };
        const file = (name, mimeType, buffer) => ({ ...base, photo: { name, mimeType, buffer } });
        const fake = await send(ctx, 'POST', '/vi/bao-su-co', {}, { multipart: file('gia-anh.jpg', 'image/jpeg', fs.readFileSync(path.join(FIXTURES, 'gia-anh.jpg'))) });
        const svg = await send(ctx, 'POST', '/vi/bao-su-co', {}, { multipart: file('anh.svg', 'image/svg+xml', Buffer.from('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>')) });
        const big = Buffer.concat([fs.readFileSync(path.join(FIXTURES, 'anh-kiem-thu.png')), Buffer.alloc(5.2 * 1024 * 1024)]);
        const large = await send(ctx, 'POST', '/vi/bao-su-co', {}, { multipart: file('lon.png', 'image/png', big) });
        const good = await send(ctx, 'POST', '/vi/bao-su-co', {}, { multipart: file('anh-kiem-thu.png', 'image/png', fs.readFileSync(path.join(FIXTURES, 'anh-kiem-thu.png'))), headers: { 'X-Inertia': 'true' } });
        const out = `văn bản đổi đuôi .jpg (Content-Type image/jpeg) → ${fake.status} ${JSON.stringify(fake.body.errors?.photo)}\nSVG có script → ${svg.status} ${JSON.stringify(svg.body.errors?.photo)}\nPNG 5,2 MB → ${large.status} ${JSON.stringify(large.body.errors?.photo)}\nPNG thật 64×48 → ${good.status} ${good.location}`;
        await showText(page, 'TC-SEC-011 Tải ảnh sự cố', out);
        await shot(page, 'TC-SEC-011', info);
        actual(info, out.replace(/\n/g, ' ; '));
        expect(fake.status).toBe(422);
        expect(svg.status).toBe(422);
        expect(large.status).toBe(422);
        expect([302, 303]).toContain(good.status);
        await ctx.dispose();
    });

    test('TC-SEC-013 lỗi không lộ stack trace / đường dẫn mã (APP_DEBUG=false)', async ({ page, request }, info) => {
        const r1 = await request.post('/api/v1/trips/shared/xyz/checkin', { headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, data: '{"status": "safe",,,}' });
        const r2 = await request.get('/vi/canh-bao?page[]=x&severity[]=1');
        const r3 = await request.get('/vi/hanh-trinh/chia-se/%27%22%3E%3Cscript%3E');
        const out = [];
        for (const [n, r] of [['JSON hỏng', r1], ['tham số mảng lạ', r2], ['token chứa ký tự đặc biệt', r3]]) {
            const t = await r.text();
            out.push(`${n} → ${r.status()}; lộ stack/đường dẫn: ${/Stack trace|vendor\/laravel|\/var\/www|Whoops|#0 /.test(t)}`);
        }
        await showText(page, 'TC-SEC-013', out.join('\n'));
        await shot(page, 'TC-SEC-013', info);
        actual(info, out.join(' ; '));
        expect(out.every((l) => l.endsWith('false'))).toBeTruthy();
        expect(out.every((l) => !/ → 5\d\d/.test(l))).toBeTruthy();
    });
});

test.describe('Nhật ký kiểm toán (admin)', () => {
    test.use({ storageState: authFile('admin') });

    test('TC-SEC-014 audit_logs ghi đăng nhập, phát/hết hiệu lực cảnh báo, phản hồi check-in, mô phỏng', async ({ page, request }, info) => {
        const p = (await getPage(request, '/vi/quan-tri/nhat-ky')).page.props;
        const rows = p.logs?.data || p.auditLogs?.data || p.entries?.data || [];
        const actions = [...new Set(rows.map((r) => r.action))];
        await page.goto('/vi/quan-tri/nhat-ky');
        await shot(page, 'TC-SEC-014', info);
        actual(info, `${(p.logs?.total ?? p.logs?.meta?.total) ?? rows.length} bản ghi; hành động trên trang 1: ${actions.join(', ')}; ví dụ: ${rows[0] ? `${rows[0].action} bởi ${rows[0].user?.name ?? rows[0].actor?.name} lúc ${rows[0].created_at}, IP ${rows[0].ip ?? rows[0].ip_address}` : '—'}`);
        expect(rows.length).toBeGreaterThan(0);
        expect(actions.some((a) => /^auth\.login/.test(a))).toBeTruthy();
    });
});
