// Tiện ích dùng chung cho bộ E2E DuKhachAnToan (ĐT-15).
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect } from '@playwright/test';

const here = path.dirname(fileURLToPath(import.meta.url));
export const AUTH_DIR = path.join(here, '.auth');
export const FIXTURES = path.join(here, 'fixtures');
// Minh chứng ghi ra hồ sơ nộp (ngoài Git); đổi bằng env E2E_EVIDENCE_DIR
export const IMG_DIR = process.env.E2E_EVIDENCE_DIR || path.resolve(here, '../../../../05_KiemThu/img');
export const MATRIX_DIR = process.env.E2E_MATRIX_DIR || path.resolve(here, '../../../../05_KiemThu/matrix');
export const PASSWORD = process.env.E2E_PASSWORD || 'DuKhach@2026';
export const ROLES = {
    admin: 'admin@dukhach.test',
    agency: 'agency@dukhach.test',
    partner: 'partner@dukhach.test',
    traveler: 'traveler@dukhach.test',
    traveler1: 'traveler1@dukhach.test',
    traveler3: 'traveler3@dukhach.test',
    traveler5: 'traveler5@dukhach.test',
    traveler6: 'traveler6@dukhach.test',
};
// Tài khoản ghi dữ liệu (tạo hành trình, báo sự cố) theo trình duyệt: các route throttle:20,1 / 30,60 / 10,60 của ứng dụng
// dùng chung một bộ đếm theo người dùng (lỗi BUG-8), nên mỗi trình duyệt dùng tài khoản riêng để không chặn lẫn nhau.
export const WRITER = { chromium: 'traveler', firefox: 'traveler5', webkit: 'traveler6' };
export const writerFile = (testInfo) => authFile(WRITER[testInfo.project.name] || 'traveler');
// Tài khoản đăng nhập bằng giao diện theo trình duyệt (tránh giới hạn 5 lần/phút theo email khi 3 project chạy liền nhau)
export const UI_LOGIN = { chromium: 'traveler2@dukhach.test', firefox: 'traveler4@dukhach.test', webkit: 'traveler5@dukhach.test' };

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
    const json = m[1].replace(/&quot;/g, '"').replace(/&#039;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
    return JSON.parse(json);
}

/** GET một trang và trả {status, page, html, res} (page = đối tượng Inertia). */
export async function getPage(req, url, opts = {}) {
    const res = await req.get(url, opts);
    const html = await res.text();
    let page = null;
    try {
        page = pageFromHtml(html);
    } catch {
        page = null;
    }
    return { status: res.status(), page, html, res };
}

/** Gửi yêu cầu ghi có CSRF. Mặc định Accept JSON để nhận 422 JSON khi lỗi hợp lệ; không theo chuyển hướng. */
export async function send(req, method, url, data = {}, { multipart = null, form = false, accept = 'json', headers: extra = {}, csrf = true } = {}) {
    if (csrf && !(await req.storageState()).cookies.some((c) => c.name === 'XSRF-TOKEN')) await req.get('/vi/gioi-thieu');
    const headers = { ...(csrf ? await xsrfHeader(req) : {}), Accept: accept === 'json' ? 'application/json' : 'text/html', ...extra };
    const opts = { method, headers, maxRedirects: 0 };
    if (multipart) opts.multipart = multipart;
    else if (form) opts.form = data;
    else opts.data = data;
    const res = await req.fetch(url, opts);
    const text = await res.text();
    let body;
    try {
        body = JSON.parse(text);
    } catch {
        body = text;
    }
    return { status: res.status(), location: res.headers()['location'] || '', body, headers: res.headers() };
}

/** Lưu ảnh minh chứng theo mã ca (chỉ lượt Chromium ghi tên chuẩn TC-xxx.png; trình duyệt khác thêm hậu tố). */
export async function shot(page, code, testInfo, opts = {}) {
    const project = testInfo?.project?.name || 'chromium';
    const name = project === 'chromium' ? `${code}.png` : `${code}-${project}.png`;
    if (project !== 'chromium' && !opts.allBrowsers) return;
    fs.mkdirSync(IMG_DIR, { recursive: true });
    const { allBrowsers, ...rest } = opts;
    await page.screenshot({ path: path.join(IMG_DIR, name), fullPage: false, ...rest });
}

/** Ghi kết quả thực tế vào annotation để tổng hợp vào 05_KiemThu/test_cases.json. */
export function actual(testInfo, text) {
    testInfo.annotations.push({ type: 'actual', description: text });
}

/** Trang HTML hiển thị một khối văn bản (dùng để chụp minh chứng cho ca HTTP/API). */
export async function showText(page, title, text) {
    await page.setContent(`<!doctype html><meta charset="utf-8"><body style="font:14px/1.45 monospace;padding:16px;background:#f5fbf8">
<h2 style="font-family:sans-serif;color:#0b7a64">${escapeHtml(title)}</h2><pre style="white-space:pre-wrap">${escapeHtml(text)}</pre></body>`);
}

function escapeHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

/** Đăng nhập qua giao diện; trả URL đích. */
export async function loginUI(page, email, password = PASSWORD) {
    await page.goto('/vi/dang-nhap');
    await page.fill('input[type=email]', email);
    await page.fill('input[type=password]', password);
    await Promise.all([page.waitForURL((u) => !u.pathname.endsWith('/dang-nhap'), { timeout: 15_000 }), page.click('button[type=submit]')]);
    return new URL(page.url()).pathname;
}

/** Ngày giờ địa phương 'YYYY-MM-DDTHH:mm' (Asia/Ho_Chi_Minh) lệch d ngày, h giờ so với bây giờ. */
export function localDT(days = 0, hours = 0) {
    const t = new Date(Date.now() + (days * 24 + hours) * 3600_000 + 7 * 3600_000);
    return t.toISOString().slice(0, 16);
}
export const localDate = (days = 0) => localDT(days).slice(0, 10);

/** Chọn một cảnh báo đang hiệu lực, không mô phỏng, còn hiệu lực ít nhất 3 giờ (để đối sánh chặng tạo mới). */
export async function pickActiveAlert(req) {
    const res = await req.get('/api/v1/alerts?active=1&per_page=50');
    expect(res.status()).toBe(200);
    const { data } = await res.json();
    const soon = Date.now() + 3 * 3600_000;
    const ok = data.filter((a) => a.zones?.length && (!a.ends_at || Date.parse(a.ends_at) > soon) && Date.parse(a.starts_at) < Date.now());
    // ưu tiên cảnh báo theo vùng (không hình học) của dữ liệu mẫu để kết quả đối sánh ổn định
    return ok.find((a) => !a.geometry && /^AL-SIM-/.test(a.code)) || ok.find((a) => !a.geometry) || ok[0];
}

/** Tạo hành trình qua POST (như wizard gửi), trả {status, code, location, body}. */
export async function createTrip(req, { title, legs, start, end, extra = {} }) {
    const payload = {
        title,
        start_date: start,
        end_date: end,
        group_size: 2,
        nationality: 'VN',
        preferred_locale: 'vi',
        contact_name: 'Kiểm thử E2E',
        contact_phone: '0901234567',
        contact_email: 'e2e@dukhach.test',
        status: 'active',
        legs,
        members: [],
        ...extra,
    };
    const r = await send(req, 'POST', '/vi/hanh-trinh/tao', payload, { headers: { 'X-Inertia': 'true' } });
    const m = (r.location || '').match(/\/hanh-trinh\/(TR-[A-Z0-9-]+)$/);
    return { ...r, code: m ? m[1] : null };
}

/** Lấy TripDetail (props.trip) của trang hành trình. */
export async function tripDetail(req, code) {
    const { status, page } = await getPage(req, `/vi/hanh-trinh/${code}`);
    return { status, trip: page?.props?.trip, props: page?.props };
}

/** Token chia sẻ (40 ký tự) từ share_url. */
export const tokenOf = (trip) => (trip?.share_url || '').split('/chia-se/')[1]?.split(/[/?#]/)[0];
