<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} – DuKhachAnToan</title>
    <style>
        :root { --bg:#F7F9F8; --surface:#FFFFFF; --text:#111A17; --muted:#4A5753; --primary:#0A6357; --border:#E2E7E5; }
        @media (prefers-color-scheme: dark) { :root { --bg:#0F1514; --surface:#171F1D; --text:#E4ECE9; --muted:#A3B3AE; --primary:#55D3B7; --border:rgba(255,255,255,.08); } }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:24px; padding:16px;
               background:var(--bg); color:var(--text); font-family:"Plus Jakarta Sans", system-ui, sans-serif; }
        main { max-width:520px; width:100%; background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:32px; box-shadow:0 1px 2px rgba(16,24,22,.05); text-align:center; }
        .code { font-family:"Plus Jakarta Sans", system-ui, sans-serif; font-size:64px; font-weight:800; letter-spacing:-0.02em; color:var(--primary); margin:0; line-height:1; }
        p { line-height:1.6; }
        .muted { color:var(--muted); font-size:14px; }
        a { display:inline-block; margin-top:16px; padding:10px 20px; border-radius:10px; background:var(--primary); color:#fff; text-decoration:none; font-weight:600; }
        a:focus-visible { outline:3px solid var(--text); outline-offset:2px; }
        @media (prefers-color-scheme: dark) { a { color:#04100D; } }
        .ssr-academic-footer { max-width:520px; text-align:center; font-size:13px; line-height:1.6; color:var(--muted); }
        .ssr-academic-footer p { margin:0; }
    </style>
</head>
<body>
    <main>
        <p class="code">{{ $code }}</p>
        @if (app()->getLocale() === 'en')
            <p>{{ $messageEn }}</p>
            <p class="muted">{{ $messageVi }}</p>
            <a href="/en">Back to home</a>
        @else
            <p>{{ $messageVi }}</p>
            <p class="muted">{{ $messageEn }}</p>
            <a href="/vi">Về trang chủ</a>
        @endif
    </main>
    @include('partials.academic-footer')
</body>
</html>
