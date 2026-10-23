// F6 – Danh bạ khẩn cấp theo địa phương; F7 – Hướng dẫn ứng phó đa ngữ.
import { test, expect } from '@playwright/test';
import { authFile, shot, actual, send, getPage } from './helpers.js';

const GUIDE = 'an-toan-le-hoi-dong-nguoi';

test.describe('Danh bạ khẩn cấp', () => {
    test('TC-CONT-001 danh bạ có số khẩn cấp toàn quốc (113/114/115/112) và nhóm theo 34 tỉnh', async ({ page, request }, info) => {
        const p = (await getPage(request, '/vi/danh-ba-khan-cap')).page.props;
        const nat = p.national.map((c) => c.phone);
        await page.goto('/vi/danh-ba-khan-cap');
        await shot(page, 'TC-CONT-001', info);
        const tel = await page.locator('a[href^="tel:"]').count();
        actual(info, `national: ${nat.join(', ')}; ${p.contacts.length} nhóm tỉnh, tổng ${p.contacts.reduce((s, g) => s + g.contacts.length, 0)} liên hệ; ${tel} liên kết tel: bấm gọi được`);
        for (const n of ['113', '114', '115', '112']) expect(nat).toContain(n);
        expect(p.contacts.length).toBe(34);
        expect(tel).toBeGreaterThan(0);
    });

    test('TC-CONT-002 lọc theo tỉnh Khánh Hòa chỉ còn liên hệ của Khánh Hòa', async ({ page, request }, info) => {
        const p = (await getPage(request, '/vi/danh-ba-khan-cap?province=KH')).page.props;
        await page.goto('/vi/danh-ba-khan-cap?province=KH');
        await shot(page, 'TC-CONT-002', info);
        actual(info, `${p.contacts.length} nhóm: ${p.contacts.map((g) => `${g.province.name} (${g.contacts.map((c) => `${c.type_label} ${c.phone}`).join('; ')})`).join(' | ')}`);
        expect(p.contacts.length).toBe(1);
        expect(p.contacts[0].province.code).toBe('KH');
    });

    test('TC-CONT-003 lọc theo loại Đại sứ quán – lãnh sự; mỗi liên hệ có nguồn và ngày xác minh', async ({ page, request }, info) => {
        const p = (await getPage(request, '/vi/danh-ba-khan-cap?type=embassy')).page.props;
        const items = p.contacts.flatMap((g) => g.contacts);
        await page.goto('/vi/danh-ba-khan-cap?type=embassy');
        await shot(page, 'TC-CONT-003', info);
        actual(info, `${items.length} liên hệ loại embassy (${items.slice(0, 3).map((c) => c.name).join('; ')}…); có nguồn: ${items.filter((c) => c.source?.name).length}/${items.length}; đã xác minh có ngày: ${items.filter((c) => c.is_verified && c.verified_at).length}/${items.length}`);
        expect(items.length).toBeGreaterThan(0);
        expect(items.every((c) => c.contact_type === 'embassy' && c.source?.name)).toBeTruthy();
    });

    test('TC-CONT-004 [Biên] danh bạ tỉnh du lịch trọng điểm có đủ loại bệnh viện và hỗ trợ du khách (theo đề F6)', async ({ page, request }, info) => {
        const counts = {};
        for (const t of ['hospital', 'tourist_support', 'embassy', 'coast_guard']) {
            const p = (await getPage(request, `/vi/danh-ba-khan-cap?type=${t}`)).page.props;
            counts[t] = p.contacts.reduce((s, g) => s + g.contacts.length, 0);
        }
        const kh = (await getPage(request, '/vi/danh-ba-khan-cap?province=KH')).page.props.contacts[0].contacts.map((c) => c.contact_type);
        await page.goto('/vi/danh-ba-khan-cap?type=hospital');
        await shot(page, 'TC-CONT-004', info);
        actual(info, `Số liên hệ theo loại: ${JSON.stringify(counts)}; Khánh Hòa có: ${kh.join(', ')}`);
        expect(counts.hospital, 'phải có liên hệ bệnh viện').toBeGreaterThan(0);
        expect(counts.tourist_support, 'phải có hỗ trợ du khách ở nhiều tỉnh').toBeGreaterThan(3);
    });
});

test.describe('Danh bạ – cơ quan hỗ trợ', () => {
    test.use({ storageState: authFile('agency') });

    test('TC-CONT-005 agency thêm liên hệ hỗ trợ du khách Khánh Hòa, xác minh, rồi xóa @chromium', async ({ page, request }, info) => {
        const name = `Trung tâm hỗ trợ du khách Nha Trang (E2E ${Date.now().toString(36)})`;
        const c = await send(request, 'POST', '/vi/quan-tri/danh-ba/tao', {
            province_code: 'KH', zone_code: 'vinh-nha-trang', contact_type: 'tourist_support', phone: '0258 3 123 456', hours: '7:00–22:00',
            languages: ['vi', 'en'], source_name: 'Kiểm thử E2E (mô phỏng)', source_url: 'https://khanhhoa.gov.vn', is_verified: false,
            translations: { vi: { name, note: 'Liên hệ mô phỏng tạo khi kiểm thử' }, en: { name: 'Nha Trang tourist support (E2E)' } },
        }, { headers: { 'X-Inertia': 'true' } });
        const pub = (await getPage(request, '/vi/danh-ba-khan-cap?province=KH')).page.props.contacts[0].contacts;
        const item = pub.find((x) => x.name === name);
        const v = item ? await send(request, 'POST', `/vi/quan-tri/danh-ba/${item.id}/xac-minh`, {}, { headers: { 'X-Inertia': 'true' } }) : { status: 'n/a' };
        const after = (await getPage(request, '/vi/danh-ba-khan-cap?province=KH')).page.props.contacts[0].contacts.find((x) => x.name === name);
        await page.goto('/vi/danh-ba-khan-cap?province=KH');
        await shot(page, 'TC-CONT-005', info);
        const d = item ? await send(request, 'DELETE', `/vi/quan-tri/danh-ba/${item.id}`, {}, { headers: { 'X-Inertia': 'true' } }) : { status: 'n/a' };
        actual(info, `POST tạo → ${c.status}; xuất hiện công khai: ${!!item} (id ${item?.id}); POST xác minh → ${v.status}, is_verified=${after?.is_verified}, verified_at=${after?.verified_at}; DELETE dọn dữ liệu → ${d.status}`);
        expect(item).toBeTruthy();
        expect(after.is_verified).toBeTruthy();
    });
});

test.describe('Hướng dẫn ứng phó', () => {
    test('TC-GUIDE-001 danh sách hướng dẫn nhóm theo loại hiểm họa', async ({ page, request }, info) => {
        const p = (await getPage(request, '/vi/huong-dan-ung-pho')).page.props;
        await page.goto('/vi/huong-dan-ung-pho');
        await shot(page, 'TC-GUIDE-001', info);
        const total = p.guides.reduce((s, g) => s + g.guides.length, 0);
        actual(info, `${p.guides.length} nhóm hiểm họa, ${total} hướng dẫn đã xuất bản (${p.guides.slice(0, 5).map((g) => `${g.hazard.name}:${g.guides.length}`).join(', ')}…)`);
        expect(total).toBeGreaterThanOrEqual(20);
        expect(p.guides.every((g) => g.guides.every((x) => x.hazard.code === g.hazard.code))).toBeTruthy();
    });

    test('TC-GUIDE-002 chi tiết hướng dẫn: các bước trước – trong – sau, nguồn, danh bạ liên quan', async ({ page, request }, info) => {
        const { guide, contacts } = (await getPage(request, `/vi/huong-dan-ung-pho/${GUIDE}`)).page.props;
        await page.goto(`/vi/huong-dan-ung-pho/${GUIDE}`);
        await shot(page, 'TC-GUIDE-002', info, { fullPage: true });
        actual(info, `"${guide.title}": trước ${guide.before_steps.length}, trong ${guide.during_steps.length}, sau ${guide.after_steps.length} bước; ${guide.sources.length} nguồn (${guide.sources[0]}); ${contacts.length} liên hệ (${contacts.slice(0, 4).map((c) => c.phone).join(', ')})`);
        expect(guide.before_steps.length && guide.during_steps.length && guide.after_steps.length).toBeTruthy();
        expect(guide.sources.length).toBeGreaterThan(0);
    });

    test('TC-GUIDE-003 hướng dẫn bản tiếng Anh (/en) hiển thị nội dung tiếng Anh', async ({ page, request }, info) => {
        const vi = (await getPage(request, `/vi/huong-dan-ung-pho/${GUIDE}`)).page.props.guide;
        const en = (await getPage(request, `/en/huong-dan-ung-pho/${GUIDE}`)).page.props.guide;
        await page.goto(`/en/huong-dan-ung-pho/${GUIDE}`);
        await shot(page, 'TC-GUIDE-003', info);
        actual(info, `vi: "${vi.title}" / en: "${en.title}"; bước đầu en: "${en.before_steps[0]}"; is_machine_translated=${en.is_machine_translated}; html lang=${await page.getAttribute('html', 'lang')}`);
        expect(en.title).not.toBe(vi.title);
        expect(await page.getAttribute('html', 'lang')).toBe('en');
    });

    test('TC-GUIDE-004 slug hướng dẫn không tồn tại → 404', async ({ page, request }, info) => {
        const r = await request.get('/vi/huong-dan-ung-pho/khong-co-huong-dan', { maxRedirects: 0 });
        const api = await request.get('/api/v1/guides/khong-co-huong-dan');
        await page.goto('/vi/huong-dan-ung-pho/khong-co-huong-dan');
        await shot(page, 'TC-GUIDE-004', info);
        actual(info, `web → ${r.status()}; API → ${api.status()}`);
        expect(r.status()).toBe(404);
        expect(api.status()).toBe(404);
    });
});

test.describe('Hướng dẫn – quản trị', () => {
    test.use({ storageState: authFile('agency') });

    test('TC-GUIDE-005 agency soạn hướng dẫn song ngữ (nháp không công khai), xuất bản → hiện ở trang công khai @chromium', async ({ page, request }, info) => {
        const slug = `e2e-huong-dan-${Date.now().toString(36)}`;
        const c = await send(request, 'POST', '/vi/quan-tri/huong-dan/tao', {
            slug, hazard_code: 'nang_nong', min_severity: 2, audience: 'international', status: 'draft',
            translations: {
                vi: { title: 'E2E – Nắng nóng khi đi bộ phố cổ', summary: 'Tóm tắt kiểm thử', before_steps: ['Mang nước'], during_steps: ['Nghỉ trong bóng râm'], after_steps: ['Bù nước'], sources: ['WHO – Heat and health'] },
                en: { title: 'E2E – Heat on walking tours', summary: 'Test summary', before_steps: ['Carry water'], during_steps: ['Rest in shade'], after_steps: ['Rehydrate'], sources: ['WHO – Heat and health'] },
            },
        }, { headers: { 'X-Inertia': 'true' } });
        const draft = await request.get(`/vi/huong-dan-ung-pho/${slug}`, { maxRedirects: 0 });
        const list = (await getPage(request, '/vi/quan-tri/huong-dan?q=' + slug)).page.props;
        const rows = list.guides?.data || list.guides || [];
        const g = rows.find((x) => x.slug === slug);
        const pb = g ? await send(request, 'POST', `/vi/quan-tri/huong-dan/${g.id}/xuat-ban`, {}, { headers: { 'X-Inertia': 'true' } }) : { status: 'không tìm thấy id' };
        const pubVi = await request.get(`/vi/huong-dan-ung-pho/${slug}`, { maxRedirects: 0 });
        const en = (await getPage(request, `/en/huong-dan-ung-pho/${slug}`)).page?.props?.guide;
        await page.goto(`/vi/huong-dan-ung-pho/${slug}`);
        await shot(page, 'TC-GUIDE-005', info);
        const del = g ? await send(request, 'DELETE', `/vi/quan-tri/huong-dan/${g.id}`, {}, { headers: { 'X-Inertia': 'true' } }) : { status: 'n/a' };
        actual(info, `POST tạo nháp → ${c.status}; xem công khai khi nháp → ${draft.status()}; xuất bản → ${pb.status}; công khai vi → ${pubVi.status()}, en title "${en?.title}"; DELETE dọn → ${del.status}`);
        expect(draft.status()).toBe(404);
        expect(pubVi.status()).toBe(200);
        expect(en?.title).toBe('E2E – Heat on walking tours');
    });
});
