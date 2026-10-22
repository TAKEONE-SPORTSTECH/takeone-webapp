{{-- The <head> both verification documents share. Standalone (no app shell): the page is public. --}}
<meta charset="utf-8">
{{-- LIGHT by design; say so, or force-dark and Dark Reader invert it (see layouts/tailwind). --}}
<meta name="color-scheme" content="light">
<meta name="darkreader-lock">
<style>html { color-scheme: only light; }</style>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>{{ __('cert::messages.verify_page_title') }} · {{ config('app.name', 'TakeOne') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
@vite(['resources/css/app.css'])
{{-- The sections beyond the paper (partials/story) — own rules, as the shared bundle is prebuilt. --}}
<style>
    .cv-photo { position: relative; width: 48px; height: 64px; flex: 0 0 48px; border-radius: 12px; overflow: hidden; background: #ede9fe; }
    .cv-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .cv-photo-badge { position: absolute; right: 3px; bottom: 3px; width: 18px; height: 18px; border-radius: 99px; display: grid; place-items: center; font-size: 11px; color: #fff; background: #059669; box-shadow: 0 0 0 2px #fff; }
    .cv-photo-badge.is-revoked { background: #e11d48; }
    .cv-clubline { display: flex; align-items: center; gap: 6px; }
    .cv-clubline img, .cv-crest { width: 18px; height: 18px; object-fit: contain; flex: 0 0 auto; }
    .cv-stack { display: flex; flex-direction: column; gap: 12px; }
    .cv-card { background: #fff; border: 1px solid #f1f5f9; border-radius: 16px; box-shadow: 0 1px 2px rgba(15,23,42,.05); padding: 16px; }
    .cv-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; }
    .cv-head h2 { margin: 0; font-size: 15px; font-weight: 800; color: #0f172a; }
    .cv-record { font-size: 12px; font-weight: 700; color: #475569; background: #f1f5f9; border-radius: 99px; padding: 3px 10px; }
    .cv-medal { display: flex; align-items: center; gap: 14px; border-radius: 16px; padding: 14px 16px; color: #fff; box-shadow: 0 6px 20px rgba(15,23,42,.12); }
    .cv-medal-1 { background: linear-gradient(135deg, #b8892b, #e9c46a); }
    .cv-medal-2 { background: linear-gradient(135deg, #6b7280, #cbd5e1); }
    .cv-medal-3 { background: linear-gradient(135deg, #8a4f22, #d29a6a); }
    .cv-medal-text { display: flex; flex-direction: column; min-width: 0; }
    .cv-medal-text b { font-size: 20px; font-weight: 900; line-height: 1.1; }
    .cv-medal-text span { font-size: 13px; opacity: .92; margin-top: 2px; overflow-wrap: anywhere; }
    .cv-event { display: flex; align-items: center; gap: 12px; }
    .cv-logo { width: 44px; height: 44px; flex: 0 0 44px; }
    .cv-logo img { width: 100%; height: 100%; object-fit: contain; }
    .cv-event-text { display: flex; flex-direction: column; min-width: 0; }
    .cv-event-text b { font-size: 15px; font-weight: 800; color: #0f172a; overflow-wrap: anywhere; }
    .cv-event-text span { font-size: 12px; color: #64748b; }
    .cv-facts { margin: 12px 0 0; display: flex; flex-direction: column; gap: 8px; }
    .cv-facts div { display: flex; gap: 10px; align-items: flex-start; }
    .cv-facts dt { width: 18px; color: #7c6bf5; text-align: center; flex: 0 0 18px; }
    .cv-facts dd { margin: 0; font-size: 13px; font-weight: 600; color: #1e293b; overflow-wrap: anywhere; }
    .cv-bouts { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
    .cv-bout { display: flex; align-items: center; gap: 10px; padding: 10px; border-radius: 12px; background: #f8fafc; border-inline-start: 4px solid #cbd5e1; }
    .cv-bout.is-won { border-color: #059669; }
    .cv-bout.is-lost { border-color: #e11d48; }
    .cv-result { flex: 0 0 auto; min-width: 34px; text-align: center; font-size: 11px; font-weight: 900; letter-spacing: .04em; text-transform: uppercase; border-radius: 8px; padding: 4px 6px; background: #e2e8f0; color: #475569; }
    .is-won .cv-result { background: #d1fae5; color: #047857; }
    .is-lost .cv-result { background: #ffe4e6; color: #be123c; }
    .cv-bout.is-open { border-color: #f59e0b; }
    .is-open .cv-result { background: #fef3c7; color: #b45309; }
    .cv-face { width: 30px; height: 40px; flex: 0 0 30px; border-radius: 8px; overflow: hidden; background: #ede9fe; }
    .cv-face img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .cv-bout-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
    .cv-bout-text b { font-size: 13px; font-weight: 700; color: #0f172a; overflow-wrap: anywhere; }
    .cv-bout-text span { font-size: 11px; color: #64748b; display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
    .cv-score { font-size: 13px; font-weight: 800; color: #334155; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .cv-play { width: 34px; height: 34px; flex: 0 0 34px; border-radius: 99px; display: grid; place-items: center; background: #7c6bf5; color: #fff; font-size: 16px; text-decoration: none; }
    .cv-hint { margin: 10px 0 0; font-size: 11px; color: #64748b; }
    .cv-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .cv-btn { display: inline-flex; align-items: center; gap: 8px; height: 42px; padding: 0 14px; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; color: #0f172a; font: 600 13px Inter, system-ui, sans-serif; text-decoration: none; cursor: pointer; }
    .cv-btn:hover { background: #f8fafc; }
    .cv-btn.is-primary { background: #7c6bf5; border-color: #7c6bf5; color: #fff; }
    .cv-btn.is-primary:hover { background: #6d5bd0; }
    .cv-btn.is-quiet { border-color: transparent; background: transparent; color: #475569; }
    .cv-seal { display: flex; align-items: center; gap: 8px; margin: 2px 0 0; font-size: 12px; font-weight: 600; color: #047857; }
    .cv-seal.is-revoked { color: #be123c; }
    .bracket-icon { display: inline-block; transform: rotate(90deg); }
    .cv-link { color: inherit; text-decoration: none; border-radius: 4px; }
    .cv-link:hover { color: #6d5bd0; text-decoration: underline; text-underline-offset: 3px; }
    a.cv-link, a.cv-event, .cv-facts a, a.cv-medal, a.cv-result, a.cv-score, a.cv-face { cursor: pointer; }
    .cv-go { color: #94a3b8; font-size: 11px; margin-inline-start: auto; }
    [dir="rtl"] .bi-chevron-right.cv-go, [dir="rtl"] .cv-go-light { transform: scaleX(-1); }
    .cv-go-light { margin-inline-start: auto; opacity: .85; }
    a.cv-medal { text-decoration: none; transition: transform .12s; }
    a.cv-medal:hover { transform: translateY(-1px); }
    a.cv-event { text-decoration: none; border-radius: 12px; margin: -6px; padding: 6px; transition: background .12s; }
    a.cv-event:hover, .cv-facts a:hover { background: #f8fafc; }
    .cv-sublink { display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; font-size: 12px; font-weight: 600; color: #6d5bd0; text-decoration: none; }
    .cv-facts a { display: flex; gap: 10px; align-items: center; text-decoration: none; border-radius: 10px; margin: 0 -6px; padding: 4px 6px; }
    a.cv-result, a.cv-score { text-decoration: none; }
    a.cv-face { display: block; }
    .cv-bout-text span { gap: 8px; }
    .cv-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    .cv-chip { display: inline-flex; align-items: center; gap: 5px; height: 26px; padding: 0 10px; border-radius: 99px; background: #f1f5f9; color: #334155; font: 700 12px Inter, system-ui, sans-serif; text-decoration: none; }
    .cv-chip:hover { background: #ede9fe; color: #6d5bd0; }
    .cv-ok { color: #059669; }
    .cv-copy { border: 0; background: none; padding: 0; font: inherit; color: inherit; cursor: pointer; }
    .cv-copy:hover { color: #6d5bd0; }
    /* The team (partials/story) — everyone a club brought, medals first. */
    .cv-team-staff { display: flex; align-items: center; gap: 6px; margin: -2px 0 10px; font-size: 11.5px; font-weight: 600; color: #8a6d20; }
    .cv-tally { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-bottom: 12px; }
    .cv-tally div { border-radius: 12px; padding: 9px 8px; text-align: center; display: flex; flex-direction: column; }
    .cv-tally b { font-size: 21px; font-weight: 900; line-height: 1.1; }
    .cv-tally span { font-size: 11px; font-weight: 700; }
    .cv-tally-1 { background: linear-gradient(135deg, #fbf3dc, #f5e3b0); color: #7c5a12; }
    .cv-tally-2 { background: linear-gradient(135deg, #f1f3f6, #dde2e8); color: #475569; }
    .cv-tally-3 { background: linear-gradient(135deg, #f8ece2, #ecd2bd); color: #7a3f17; }
    .cv-team { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
    .cv-athlete { display: flex; align-items: center; gap: 10px; padding: 10px; border-radius: 14px; background: #f8fafc; border: 1px solid #eef1f6; }
    .cv-athlete.is-medal-1 { border-color: #f1dfa8; }
    .cv-athlete.is-medal-2 { border-color: #e2e8f0; }
    .cv-athlete.is-medal-3 { border-color: #ecd2bd; }
    .cv-athlete-medal { width: 28px; flex: 0 0 28px; display: grid; place-items: center; }
    .cv-nomedal { width: 22px; height: 22px; border-radius: 99px; background: #f1f5f9; box-shadow: inset 0 0 0 2px #cbd5e1; }
    .cv-athlete-face { width: 36px; height: 48px; flex: 0 0 36px; border-radius: 9px; }
    .cv-athlete-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; align-items: flex-start; }
    .cv-athlete-name { display: flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 700; color: #0f172a; line-height: 1.25; overflow-wrap: anywhere; }
    .cv-flag { width: 16px; height: 12px; flex: 0 0 16px; border-radius: 2px; box-shadow: 0 0 0 1px #d5dae3; object-fit: cover; }
    .cv-athlete-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 10px; font-size: 12px; color: #64748b; }
    .cv-athlete-kg { font-weight: 700; color: #0f172a; font-variant-numeric: tabular-nums; display: inline-flex; align-items: center; gap: 4px; }
    .cv-division { display: inline-flex; align-items: center; gap: 6px; max-width: 100%; padding: 4px 10px; border-radius: 99px; background: #ede9fe; color: #5b4bc4; font-size: 11.5px; font-weight: 700; text-decoration: none; line-height: 1.3; }
    .cv-division span { overflow-wrap: anywhere; }
    a.cv-division:hover { background: #ddd6fe; }
    .cv-division .cv-go-light { margin-inline-start: 0; font-size: 9px; }
</style>
