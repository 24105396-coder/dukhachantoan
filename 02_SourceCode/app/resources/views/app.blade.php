<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <link rel="icon" href="/favicon.ico">
    {{-- Đặt theme trước khi render để tránh nháy màu --}}
    <script nonce="{{ Vite::cspNonce() }}">
        (function () {
            try {
                var t = localStorage.getItem('disanso-theme');
                if (t !== 'light' && t !== 'dark') {
                    t = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
                }
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
        // Font nạp không chặn hiển thị: app.js gọi hàm này khi trình duyệt rảnh sau lần vẽ đầu;
        // nếu đã từng tải (font nằm trong cache) thì nạp ngay để tránh nháy font
        (function () {
            var href = @json(Vite::asset('resources/css/fonts.css'));
            window.disansoLoadFonts = function () {
                if (window.disansoFontsLoaded) return;
                window.disansoFontsLoaded = true;
                var l = document.createElement('link');
                l.rel = 'stylesheet';
                l.href = href;
                document.head.appendChild(l);
                try { localStorage.setItem('disanso-fonts', '1'); } catch (e) {}
            };
            try { if (localStorage.getItem('disanso-fonts') === '1') window.disansoLoadFonts(); } catch (e) {}
        })();
    </script>
    {{-- Preload chunk của trang hiện tại để không phải chờ app.js mới biết cần tải gì --}}
    @php
        $pageComponent = $page['component'] ?? null;
        $pageFile = $pageComponent ? "resources/js/Pages/{$pageComponent}.vue" : null;
        $layoutFile = match (true) {
            $pageComponent === null => null,
            str_starts_with($pageComponent, 'Admin/') => 'resources/js/Layouts/AdminLayout.vue',
            str_starts_with($pageComponent, 'Auth/') => 'resources/js/Layouts/AuthLayout.vue',
            default => 'resources/js/Layouts/PublicLayout.vue',
        };
        // Chỉ preload file có mục riêng trong manifest (layout gộp vào chunk app-ui thì không có); chế độ dev nạp mọi file
        $viteManifest = Vite::isRunningHot() ? null : (json_decode((string) @file_get_contents(public_path('build/manifest.json')), true) ?: []);
        $pageAssets = array_values(array_filter(
            ['resources/css/app.css', 'resources/js/app.js', $layoutFile, $pageFile],
            fn ($f) => $f !== null && ($viteManifest === null ? is_file(base_path($f)) : isset($viteManifest[$f])),
        ));
    @endphp
    @vite($pageAssets)
    {{-- Mô tả mặc định phía máy chủ cho trình thu thập không chạy JS; PageMeta.vue (head-key="description") thay thế sau khi tải --}}
    @php
        $pageProps = $page['props'] ?? [];
        $metaDescription = collect(['site.summary', 'artifact.summary', 'figure.summary', 'route.description', 'tag.description'])
            ->map(fn ($k) => data_get($pageProps, $k))
            ->first(fn ($v) => is_string($v) && trim($v) !== '');
        $metaDescription = \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($metaDescription ?? __('meta.default_description')))), 160);
    @endphp
    <meta name="description" inertia="description" content="{{ $metaDescription }}">
    @inertiaHead
</head>
<body class="antialiased">
    @inertia
    @include('partials.academic-footer')
</body>
</html>
