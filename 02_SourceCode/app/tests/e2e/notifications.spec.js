// F4 – Thông báo phân cấp 4 mức qua hàng đợi: danh sách, đánh dấu đã đọc, tùy chọn kênh, phát thủ công, mức 4 ép mọi kênh.
import { test, expect } from '@playwright/test';
import { authFile, shot, actual, send, getPage, showText } from './helpers.js';

test.describe('Thông báo của du khách', () => {
    test.use({ storageState: authFile('traveler') });

    test('TC-NOTI-001 trang Thông báo: mức (màu + chữ), kênh, số chưa đọc khớp huy hiệu đầu trang', async ({ page, request }, info) => {
        const p = (await getPage(request, '/vi/thong-bao')).page.props;
        const rows = p.notifications.data;
        await page.goto('/vi/thong-bao');
        await shot(page, 'TC-NOTI-001', info);
        const levels = [...new Set(rows.map((n) => n.level))].sort();
        actual(info, `${(p.notifications.total ?? p.notifications.meta?.total) ?? rows.length} thông báo in-app, mức có mặt ${levels.join(',')}, chưa đọc ${p.unread}; auth.user.unread_notifications=${p.auth.user.unread_notifications}; ví dụ: [mức ${rows[0]?.level}] "${rows[0]?.title?.slice(0, 70)}" (${rows[0]?.status}, gửi ${rows[0]?.sent_at})`);
        expect(rows.length).toBeGreaterThan(0);
        expect(rows.every((n) => n.channel === 'inapp')).toBeTruthy();
        expect(p.unread).toBe(p.auth.user.unread_notifications);
    });
});

test.describe('Đánh dấu đã đọc (traveler1)', () => {
    test.use({ storageState: authFile('traveler1') });

    test('TC-NOTI-002 bấm "Đã đọc" một thông báo → read_at được ghi, số chưa đọc giảm 1 @chromium', async ({ page, request }, info) => {
        const before = (await getPage(request, '/vi/thong-bao?unread=1')).page.props;
        const n = before.notifications.data[0];
        test.skip(!n, 'traveler1 không còn thông báo chưa đọc');
        const r = await send(request, 'POST', `/vi/thong-bao/${n.id}/da-doc`, {}, { headers: { 'X-Inertia': 'true' } });
        const after = (await getPage(request, '/vi/thong-bao')).page.props;
        const same = after.notifications.data.find((x) => x.id === n.id);
        await page.goto('/vi/thong-bao');
        await shot(page, 'TC-NOTI-002', info);
        actual(info, `Thông báo #${n.id} (mức ${n.level}): POST da-doc → ${r.status}; read_at=${same?.read_at}, status=${same?.status}; chưa đọc ${before.unread} → ${after.unread}`);
        expect(same.read_at).toBeTruthy();
        expect(after.unread).toBe(before.unread - 1);
    });

    test('TC-NOTI-003 nút "Đánh dấu tất cả đã đọc" trên giao diện → chưa đọc = 0 @chromium', async ({ page, request }, info) => {
        const before = (await getPage(request, '/vi/thong-bao')).page.props.unread;
        await page.goto('/vi/thong-bao');
        const btn = page.getByRole('button', { name: /tất cả/i });
        if (await btn.count()) await btn.click();
        await page.waitForTimeout(1200);
        const after = (await getPage(request, '/vi/thong-bao')).page.props;
        await shot(page, 'TC-NOTI-003', info);
        actual(info, `Chưa đọc trước ${before}; sau khi bấm nút: ${after.unread}; huy hiệu auth.user.unread_notifications=${after.auth.user.unread_notifications}`);
        expect(after.unread).toBe(0);
    });

    test('TC-NOTI-004 lưu tùy chọn kênh theo mức + giờ yên lặng + bản tin ngày mức 1', async ({ page, request }, info) => {
        const prefs = { channels_level1: ['inapp'], channels_level2: ['inapp', 'email'], channels_level3: ['inapp', 'email', 'sms'], channels_level4: ['inapp', 'email', 'sms'], quiet_from: '22:00', quiet_to: '06:00', digest_daily: true, language: 'vi' };
        const r = await send(request, 'PUT', '/vi/tai-khoan', { preferences: prefs }, { headers: { 'X-Inertia': 'true' } });
        const p = (await getPage(request, '/vi/tai-khoan')).page.props;
        const saved = p.preferences || p.notificationPreferences || p.user?.preferences;
        await page.goto('/vi/tai-khoan');
        await shot(page, 'TC-NOTI-004', info, { fullPage: true });
        actual(info, `PUT /vi/tai-khoan → ${r.status}; đã lưu: ${JSON.stringify(saved).slice(0, 300)}`);
        expect([302, 303]).toContain(r.status);
        expect(JSON.stringify(saved)).toContain('22:00');
        expect(saved.channels_level2).toEqual(['inapp', 'email']);
    });

    test('TC-NOTI-005 [Biên] lưu kênh không hợp lệ (fax) và giờ 25:00 → 422', async ({ page, request }, info) => {
        const r = await send(request, 'PUT', '/vi/tai-khoan', { preferences: { channels_level2: ['fax'], quiet_from: '25:00' } });
        await showText(page, 'TC-NOTI-005 PUT /vi/tai-khoan (preferences sai)', `${r.status}\n${JSON.stringify(r.body, null, 1)}`);
        await shot(page, 'TC-NOTI-005', info);
        actual(info, `${r.status} ${Object.keys(r.body.errors || {}).join(', ')}`);
        expect(r.status).toBe(422);
        expect(Object.keys(r.body.errors)).toEqual(expect.arrayContaining(['preferences.channels_level2.0', 'preferences.quiet_from']));
    });
});

test.describe('Phát thông báo thủ công (admin)', () => {
    test.use({ storageState: authFile('admin') });

    test('TC-NOTI-006 phát thủ công mức 2 tới vùng Đà Nẵng qua in-app → du khách có chặng ở Đà Nẵng nhận được @chromium', async ({ page, request }, info) => {
        const title = `E2E phát thủ công mức 2 ${Date.now().toString(36)}`;
        const r = await send(request, 'POST', '/vi/quan-tri/thong-bao/phat-thu-cong', { zone_codes: ['da-nang'], level: 2, title, body: 'Nội dung kiểm thử phát thủ công', channels: ['inapp'] }, { headers: { 'X-Inertia': 'true' } });
        const q = (await getPage(request, `/vi/quan-tri/thong-bao?q=${encodeURIComponent(title)}`)).page.props.notifications.data;
        await page.goto(`/vi/quan-tri/thong-bao?q=${encodeURIComponent(title)}`);
        await shot(page, 'TC-NOTI-006', info);
        actual(info, `POST phat-thu-cong → ${r.status}; ${q.length} thông báo tạo ra, kênh ${[...new Set(q.map((n) => n.channel))]}, mức ${[...new Set(q.map((n) => n.level))]}, trạng thái ${[...new Set(q.map((n) => n.status))]}`);
        expect(q.length).toBeGreaterThan(0);
        expect(q.every((n) => n.level === 2 && n.channel === 'inapp')).toBeTruthy();
    });

    test('TC-ADV-001 mức 4 luôn gửi mọi kênh (in-app + email + SMS) dù chỉ chọn in-app @chromium', async ({ page, request }, info) => {
        const title = `E2E khẩn cấp mức 4 ${Date.now().toString(36)}`;
        const r = await send(request, 'POST', '/vi/quan-tri/thong-bao/phat-thu-cong', { zone_codes: ['da-nang'], level: 4, title, body: 'Kiểm thử mức 4', channels: ['inapp'] }, { headers: { 'X-Inertia': 'true' } });
        const q = (await getPage(request, `/vi/quan-tri/thong-bao?q=${encodeURIComponent(title)}`)).page.props.notifications.data;
        const byUser = {};
        for (const n of q) (byUser[n.user_id ?? n.user?.name ?? '?'] ||= []).push(n.channel);
        await page.goto(`/vi/quan-tri/thong-bao?q=${encodeURIComponent(title)}`);
        await shot(page, 'TC-ADV-001', info);
        actual(info, `POST phat-thu-cong level=4 channels=[inapp] → ${r.status}; ${q.length} thông báo; kênh: ${[...new Set(q.map((n) => n.channel))].join(', ')}; next_attempt_at ${q[0]?.next_attempt_at ?? 'ngay'}`);
        expect(new Set(q.map((n) => n.channel))).toEqual(new Set(['inapp', 'email', 'sms']));
    });
});
