// Thách thức nâng cao – kiểm thử tình huống bằng dữ liệu cảnh báo mô phỏng: admin chạy kịch bản từ dashboard,
// cảnh báo is_simulated=1 có nhãn rõ, kết thúc → hết hiệu lực + hủy thông báo.
import { test, expect } from '@playwright/test';
import { authFile, shot, actual, getPage } from './helpers.js';

const SLUG = 'bao-do-bo-bac-trung-bo';

test.describe.serial('Mô phỏng kịch bản (admin) @chromium', () => {
    test.use({ storageState: authFile('admin') });
    let codes = [];

    test('TC-ADV-002 admin chạy kịch bản "Bão mạnh đổ bộ Bắc Trung Bộ" trên giao diện → sinh cảnh báo mô phỏng, đối sánh, thông báo @chromium', async ({ page, request }, info) => {
        await page.goto('/vi/quan-tri/mo-phong');
        const card = page.locator('li, article').filter({ hasText: 'Bão mạnh đổ bộ Bắc Trung Bộ' }).last();
        const finishBtn = card.getByRole('button', { name: 'Kết thúc' });
        if (await finishBtn.count()) {
            await finishBtn.click();
            await page.getByRole('alertdialog').getByRole('button', { name: 'Kết thúc' }).click();
            await page.waitForTimeout(1500);
        }
        await card.getByRole('button', { name: 'Chạy' }).click();
        const dlg = page.getByRole('alertdialog');
        const instant = dlg.getByLabel(/tức thì|Ngay|instant/i);
        if (await instant.count()) await instant.first().check().catch(() => {});
        await dlg.getByRole('button', { name: 'Chạy' }).click();
        await page.waitForTimeout(3000);
        const flash = (await page.locator('[role=status], [role=alert]').allInnerTexts()).join(' | ').replace(/\s+/g, ' ').slice(0, 220);
        await shot(page, 'TC-ADV-002', info, { fullPage: true });
        const p = (await getPage(request, '/vi/quan-tri/mo-phong')).page.props;
        const run = p.runs.find((r) => r.scenario.slug === SLUG);
        codes = run?.alert_codes || [];
        const sc = p.scenarios.find((s) => s.slug === SLUG);
        actual(info, `Lần chạy #${run?.id} chế độ ${run?.mode}: alerts_created ${run?.alerts_created}, matches ${run?.matches_created}, notifications ${run?.notifications_created}, bước ${run?.steps_done}/${run?.steps_total}; mã cảnh báo ${codes.join(', ')}; trạng thái kịch bản ${sc.status}; flash "${flash}"`);
        expect(codes.length).toBeGreaterThan(0);
    });

    test('TC-ADV-003 cảnh báo mô phỏng có is_simulated=1, gắn kịch bản và nhãn "Mô phỏng" trên trang công khai @chromium', async ({ page, request }, info) => {
        test.skip(!codes.length, 'chưa có cảnh báo từ TC-ADV-002');
        const a = (await (await request.get(`/api/v1/alerts/${codes[0]}`)).json());
        const al = a.data || a;
        await page.goto(`/vi/canh-bao/${codes[0]}`);
        const body = await page.locator('main').innerText();
        const label = (body.match(/[^\n]*(MÔ PHỎNG|Mô phỏng)[^\n]*/) || [''])[0].slice(0, 120);
        await shot(page, 'TC-ADV-003', info);
        actual(info, `${codes[0]}: is_simulated=${al.is_simulated}, scenario=${JSON.stringify(al.scenario)}, nguồn "${al.source?.name}"; nhãn trên trang: "${label}"`);
        expect(al.is_simulated).toBeTruthy();
        expect(al.scenario?.slug).toBe(SLUG);
        expect(label).toBeTruthy();
    });

    test('TC-ADV-004 kết thúc mô phỏng → mọi cảnh báo của lần chạy hết hiệu lực, thông báo đang xếp hàng bị hủy @chromium', async ({ page, request }, info) => {
        test.skip(!codes.length, 'chưa có cảnh báo từ TC-ADV-002');
        await page.goto('/vi/quan-tri/mo-phong');
        const card = page.locator('li, article').filter({ hasText: 'Bão mạnh đổ bộ Bắc Trung Bộ' }).last();
        await card.getByRole('button', { name: 'Kết thúc' }).click();
        await page.getByRole('alertdialog').getByRole('button', { name: 'Kết thúc' }).click();
        await page.waitForTimeout(2500);
        const flash = (await page.locator('[role=status], [role=alert]').allInnerTexts()).join(' | ').replace(/\s+/g, ' ').slice(0, 200);
        await shot(page, 'TC-ADV-004', info);
        const statuses = [];
        for (const c of codes) statuses.push(`${c}:${(await getPage(request, `/vi/quan-tri/canh-bao/${c}`)).page.props.alert.status}`);
        const st = (await getPage(request, `/vi/quan-tri/canh-bao/${codes[0]}`)).page.props.notifications.by_status;
        const active = (await (await request.get('/api/v1/alerts?active=1&per_page=100')).json()).data.filter((a) => codes.includes(a.code)).length;
        actual(info, `flash "${flash}"; trạng thái: ${statuses.join(', ')}; thông báo của ${codes[0]}: ${JSON.stringify(st)}; còn trong danh sách đang hiệu lực: ${active}`);
        expect(statuses.every((s) => s.endsWith(':expired'))).toBeTruthy();
        expect(st.queued).toBe(0);
        expect(active).toBe(0);
    });
});
