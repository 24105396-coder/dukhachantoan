<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" href="/favicon.ico">
    {{-- Đặt theme trước khi render để tránh nháy màu --}}
    <script nonce="{{ Vite::cspNonce() }}">
        // Có JS: ẩn chân trang tĩnh phía máy chủ ngay từ lần vẽ đầu (AppFooter của Vue thay thế) để nội dung
        // không chèn lên trên nó khi Vue gắn (BUG-5, CLS); không có JS thì footer tĩnh vẫn hiển thị
        document.documentElement.classList.add('js');
        (function () {
            try {
                var t = localStorage.getItem('dka-theme');
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
            window.dkaLoadFonts = function () {
                if (window.dkaFontsLoaded) return;
                window.dkaFontsLoaded = true;
                var l = document.createElement('link');
                l.rel = 'stylesheet';
                l.href = href;
                document.head.appendChild(l);
                try { localStorage.setItem('dka-fonts', '1'); } catch (e) {}
            };
            try { if (localStorage.getItem('dka-fonts') === '1') window.dkaLoadFonts(); } catch (e) {}
        })();
    </script>
    {{-- Preload chunk của trang hiện tại để không phải chờ app.js mới biết cần tải gì --}}
    @php
        $pageComponent = $page['component'] ?? null;
        $pageFile = $pageComponent ? "resources/js/Pages/{$pageComponent}.vue" : null;
        $layoutFile = match (true) {
            $pageComponent === null => null,
            str_starts_with($pageComponent, 'Auth/') => 'resources/js/Layouts/AuthLayout.vue',
            in_array($pageComponent, ['Trips/Shared', 'Trips/SharedCheckin'], true) => 'resources/js/Layouts/PublicLayout.vue',
            (bool) preg_match('#^(Admin|Account|Notifications|Incidents|Trips|Requests)/#', $pageComponent) => 'resources/js/Layouts/DashboardLayout.vue',
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
    {{-- Trang có bản đồ Leaflet (MapBase): mở sẵn kết nối tới máy chủ tile OSM; trang bản đồ tải song song chunk
         vendor-leaflet ngay khi phân tích HTML thay vì chờ Vue gắn mới import động (ảnh tile là phần tử LCP trên điện thoại) --}}
    @php
        $mapPages = ['Home', 'Map/Index', 'Trips/Show', 'Alerts/Show', 'Admin/Dashboard', 'Admin/Trips/Show', 'Admin/Alerts/Show', 'Admin/Zones/Form'];
        $hasMap = in_array($pageComponent, $mapPages, true);
        // Chỉ preload Leaflet ở trang bản đồ toàn màn (bản đồ nằm trên màn hình đầu); trang khác bản đồ ở dưới, không tranh băng thông
        $leafletChunk = $pageComponent === 'Map/Index' && $viteManifest
            ? collect($viteManifest)->first(fn ($e) => ($e['name'] ?? null) === 'vendor-leaflet' && str_ends_with($e['file'] ?? '', '.js'))
            : null;
    @endphp
    @if ($hasMap)
        @if (\App\Http\Middleware\HandleInertiaRequests::mapProvider() === 'google')
        <link rel="preconnect" href="https://mt0.google.com">
        <link rel="preconnect" href="https://mt1.google.com">
        @else
        <link rel="preconnect" href="https://tile.openstreetmap.org">
        @endif
    @endif
    @if ($pageComponent === 'Map/Index')
        {{-- BUG-11: ảnh nền cục bộ của bản đồ (MapBase priority) là phần tử LCP trên điện thoại thay cho ô tile OSM ngoài --}}
        <link rel="preload" as="image" href="/images/map-placeholder.svg" fetchpriority="high" type="image/svg+xml">
    @endif
    @if ($leafletChunk)
        <link rel="modulepreload" href="{{ asset('build/'.$leafletChunk['file']) }}" nonce="{{ Vite::cspNonce() }}">
        @foreach ($leafletChunk['css'] ?? [] as $leafletCss)
            <link rel="preload" as="style" href="{{ asset('build/'.$leafletCss) }}" nonce="{{ Vite::cspNonce() }}">
        @endforeach
    @endif
    {{-- Mô tả mặc định phía máy chủ cho trình thu thập không chạy JS; PageMeta.vue (head-key="description") thay thế sau khi tải --}}
    @php
        $pageProps = $page['props'] ?? [];
        $metaDescription = collect(['alert.description', 'guide.summary', 'trip.title'])
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
