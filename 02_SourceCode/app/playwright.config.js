// Cấu hình Playwright cho DuKhachAnToan (CSE703073 ĐT-15) – vai trò V5 kiểm thử.
// - Ba project chromium / firefox / webkit (desktop 1280×800); bộ responsive.spec.js tự chạy thêm hai khổ 375×812 và 768×1024
//   → ma trận 3 trình duyệt × 3 khổ.
// - Ca gắn thẻ @chromium thay đổi trạng thái dùng chung (giới hạn tần suất, hàng đợi, mô phỏng, phát hành cảnh báo) nên chỉ chạy
//   trên Chromium; mọi ca còn lại chạy trên cả ba trình duyệt.
// - Hệ thống phải đang chạy: docker compose up (web 8081). Đổi địa chỉ bằng env E2E_BASE_URL.
import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL || 'http://localhost:8081';
const reportDir = process.env.E2E_REPORT_DIR || './tests/e2e/report';
const desktop = { width: 1280, height: 800 };
const chromiumOnly = /@chromium/;

export default defineConfig({
    testDir: './tests/e2e',
    outputDir: './tests/e2e/test-results',
    globalSetup: './tests/e2e/global-setup.js',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 90_000,
    expect: { timeout: 10_000 },
    reporter: [
        ['list'],
        ['html', { outputFolder: `${reportDir}/html`, open: 'never' }],
        ['json', { outputFile: `${reportDir}/results.json` }],
    ],
    use: {
        baseURL,
        locale: 'vi-VN',
        timezoneId: 'Asia/Ho_Chi_Minh',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        viewport: desktop,
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'], viewport: desktop } },
        { name: 'firefox', grepInvert: chromiumOnly, use: { ...devices['Desktop Firefox'], viewport: desktop } },
        { name: 'webkit', grepInvert: chromiumOnly, use: { ...devices['Desktop Safari'], viewport: desktop } },
    ],
});
