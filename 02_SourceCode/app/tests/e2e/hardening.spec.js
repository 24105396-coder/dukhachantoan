// Vòng 3 (18/10/2026): kiểm các bản sửa bảo mật trong _workspace/05_security_fix_report.md
// H1 (XFF không vượt rate limit), H2 (upload giả mp3/svg, header /storage), M1 (tài khoản khóa), M2 (đổi mật khẩu
// hủy phiên khác), H3 (contributor không thấy txn_ref), L3 (tệp đính kèm đóng góp riêng tư), L4 (thoát LIKE).
// Các ca M6/M5/H3-IPN/M3 nằm trong booking.spec.js.
import fs from 'node:fs';
import path from 'node:path';
import { test, expect, request as pwRequest } from '@playwright/test';
import { FIXTURES, authFile, actual, shot, getPage, send, showText, rnd, xsrfHeader, registerFresh, FRESH_PASSWORD } from './helpers.js';

const fx = (n) => path.join(FIXTURES, n);
const filePart = (name, mimeType, buffer) => ({ name, mimeType, buffer });
const HTML = Buffer.from('<!doctype html><html><body><script>fetch("/vi/quan-tri/nguoi-dung")</script></body></html>');
const SVG = Buffer.from('<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" onload="alert(document.domain)"><script>alert(1)</script></svg>');
// MP3 tối thiểu: thẻ ID3v2.3 rỗng + vài khung MPEG-1 Layer III (FF FB) → finfo nhận audio/mpeg
const MP3 = Buffer.concat([Buffer.from('ID3'), Buffer.from([3, 0, 0, 0, 0, 0, 0]), ...Array.from({ length: 8 }, () => Buffer.concat([Buffer.from([0xff, 0xfb, 0x90, 0x64]), Buffer.alloc(413, 0)]))]);

async function loginCtx(browser, email, password) {
    const ctx = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    await ctx.request.get('/vi/dang-nhap');
    const r = await ctx.request.post('/vi/dang-nhap', { form: { email, password, remember: '0' }, headers: { ...(await xsrfHeader(ctx)), Accept: 'text/html' }, maxRedirects: 0 });
    return { ctx, status: r.status(), location: r.headers()['location'] || '' };
}

test.describe('SEC vòng 3 – khách', () => {
    test('TC-SEC-013 đổi X-Forwarded-For mỗi lần không vượt được giới hạn đăng nhập; nhật ký ghi IP thật', async ({ page, baseURL, browser }, ti) => {
        const email = `qa_xff_${rnd()}@disanso.test`;
        const api = await pwRequest.newContext({ baseURL, storageState: { cookies: [], origins: [] } });
        await api.get('/vi/dang-nhap');
        const codes = [];
        for (let i = 1; i <= 7; i++) {
            const r = await api.post('/vi/dang-nhap', {
                data: { email, password: `SaiMatKhau${i}` },
                headers: { ...(await xsrfHeader(api)), Accept: 'application/json', 'X-Forwarded-For': `10.9.8.${i}`, 'X-Real-IP': `10.9.8.${i}` },
                maxRedirects: 0,
            });
            codes.push(r.status());
        }
        await api.dispose();
        const adm = await browser.newContext({ storageState: authFile('admin') });
        const { page: logs } = await getPage(adm.request, '/vi/quan-tri/nhat-ky?action=auth.login_failed');
        await adm.close();
        const mine = logs.props.logs.data.filter((l) => l.meta?.email === email);
        const ips = [...new Set(mine.map((l) => l.ip))];
        const text = `Email thử (không tồn tại): ${email}\n7 POST /vi/dang-nhap, mỗi lần X-Forwarded-For/X-Real-IP 10.9.8.1…7 → ${codes.join(', ')}\nauth.login_failed ghi cho email này: ${mine.length} bản ghi, IP: ${ips.join(', ')}`;
        actual(ti, text.replace(/\n/g, '; '));
        await showText(page, 'TC-SEC-013', 'Vượt rate limit bằng X-Forwarded-For (H1)', text);
        expect(codes.slice(0, 5).every((c) => c === 422)).toBe(true);
        expect(codes.slice(5)).toEqual([429, 429]);
        expect(ips.some((ip) => ip.startsWith('10.9.8.'))).toBe(false);
    });

    test('TC-SEC-015 /storage phục vụ kèm nosniff + CSP sandbox, chặn đuôi thực thi được', async ({ page, request }, ti) => {
        const { page: site } = await getPage(request, '/vi/di-san/hoang-thanh-thang-long');
        const svg = site.props.site.cover?.url || '/storage/media/site/hoang-thanh-thang-long-1.svg';
        const r = await request.get(svg);
        const h = r.headers();
        const probes = ['/storage/media/audio/qa.html', '/storage/media/audio/qa.HTML', '/storage/media/model3d/qa.php', '/storage/media/site/qa.js', '/storage/media/site/qa.phtml', '/storage/contributions/'];
        const out = [];
        for (const u of probes) out.push(`${u} → ${(await request.get(u, { maxRedirects: 0 })).status()}`);
        const text = `GET ${svg} → ${r.status()}\ncontent-type: ${h['content-type']}\nx-content-type-options: ${h['x-content-type-options'] ?? '(thiếu)'}\ncontent-security-policy: ${h['content-security-policy'] ?? '(thiếu)'}\n\nĐuôi bị chặn trong /storage (tệp không cần tồn tại):\n${out.join('\n')}`;
        actual(ti, text.replace(/\n/g, '; '));
        await showText(page, 'TC-SEC-015', 'Header và luật chặn của /storage (H2)', text);
        expect(r.status()).toBe(200);
        expect(h['x-content-type-options']).toBe('nosniff');
        expect(h['content-security-policy']).toMatch(/default-src 'none'.*sandbox|sandbox.*default-src 'none'/);
        expect(out.slice(0, 5).every((l) => l.endsWith('→ 403'))).toBe(true);
    });
});

test.describe.serial('SEC vòng 3 – upload media (manager)', () => {
    test.use({ storageState: authFile('manager') });

    test('TC-SEC-014 tệp HTML/SVG đội tên .mp3/.glb/.png bị từ chối 422; mp3 thật lưu tên ngẫu nhiên', async ({ page }, ti) => {
        await page.goto('/vi');
        const base = { subject_type: 'site', subject_id: '1', caption_vi: 'QA H2', source_name: 'QA', license: 'simulated' };
        const tries = [
            ['audio', 'bai-hat.mp3', 'audio/mpeg', HTML, 'HTML đội tên .mp3'],
            ['audio', 'bai-hat.mp3', 'audio/mpeg', SVG, 'SVG đội tên .mp3'],
            ['model3d', 'mo-hinh.glb', 'model/gltf-binary', HTML, 'HTML đội tên .glb'],
            ['image', 'anh.png', 'image/png', SVG, 'SVG đổi tên .png'],
            ['image', 'anh.svg', 'image/svg+xml', SVG, 'SVG đúng đuôi'],
        ];
        const lines = [];
        const statuses = [];
        for (const [type, name, mime, buf, label] of tries) {
            const r = await send(page.request, 'POST', '/vi/quan-tri/media', null, { multipart: { ...base, media_type: type, file: filePart(name, mime, buf) } });
            statuses.push(r.status);
            lines.push(`${label} (media_type=${type}) → ${r.status} ${JSON.stringify(r.body?.errors?.file ?? r.body?.message ?? '')}`);
        }
        // Đối chứng: mp3 thật được nhận, lưu tên ngẫu nhiên 40 ký tự + .mp3
        const src = `QA V5 mp3 ${rnd()}`;
        const ok = await send(page.request, 'POST', '/vi/quan-tri/media', null, { accept: 'html', multipart: { ...base, media_type: 'audio', source_name: src, file: filePart('thuyet-minh.mp3', 'audio/mpeg', MP3) } });
        const { page: list } = await getPage(page.request, `/vi/quan-tri/media?q=${encodeURIComponent(src)}`);
        const m = list.props.media.data[0];
        const file = m?.url ? await page.request.get(m.url) : null;
        const del = m ? await send(page.request, 'DELETE', `/vi/quan-tri/media/${m.id}`, {}, { accept: 'html' }) : { status: '-' };
        lines.push(`mp3 thật (ID3 + khung MPEG) → ${ok.status}; lưu tại ${m?.url}; GET → ${file?.status()} ${file?.headers()['content-type']}, nosniff=${file?.headers()['x-content-type-options']}; xóa → ${del.status}`);
        const text = lines.join('\n');
        actual(ti, text.replace(/\n/g, '; '));
        await showText(page, 'TC-SEC-014', 'Upload tệp giả mạo loại (H2)', text);
        expect(statuses.every((s) => s === 422)).toBe(true);
        expect(m?.url).toMatch(/\/storage\/media\/audio\/[a-z0-9]{40}\.mp3$/);
    });

    test('TC-SEC-021 ký tự đại diện LIKE (% và \\) trong ô tìm kiếm quản trị được thoát', async ({ page }, ti) => {
        const all = (await getPage(page.request, '/vi/quan-tri/tuyen-tham-quan')).page.props.routes;
        const pct = (await getPage(page.request, '/vi/quan-tri/tuyen-tham-quan?q=%25')).page.props.routes;
        const bs = (await getPage(page.request, '/vi/quan-tri/tuyen-tham-quan?q=%5C')).page.props.routes;
        const us = (await getPage(page.request, '/vi/quan-tri/tuyen-tham-quan?q=_')).page.props.routes;
        const n = (x) => (x.data ?? x).length;
        await page.goto('/vi/quan-tri/tuyen-tham-quan?q=%25');
        await shot(page, 'TC-SEC-021');
        actual(ti, `Không lọc: ${n(all)} tuyến; q="%" → ${n(pct)}; q="\\" → ${n(bs)}; q="_" → ${n(us)}.`);
        expect(n(all)).toBeGreaterThan(0);
        expect(n(pct)).toBe(0);
        expect(n(bs)).toBe(0);
        expect(n(us)).toBe(0);
    });
});

test.describe.serial('SEC vòng 3 – phiên và tài khoản', () => {
    test.describe.configure({ timeout: 150_000 });
    const st = {};

    test('TC-SEC-018 đổi mật khẩu hủy phiên đang mở ở thiết bị khác, giữ phiên hiện tại', async ({ browser, page }, ti) => {
        st.acc = await registerFresh(browser, 'pwd');
        const a = await browser.newContext({ storageState: st.acc.file });
        const b = await loginCtx(browser, st.acc.email, FRESH_PASSWORD);
        const bBefore = (await b.ctx.request.get('/vi/tai-khoan', { maxRedirects: 0 })).status();
        await a.request.get('/vi/tai-khoan/ho-so');
        const newPw = 'DoiMatKhau2026';
        const r = await send(a.request, 'PATCH', '/vi/tai-khoan/mat-khau', { current_password: FRESH_PASSWORD, password: newPw, password_confirmation: newPw }, { accept: 'html', headers: { Referer: 'http://localhost:8080/vi/tai-khoan/ho-so' } });
        const aAfter = (await a.request.get('/vi/tai-khoan', { maxRedirects: 0 })).status();
        const bAfter = await b.ctx.request.get('/vi/tai-khoan', { maxRedirects: 0 });
        const bp = await b.ctx.newPage();
        await bp.goto('/vi/tai-khoan');
        await shot(bp, 'TC-SEC-018');
        const landed = new URL(bp.url()).pathname;
        await a.storageState({ path: st.acc.file });
        await a.close();
        await b.ctx.close();
        const oldLogin = await loginCtx(browser, st.acc.email, FRESH_PASSWORD);
        await oldLogin.ctx.close();
        st.password = newPw;
        actual(ti, `${st.acc.email}: phiên B trước khi đổi → ${bBefore}; phiên A PATCH /vi/tai-khoan/mat-khau → ${r.status}; sau đó phiên A → ${aAfter}, phiên B → ${bAfter.status()} ${bAfter.headers()['location'] ?? ''} (trình duyệt B dừng ở ${landed}); đăng nhập bằng mật khẩu cũ → ${oldLogin.status} ${oldLogin.location}.`);
        expect(bBefore).toBe(200);
        expect(aAfter).toBe(200);
        expect([302, 303]).toContain(bAfter.status());
        expect(bAfter.headers()['location']).toContain('/dang-nhap');
        expect(oldLogin.location).toContain('/dang-nhap');
    });

    test('TC-SEC-017 tài khoản quản lý bị khóa: phiên đang mở vào /vi/quan-tri bị chuyển về đăng nhập, không đăng nhập lại được', async ({ browser }, ti) => {
        const adm = await browser.newContext({ storageState: authFile('admin') });
        await adm.request.get('/vi');
        const hdr = { headers: { Referer: 'http://localhost:8080/vi/quan-tri/nguoi-dung' } };
        const promote = await send(adm.request, 'PATCH', `/vi/quan-tri/nguoi-dung/${st.acc.id}`, { role_id: 2, is_active: true }, hdr);
        const u = await loginCtx(browser, st.acc.email, st.password);
        const before = (await u.ctx.request.get('/vi/quan-tri', { maxRedirects: 0 })).status();
        const lock = await send(adm.request, 'PATCH', `/vi/quan-tri/nguoi-dung/${st.acc.id}`, { role_id: 2, is_active: false }, hdr);
        const after = await u.ctx.request.get('/vi/quan-tri', { maxRedirects: 0 });
        const up = await u.ctx.newPage();
        await up.goto('/vi/quan-tri');
        const landed = new URL(up.url()).pathname;
        await shot(up, 'TC-SEC-017');
        await u.ctx.close();
        const again = await loginCtx(browser, st.acc.email, st.password);
        const againPage = await again.ctx.request.get('/vi/quan-tri', { maxRedirects: 0 });
        await again.ctx.close();
        // Dọn: hạ về member, giữ trạng thái khóa
        await send(adm.request, 'PATCH', `/vi/quan-tri/nguoi-dung/${st.acc.id}`, { role_id: 4, is_active: false }, hdr);
        await adm.close();
        actual(ti, `${st.acc.email} được nâng manager → ${promote.status}; phiên đang mở GET /vi/quan-tri → ${before}; admin khóa → ${lock.status}; cùng phiên GET /vi/quan-tri → ${after.status()} ${after.headers()['location'] ?? ''} (trình duyệt dừng ở ${landed}); đăng nhập lại → ${again.status} ${again.location}, sau đó /vi/quan-tri → ${againPage.status()} ${againPage.headers()['location'] ?? ''}.`);
        expect(before).toBe(200);
        expect([302, 303]).toContain(after.status());
        expect(after.headers()['location']).toContain('/vi/dang-nhap');
        expect(landed).toBe('/vi/dang-nhap');
        expect([302, 303]).toContain(againPage.status());
        expect(againPage.headers()['location']).toContain('/dang-nhap');
    });
});

test.describe('SEC vòng 3 – contributor', () => {
    test.use({ storageState: authFile('contributor') });
    test('TC-SEC-020 contributor xem đặt lịch tuyến mình nhưng không thấy mã giao dịch thanh toán', async ({ page, browser }, ti) => {
        const { page: bk, html } = await getPage(page.request, '/vi/quan-tri/dat-lich');
        const rows = bk.props.bookings.data ?? [];
        const withPay = rows.filter((b) => (b.payments ?? []).length > 0).length;
        const txnInHtml = (html.match(/SB-\d{12}-[A-Z0-9]{6}/g) || []).length;
        const adm = await browser.newContext({ storageState: authFile('admin') });
        const { page: ab } = await getPage(adm.request, '/vi/quan-tri/dat-lich');
        await adm.close();
        const adminPays = (ab.props.bookings.data ?? []).reduce((n, b) => n + (b.payments ?? []).length, 0);
        await page.goto('/vi/quan-tri/dat-lich');
        await shot(page, 'TC-SEC-020');
        actual(ti, `Contributor thấy ${rows.length} đặt lịch (tuyến của mình), ${withPay} dòng có payments; mã SB-… trong HTML: ${txnInHtml}; đối chứng admin thấy ${adminPays} giao dịch trên trang đầu.`);
        expect(rows.length).toBeGreaterThan(0);
        expect(withPay).toBe(0);
        expect(txnInHtml).toBe(0);
        expect(adminPays).toBeGreaterThan(0);
    });
});

test.describe.serial('CONTRIB vòng 3 – tệp đính kèm riêng tư', () => {
    test.use({ storageState: authFile('member') });
    const st = {};

    test('TC-CONTRIB-005 tệp đính kèm chỉ chủ đóng góp và staff tải được qua route có kiểm quyền', async ({ page, browser, baseURL }, ti) => {
        await page.goto('/vi');
        const c = await send(page.request, 'POST', '/vi/dong-gop', null, {
            accept: 'html',
            multipart: { contribution_type: 'media', locale: 'vi', title: `Ảnh tư liệu QA ${rnd()}`, body: 'Ảnh chụp bia đá tại Hoàng thành Thăng Long, gửi kèm để kiểm duyệt viên đối chiếu.', attachment: filePart('anh-kiem-thu.png', 'image/png', fs.readFileSync(fx('anh-kiem-thu.png'))) },
        });
        st.id = c.location.split('/').pop();
        const { page: show } = await getPage(page.request, `/vi/dong-gop/${st.id}`);
        const url = show.props.contribution.attachment_url;
        const parsed = new URL(url, baseURL);
        // URL có id mã hóa + chữ ký (Mục 6.6): giữ nguyên query expires/signature khi gọi lại
        const path_ = parsed.pathname + parsed.search;
        const owner = await page.request.get(path_);
        const oh = owner.headers();
        const other = await browser.newContext({ storageState: authFile('member2') });
        const o = (await other.request.get(path_, { maxRedirects: 0 })).status();
        await other.close();
        // request.newContext trong test kế thừa storageState của test.use → truyền trạng thái rỗng để là khách thật
        const guest = await pwRequest.newContext({ baseURL, storageState: { cookies: [], origins: [] } });
        const g = await guest.get(path_, { maxRedirects: 0 });
        await guest.dispose();
        const mgr = await browser.newContext({ storageState: authFile('manager') });
        const mres = (await mgr.request.get(path_)).status();
        await mgr.request.get('/vi');
        await send(mgr.request, 'PATCH', `/vi/quan-tri/kiem-duyet/${st.id}`, { status: 'rejected', review_note: 'Ca kiểm thử L3 – dọn dữ liệu' });
        await mgr.close();
        await page.goto(`/vi/dong-gop/${st.id}`);
        await shot(page, 'TC-CONTRIB-005');
        const text = `Đóng góp #${st.id} → ${c.status}; attachment_url = ${url}\nChủ (user@) GET → ${owner.status()} ${oh['content-type']}; nosniff=${oh['x-content-type-options']}; CSP=${oh['content-security-policy']}; cache-control=${oh['cache-control']}\nThành viên khác (member1@) → ${o}\nKhách → ${g.status()} ${g.headers()['location'] ?? ''}\nManager → ${mres}`;
        actual(ti, text.replace(/\n/g, '; '));
        expect(parsed.pathname).toMatch(/^\/vi\/dong-gop\/tep-dinh-kem\/[A-Za-z0-9_-]+$/);
        expect(parsed.pathname).not.toContain(`/${st.id}/`);
        expect(parsed.searchParams.get('signature')).toBeTruthy();
        expect(owner.status()).toBe(200);
        expect(oh['content-type']).toContain('image/png');
        expect(oh['x-content-type-options']).toBe('nosniff');
        expect(o).toBe(403);
        expect([302, 303]).toContain(g.status());
        expect(g.headers()['location']).toContain('/dang-nhap');
        expect(mres).toBe(200);
    });

    test('TC-CONTRIB-006 tệp HTML đội tên .pdf đính kèm đóng góp bị từ chối 422', async ({ page }, ti) => {
        await page.goto('/vi');
        const r = await send(page.request, 'POST', '/vi/dong-gop', null, {
            multipart: { contribution_type: 'media', locale: 'vi', title: 'Tài liệu giả PDF', body: 'Nội dung đủ dài để vượt qua kiểm tra hai mươi ký tự.', attachment: filePart('tai-lieu.pdf', 'application/pdf', HTML) },
        });
        const s = await send(page.request, 'POST', '/vi/dong-gop', null, {
            multipart: { contribution_type: 'media', locale: 'vi', title: 'Ảnh giả SVG', body: 'Nội dung đủ dài để vượt qua kiểm tra hai mươi ký tự.', attachment: filePart('anh.png', 'image/png', SVG) },
        });
        const text = `HTML đội tên tai-lieu.pdf → HTTP ${r.status} ${JSON.stringify(r.body?.errors?.attachment)}\nSVG đổi tên anh.png → HTTP ${s.status} ${JSON.stringify(s.body?.errors?.attachment)}`;
        actual(ti, text.replace(/\n/g, '; '));
        await showText(page, 'TC-CONTRIB-006', 'Đính kèm giả mạo loại tệp', text);
        expect(r.status).toBe(422);
        expect(r.body.errors.attachment).toBeTruthy();
        expect(s.status).toBe(422);
    });
});
