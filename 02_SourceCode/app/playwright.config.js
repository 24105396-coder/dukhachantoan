// Cấu hình Playwright cho DiSanSo (vai trò V5 – kiểm thử).
// - Bộ chức năng (*.spec.js trừ responsive) chạy trên Chromium vì có thao tác ghi dữ liệu (đặt lịch, thanh toán…).
// - Bộ responsive chạy trên cả ba trình duyệt × ba khổ màn hình (ma trận tương thích).
import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL || 'http://localhost:8080';
const responsive = /responsive\.spec\.js/;

export default defineConfig({
    testDir: './tests/e2e',
    outputDir: './tests/e2e/test-results',
    globalSetup: './tests/e2e/global-setup.js',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 60_000,
    expect: { timeout: 10_000 },
    reporter: [
        ['list'],
        ['html', { outputFolder: './tests/e2e/report', open: 'never' }],
        ['json', { outputFile: './tests/e2e/report/results.json' }],
    ],
    use: {
        baseURL,
        locale: 'vi-VN',
        timezoneId: 'Asia/Ho_Chi_Minh',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        viewport: { width: 1280, height: 800 },
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 } } },
        { name: 'firefox', testMatch: responsive, use: { ...devices['Desktop Firefox'], viewport: { width: 1280, height: 800 } } },
        { name: 'webkit', testMatch: responsive, use: { ...devices['Desktop Safari'], viewport: { width: 1280, height: 800 } } },
    ],
});
