<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — QR</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --primary: hsl(250 65% 65%); }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', system-ui, sans-serif; background: #f3f4f6; color: #111827;
               display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 24px; }
        .poster { width: 420px; max-width: 100%; background: #fff; border-radius: 28px; padding: 40px 36px;
                  box-shadow: 0 20px 60px rgba(0,0,0,.12); text-align: center; }
        .brand { display: inline-flex; align-items: center; gap: 8px; font-weight: 800; color: var(--primary);
                 font-size: 13px; letter-spacing: .12em; text-transform: uppercase; }
        .logo { width: 76px; height: 76px; border-radius: 20px; object-fit: cover; margin: 18px auto 0; display: block;
                border: 1px solid #eee; }
        .logo-fallback { width: 76px; height: 76px; border-radius: 20px; margin: 18px auto 0;
                         display: grid; place-items: center; background: hsl(250 60% 92%); color: var(--primary); font-size: 30px; }
        h1 { font-size: 26px; font-weight: 900; margin: 16px 0 4px; line-height: 1.15; }
        .sub { color: #6b7280; font-size: 14px; margin: 0 0 4px; }
        .kicker { display:inline-block; margin-top:14px; background: hsl(250 60% 95%); color: var(--primary);
                  font-weight: 700; font-size: 12px; padding: 6px 14px; border-radius: 999px; }
        .qr { margin: 22px auto 10px; width: 280px; height: 280px; }
        .qr svg { width: 100%; height: 100%; display: block; }
        .cta { font-weight: 700; font-size: 16px; margin: 8px 0 2px; }
        .hint { color: #6b7280; font-size: 13px; margin: 2px 0 0; }
        .url { color: var(--primary); font-size: 11px; word-break: break-all; margin-top: 12px; }
        .actions { margin-top: 22px; text-align: center; }
        .btn { display: inline-flex; align-items: center; gap: 8px; background: var(--primary); color: #fff;
               border: 0; border-radius: 12px; padding: 12px 20px; font-weight: 600; font-size: 14px; cursor: pointer; font-family: inherit; }
        .foot { margin-top: 18px; color: #9ca3af; font-size: 11px; }
        @media print {
            body { background: #fff; padding: 0; }
            .poster { box-shadow: none; border-radius: 0; }
            .actions { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="poster">
        <span class="brand"><i class="bi bi-qr-code-scan"></i> {{ $brand ?? 'TAKEONE' }}</span>

        @if(!empty($logo))
            <img class="logo" src="{{ $logo }}" alt="">
        @else
            <div class="logo-fallback"><i class="bi {{ $logoIcon ?? 'bi-buildings' }}"></i></div>
        @endif

        <h1>{{ $title }}</h1>
        @if(!empty($subtitle))<p class="sub">{{ $subtitle }}</p>@endif
        @if(!empty($kicker))<span class="kicker">{{ $kicker }}</span>@endif

        <div class="qr">{!! $svg !!}</div>

        <p class="cta">{{ $cta ?? __('shared.qr_poster_scan_to_open') }}</p>
        @if(!empty($hint))<p class="hint">{{ $hint }}</p>@endif
        <p class="url">{{ $url }}</p>

        <div class="actions">
            <button class="btn" onclick="window.print()"><i class="bi bi-printer"></i> {{ __('shared.qr_poster_print') }}</button>
        </div>
        <p class="foot">{{ __('shared.qr_poster_camera_hint') }}</p>
    </div>
</body>
</html>
