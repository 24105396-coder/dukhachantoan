<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} – DuKhachAnToan</title>
    <style>
        :root { --bg:#DCF1E9; --surface:#F3FBF7; --text:#0C1A16; --muted:#16342C; --primary:#0A6357; --border:#AEE3D0; }
        @media (prefers-color-scheme: dark) { :root { --bg:#04100D; --surface:#0A1E19; --text:#D8ECE6; --muted:#AEE3D0; --primary:#55D3B7; --border:#0A6357; } }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:24px; padding:16px;
               background:var(--bg); color:var(--text); font-family:"Plus Jakarta Sans", system-ui, sans-serif; }
        main { max-width:520px; width:100%; background:var(--surface); border:1px solid var(--border); border-radius:28px; padding:32px; box-shadow:0 18px 40px -18px rgba(10,99,87,.35); text-align:center; }
        .code { font-family:"Baloo 2", "Plus Jakarta Sans", system-ui, sans-serif; font-size:64px; font-weight:700; color:var(--primary); margin:0; line-height:1; }
        p { line-height:1.6; }
        .muted { color:var(--muted); font-size:14px; }
        a { display:inline-block; margin-top:16px; padding:10px 20px; border-radius:999px; background:var(--primary); color:#fff; text-decoration:none; font-weight:600; }
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
