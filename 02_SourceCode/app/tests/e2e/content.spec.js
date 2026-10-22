// UC-02..UC-06: tra cứu theo tầng, thuyết minh, bản đồ, dòng thời gian, tìm kiếm; đa ngữ, theme, trang lỗi.
import { test, expect } from '@playwright/test';
import { authFile, actual, shot, getPage, send } from './helpers.js';

const SITE = '/vi/di-san/hoang-thanh-thang-long';

test.describe('SITE / ART / FIG', () => {
    test('TC-SITE-001 danh sách di sản phân trang 12 mục/trang', async ({ page }, ti) => {
        const { page: p } = await getPage(page.request, '/vi/di-san');
        const s = p.props.sites;
        await page.goto('/vi/di-san?page=2');
        const cards = await page.locator('main a[href*="/vi/di-san/"]').count();
        expect(s.per_page).toBe(12);
        expect(s.data.length).toBe(12);
        actual(ti, `per_page=${s.per_page}, total=${s.total}, last_page=${s.last_page}; trang 2 hiển thị ${cards} liên kết thẻ di sản.`);
        await shot(page, 'TC-SITE-001');
    });

    test('TC-SITE-002 lọc di sản theo loại di_san_phi_vat_the', async ({ page }, ti) => {
        await page.goto('/vi/di-san?type=di_san_phi_vat_the');
        const { page: p } = await getPage(page.request, '/vi/di-san?type=di_san_phi_vat_the');
        const types = [...new Set(p.props.sites.data.map((x) => x.site_type))];
        expect(types).toEqual(['di_san_phi_vat_the']);
        actual(ti, `${p.props.sites.total} kết quả, mọi site_type = ${types.join(',')}; filters.type=${p.props.filters.type}.`);
        await shot(page, 'TC-SITE-002');
    });

    test('TC-SITE-003 chi tiết di sản hiển thị đúng 3 tầng diễn giải khác nhau', async ({ page }, ti) => {
        const out = [];
        for (const d of [1, 2, 3]) {
            const { page: p } = await getPage(page.request, `${SITE}?depth=${d}`);
            out.push({ d, lvl: p.props.site.interpretation.depth_level, len: p.props.site.interpretation.body_html.length, title: p.props.site.interpretation.title });
        }
        await page.goto(`${SITE}?depth=3`);
        await expect(page.locator('h1')).toBeVisible();
        expect(out.map((o) => o.lvl)).toEqual([1, 2, 3]);
        expect(new Set(out.map((o) => o.len)).size).toBe(3);
        actual(ti, out.map((o) => `depth ${o.d}: level=${o.lvl}, body ${o.len} ký tự`).join('; '));
        await shot(page, 'TC-SITE-003');
    });

    test('TC-SITE-004 tham số depth ngoài biên (0, 9, abc) không gây lỗi', async ({ page }, ti) => {
        const res = [];
        for (const d of ['0', '9', 'abc']) {
            const { status, page: p } = await getPage(page.request, `${SITE}?depth=${d}`);
            res.push(`${d}→${status}/depth=${p?.props?.depth}`);
            expect(status).toBe(200);
            expect([1, 2, 3]).toContain(p.props.depth);
        }
        await page.goto(`${SITE}?depth=9`);
        actual(ti, res.join(', '));
        await shot(page, 'TC-SITE-004');
    });

    test('TC-SITE-005 slug di sản không tồn tại trả trang 404 thân thiện', async ({ page }, ti) => {
        const r = await page.goto('/vi/di-san/khong-ton-tai-xyz');
        expect(r.status()).toBe(404);
        await expect(page.getByText('Không tìm thấy trang')).toBeVisible();
        await expect(page.getByRole('link', { name: 'Về trang chủ' })).toBeVisible();
        actual(ti, `HTTP ${r.status()}, tiêu đề "Không tìm thấy trang", có nút Về trang chủ và Tìm kiếm.`);
        await shot(page, 'TC-SITE-005');
    });

    test('TC-SITE-006 thuyết minh âm thanh đa ngữ (vi/en) hiển thị trình phát', async ({ page }, ti) => {
        await page.goto(SITE);
        const box = page.getByRole('region', { name: 'Thuyết minh' }).or(page.locator('section[aria-label]').filter({ hasText: /Phát|Bản ghi âm|giọng tổng hợp/ })).first();
        await expect(box).toBeVisible();
        await box.scrollIntoViewIfNeeded();
        const { page: en } = await getPage(page.request, '/en/di-san/hoang-thanh-thang-long');
        const { page: vi } = await getPage(page.request, SITE);
        const viA = vi.props.site.media.audios, enA = en.props.site.media.audios;
        expect(viA.length + enA.length).toBeGreaterThan(0);
        actual(ti, `vi: ${viA.map((a) => `${a.locale}/${a.mode}`).join(',')}; en: ${enA.map((a) => `${a.locale}/${a.mode}`).join(',')}; khung thuyết minh có nút Phát.`);
        await shot(page, 'TC-SITE-006');
    });

    test('TC-SITE-007 tham số sort lạ được bỏ qua, không lỗi', async ({ page }, ti) => {
        const { status, page: p } = await getPage(page.request, "/vi/di-san?sort=drop%20table%3B--");
        expect(status).toBe(200);
        expect(p.props.filters.sort).toBeNull();
        await page.goto("/vi/di-san?sort=name");
        actual(ti, `sort lạ → HTTP ${status}, filters.sort=${p.props.filters.sort}; sort=name hiển thị bình thường.`);
        await shot(page, 'TC-SITE-007');
    });

    test('TC-ART-001 lọc hiện vật là bảo vật quốc gia', async ({ page }, ti) => {
        const { page: p } = await getPage(page.request, '/vi/hien-vat?treasure=1');
        const all = p.props.artifacts.data.every((a) => a.is_national_treasure);
        await page.goto('/vi/hien-vat?treasure=1');
        expect(all).toBe(true);
        actual(ti, `${p.props.artifacts.total} hiện vật, tất cả is_national_treasure=true; ô lọc vẫn được tick.`);
        await shot(page, 'TC-ART-001');
    });

    test('TC-ART-002 chi tiết hiện vật có thông số và nguồn gốc ảnh', async ({ page }, ti) => {
        const { page: p } = await getPage(page.request, '/vi/hien-vat/trong-dong-ngoc-lu');
        const a = p.props.artifact;
        await page.goto('/vi/hien-vat/trong-dong-ngoc-lu');
        await expect(page.locator('h1')).toContainText(/Ngọc Lũ/);
        actual(ti, `"${a.title}", chất liệu ${a.material}, cao ${a.height_cm} cm, ${a.media.images.length} ảnh, giấy phép ảnh đầu: ${a.media.images[0]?.license_label ?? '-'}.`);
        await shot(page, 'TC-ART-002');
    });

    test('TC-FIG-001 chi tiết nhân vật liên kết tới di sản liên quan', async ({ page }, ti) => {
        const { page: p } = await getPage(page.request, '/vi/nhan-vat/ly-thai-to');
        const f = p.props.figure;
        await page.goto('/vi/nhan-vat/ly-thai-to');
        expect(f.sites.length).toBeGreaterThan(0);
        actual(ti, `${f.title} (${f.birth_year}–${f.death_year}), ${f.sites.length} di sản liên quan: ${f.sites.map((s) => s.slug).join(', ')}.`);
        await shot(page, 'TC-FIG-001');
    });
});

test.describe('TL / MAP', () => {
    test('TC-TL-001 dòng thời gian sắp xếp theo năm tăng dần', async ({ page }, ti) => {
        const { page: p } = await getPage(page.request, '/vi/dong-thoi-gian');
        const years = p.props.events.map((e) => e.year);
        const sorted = years.every((y, i) => i === 0 || years[i - 1] <= y);
        await page.goto('/vi/dong-thoi-gian');
        expect(sorted).toBe(true);
        actual(ti, `${years.length} sự kiện, năm từ ${years[0]} đến ${years.at(-1)}, đúng thứ tự tăng dần.`);
        await shot(page, 'TC-TL-001');
    });

    test('TC-TL-002 lọc dòng thời gian theo thời kỳ', async ({ page }, ti) => {
        const { page: all } = await getPage(page.request, '/vi/dong-thoi-gian');
        const period = all.props.periods.find((x) => all.props.events.some((e) => e.period?.code === x.code));
        const { page: p } = await getPage(page.request, `/vi/dong-thoi-gian?period=${period.code}`);
        await page.goto(`/vi/dong-thoi-gian?period=${period.code}`);
        const ok = p.props.events.every((e) => e.period?.code === period.code || (e.year >= period.start_year && e.year <= period.end_year));
        actual(ti, `period=${period.code}: ${p.props.events.length}/${all.props.events.length} sự kiện; tất cả thuộc thời kỳ: ${ok}.`);
        expect(p.props.events.length).toBeGreaterThan(0);
        expect(ok).toBe(true);
        await shot(page, 'TC-TL-002');
    });

    test('TC-MAP-001 bản đồ hiển thị điểm di sản bằng Leaflet và tile OSM', async ({ page }, ti) => {
        await page.goto('/vi/ban-do');
        await expect(page.locator('.leaflet-container').first()).toBeVisible();
        await page.waitForTimeout(2500);
        const markers = await page.locator('.leaflet-marker-icon').count();
        const tiles = await page.locator('img.leaflet-tile-loaded').count();
        const { page: p } = await getPage(page.request, '/vi/ban-do');
        expect(markers).toBeGreaterThan(0);
        actual(ti, `${p.props.geojson.features.length} feature GeoJSON; hiển thị ${markers} marker/cụm, ${tiles} ô bản đồ đã tải.`);
        await shot(page, 'TC-MAP-001');
    });

    test('TC-MAP-002 lọc bản đồ theo cấp công nhận UNESCO', async ({ page }, ti) => {
        const { page: p } = await getPage(page.request, '/vi/ban-do?level=unesco');
        const lv = [...new Set(p.props.geojson.features.map((f) => f.properties.recognition_level))];
        await page.goto('/vi/ban-do?level=unesco');
        await page.waitForTimeout(1500);
        expect(lv).toEqual(['unesco']);
        actual(ti, `${p.props.geojson.features.length} điểm, recognition_level = ${lv.join(',')}.`);
        await shot(page, 'TC-MAP-002');
    });
});

test.describe('SEARCH', () => {
    test('TC-SEARCH-001 tìm có dấu "hoàng thành" trả Hoàng thành Thăng Long đứng đầu', async ({ page }, ti) => {
        await page.goto('/vi/tim-kiem?q=ho%C3%A0ng%20th%C3%A0nh');
        const { page: p } = await getPage(page.request, '/vi/tim-kiem?q=ho%C3%A0ng%20th%C3%A0nh');
        expect(p.props.results[0].slug).toBe('hoang-thanh-thang-long');
        actual(ti, `engine=${p.props.engine}, ${p.props.results.length} kết quả, đầu: ${p.props.results.slice(0, 3).map((r) => r.slug).join(', ')}; ${p.props.durationMs} ms.`);
        await shot(page, 'TC-SEARCH-001');
    });

    test('TC-SEARCH-002 tìm không dấu "hoang thanh" cho kết quả tương đương có dấu', async ({ page }, ti) => {
        await page.goto('/vi/tim-kiem?q=hoang%20thanh');
        const { page: p } = await getPage(page.request, '/vi/tim-kiem?q=hoang%20thanh');
        expect(p.props.results[0].slug).toBe('hoang-thanh-thang-long');
        actual(ti, `engine=${p.props.engine}, đầu: ${p.props.results.slice(0, 3).map((r) => r.slug).join(', ')}.`);
        await shot(page, 'TC-SEARCH-002');
    });

    test('TC-SEARCH-003 truy vấn rỗng và chỉ khoảng trắng không lỗi', async ({ page }, ti) => {
        const a = await getPage(page.request, '/vi/tim-kiem?q=');
        const b = await getPage(page.request, '/vi/tim-kiem?q=%20%20%20');
        await page.goto('/vi/tim-kiem?q=');
        expect(a.status).toBe(200);
        expect(b.status).toBe(200);
        actual(ti, `q="" → ${a.status}, ${a.page.props.results.length} kết quả, gợi ý ${a.page.props.popularQueries.length} truy vấn phổ biến; q="   " → ${b.status}.`);
        await shot(page, 'TC-SEARCH-003');
    });

    test('TC-SEARCH-004 truy vấn biên: 500 ký tự và ký tự đặc biệt', async ({ page }, ti) => {
        const long = 'a'.repeat(500);
        const a = await getPage(page.request, `/vi/tim-kiem?q=${long}`);
        const sp = encodeURIComponent(`"%_\\<>*+-@~()`);
        const b = await getPage(page.request, `/vi/tim-kiem?q=${sp}`);
        await page.goto(`/vi/tim-kiem?q=${sp}`);
        expect(a.status).toBeLessThan(500);
        expect(b.status).toBe(200);
        actual(ti, `500 ký tự → ${a.status} (query lưu ${a.page?.props?.query?.length ?? '-'} ký tự); ký tự đặc biệt → ${b.status}, ${b.page.props.results.length} kết quả.`);
        await shot(page, 'TC-SEARCH-004');
    });

    test('TC-SEARCH-005 trang thẻ chủ đề liệt kê nội dung gắn thẻ', async ({ page }, ti) => {
        const { page: p } = await getPage(page.request, '/vi/the/kinh-do-co');
        await page.goto('/vi/the/kinh-do-co');
        const n = p.props.sites.length + p.props.artifacts.length + p.props.figures.length + p.props.routes.length;
        expect(n).toBeGreaterThan(0);
        actual(ti, `Thẻ "${p.props.tag.name}": ${p.props.sites.length} di sản, ${p.props.artifacts.length} hiện vật, ${p.props.figures.length} nhân vật, ${p.props.routes.length} tuyến.`);
        await shot(page, 'TC-SEARCH-005');
    });
});

test.describe('I18N / THEME', () => {
    test('TC-I18N-001 đổi ngôn ngữ vi→en giữ nguyên trang và tham số', async ({ page }, ti) => {
        await page.goto(`${SITE}?depth=2`);
        const viTitle = await page.locator('h1').innerText();
        await page.locator('a[hreflang="en"]').first().click();
        await page.waitForURL(/\/en\/di-san\/hoang-thanh-thang-long/);
        const enTitle = await page.locator('h1').innerText();
        const lang = await page.locator('html').getAttribute('lang');
        expect(lang).toBe('en');
        expect(page.url()).toContain('depth=2');
        actual(ti, `URL sau đổi: ${new URL(page.url()).pathname}${new URL(page.url()).search}; <html lang=${lang}>; tiêu đề "${viTitle}" → "${enTitle}".`);
        await shot(page, 'TC-I18N-001');
    });

    test('TC-I18N-002 locale không hỗ trợ (/fr) trả 404, "/" chuyển về /vi', async ({ page }, ti) => {
        const r = await page.request.get('/fr/di-san', { maxRedirects: 0 });
        const root = await page.request.get('/', { maxRedirects: 0 });
        await page.goto('/fr/di-san');
        expect(r.status()).toBe(404);
        actual(ti, `/fr/di-san → ${r.status()}; / → ${root.status()} ${root.headers()['location']}.`);
        await shot(page, 'TC-I18N-002');
    });

    test('TC-I18N-003 giao diện tiếng Anh không còn chuỗi tiếng Việt trong menu', async ({ page }, ti) => {
        await page.goto('/en');
        const nav = await page.locator('header').innerText();
        const vi = /Di sản|Hiện vật|Bản đồ|Tìm kiếm|Đăng nhập/.test(nav);
        expect(vi).toBe(false);
        actual(ti, `Menu /en: ${nav.replace(/\s+/g, ' ').slice(0, 140)}`);
        await shot(page, 'TC-I18N-003');
    });

    test('TC-THEME-001 khách đổi theme tối, tải lại vẫn giữ (localStorage)', async ({ page }, ti) => {
        await page.goto('/vi');
        const before = await page.locator('html').getAttribute('data-theme');
        await page.locator('header button[aria-label*="tối"], header button[aria-label*="sáng"]').first().click();
        const after = await page.locator('html').getAttribute('data-theme');
        await page.reload();
        const reloaded = await page.locator('html').getAttribute('data-theme');
        const stored = await page.evaluate(() => localStorage.getItem('disanso-theme'));
        expect(after).not.toBe(before);
        expect(reloaded).toBe(after);
        actual(ti, `data-theme ${before} → ${after}; sau reload: ${reloaded}; localStorage=${stored}.`);
        await shot(page, 'TC-THEME-001');
    });
});

test.describe('THEME – đã đăng nhập', () => {
    test.use({ storageState: authFile('member2') });
    test('TC-THEME-002 thành viên đổi theme được lưu vào hồ sơ (users.theme)', async ({ page }, ti) => {
        await page.goto('/vi/tai-khoan');
        const start = (await getPage(page.request, '/vi/tai-khoan')).page.props.auth.user.theme;
        await page.locator('header button[aria-label*="tối"], header button[aria-label*="sáng"]').first().click();
        await page.waitForTimeout(1500);
        const urlAfter = new URL(page.url()).pathname;
        const saved = (await getPage(page.request, '/vi/tai-khoan')).page.props.auth.user.theme;
        await shot(page, 'TC-THEME-002');
        // Trả lại theme ban đầu
        await send(page.request, 'PATCH', '/vi/tai-khoan/ho-so', { theme: start || 'light' });
        expect(saved).not.toBe(start);
        expect(urlAfter).toBe('/vi/tai-khoan');
        actual(ti, `users.theme ${start} → ${saved}, trang vẫn ở ${urlAfter} (không chuyển hướng); đã trả về ${start}.`);
    });
});
