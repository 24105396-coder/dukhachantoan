<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} – DiSanSo</title>
    <style>
        :root { --bg:#FBF6EC; --surface:#FFFFFF; --text:#2B1D14; --muted:#6B5546; --primary:#9B2C2C; --border:#E6D9C3; }
        @media (prefers-color-scheme: dark) { :root { --bg:#141210; --surface:#1F1B17; --text:#EDE6D6; --muted:#B8AE9C; --primary:#E07A5F; --border:#3A332C; } }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:24px; padding:16px;
               background:var(--bg); color:var(--text); font-family:"Be Vietnam Pro", system-ui, sans-serif; }
        main { max-width:520px; width:100%; background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:32px; text-align:center; }
        .code { font-family:"Playfair Display", Georgia, serif; font-size:64px; font-weight:700; color:var(--primary); margin:0; line-height:1; }
        p { line-height:1.6; }
        .muted { color:var(--muted); font-size:14px; }
        a { display:inline-block; margin-top:16px; padding:10px 20px; border-radius:999px; background:var(--primary); color:#fff; text-decoration:none; font-weight:600; }
        a:focus-visible { outline:3px solid var(--text); outline-offset:2px; }
        @media (prefers-color-scheme: dark) { a { color:#141210; } }
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
