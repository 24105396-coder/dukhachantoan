import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

// Tách thư viện bên thứ ba thành chunk riêng để cache lâu dài và chỉ nạp khi trang cần
const VENDOR_GROUPS = [
    ['vendor-vue', ['/node_modules/vue/', '/node_modules/@vue/']],
    ['vendor-inertia', ['/node_modules/@inertiajs/', '/node_modules/axios/', '/node_modules/qs/', '/node_modules/es-toolkit/', '/node_modules/nprogress/']],
    ['vendor-leaflet', ['/node_modules/leaflet/']],
    ['vendor-chart', ['/node_modules/chart.js/', '/node_modules/@kurkle/']],
    ['vendor-icons', ['/node_modules/lucide-vue-next/']],
];

// Component/composable dùng chung của khu công khai gộp một chunk: Apache phục vụ HTTP/1.1 (6 kết nối),
// hàng chục chunk 1–2 KB trên đường găng làm chậm FCP/LCP trên mạng di động nhiều hơn là tiết kiệm được
const SHARED_UI = ['/resources/js/Components/', '/resources/js/Composables/', '/resources/js/Utils/', '/resources/js/Layouts/PublicLayout.vue'];
const ADMIN_UI = '/resources/js/Components/Admin/';

function vendorChunk(id) {
    if (!id.includes('/node_modules/')) {
        if (id.includes(ADMIN_UI)) return undefined;
        return SHARED_UI.some((p) => id.includes(p)) ? 'app-ui' : undefined;
    }
    const group = VENDOR_GROUPS.find(([, paths]) => paths.some((p) => id.includes(p)));
    return group ? group[0] : undefined;
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/fonts.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: { base: null, includeAbsolute: false },
            },
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    build: {
        // Chunk lớn nhất là Leaflet/Chart.js (~200 KB), đều nạp động theo trang
        chunkSizeWarningLimit: 500,
        rollupOptions: {
            output: {
                manualChunks: vendorChunk,
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
