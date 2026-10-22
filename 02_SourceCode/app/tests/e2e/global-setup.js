// Đăng nhập một lần cho mỗi vai trò và lưu storageState (giới hạn đăng nhập 5 lần/phút theo email+IP).
// Nếu storageState cũ vẫn còn phiên hợp lệ thì dùng lại, không đăng nhập lại.
import fs from 'node:fs';
import path from 'node:path';
import { request } from '@playwright/test';
import { AUTH_DIR, ROLES, PASSWORD, xsrfHeader } from './helpers.js';

async function sessionValid(baseURL, file) {
    if (!fs.existsSync(file)) return false;
    const ctx = await request.newContext({ baseURL, storageState: file });
    const res = await ctx.get('/vi/tai-khoan', { maxRedirects: 0 });
    await ctx.dispose();
    return res.status() === 200;
}

async function login(baseURL, email, file) {
    const ctx = await request.newContext({ baseURL });
    await ctx.get('/vi/dang-nhap');
    const res = await ctx.post('/vi/dang-nhap', {
        form: { email, password: PASSWORD, remember: '0' },
        headers: await xsrfHeader(ctx),
        maxRedirects: 0,
    });
    const location = res.headers()['location'] || '';
    if (![302, 303].includes(res.status()) || !location.includes('/tai-khoan') && !location.includes('/quan-tri')) {
        throw new Error(`Đăng nhập ${email} thất bại: ${res.status()} ${location}`);
    }
    await ctx.storageState({ path: file });
    await ctx.dispose();
}

export default async function globalSetup(config) {
    const baseURL = config.projects[0].use.baseURL;
    fs.mkdirSync(AUTH_DIR, { recursive: true });
    for (const [role, email] of Object.entries(ROLES)) {
        const file = path.join(AUTH_DIR, `${role}.json`);
        if (await sessionValid(baseURL, file)) continue;
        await login(baseURL, email, file);
    }
}
