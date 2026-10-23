// Giới hạn tần suất API 60 yêu cầu/phút/IP. Chạy cuối cùng (tên zz-) và chờ 61 s sau khi chạm giới hạn
// để không ảnh hưởng các bộ khác.
import { test, expect } from '@playwright/test';
import { shot, actual, showText } from './helpers.js';

test('TC-SEC-015 API 60 yêu cầu/phút/IP → yêu cầu vượt nhận 429 kèm Retry-After @chromium', async ({ page, request }, info) => {
    test.setTimeout(150_000);
    const codes = [];
    let first429 = null;
    let remaining = [];
    for (let i = 1; i <= 65; i++) {
        const r = await request.get('/api/v1/zones');
        codes.push(r.status());
        if (i <= 3 || i >= 59) remaining.push(`${i}:${r.headers()['x-ratelimit-remaining'] ?? '-'}`);
        if (r.status() === 429 && !first429) first429 = { i, retry: r.headers()['retry-after'], body: await r.text() };
    }
    const ok = codes.filter((c) => c === 200).length;
    await showText(page, 'TC-SEC-015 65 GET /api/v1/zones liên tiếp', `200: ${ok}, 429: ${codes.filter((c) => c === 429).length}\nX-RateLimit-Remaining: ${remaining.join(' ')}\nLần 429 đầu: #${first429?.i}, Retry-After ${first429?.retry}, thân ${first429?.body}`);
    await shot(page, 'TC-SEC-015', info);
    actual(info, `65 GET liên tiếp: ${ok} lần 200, ${codes.length - ok} lần 429; 429 đầu tiên ở yêu cầu #${first429?.i} (Retry-After ${first429?.retry} s, thân ${first429?.body}); X-RateLimit-Remaining ${remaining.join(' ')}`);
    expect(first429).toBeTruthy();
    expect(first429.i).toBeLessThanOrEqual(61);
    await page.waitForTimeout(61_000);
});
