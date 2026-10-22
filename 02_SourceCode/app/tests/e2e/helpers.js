// Tiện ích dùng chung cho các bộ kiểm thử E2E DiSanSo.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
export const AUTH_DIR = path.join(here, '.auth');
export const IMG_DIR = path.resolve(here, '../../../../05_KiemThu/img');
export const MATRIX_DIR = path.resolve(here, '../../../../05_KiemThu/matrix');
export const FIXTURES = path.join(here, 'fixtures');
export const PASSWORD = process.env.E2E_PASSWORD || 'DiSan@2026';
export const ROLES = {
    admin: 'admin@disanso.test',
    manager: 'manager@disanso.test',
    contributor: 'partner@disanso.test',
    member: 'user@disanso.test',
    member2: 'member1@disanso.test',
};

/** Đường dẫn storageState của một vai trò. */
export const authFile = (role) => path.join(AUTH_DIR, `${role}.json`);

/** Header X-XSRF-TOKEN lấy từ cookie XSRF-TOKEN của context (Laravel chấp nhận giá trị đã mã hóa). */
export async function xsrfHeader(ctx) {
    const state = await ctx.storageState();
    const c = state.cookies.find((x) => x.name === 'XSRF-TOKEN');
    return c ? { 'X-XSRF-TOKEN': decodeURIComponent(c.value) } : {};
}

/** Giải mã thuộc tính data-page của Inertia từ HTML. */
export function pageFromHtml(html) {
    const m = html.match(/data-page="([^"]*)"/);
    if (!m) return null;
    const json = m[1]
        .replace(/&quot;/g, '"')
        .replace(/&#039;/g, "'")
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&amp;/g, '&');
    return JSON.parse(json);
}

/** GET một trang và trả {status, page} (page = đối tượng Inertia). */
export async function getPage(req, url) {
    const res = await req.get(url);
    const html = await res.text();
    return { status: res.status(), page: pageFromHtml(html), html, res };
}

/** Gửi yêu cầu ghi có CSRF. accept=json để nhận 422 JSON khi lỗi hợp lệ. */
export async function send(req, method, url, data = {}, { json = true, multipart = null, accept = 'json', headers: extra = {} } = {}) {
    const headers = { ...(await xsrfHeader(req)), Accept: accept === 'json' ? 'application/json' : 'text/html', ...extra };
    const opts = { method, headers, maxRedirects: 0 };
    if (multipart) opts.multipart = multipart;
    else if (json) opts.data = data;
    else opts.form = data;
    const res = await req.fetch(url, opts);
    let body = null;
    const text = await res.text();
    try {
        body = JSON.parse(text);
    } catch {
        body = text;
    }
    return { status: res.status(), location: res.headers()['location'] || '', body, headers: res.headers() };
}

/** Lưu ảnh minh chứng theo mã ca. */
export async function shot(page, code, opts = {}) {
    fs.mkdirSync(IMG_DIR, { recursive: true });
    await page.screenshot({ path: path.join(IMG_DIR, `${code}.png`), fullPage: false, ...opts });
}

/** Lưu minh chứng dạng văn bản (kết quả HTTP). */
export function evidence(code, text) {
    fs.mkdirSync(IMG_DIR, { recursive: true });
    fs.writeFileSync(path.join(IMG_DIR, `${code}.txt`), text, 'utf8');
}

/** Ghi kết quả thực tế vào annotation để tổng hợp vào test_cases.json. */
export function actual(testInfo, text) {
    testInfo.annotations.push({ type: 'actual', description: text });
}

/** Hiển thị một khối văn bản (JSON/HTTP) trên trang trắng để chụp làm minh chứng. */
export async function showText(page, code, title, text) {
    const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;');
    await page.setContent(`<html><body style="font:13px monospace;padding:16px;background:#fff;color:#111"><h3>${esc(code)} – ${esc(title)}</h3><pre style="white-space:pre-wrap">${esc(text)}</pre></body></html>`);
    await shot(page, code, { fullPage: true });
    evidence(code, `${title}\n\n${text}`);
}

/** Lấy ngẫu nhiên chuỗi ngắn để tạo dữ liệu kiểm thử không trùng. */
export const rnd = () => Math.random().toString(36).slice(2, 8);

/** Mật khẩu của tài khoản kiểm thử đăng ký mới (đủ chữ hoa, chữ thường, chữ số). */
export const FRESH_PASSWORD = 'KiemThu2026';

/**
 * Vòng 3 (M6: tối đa 3 booking chưa thanh toán và 10 chỗ/ngày mỗi tài khoản): đăng ký một tài khoản member mới
 * qua form đăng ký (POST /vi/dang-ky giống form giao diện, có CSRF). Gặp 429 (đăng ký 6 lần/phút/IP) thì chờ
 * Retry-After rồi thử lại. Lưu storageState vào .auth/<label>.json và trả { email, password, id, file }.
 */
export async function registerFresh(browser, label, password = FRESH_PASSWORD) {
    const email = `qa_${label}_${rnd()}@disanso.test`.toLowerCase();
    // storageState rỗng: nếu không, context mới kế thừa storageState của test.use (đang đăng nhập) và POST bị guest-middleware chuyển hướng
    const ctx = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    try {
        for (let attempt = 0; attempt < 4; attempt++) {
            await ctx.request.get('/vi/dang-ky');
            const r = await ctx.request.post('/vi/dang-ky', {
                form: { name: `QA V5 ${label}`, email, password, password_confirmation: password },
                headers: { ...(await xsrfHeader(ctx)), Accept: 'text/html' },
                maxRedirects: 0,
            });
            if (r.status() === 429) {
                const wait = Number(r.headers()['retry-after'] || 30) + 1;
                await new Promise((res) => setTimeout(res, wait * 1000));
                continue;
            }
            const loc = r.headers()['location'] || '';
            if (![302, 303].includes(r.status()) || loc.includes('/dang-ky')) {
                throw new Error(`Đăng ký ${email} thất bại: ${r.status()} ${loc}`);
            }
            const file = authFile(label);
            await ctx.storageState({ path: file });
            const user = (await getPage(ctx.request, '/vi/tai-khoan')).page.props.auth.user;
            if (user?.email !== email) throw new Error(`Đăng ký ${email}: phiên đang là ${user?.email}`);
            return { email, password, id: user.id, file };
        }
        throw new Error(`Đăng ký ${email}: vẫn 429 sau 4 lần thử`);
    } finally {
        await ctx.close();
    }
}
