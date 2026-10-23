<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header bảo mật: chống clickjacking, MIME sniffing, giới hạn nguồn tài nguyên (CSP).
 * Tham số "api" (SecurityHeaders::class.':api') cho /api/* và /healthz: cùng header cơ bản, CSP tối giản cho JSON.
 */
class SecurityHeaders
{
    public const MODE_API = 'api';

    /** Tile OpenStreetMap thật (MapBase.vue); Google chỉ thêm khi MAP_PROVIDER=google. */
    private const OSM_TILES = 'https://tile.openstreetmap.org https://*.tile.openstreetmap.org';

    private const GOOGLE_TILES = 'https://mt0.google.com https://mt1.google.com https://mt2.google.com https://mt3.google.com';

    private const JSON_CSP = "default-src 'none'; frame-ancestors 'none'; base-uri 'none'";

    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(self), payment=()');
        $headers->set('Content-Security-Policy', $mode === self::MODE_API ? self::JSON_CSP : $this->csp());

        if ($request->secure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function csp(): string
    {
        $nonce = Vite::cspNonce();
        $dev = app()->isLocal() ? ' http://localhost:5173 ws://localhost:5173 http://127.0.0.1:5173 ws://127.0.0.1:5173' : '';

        return implode('; ', [
            "default-src 'self'",
            // Font (@fontsource) tự host → không cần CDN
            // WebAssembly (giải mã GLB), không mở eval JS
            "script-src 'self' 'nonce-{$nonce}'{$dev}",
            "style-src 'self' 'unsafe-inline'{$dev}",
            "font-src 'self' data:",
            // Ảnh chỉ từ máy chủ + tile bản đồ nền (không mở "https:" chung)
            "img-src 'self' data: blob: ".$this->tileSources(),
            "media-src 'self' data: blob:",
            "connect-src 'self'{$dev}",
            "worker-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }

    private function tileSources(): string
    {
        return HandleInertiaRequests::mapProvider() === 'google' ? self::OSM_TILES.' '.self::GOOGLE_TILES : self::OSM_TILES;
    }
}
