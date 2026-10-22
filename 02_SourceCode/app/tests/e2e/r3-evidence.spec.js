// Vòng 3b (18/10/2026): minh chứng cho các thay đổi mới
// (a) chân trang trên trang quản trị + trang lỗi 403/404/419/500/503, HTML máy chủ có <footer id="static-footer">
// (b) kiểm tra hợp lệ phía máy khách: báo lỗi tiếng Việt, KHÔNG gửi request
// (c) URL tệp đính kèm đóng góp: id mã hóa + expires + signature (60 phút)
// (d) truy cập trái phép ghi Log::warning + audit_logs access.denied; nhật ký tách ngày 3 mức
// Các ca (d) đọc log/CSDL trong container qua `docker exec` (chỉ đọc; riêng TC-ADMIN-013 chạy app:backup-db để có dòng INFO).
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { test, expect, request as pwRequest } from '@playwright/test';
import { FIXTURES, authFile, actual, shot, evidence, getPage, send, showText, rnd, xsrfHeader } from './helpers.js';

const APP = process.env.E2E_APP_CONTAINER || '02_sourcecode-app-1';
const DB = process.env.E2E_DB_CONTAINER || '02_sourcecode-db-1';
const docker = (args) => execFileSync('docker', args, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
const appExec = (...args) => docker(['exec', APP, ...args]);
const tinker = (code) => appExec('php', 'artisan', 'tinker', `--execute=${code}`);
const sql = (q) => docker(['exec', DB, 'mysql', '-udisanso', '-pdisanso_secret', 'disanso', '-N', '-e', q]).trim();
const today = () => appExec('date', '+%F').trim();
const logFile = () => `storage/logs/laravel-${today()}.log`;
const logLines = () => Number(appExec('sh', '-c', `wc -l < ${logFile()} 2>/dev/null || echo 0`).trim());
const logSince = (n) => appExec('sh', '-c', `tail -n +${n + 1} ${logFile()}`);

const GUEST = { cookies: [], origins: [] };
const FOOTER_TAG = '<footer id="static-footer"';
const png = () => fs.readFileSync(path.join(FIXTURES, 'anh-kiem-thu.png'));

/** Đếm request ghi (khác GET) và XHR/fetch phát sinh trong khi chạy fn. */
async function captureRequests(page, fn) {
    const seen = [];
    const onReq = (r) => {
        const t = r.resourceType();
        if (r.method() !== 'GET' || t === 'xhr' || t === 'fetch') seen.push(`${r.method()} ${new URL(r.url()).pathname} (${t})`);
    };
    page.on('request', onReq);
    await fn();
    await page.waitForTimeout(1500);
    page.off('request', onReq);
    return seen;
}

const errorsIn = async (scope) => (await scope.locator('.field-error').allInnerTexts()).map((s) => s.trim()).filter(Boolean);

// ---------------------------------------------------------------- (a) chân trang
test.describe('SITE vòng 3b – chân trang học thuật', () => {
    test('TC-SITE-010 trang quản trị có chân trang; HTML máy chủ có <footer id="static-footer">; JS tắt vẫn thấy', async ({ browser }, ti) => {
        const ctx = await browser.newContext({ storageState: authFile('admin') });
        const page = await ctx.newPage();
        const rows = [];
        for (const url of ['/vi/quan-tri', '/vi/quan-tri/di-san', '/en/quan-tri/di-san']) {
            const src = await (await ctx.request.get(url)).text();
            await page.goto(url);
            // app.js gỡ #static-footer ngay sau khi Vue mount (sau sự kiện load vì trang/layout nạp động)
            await page.locator('#static-footer').waitFor({ state: 'detached', timeout: 10_000 }).catch(() => {});
            const footer = page.locator('footer[data-academic-footer]:visible');
            await expect(footer).toHaveCount(1);
            const text = (await footer.innerText()).replace(/\s+/g, ' ');
            rows.push({ url, srcHasStatic: src.includes(FOOTER_TAG), staticAfterMount: await page.locator('#static-footer').count(), footer: text });
        }
        await page.goto('/vi/quan-tri');
        await page.locator('footer[data-academic-footer]:visible').scrollIntoViewIfNeeded();
        await shot(page, 'TC-SITE-010', { fullPage: true });
        await ctx.close();
        // JS tắt: chỉ còn bản Blade phía máy chủ
        const noJs = await browser.newContext({ storageState: authFile('admin'), javaScriptEnabled: false });
        const p2 = await noJs.newPage();
        await p2.goto('/vi/quan-tri');
        const staticVisible = await p2.locator('#static-footer').isVisible();
        const staticText = (await p2.locator('#static-footer').innerText()).replace(/\s+/g, ' ');
        await shot(p2, 'TC-SITE-010_nojs', { fullPage: true });
        await noJs.close();
        const text = rows.map((r) => `${r.url}: view-source có ${FOOTER_TAG}> = ${r.srcHasStatic}; #static-footer sau khi Vue mount = ${r.staticAfterMount} phần tử; chân trang hiển thị: "${r.footer}"`).join('\n')
            + `\nJS tắt (/vi/quan-tri): #static-footer hiển thị = ${staticVisible}; nội dung "${staticText}"`;
        evidence('TC-SITE-010', `Chân trang học thuật trên trang quản trị (admin@disanso.test)\n\n${text}`);
        actual(ti, text.replace(/\n/g, '; '));
        for (const r of rows) {
            expect(r.srcHasStatic).toBe(true);
            expect(r.staticAfterMount).toBe(0);
            expect(r.footer).toContain('CSE703073');
        }
        expect(staticVisible).toBe(true);
        expect(staticText).toContain('CSE703073');
    });

    test('TC-SITE-011 trang lỗi 403/404/419 (HTTP thật) có chân trang học thuật trong HTML máy chủ', async ({ browser, baseURL }, ti) => {
        const out = [];
        // 403: member mở khu quản trị (điều hướng thường → Blade errors/403)
        const mem = await browser.newContext({ storageState: authFile('member') });
        const mp = await mem.newPage();
        const r403 = await mp.goto('/vi/quan-tri');
        const html403 = await r403.text();
        await shot(mp, 'TC-SITE-011_403', { fullPage: true });
        out.push({ code: 403, url: 'GET /vi/quan-tri (member)', status: r403.status(), footer: html403.includes(FOOTER_TAG), visible: await mp.locator('#static-footer').isVisible() });
        await mem.close();
        // 404: khách mở slug không tồn tại
        const guest = await browser.newContext({ storageState: GUEST });
        const gp = await guest.newPage();
        const r404 = await gp.goto('/vi/di-san/khong-ton-tai-qa-r3');
        const html404 = await r404.text();
        await shot(gp, 'TC-SITE-011_404', { fullPage: true });
        out.push({ code: 404, url: 'GET /vi/di-san/khong-ton-tai-qa-r3 (khách)', status: r404.status(), footer: html404.includes(FOOTER_TAG), visible: await gp.locator('#static-footer').isVisible() });
        // 419: POST đăng nhập có cookie phiên nhưng không gửi CSRF token (Accept text/html)
        const api = await pwRequest.newContext({ baseURL, storageState: GUEST });
        await api.get('/vi/dang-nhap');
        const r419 = await api.post('/vi/dang-nhap', { form: { email: 'user@disanso.test', password: 'x' }, headers: { Accept: 'text/html' }, maxRedirects: 0 });
        const html419 = await r419.text();
        await api.dispose();
        await gp.setContent(html419);
        await shot(gp, 'TC-SITE-011_419', { fullPage: true });
        out.push({ code: 419, url: 'POST /vi/dang-nhap không có _token (khách)', status: r419.status(), footer: html419.includes(FOOTER_TAG), visible: await gp.locator('#static-footer').isVisible() });
        await gp.goto('/vi/di-san/khong-ton-tai-qa-r3');
        await shot(gp, 'TC-SITE-011', { fullPage: true });
        await guest.close();
        const text = out.map((o) => `${o.url} → HTTP ${o.status}; HTML có ${FOOTER_TAG}> = ${o.footer}; chân trang hiển thị = ${o.visible}`).join('\n');
        evidence('TC-SITE-011', `Trang lỗi Blade có chân trang học thuật\n\n${text}\nẢnh: TC-SITE-011_403.png, TC-SITE-011_404.png, TC-SITE-011_419.png`);
        actual(ti, text.replace(/\n/g, '; '));
        for (const o of out) {
            expect(o.status).toBe(o.code);
            expect(o.footer).toBe(true);
            expect(o.visible).toBe(true);
        }
    });

    test('TC-SITE-012 trang lỗi 500/503 dựng qua ExceptionHandler thật có chân trang, không lộ thông tin kỹ thuật', async ({ page }, ti) => {
        // Không bật maintenance/không gây lỗi thật trên hệ thống dùng chung: gọi ExceptionHandler của ứng dụng trong container
        const code = 'foreach (["503" => new Symfony\\Component\\HttpKernel\\Exception\\HttpException(503), "500" => new RuntimeException("qa-r3 SQLSTATE secret-trace")] as $k => $e) {'
            + ' $r = app(Illuminate\\Contracts\\Debug\\ExceptionHandler::class)->render(Illuminate\\Http\\Request::create("/vi/qa-r3", "GET"), $e);'
            + ' file_put_contents("/tmp/qa_r3_err_".$k.".html", $r->getContent()); echo $k, "=", $r->getStatusCode(), PHP_EOL; }';
        const statuses = tinker(code).trim();
        const out = [];
        for (const k of ['500', '503']) {
            const html = appExec('cat', `/tmp/qa_r3_err_${k}.html`);
            await page.setContent(html);
            await shot(page, `TC-SITE-012_${k}`, { fullPage: true });
            out.push({ k, footer: html.includes(FOOTER_TAG), visible: await page.locator('#static-footer').isVisible(), leak: /SQLSTATE|secret-trace|RuntimeException|vendor\//.test(html), title: await page.title() });
        }
        appExec('rm', '-f', '/tmp/qa_r3_err_500.html', '/tmp/qa_r3_err_503.html');
        const text = `ExceptionHandler::render → ${statuses.replace(/\n/g, ', ')}\n` + out.map((o) => `${o.k}: title="${o.title}"; HTML có ${FOOTER_TAG}> = ${o.footer}; chân trang hiển thị = ${o.visible}; lộ chi tiết lỗi (SQLSTATE/tên lớp/vendor) = ${o.leak}`).join('\n');
        await showText(page, 'TC-SITE-012', 'Trang lỗi 500/503 (APP_DEBUG=false)', `${text}\nẢnh: TC-SITE-012_500.png, TC-SITE-012_503.png`);
        actual(ti, text.replace(/\n/g, '; '));
        expect(statuses).toContain('500=500');
        expect(statuses).toContain('503=503');
        for (const o of out) {
            expect(o.footer).toBe(true);
            expect(o.visible).toBe(true);
            expect(o.leak).toBe(false);
        }
    });
});

// ---------------------------------------------------------------- (b) kiểm tra hợp lệ phía máy khách
test.describe('VALID vòng 3b – kiểm tra phía máy khách không gửi request', () => {
    test('TC-AUTH-010 đăng nhập rỗng/email sai: lỗi tiếng Việt ngay, không có request', async ({ browser }, ti) => {
        const ctx = await browser.newContext({ storageState: GUEST });
        const page = await ctx.newPage();
        await page.goto('/vi/dang-nhap');
        const form = page.locator('form:has(input[type="password"])');
        const req1 = await captureRequests(page, () => form.locator('button[type="submit"]').click());
        const e1 = await errorsIn(form);
        await form.locator('input[type="email"]').fill('abc@');
        await form.locator('input[type="password"]').fill('x');
        const req2 = await captureRequests(page, () => form.locator('button[type="submit"]').click());
        const e2 = await errorsIn(form);
        const focused = await page.evaluate(() => document.activeElement?.getAttribute('type'));
        await shot(page, 'TC-AUTH-010');
        await ctx.close();
        const text = `Rỗng → lỗi ${JSON.stringify(e1)}; request phát sinh: ${req1.length} ${JSON.stringify(req1)}\nemail="abc@" → lỗi ${JSON.stringify(e2)}; request: ${req2.length}; tiêu điểm ở ô type=${focused}`;
        evidence('TC-AUTH-010', text);
        actual(ti, text.replace(/\n/g, '; '));
        expect(e1).toEqual(expect.arrayContaining(['Email là bắt buộc.', 'Mật khẩu là bắt buộc.']));
        expect(e2).toContain('Email không đúng định dạng email.');
        expect(req1.length + req2.length).toBe(0);
    });

    test('TC-AUTH-011 đăng ký: tên 1 ký tự, email sai, mật khẩu 7 ký tự, nhập lại lệch – không có request', async ({ browser }, ti) => {
        const ctx = await browser.newContext({ storageState: GUEST });
        const page = await ctx.newPage();
        await page.goto('/vi/dang-ky');
        const form = page.locator('form:has(input[type="password"])');
        const req1 = await captureRequests(page, () => form.locator('button[type="submit"]').click());
        const e1 = await errorsIn(form);
        await form.locator('input[autocomplete="name"]').fill('A');
        await form.locator('input[type="email"]').fill('khong-phai-email');
        const pw = form.locator('input[type="password"]');
        await pw.nth(0).fill('abc1234');
        await pw.nth(1).fill('abc12345');
        const req2 = await captureRequests(page, () => form.locator('button[type="submit"]').click());
        const e2 = await errorsIn(form);
        await shot(page, 'TC-AUTH-011', { fullPage: true });
        await ctx.close();
        const text = `Rỗng → lỗi ${JSON.stringify(e1)}; request: ${req1.length}\nDữ liệu sai → lỗi ${JSON.stringify(e2)}; request: ${req2.length} ${JSON.stringify(req2)}`;
        evidence('TC-AUTH-011', text);
        actual(ti, text.replace(/\n/g, '; '));
        expect(e1.length).toBeGreaterThanOrEqual(4);
        expect(e2.join(' ')).toMatch(/ít nhất 2 ký tự/);
        expect(e2.join(' ')).toMatch(/định dạng email/);
        expect(e2.join(' ')).toMatch(/ít nhất 8 ký tự/);
        expect(req1.length + req2.length).toBe(0);
    });

    test('TC-BOOK-014 form đặt lịch: số khách 0, tên rỗng, SĐT/email sai – lỗi tiếng Việt, không gửi POST', async ({ browser }, ti) => {
        const ctx = await browser.newContext({ storageState: authFile('member') });
        const page = await ctx.newPage();
        await page.goto('/vi/tuyen-tham-quan/dau-an-thang-long');
        const form = page.locator('#booking form');
        await form.locator('input[type="number"]').fill('0');
        await form.locator('input[autocomplete="name"]').fill('');
        await form.locator('input[autocomplete="tel"]').fill('09ab');
        await form.locator('input[autocomplete="email"]').fill('abc');
        const req = await captureRequests(page, () => form.locator('button[type="submit"]').click());
        const errs = await errorsIn(form);
        await form.scrollIntoViewIfNeeded();
        await shot(page, 'TC-BOOK-014');
        await form.locator('input[type="number"]').fill('11');
        const req2 = await captureRequests(page, () => form.locator('button[type="submit"]').click());
        const errs2 = await errorsIn(form);
        await ctx.close();
        const text = `quantity=0, tên rỗng, SĐT=09ab, email=abc → lỗi ${JSON.stringify(errs)}; request: ${req.length} ${JSON.stringify(req)}\nquantity=11 → lỗi ${JSON.stringify(errs2)}; request: ${req2.length}`;
        evidence('TC-BOOK-014', text);
        actual(ti, text.replace(/\n/g, '; '));
        expect(errs.join(' ')).toMatch(/Số khách phải là số trong khoảng 1 đến 10/);
        expect(errs.join(' ')).toMatch(/9–15 chữ số/);
        expect(errs.join(' ')).toMatch(/định dạng email/);
        expect(errs2.join(' ')).toMatch(/1 đến 10/);
        expect(req.length + req2.length).toBe(0);
    });

    test('TC-CONTRIB-007 form đóng góp rỗng và quá ngắn – lỗi tiếng Việt, không gửi POST', async ({ browser }, ti) => {
        const ctx = await browser.newContext({ storageState: authFile('member') });
        const page = await ctx.newPage();
        await page.goto('/vi/dong-gop/moi');
        const form = page.locator('form:has(textarea)');
        const req1 = await captureRequests(page, () => form.locator('button[type="submit"]').click());
        const e1 = await errorsIn(form);
        await form.locator('input[type="text"]').first().fill('Abc');
        await form.locator('textarea').first().fill('Quá ngắn');
        const req2 = await captureRequests(page, () => form.locator('button[type="submit"]').click());
        const e2 = await errorsIn(form);
        await shot(page, 'TC-CONTRIB-007', { fullPage: true });
        await ctx.close();
        const text = `Rỗng → lỗi ${JSON.stringify(e1)}; request: ${req1.length}\ntiêu đề "Abc", nội dung "Quá ngắn" → lỗi ${JSON.stringify(e2)}; request: ${req2.length} ${JSON.stringify(req2)}`;
        evidence('TC-CONTRIB-007', text);
        actual(ti, text.replace(/\n/g, '; '));
        expect(e1.join(' ')).toMatch(/là bắt buộc/);
        expect(e2.join(' ')).toMatch(/ít nhất 5 ký tự/);
        expect(e2.join(' ')).toMatch(/ít nhất 20 ký tự/);
        expect(req1.length + req2.length).toBe(0);
    });

    test('TC-MEDIA-005 form tải media rỗng/URL không phải https – lỗi tiếng Việt, không gửi POST', async ({ browser }, ti) => {
        const ctx = await browser.newContext({ storageState: authFile('admin') });
        const page = await ctx.newPage();
        await page.goto('/vi/quan-tri/di-san/chua-mot-cot/sua');
        const form = page.locator('form.border-dashed');
        await form.scrollIntoViewIfNeeded();
        const req1 = await captureRequests(page, () => form.locator('button[type="submit"]').click());
        const e1 = await errorsIn(form);
        await form.locator('input[type="url"]').first().fill('http://example.org/anh.jpg');
        const req2 = await captureRequests(page, () => form.locator('button[type="submit"]').click());
        const e2 = await errorsIn(form);
        await form.scrollIntoViewIfNeeded();
        await shot(page, 'TC-MEDIA-005');
        await ctx.close();
        const text = `Rỗng → lỗi ${JSON.stringify(e1)}; request: ${req1.length}\nURL ngoài = http://… → lỗi ${JSON.stringify(e2)}; request: ${req2.length} ${JSON.stringify(req2)}`;
        evidence('TC-MEDIA-005', text);
        actual(ti, text.replace(/\n/g, '; '));
        expect(e1.join(' ')).toMatch(/là bắt buộc/);
        expect(e1.length).toBeGreaterThanOrEqual(3);
        expect(e2.join(' ')).toMatch(/URL hợp lệ/);
        expect(req1.length + req2.length).toBe(0);
    });
});

// ---------------------------------------------------------------- (c)+(d) signed URL và nhật ký truy cập trái phép
test.describe.serial('SEC vòng 3b – URL ký tệp đính kèm + nhật ký access.denied', () => {
    const st = {};

    test.beforeAll(async ({ browser }) => {
        const ctx = await browser.newContext({ storageState: authFile('member') });
        await ctx.request.get('/vi');
        const c = await send(ctx.request, 'POST', '/vi/dong-gop', null, {
            accept: 'html',
            multipart: { contribution_type: 'media', locale: 'vi', title: `Ảnh tư liệu QA r3b ${rnd()}`, body: 'Ảnh chụp bia đá tại Hoàng thành Thăng Long, gửi kèm để kiểm duyệt viên đối chiếu (QA r3b).', attachment: { name: 'anh-kiem-thu.png', mimeType: 'image/png', buffer: png() } },
        });
        st.id = Number(c.location.split('/').pop());
        const { page } = await getPage(ctx.request, `/vi/dong-gop/${st.id}`);
        st.url = page.props.contribution.attachment_url;
        const u = new URL(st.url, 'http://x');
        st.path = u.pathname;
        st.expires = u.searchParams.get('expires');
        st.signature = u.searchParams.get('signature');
        st.token = u.pathname.split('/').pop();
        st.ownerStatus = (await ctx.request.get(st.url)).status();
        await ctx.close();
        st.auditBefore = Number(sql("SELECT COUNT(*) FROM audit_logs WHERE action='access.denied'"));
        st.logStart = logLines();
    });

    const get = async (role, url) => {
        const ctx = await pwRequest.newContext({ baseURL: process.env.E2E_BASE_URL || 'http://localhost:8080', storageState: role ? authFile(role) : GUEST });
        const r = await ctx.get(url, { maxRedirects: 0 });
        const out = { status: r.status(), location: r.headers()['location'] || '', ctype: r.headers()['content-type'] || '' };
        await ctx.dispose();
        return out;
    };

    test('TC-SEC-022 URL đính kèm thiếu chữ ký/thiếu expires → 403', async ({ page }, ti) => {
        const noQuery = await get('member', st.path);
        const noSig = await get('member', `${st.path}?expires=${st.expires}`);
        const noExp = await get('member', `${st.path}?signature=${st.signature}`);
        const ttl = Number(st.expires) - Math.floor(Date.now() / 1000);
        const text = `Đóng góp #${st.id} (user@) → attachment_url = ${st.url}\nĐịnh dạng: /vi/dong-gop/tep-dinh-kem/{token mã hóa ${st.token.length} ký tự}?expires=…&signature=…; còn hạn ≈ ${ttl}s (TTL 60 phút)\nChủ GET URL đầy đủ → ${st.ownerStatus}\nChủ GET không query → ${noQuery.status}\nChủ GET chỉ expires (thiếu signature) → ${noSig.status}\nChủ GET chỉ signature (thiếu expires) → ${noExp.status}`;
        await showText(page, 'TC-SEC-022', 'URL ký thiếu chữ ký', text);
        actual(ti, text.replace(/\n/g, '; '));
        expect(st.path).toMatch(/^\/vi\/dong-gop\/tep-dinh-kem\/[A-Za-z0-9_-]{40,}$/);
        expect(st.path).not.toContain(`/${st.id}`);
        expect(ttl).toBeGreaterThan(3500);
        expect(ttl).toBeLessThanOrEqual(3600);
        expect(st.ownerStatus).toBe(200);
        expect([noQuery.status, noSig.status, noExp.status]).toEqual([403, 403, 403]);
    });

    test('TC-SEC-023 URL đính kèm sai chữ ký / sửa expires / sửa token → 403; đường dẫn cũ → 404', async ({ page }, ti) => {
        const last = st.signature.slice(-1) === 'a' ? 'b' : 'a';
        const badSig = await get('member', `${st.path}?expires=${st.expires}&signature=${st.signature.slice(0, -1)}${last}`);
        const extend = await get('member', `${st.path}?expires=${Number(st.expires) + 86400}&signature=${st.signature}`);
        const tok = st.token.slice(0, -2) + (st.token.slice(-2) === 'AA' ? 'BB' : 'AA');
        const badTok = await get('member', `/vi/dong-gop/tep-dinh-kem/${tok}?expires=${st.expires}&signature=${st.signature}`);
        const oldPath = await get('member', `/vi/dong-gop/${st.id}/tep-dinh-kem`);
        const enPath = await get('member', `${st.path.replace('/vi/', '/en/')}?expires=${st.expires}&signature=${st.signature}`);
        const text = `Đổi 1 ký tự signature → ${badSig.status}\nGiữ signature, kéo dài expires +1 ngày → ${extend.status}\nĐổi 2 ký tự cuối token, giữ expires+signature → ${badTok.status}\nĐổi /vi/ thành /en/ (đường dẫn khác chữ ký) → ${enPath.status}\nĐường dẫn cũ /vi/dong-gop/${st.id}/tep-dinh-kem → ${oldPath.status}`;
        await showText(page, 'TC-SEC-023', 'URL ký bị sửa', text);
        actual(ti, text.replace(/\n/g, '; '));
        expect([badSig.status, extend.status, badTok.status, enPath.status]).toEqual([403, 403, 403, 403]);
        expect(oldPath.status).toBe(404);
    });

    test('TC-SEC-024 URL đính kèm đúng chữ ký nhưng đã hết hạn → 403', async ({ page }, ti) => {
        // Ký bằng đúng APP_KEY của ứng dụng nhưng expires lùi 1 phút (mô phỏng người dùng mở lại liên kết sau 60 phút)
        const code = `echo Illuminate\\Support\\Facades\\URL::temporarySignedRoute("contributions.attachment", now()->subMinute(), ["locale" => "vi", "token" => App\\Support\\UrlToken::encode(App\\Http\\Resources\\ContributionItemResource::URL_TOKEN_PURPOSE, ${st.id})], false);`;
        const expired = tinker(code).trim().split('\n').pop().trim();
        const fresh = `echo Illuminate\\Support\\Facades\\URL::temporarySignedRoute("contributions.attachment", now()->addMinutes(60), ["locale" => "vi", "token" => App\\Support\\UrlToken::encode(App\\Http\\Resources\\ContributionItemResource::URL_TOKEN_PURPOSE, ${st.id})], false);`;
        const freshUrl = tinker(fresh).trim().split('\n').pop().trim();
        const e = await get('member', expired);
        const f = await get('member', freshUrl);
        const exp = Number(new URL(expired, 'http://x').searchParams.get('expires'));
        const text = `URL ký hợp lệ, expires = ${exp} (${new Date(exp * 1000).toISOString()}, trước hiện tại ${Math.floor(Date.now() / 1000) - exp}s)\n${expired}\nChủ GET → ${e.status}\nĐối chứng: cùng cách ký, expires +60 phút → ${f.status} ${f.ctype}`;
        await showText(page, 'TC-SEC-024', 'URL ký đã hết hạn', text);
        actual(ti, text.replace(/\n/g, '; '));
        expect(e.status).toBe(403);
        expect(f.status).toBe(200);
    });

    test('TC-SEC-025 URL đính kèm đúng chữ ký nhưng không có quyền → 403 (thành viên khác), khách → đăng nhập, staff → 200', async ({ page }, ti) => {
        const other = await get('member2', st.url);
        const contributor = await get('contributor', st.url);
        const guest = await get(null, st.url);
        const mgr = await get('manager', st.url);
        const text = `URL đầy đủ, chữ ký đúng, còn hạn: ${st.url}\nmember1@ (thành viên khác) → ${other.status}\npartner@ (contributor, không phải chủ) → ${contributor.status}\nKhách → ${guest.status} ${guest.location}\nmanager@ → ${mgr.status} ${mgr.ctype}`;
        await showText(page, 'TC-SEC-025', 'URL ký đúng nhưng không có quyền', text);
        actual(ti, text.replace(/\n/g, '; '));
        expect(other.status).toBe(403);
        expect(contributor.status).toBe(403);
        expect([302, 303]).toContain(guest.status);
        expect(guest.location).toContain('/dang-nhap');
        expect(mgr.status).toBe(200);
    });

    test('TC-SEC-026 truy cập trái phép (role/Policy/chữ ký) ghi Log::warning theo ngày + audit_logs access.denied', async ({ browser }, ti) => {
        const before = Number(sql("SELECT COUNT(*) FROM audit_logs WHERE action='access.denied'"));
        const start = logLines();
        const role = await get('member', '/vi/quan-tri');
        const policy = await get('member2', `/vi/dong-gop/${st.id}`);
        const sig = await get('member', `${st.path}?expires=${st.expires}&signature=deadbeef`);
        const lines = logSince(start).split('\n').filter((l) => l.includes('Truy cập trái phép'));
        const rows = sql(`SELECT id, user_id, action, ip, JSON_UNQUOTE(JSON_EXTRACT(meta,'$.reason')), JSON_UNQUOTE(JSON_EXTRACT(meta,'$.path')), created_at FROM audit_logs WHERE action='access.denied' ORDER BY id DESC LIMIT 3`);
        const after = Number(sql("SELECT COUNT(*) FROM audit_logs WHERE action='access.denied'"));
        const leak = lines.some((l) => /signature=|expires=|deadbeef/.test(l));
        const adm = await browser.newContext({ storageState: authFile('admin') });
        const p = await adm.newPage();
        await p.goto('/vi/quan-tri/nhat-ky?action=access.denied');
        await shot(p, 'TC-SEC-026', { fullPage: true });
        await adm.close();
        const text = `member GET /vi/quan-tri → ${role.status}; member1 GET /vi/dong-gop/${st.id} (của user@) → ${policy.status}; member GET tệp đính kèm signature=deadbeef → ${sig.status}\n\n${logFile()} – dòng mới:\n${lines.join('\n')}\n\naudit_logs access.denied: ${before} → ${after}\nid | user_id | action | ip | reason | path | created_at\n${rows}\n\nLog chứa query string (signature/expires) = ${leak}`;
        evidence('TC-SEC-026', `Nhật ký truy cập trái phép\n\n${text}`);
        actual(ti, `role→${role.status}, policy→${policy.status}, chữ ký sai→${sig.status}; ${lines.length} dòng WARNING "Truy cập trái phép" trong ${logFile()}; audit_logs access.denied ${before}→${after}; lý do: ${rows.split('\n').map((r) => r.split('\t')[4]).join(', ')}; log không chứa signature/expires: ${!leak}`);
        expect([role.status, policy.status, sig.status]).toEqual([403, 403, 403]);
        expect(lines.length).toBeGreaterThanOrEqual(3);
        expect(lines.every((l) => l.includes('.WARNING:'))).toBe(true);
        expect(lines.join('\n')).toMatch(/"reason":"role"/);
        expect(lines.join('\n')).toMatch(/"reason":"policy"/);
        expect(lines.join('\n')).toMatch(/"reason":"signature"/);
        expect(after - before).toBeGreaterThanOrEqual(3);
        expect(leak).toBe(false);
    });

    test.afterAll(async ({ browser }) => {
        // Dọn: từ chối đóng góp kiểm thử để không nằm trong hàng chờ kiểm duyệt
        const mgr = await browser.newContext({ storageState: authFile('manager') });
        await mgr.request.get('/vi');
        await send(mgr.request, 'PATCH', `/vi/quan-tri/kiem-duyet/${st.id}`, { status: 'rejected', review_note: 'Ca kiểm thử r3b – dọn dữ liệu' });
        await mgr.close();
    });
});

// ---------------------------------------------------------------- (d) nhật ký tách ngày, 3 mức
test.describe('ADMIN vòng 3b – nhật ký ứng dụng theo ngày', () => {
    test('TC-ADMIN-013 laravel-YYYY-MM-DD.log có đủ INFO/WARNING/ERROR và không chứa bí mật', async ({ page, baseURL }, ti) => {
        test.setTimeout(120_000);
        const start = logLines();
        // INFO 1: sao lưu CSDL (lệnh lịch nền chạy hằng ngày)
        const backup = appExec('php', 'artisan', 'app:backup-db').trim();
        // INFO 2: đăng nhập thành công (manager, form thật có CSRF)
        const api = await pwRequest.newContext({ baseURL, storageState: GUEST });
        await api.get('/vi/dang-nhap');
        const login = await api.post('/vi/dang-nhap', { form: { email: 'manager@disanso.test', password: process.env.E2E_PASSWORD || 'DiSan@2026', remember: '0' }, headers: { ...(await xsrfHeader(api)), Accept: 'text/html' }, maxRedirects: 0 });
        // WARNING: thành viên vào khu quản trị
        const mem = await pwRequest.newContext({ baseURL, storageState: authFile('member') });
        const denied = (await mem.get('/vi/quan-tri', { maxRedirects: 0 })).status();
        await mem.dispose();
        // ERROR: IPN cổng sandbox với chữ ký sai
        const ipn = await api.post('/api/v1/payments/sandbox/ipn', { data: { txn_ref: 'SB-QA-R3B-KHONGTONTAI', amount: 100000, outcome: 'success', signature: 'deadbeef' }, headers: { Accept: 'application/json' } });
        const ipnBody = await ipn.text();
        await api.dispose();
        const chunk = logSince(start);
        const entries = chunk.split('\n').filter((l) => /^\[\d{4}-\d{2}-\d{2} /.test(l));
        const levels = [...new Set(entries.map((l) => (l.match(/\.(DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL):/) || [])[1]).filter(Boolean))];
        const files = appExec('sh', '-c', 'ls -la storage/logs/').trim();
        let secrets = '';
        try {
            secrets = appExec('sh', '-c', "grep -inE 'password|passwd|secret|token=' storage/logs/*.log || true").trim();
        } catch (e) {
            secrets = String(e.stdout || e.message);
        }
        const ipnTrace = chunk.split('\n').filter((l) => l.includes('Invalid signature')).length;
        const text = [
            `Tệp: ${logFile()} (LOG_CHANNEL=stack, LOG_STACK=daily, LOG_LEVEL=info, LOG_DAILY_DAYS=14)`,
            '',
            'Thao tác kích hoạt:',
            `  1) php artisan app:backup-db → "${backup}"`,
            `  2) POST /vi/dang-nhap manager@disanso.test → HTTP ${login.status()} ${login.headers()['location'] || ''}`,
            `  3) user@ (member) GET /vi/quan-tri → HTTP ${denied}`,
            `  4) POST /api/v1/payments/sandbox/ipn signature=deadbeef → HTTP ${ipn.status()} ${ipnBody}`,
            '',
            `Các dòng nhật ký mới (${entries.length} bản ghi; mức: ${levels.join(', ')}):`,
            ...entries.map((l) => `  ${l.length > 400 ? `${l.slice(0, 400)} …[cắt, dòng dài ${l.length} ký tự]` : l}`),
            '',
            `ls -la storage/logs/:\n${files}`,
            '',
            "grep -inE 'password|passwd|secret|token=' storage/logs/*.log:",
            secrets === '' ? '  (rỗng – không có dòng nào khớp)' : secrets.split('\n').map((s) => `  ${s.slice(0, 300)}`).join('\n'),
        ].join('\n');
        evidence('TC-ADMIN-013_raw', text);
        fs.writeFileSync(path.join(path.dirname(authFile('x')), 'r3b_log_chunk.txt'), chunk, 'utf8');
        await showText(page, 'TC-ADMIN-013', 'Nhật ký ứng dụng theo ngày – 3 mức', text);
        actual(ti, `${logFile()}: ${entries.length} bản ghi mới, mức ${levels.join('/')}; backup → INFO "Database backup completed"; đăng nhập → ${login.status()} INFO "Đăng nhập thành công"; member /vi/quan-tri → ${denied} WARNING "Truy cập trái phép"; IPN chữ ký sai → ${ipn.status()} ERROR "Invalid signature" (${ipnTrace} dòng); grep bí mật trong storage/logs/*.log: ${secrets === '' ? 'rỗng' : `${secrets.split('\n').length} dòng khớp`}`);
        expect(levels).toEqual(expect.arrayContaining(['INFO', 'WARNING', 'ERROR']));
        expect(entries.some((l) => l.includes('INFO: Database backup completed'))).toBe(true);
        expect(entries.some((l) => l.includes('INFO: Đăng nhập thành công'))).toBe(true);
        expect(entries.some((l) => l.includes('WARNING: Truy cập trái phép'))).toBe(true);
        expect(entries.some((l) => l.includes('ERROR: Invalid signature'))).toBe(true);
        expect(ipn.status()).toBe(400);
        expect(secrets).toBe('');
    });
    test('TC-ADMIN-014 dashboard quản trị tiếng Anh /en/quan-tri tải được (biểu đồ di sản theo tỉnh)', async ({ browser }, ti) => {
        const ctx = await browser.newContext({ storageState: authFile('admin') });
        const page = await ctx.newPage();
        const start = logLines();
        const vi = await page.goto('/vi/quan-tri');
        const viStatus = vi.status();
        const en = await page.goto('/en/quan-tri');
        const enStatus = en.status();
        await shot(page, 'TC-ADMIN-014', { fullPage: true });
        await ctx.close();
        const err = logSince(start).split('\n').filter((l) => l.includes('.ERROR:')).map((l) => l.slice(0, 600));
        const text = `GET /vi/quan-tri → ${viStatus}\nGET /en/quan-tri → ${enStatus}\n\nDòng ERROR mới trong ${logFile()}:\n${err.join('\n') || '(không có)'}`;
        evidence('TC-ADMIN-014', `Dashboard quản trị theo ngôn ngữ (admin@disanso.test)\n\n${text}`);
        actual(ti, `GET /vi/quan-tri → ${viStatus}; GET /en/quan-tri → ${enStatus}${err.length ? `; log: ${err[0].slice(22, 260)}…` : ''}`);
        expect(viStatus).toBe(200);
        expect(enStatus).toBe(200);
    });
});
