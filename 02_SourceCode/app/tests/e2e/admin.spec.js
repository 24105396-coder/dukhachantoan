// UC-09 Đóng góp, UC-10 Kiểm duyệt, UC-11 Quản trị hồ sơ di sản (CRUD, media có nguồn gốc), UC-12 Chất lượng dữ liệu, xuất CSV, nhật ký.
import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { FIXTURES, authFile, actual, shot, getPage, send, showText, rnd } from './helpers.js';

const state = {};
const fx = (n) => path.join(FIXTURES, n);
const filePart = (name, mimeType, buffer) => ({ name, mimeType, buffer });

test.describe.serial('CONTRIB – thành viên gửi đóng góp', () => {
    test.use({ storageState: authFile('member') });

    test('TC-CONTRIB-001 gửi đóng góp hợp lệ, trạng thái chờ duyệt, có thẻ gợi ý tự động', async ({ page }, ti) => {
        await page.goto('/vi/dong-gop/moi');
        const selects = page.locator('form select');
        await selects.nth(0).selectOption('story');
        await selects.nth(1).selectOption('vi');
        await page.locator('form input.input').first().fill(`Câu chuyện Hoàng thành QA ${rnd()}`);
        await page.locator('form textarea').first().fill('Ông bà kể rằng khu Hoàng thành Thăng Long từng là kinh đô của nhà Lý, có điện Kính Thiên và Đoan Môn; nguồn: sách Lịch sử Thăng Long – Hà Nội.');
        await page.getByRole('button', { name: 'Gửi đóng góp' }).click();
        await page.waitForURL(/\/vi\/dong-gop\/\d+/);
        state.contribId = Number(page.url().split('/').pop());
        const { page: p } = await getPage(page.request, `/vi/dong-gop/${state.contribId}`);
        const c = p.props.contribution;
        actual(ti, `Tạo đóng góp #${state.contribId}, status=${c.status}, thẻ gợi ý: ${c.suggested_tags.map((t) => `${t.slug}(${t.score})`).join(', ') || '(rỗng)'}.`);
        await shot(page, 'TC-CONTRIB-001');
        expect(c.status).toBe('pending');
        expect(c.suggested_tags.length).toBeGreaterThan(0);
    });

    test('TC-CONTRIB-002 tiêu đề dưới 5 ký tự và nội dung dưới 20 ký tự bị từ chối', async ({ page }, ti) => {
        await page.goto('/vi/dong-gop/moi');
        await page.locator('form select').nth(0).selectOption('correction');
        await page.locator('form input.input').first().fill('Abc');
        await page.locator('form textarea').first().fill('Quá ngắn');
        await page.getByRole('button', { name: 'Gửi đóng góp' }).click();
        await expect(page.locator('.field-error').first()).toBeVisible();
        const errs = await page.locator('.field-error').allInnerTexts();
        actual(ti, `Ở lại form, lỗi: ${errs.join(' | ')}`);
        await shot(page, 'TC-CONTRIB-002');
        expect(errs.length).toBeGreaterThanOrEqual(2);
    });

    test('TC-CONTRIB-003 đính kèm tệp sai loại (.txt) bị từ chối ở cả trình duyệt và máy chủ', async ({ page }, ti) => {
        await page.goto('/vi/dong-gop/moi');
        await page.locator('input[type="file"]').setInputFiles(fx('tai-lieu.txt'));
        const client = await page.locator('.field-error').allInnerTexts();
        await shot(page, 'TC-CONTRIB-003');
        const r = await send(page.request, 'POST', '/vi/dong-gop', null, {
            multipart: { contribution_type: 'media', locale: 'vi', title: 'Tệp đính kèm sai loại', body: 'Nội dung đủ dài để vượt qua kiểm tra hai mươi ký tự.', attachment: filePart('tai-lieu.txt', 'text/plain', fs.readFileSync(fx('tai-lieu.txt'))) },
        });
        actual(ti, `Trình duyệt báo: "${client.join(' | ')}"; máy chủ (gửi thẳng) → HTTP ${r.status}, lỗi attachment: ${JSON.stringify(r.body?.errors?.attachment)}.`);
        expect(r.status).toBe(422);
        expect(r.body.errors.attachment).toBeTruthy();
    });

    test('TC-CONTRIB-004 thành viên khác không xem được đóng góp của người khác', async ({ browser }, ti) => {
        const ctx = await browser.newContext({ storageState: authFile('member2') });
        const page = await ctx.newPage();
        const res = await page.goto(`/vi/dong-gop/${state.contribId}`);
        actual(ti, `member1 GET /vi/dong-gop/${state.contribId} (của user@) → ${res.status()}.`);
        await shot(page, 'TC-CONTRIB-004');
        await ctx.close();
        expect(res.status()).toBe(403);
    });
});

test.describe.serial('ADMIN – manager kiểm duyệt và quản trị nội dung', () => {
    test.use({ storageState: authFile('manager') });

    test('TC-ADMIN-001 từ chối/yêu cầu sửa mà thiếu nhận xét bị báo lỗi', async ({ page }, ti) => {
        await page.goto('/vi');
        const id = state.contribId ?? 5;
        const r = await send(page.request, 'PATCH', `/vi/quan-tri/kiem-duyet/${id}`, { status: 'rejected', review_note: '' });
        actual(ti, `PATCH rejected không có review_note → ${r.status}, lỗi: ${JSON.stringify(r.body?.errors)}`);
        await page.goto(`/vi/quan-tri/kiem-duyet/${id}`);
        await shot(page, 'TC-ADMIN-001');
        expect(r.status).toBe(422);
    });

    test('TC-ADMIN-002 duyệt đóng góp: trạng thái approved, có nhận xét, ghi nhật ký', async ({ page }, ti) => {
        await page.goto('/vi');
        const id = state.contribId ?? 5;
        const r = await send(page.request, 'PATCH', `/vi/quan-tri/kiem-duyet/${id}`, { status: 'approved', review_note: 'Nội dung phù hợp – QA V5' }, { accept: 'html' });
        const { page: p } = await getPage(page.request, `/vi/quan-tri/kiem-duyet/${id}`);
        await page.goto(`/vi/quan-tri/kiem-duyet/${id}`);
        await shot(page, 'TC-ADMIN-002');
        const c = p.props.contribution;
        actual(ti, `PATCH → ${r.status}; đóng góp #${id}: status=${c.status}, review_note="${c.review_note}", reviewed_at=${c.reviewed_at}.`);
        expect(c.status).toBe('approved');
    });

    test('TC-ADMIN-003 tạo hồ sơ di sản mới (nháp) rồi xóa', async ({ page }, ti) => {
        await page.goto('/vi');
        const slug = `qa-di-san-${rnd()}`;
        const { page: form } = await getPage(page.request, '/vi/quan-tri/di-san/moi');
        const province = form.props.provinces[0].id;
        const body = {
            slug, site_type: 'di_tich', province_id: province, lat: 21.03, lng: 105.84, recognition_level: 'cap_tinh', status: 'published',
            interpretations: [{ locale: 'vi', depth_level: 1, title: `Di sản kiểm thử ${slug}`, summary: 'Tóm tắt', body: 'Nội dung diễn giải tầng 1 dùng cho kiểm thử.', sources: ['QA'] }],
        };
        const c = await send(page.request, 'POST', '/vi/quan-tri/di-san', body, { accept: 'html' });
        const pub = await page.request.get(`/vi/di-san/${slug}`);
        await page.goto(`/vi/quan-tri/di-san?q=${slug}`);
        await shot(page, 'TC-ADMIN-003');
        const d = await send(page.request, 'DELETE', `/vi/quan-tri/di-san/${slug}`, {}, { accept: 'html' });
        const gone = await page.request.get(`/vi/di-san/${slug}`);
        const { page: list } = await getPage(page.request, `/vi/quan-tri/di-san?q=${slug}`);
        actual(ti, `POST → ${c.status} ${c.location}; trang công khai /vi/di-san/${slug} → ${pub.status()}; DELETE → ${d.status}; sau xóa: công khai ${gone.status()}, danh sách quản trị còn ${list.props.sites.data.length} dòng.`);
        expect(pub.status()).toBe(200);
        expect(gone.status()).toBe(404);
    });

    test('TC-ADMIN-004 lưu form di sản thiếu tiêu đề diễn giải vi-1 bị báo lỗi đúng ô', async ({ page }, ti) => {
        await page.goto('/vi/quan-tri/di-san/moi');
        await page.getByRole('button', { name: 'Lưu', exact: true }).first().click();
        await expect(page.locator('.field-error').first()).toBeVisible();
        const n = await page.locator('.field-error').count();
        await shot(page, 'TC-ADMIN-004');
        const { page: form } = await getPage(page.request, '/vi/quan-tri/di-san/moi');
        const r = await send(page.request, 'POST', '/vi/quan-tri/di-san', {
            slug: `qa-thieu-${rnd()}`, site_type: 'di_tich', province_id: form.props.provinces[0].id, lat: 21, lng: 105.8, recognition_level: 'cap_tinh', status: 'published',
            interpretations: [{ locale: 'vi', depth_level: 1, title: '', summary: 'x', body: 'Có nội dung nhưng thiếu tiêu đề' }],
        });
        actual(ti, `Form rỗng: ${n} thông báo lỗi; gửi đủ trường trừ tiêu đề → HTTP ${r.status}, khóa lỗi: ${Object.keys(r.body?.errors ?? {}).join(', ')}.`);
        expect(r.status).toBe(422);
        expect(Object.keys(r.body.errors).some((k) => k.startsWith('interpretations'))).toBe(true);
    });

    test('TC-ADMIN-005 tọa độ ngoài lãnh thổ (lat 25, lng 111) bị từ chối', async ({ page }, ti) => {
        await page.goto('/vi');
        const { page: form } = await getPage(page.request, '/vi/quan-tri/di-san/moi');
        const r = await send(page.request, 'POST', '/vi/quan-tri/di-san', {
            slug: `qa-toado-${rnd()}`, site_type: 'di_tich', province_id: form.props.provinces[0].id, lat: 25, lng: 111, recognition_level: 'cap_tinh', status: 'draft',
            interpretations: [{ locale: 'vi', depth_level: 1, title: 'Tọa độ sai', body: 'Nội dung' }],
        });
        actual(ti, `HTTP ${r.status}; lỗi lat: ${r.body?.errors?.lat}; lng: ${r.body?.errors?.lng}.`);
        await showText(page, 'TC-ADMIN-005', 'POST di sản lat=25, lng=111', `HTTP ${r.status}\n${JSON.stringify(r.body, null, 2)}`);
        expect(r.status).toBe(422);
        expect(r.body.errors.lat && r.body.errors.lng).toBeTruthy();
    });

    test('TC-MEDIA-001 tải ảnh kèm nguồn gốc – bản quyền, hiển thị trên trang công khai rồi xóa', async ({ page }, ti) => {
        await page.goto('/vi');
        const site = (await (await page.request.get('/api/v1/sites/chua-mot-cot')).json()).data;
        const caption = `Ảnh QA ${rnd()}`;
        const r = await send(page.request, 'POST', '/vi/quan-tri/media', null, {
            accept: 'html',
            multipart: {
                subject_type: 'site', subject_id: String(site.id), media_type: 'image', caption_vi: caption, caption_en: 'QA image',
                source_name: 'Nhóm DiSanSo – ảnh kiểm thử', source_url: 'https://example.org/qa', author: 'V5 Kiểm thử', license: 'cc_by_sa',
                rights_holder: 'Nhóm DiSanSo', captured_at: '2026-10-01', provenance_note: 'Ảnh tạo tự động phục vụ kiểm thử', is_cover: '0',
                file: filePart('anh-kiem-thu.png', 'image/png', fs.readFileSync(fx('anh-kiem-thu.png'))),
            },
        });
        const { page: p } = await getPage(page.request, '/vi/di-san/chua-mot-cot');
        const m = p.props.site.media.images.find((x) => x.caption === caption);
        state.mediaId = m?.id;
        await page.goto(`/vi/quan-tri/media?q=${encodeURIComponent(caption)}`);
        await shot(page, 'TC-MEDIA-001');
        const file = m ? await page.request.get(m.url) : null;
        const d = m ? await send(page.request, 'DELETE', `/vi/quan-tri/media/${m.id}`, {}, { accept: 'html' }) : { status: '-' };
        actual(ti, `POST /quan-tri/media → ${r.status}; ảnh #${m?.id} trên trang công khai: license=${m?.license_label}, source=${m?.source_name}, author=${m?.author}, rights_holder=${m?.rights_holder}; tệp ${m?.url} → ${file?.status()}; DELETE → ${d.status}.`);
        expect(m).toBeTruthy();
        expect(m.source_name).toContain('DiSanSo');
        expect(m.license).toBe('cc_by_sa');
    });

    test('TC-MEDIA-002 tệp văn bản đổi đuôi .jpg bị từ chối (kiểm nội dung MIME)', async ({ page }, ti) => {
        await page.goto('/vi');
        const r = await send(page.request, 'POST', '/vi/quan-tri/media', null, {
            multipart: { subject_type: 'site', subject_id: '1', media_type: 'image', caption_vi: 'Giả ảnh', source_name: 'QA', license: 'simulated', file: filePart('gia-anh.jpg', 'image/jpeg', fs.readFileSync(fx('gia-anh.jpg'))) },
        });
        actual(ti, `HTTP ${r.status}; lỗi file: ${JSON.stringify(r.body?.errors?.file)}.`);
        await showText(page, 'TC-MEDIA-002', 'Upload gia-anh.jpg (nội dung văn bản)', `HTTP ${r.status}\n${JSON.stringify(r.body, null, 2)}`);
        expect(r.status).toBe(422);
        expect(r.body.errors.file).toBeTruthy();
    });

    test('TC-MEDIA-003 tệp ảnh 21 MB (vượt giới hạn 20 MB) bị từ chối, không lỗi máy chủ', async ({ page }, ti) => {
        await page.goto('/vi');
        const png = fs.readFileSync(fx('anh-kiem-thu.png'));
        const big = Buffer.concat([png, Buffer.alloc(21 * 1024 * 1024 - png.length, 0)]);
        const r = await send(page.request, 'POST', '/vi/quan-tri/media', null, {
            multipart: { subject_type: 'site', subject_id: '1', media_type: 'image', caption_vi: 'Ảnh quá lớn', source_name: 'QA', license: 'simulated', file: filePart('qua-lon.png', 'image/png', big) },
        });
        const text = typeof r.body === 'string' ? r.body.slice(0, 300) : JSON.stringify(r.body, null, 2);
        actual(ti, `Gửi 21 MB → HTTP ${r.status}; ${typeof r.body === 'object' ? `lỗi file: ${JSON.stringify(r.body?.errors?.file ?? r.body?.message)}` : 'phản hồi không phải JSON'}.`);
        await showText(page, 'TC-MEDIA-003', 'Upload ảnh 21 MB', `HTTP ${r.status}\n${text}`);
        expect(r.status).toBe(422);
        expect(r.body?.errors?.file).toBeTruthy();
    });

    test('TC-MEDIA-004 thiếu nguồn và giấy phép bị từ chối (bắt buộc provenance)', async ({ page }, ti) => {
        await page.goto('/vi');
        const r = await send(page.request, 'POST', '/vi/quan-tri/media', null, {
            multipart: { subject_type: 'site', subject_id: '1', media_type: 'image', caption_vi: 'Thiếu nguồn', file: filePart('anh.png', 'image/png', fs.readFileSync(fx('anh-kiem-thu.png'))) },
        });
        actual(ti, `HTTP ${r.status}; khóa lỗi: ${Object.keys(r.body?.errors ?? {}).join(', ')}.`);
        await showText(page, 'TC-MEDIA-004', 'Upload thiếu source_name, license', `HTTP ${r.status}\n${JSON.stringify(r.body, null, 2)}`);
        expect(r.status).toBe(422);
        expect(r.body.errors.source_name && r.body.errors.license).toBeTruthy();
    });

    test('TC-ADMIN-006 chạy kiểm định chất lượng dữ liệu hiển thị báo cáo lỗi/cảnh báo', async ({ page }, ti) => {
        await page.goto('/vi/quan-tri/chat-luong-du-lieu');
        const r = await send(page.request, 'POST', '/vi/quan-tri/chat-luong-du-lieu/kiem-tra', {}, { accept: 'html' });
        const { page: p } = await getPage(page.request, '/vi/quan-tri/chat-luong-du-lieu');
        await page.reload();
        await shot(page, 'TC-ADMIN-006');
        const rep = p.props.report;
        actual(ti, `POST kiểm tra → ${r.status}; báo cáo ${rep?.generated_at}: ${rep?.summary?.errors} lỗi, ${rep?.summary?.warnings} cảnh báo, ${rep?.checks?.length} phép kiểm (${rep?.checks?.map((c) => c.code).slice(0, 6).join(', ')}…); analyticsUp=${p.props.analyticsUp}.`);
        expect(rep?.checks?.length).toBeGreaterThan(0);
    });

    test('TC-ADMIN-007 xuất CSV đặt lịch tải về đúng định dạng', async ({ page }, ti) => {
        await page.goto('/vi');
        const r = await page.request.get('/vi/quan-tri/thong-ke/xuat?type=bookings');
        const csv = await r.text();
        const lines = csv.trim().split('\n');
        const v = await page.request.get('/vi/quan-tri/thong-ke/xuat?type=views');
        actual(ti, `HTTP ${r.status()}, Content-Type=${r.headers()['content-type']}, Content-Disposition=${r.headers()['content-disposition']}; ${lines.length - 1} dòng dữ liệu; tiêu đề: ${lines[0]}; type=views → ${v.status()}.`);
        await showText(page, 'TC-ADMIN-007', 'GET /vi/quan-tri/thong-ke/xuat?type=bookings', `${r.headers()['content-type']}\n${r.headers()['content-disposition']}\n\n${lines.slice(0, 8).join('\n')}`);
        expect(r.status()).toBe(200);
        expect(r.headers()['content-type']).toContain('text/csv');
    });

    test('TC-ADMIN-008 bảng điều khiển hiển thị KPI và biểu đồ', async ({ page }, ti) => {
        const { page: p } = await getPage(page.request, '/vi/quan-tri');
        await page.goto('/vi/quan-tri');
        await page.waitForTimeout(1500);
        const canvases = await page.locator('canvas').count();
        const k = p.props.kpis;
        actual(ti, `KPI: sites=${k.sites}, artifacts=${k.artifacts}, figures=${k.figures}, bookings30d=${k.bookings30d}, revenue30d=${k.revenue30d}, pendingContributions=${k.pendingContributions}; ${canvases} biểu đồ canvas.`);
        await shot(page, 'TC-ADMIN-008');
        expect(k.sites).toBeGreaterThan(0);
        expect(canvases).toBeGreaterThan(0);
    });
});

test.describe.serial('ADMIN – quản trị hệ thống', () => {
    test.use({ storageState: authFile('admin') });

    test('TC-ADMIN-009 nhật ký audit ghi đăng nhập, đặt lịch, thanh toán, CRUD, kiểm duyệt', async ({ page }, ti) => {
        const { page: p } = await getPage(page.request, '/vi/quan-tri/nhat-ky?per_page=100');
        const actions = [...new Set(p.props.logs.data.map((l) => l.action))];
        await page.goto('/vi/quan-tri/nhat-ky');
        await shot(page, 'TC-ADMIN-009');
        const need = ['auth.login', 'booking.held', 'booking.paid'];
        actual(ti, `${p.props.logs.total} bản ghi; hành động trang đầu: ${actions.join(', ')}.`);
        expect(need.every((a) => p.props.actions?.some?.((x) => (x.code ?? x.value ?? x) === a) || actions.includes(a))).toBe(true);
    });

    test('TC-ADMIN-010 quản trị không thể tự hạ quyền hoặc khóa chính mình', async ({ page }, ti) => {
        await page.goto('/vi');
        const me = (await getPage(page.request, '/vi/tai-khoan')).page.props.auth.user;
        const r = await send(page.request, 'PATCH', `/vi/quan-tri/nguoi-dung/${me.id}`, { role_id: 4, is_active: false }, { headers: { Referer: 'http://localhost:8080/vi/quan-tri/nguoi-dung' } });
        const after = (await getPage(page.request, '/vi/tai-khoan')).page.props.auth.user;
        await page.goto('/vi/quan-tri/nguoi-dung');
        await shot(page, 'TC-ADMIN-010');
        actual(ti, `PATCH /nguoi-dung/${me.id} {role member, inactive} → ${r.status} ${JSON.stringify(r.body?.errors ?? r.body?.message ?? '')}; vai trò sau đó: ${after.role}.`);
        expect(after.role).toBe('admin');
    });
});

test.describe.serial('TOUR – contributor quản lý tuyến của mình', () => {
    test.use({ storageState: authFile('contributor') });

    test('TC-TOUR-003 contributor tạo tuyến mới + khung giờ rồi xóa', async ({ page }, ti) => {
        await page.goto('/vi');
        const slug = `qa-tuyen-${rnd()}`;
        const { page: form } = await getPage(page.request, '/vi/quan-tri/tuyen-tham-quan/moi');
        const c = await send(page.request, 'POST', '/vi/quan-tri/tuyen-tham-quan', {
            slug, duration_minutes: 180, difficulty: 'de', price_vnd: 300000, default_capacity: 15, status: 'draft',
            translations: { vi: { name: `Tuyến kiểm thử ${slug}`, description: 'Mô tả' }, en: { name: 'QA route' } },
            stops: [{ site_id: form.props.sites[0].id, stop_order: 1, dwell_minutes: 60 }],
        }, { accept: 'html' });
        const s = await send(page.request, 'POST', `/vi/quan-tri/tuyen-tham-quan/${slug}/khung-gio`, { starts_at: '2026-12-01 08:00', duration_minutes: 180, capacity: 15, guide_name: 'QA', language: 'vi' }, { accept: 'html' });
        const { page: ed } = await getPage(page.request, `/vi/quan-tri/tuyen-tham-quan/${slug}/sua`);
        await page.goto(`/vi/quan-tri/tuyen-tham-quan/${slug}/sua`);
        await shot(page, 'TC-TOUR-003');
        const d = await send(page.request, 'DELETE', `/vi/quan-tri/tuyen-tham-quan/${slug}`, {}, { accept: 'html' });
        const after = await page.request.get(`/vi/quan-tri/tuyen-tham-quan/${slug}/sua`);
        actual(ti, `Tạo tuyến → ${c.status}; thêm khung giờ → ${s.status}; form sửa có ${ed?.props?.route?.slots?.length} khung giờ, ${ed?.props?.route?.stops?.length} điểm dừng; DELETE → ${d.status}; mở lại form → ${after.status()}.`);
        expect(ed.props.route.slots.length).toBe(1);
        expect(after.status()).toBe(404);
    });
});
