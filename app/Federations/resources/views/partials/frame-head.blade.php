{{--
    The <head> of the federation dashboard's editor frames (athlete, official):
    light-only, the platform stylesheet, fonts and icons, and the form styles
    both share. Pass `title`. Alpine is loaded by the page itself, after its
    own state function is registered.
--}}
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <meta name="color-scheme" content="light">
    <meta name="darkreader-lock">
    <style>html { color-scheme: only light; }</style>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('images/flags/flag-icons.css') }}">
    @vite(['resources/css/app.css'])
    <style>
        [x-cloak] { display: none !important; }
        html, body { margin: 0; background: #fff; }
        body { font-family: Inter, system-ui, sans-serif; color: #1e2c4f; }
        .ae-sec { border: 1px solid #eef1f6; border-radius: 18px; padding: 16px; background: #fff; }
        .ae-h { display: flex; align-items: center; gap: 8px; font-size: .66rem; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: #8a6d20; margin: 0 0 12px; }
        .ae-h i { font-size: .8rem; }
        .ae-lbl { display: block; font-size: .78rem; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .ae-in { width: 100%; height: 44px; padding: 0 12px; border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; font: inherit; font-size: .875rem; color: #111827; outline: none; box-sizing: border-box; transition: border-color .15s, box-shadow .15s; }
        .ae-in:focus { border-color: transparent; box-shadow: 0 0 0 2px hsl(250 65% 65%); }
        .ae-in:disabled { background: #f7f8fb; color: #6b7280; }
        .ae-seg { display: inline-flex; padding: 3px; border-radius: 999px; background: #f1f3f7; }
        .ae-seg button { height: 30px; padding: 0 14px; border: 0; border-radius: 999px; background: transparent; font: inherit; font-size: .76rem; font-weight: 700; color: #6b7280; cursor: pointer; transition: background .15s, color .15s, box-shadow .15s; }
        .ae-seg button.is-on { background: #fff; color: #1e2c4f; box-shadow: 0 1px 3px rgba(30,44,79,.14); }
        .ae-step { width: 44px; height: 44px; flex: none; border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; font-size: 1.1rem; color: #1e2c4f; cursor: pointer; display: grid; place-items: center; }
        .ae-step:hover { border-color: #caa14f; background: #fdf6e6; }
        .ae-photo { position: relative; width: 108px; height: 144px; flex: none; border-radius: 16px; overflow: hidden; background: #eef1f6; display: grid; place-items: center; cursor: pointer; border: 0; padding: 0; }
        .ae-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .ae-photo .ae-cam { position: absolute; right: 6px; bottom: 6px; width: 32px; height: 32px; border-radius: 999px; background: #1e2c4f; color: #edcd85; display: grid; place-items: center; box-shadow: 0 4px 12px rgba(30,44,79,.3); }
        .ae-save { height: 44px; padding: 0 24px; border: 0; border-radius: 12px; font: inherit; font-size: .86rem; font-weight: 800; color: #1e2c4f; background: linear-gradient(100deg,#edcd85,#d0a854); box-shadow: 0 6px 16px rgba(202,161,79,.3); cursor: pointer; transition: transform .15s, opacity .15s; }
        .ae-save:hover { transform: translateY(-1px); }
        .ae-save:disabled { opacity: .55; cursor: wait; transform: none; }
        .ae-ghost { height: 44px; padding: 0 18px; border: 1px solid #d5dae3; border-radius: 12px; background: #fff; font: inherit; font-size: .82rem; font-weight: 600; color: #5d6880; cursor: pointer; }
        .ae-skel { border-radius: 12px; background: linear-gradient(90deg,#f1f3f7 0%,#f8f9fb 50%,#f1f3f7 100%); background-size: 200% 100%; animation: ae-sh 1.2s infinite linear; }
        @keyframes ae-sh { to { background-position: -200% 0; } }
        @media (prefers-reduced-motion: reduce) { .ae-skel { animation: none; } }
        fieldset { border: 0; margin: 0; padding: 0; min-width: 0; }
        /* The window is one fixed card: the band and the footer stay put and only
           the middle — where the form changes — scrolls. */
        html, body { height: 100%; overflow: hidden; }
        .ae-shell { height: 100%; }
        .ae-scroll { overflow-y: auto; overscroll-behavior: contain; scrollbar-gutter: stable; }
        /* Slim gold scrollbars, in the dashboard's colours, on every scrolling
           list in the window (the form, the member results, the dropdowns). */
        * { scrollbar-width: thin; scrollbar-color: rgba(202,161,79,.55) transparent; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(202,161,79,.45); border-radius: 999px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(202,161,79,.8); }
        ::-webkit-scrollbar-corner { background: transparent; }

        /* The photo cropper as a desktop card (Zoom and Rotation sliders), in the
           dashboard's navy and gold rather than the platform's purple and green.
           Scoped to .ae-crop, so the shared cropper elsewhere is untouched. */
        .ae-crop { overflow: hidden; }
        .ae-crop > div:first-child { background: linear-gradient(150deg, #1e2c4f, #1e2c4fb0), #fff !important; border-radius: 0 !important; padding: 14px 18px !important; }
        .ae-crop > div:last-child { background: #fff !important; padding: 14px 18px !important; gap: 10px !important; }
        .ae-crop .takeone-canvas { border-radius: 14px; overflow: hidden; background: #0b132b; }
        .ae-crop .custom-slider-label { font-size: .66rem; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: #8a6d20; margin-bottom: 8px; }
        .ae-crop input[type=range] { -webkit-appearance: none; appearance: none; width: 100%; height: 6px; border-radius: 999px; background: #eef1f6; outline: none; accent-color: #caa14f; cursor: pointer; }
        .ae-crop input[type=range]::-webkit-slider-thumb { -webkit-appearance: none; width: 18px; height: 18px; border-radius: 50%; background: #caa14f; border: 3px solid #fff; box-shadow: 0 1px 4px rgba(30,44,79,.35); }
        .ae-crop input[type=range]::-moz-range-thumb { width: 14px; height: 14px; border-radius: 50%; background: #caa14f; border: 3px solid #fff; box-shadow: 0 1px 4px rgba(30,44,79,.35); }
        .ae-crop input[type=range]:focus-visible { box-shadow: 0 0 0 3px rgba(216,178,95,.35); }
        .ae-crop .btn { border-radius: 12px !important; font-family: inherit; font-size: .84rem !important; height: 44px; padding-top: 0 !important; padding-bottom: 0 !important; }
        .ae-crop .btn-success { background: linear-gradient(100deg,#edcd85,#d0a854) !important; border: 0 !important; color: #1e2c4f !important; font-weight: 800 !important; box-shadow: 0 6px 16px rgba(202,161,79,.3); }
        .ae-crop .btn-outline-secondary { background: #fff !important; border: 1px solid #d5dae3 !important; color: #5d6880 !important; font-weight: 600 !important; }
        /* Selection cards: a short known set of options, as a radio group. */
        .ae-card { width: 100%; display: flex; align-items: flex-start; gap: 10px; padding: 11px 12px; border: 1.5px solid #e5e7eb; border-radius: 14px; background: #fff; text-align: start; font: inherit; cursor: pointer; transition: border-color .15s, background .15s; }
        .ae-card:hover { border-color: #d5dae3; }
        .ae-card.is-on { border-color: #caa14f; background: #fdf6e6; }
        .ae-card .ae-dot { width: 18px; height: 18px; flex: none; border-radius: 999px; border: 1.5px solid #c3cad6; margin-top: 1px; box-sizing: border-box; transition: border .15s; }
        .ae-card.is-on .ae-dot { border: 5px solid #caa14f; }
        .ae-card .ae-box { width: 20px; height: 20px; flex: none; border-radius: 6px; border: 1.5px solid #c3cad6; margin-top: 1px; box-sizing: border-box; display: grid; place-items: center; color: transparent; font-size: .8rem; transition: background .15s, border-color .15s, color .15s; }
        .ae-card.is-on .ae-box { background: #caa14f; border-color: #caa14f; color: #fff; }
        .ae-card b { display: block; font-size: .84rem; font-weight: 700; color: #1e2c4f; }
        .ae-card small { display: block; font-size: .72rem; color: #6b7280; margin-top: 2px; line-height: 1.35; }
        .ae-danger { height: 44px; padding: 0 16px; border: 1px solid #e9b8b1; border-radius: 12px; background: #fff; font: inherit; font-size: .8rem; font-weight: 700; color: #c0392b; cursor: pointer; }
        .ae-danger.is-armed { background: #c0392b; border-color: #c0392b; color: #fff; }
    </style>
    @stack('styles')
