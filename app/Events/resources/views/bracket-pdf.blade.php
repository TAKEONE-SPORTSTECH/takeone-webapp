{{--
    The draw, as pages to print — App\Events\Support\BracketPdf feeds this to
    headless Chromium and returns what it prints.

    ── How it stays identical to the screen ─────────────────────────────────
    It does not redraw anything. The REAL board (<x-tournament-bracket>, the
    same component and runtime the draw stage uses) is mounted off-page and
    shown one division at a time; after each render its canvas — cards,
    connectors, podium, exactly as laid out — is cloned onto that division's
    sheet and scaled to fit. The stylesheet is the board's own, so the clone is
    styled by the same rules as the original.

    The data is a `data:` URL built for the requesting user (see BracketPdf),
    so nothing here talks to the server. This page is never served over HTTP.

    Inline styles throughout: it is a print document, and the Tailwind bundle
    is prebuilt.
--}}
@php
    $c = \App\Support\Palette::safe($color);
    $navy = '#16213d';
    $goldInk = '#a8862f';   // gold dark enough to read as type on white
    $rtl = app()->getLocale() === 'ar';
    $teamPages = $cover['team'] ?? [];
    $clubPages = array_values(array_filter($cover['clubs'] ?? []));
    $offset = $cover ? 2 + count($clubPages) + count($teamPages) : 0;
    $total = count($pages) + $offset;
    // Divisions that have a sheet in THIS document — only those are links.
    $sheetIds = collect($pages)->pluck('id')->flip();
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    {{-- The same two sheets the screen loads from outside (grandfathered in
         CLAUDE.md → Self-Host Everything), so the type and the icons are the
         screen's own. Everything else is read from this box's disk. --}}
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,600;0,700;0,800;0,900;1,800;1,900&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ $flagCss }}">
    @if($appCss)
        <link rel="stylesheet" href="{{ $appCss }}">
    @endif
    <style>
        @page land { size: A4 landscape; margin: 0; }
        @page port { size: A4 portrait;  margin: 0; }

        html, body { margin: 0; padding: 0; background: #fff; }
        body { font-family: 'Inter', system-ui, 'Noto Sans Arabic', sans-serif; color: #1e2c4f;
               -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        .pdf-page { position: relative; overflow: hidden; display: flex; flex-direction: column;
                    break-after: page; page-break-after: always; }
        .pdf-page:last-child { break-after: auto; page-break-after: auto; }
        .pdf-page.land { page: land; width: 297mm; height: 210mm; }
        .pdf-page.port { page: port; width: 210mm; height: 297mm; }

        /* PRINT, not web — and "Fight night" (owner's pick, 2026-10-07): the
           bracket board's own navy and gold, a condensed sporting display face,
           a gold diagonal edge under every band. No rounded boxes, pills or
           shadows; square edges and hairlines everywhere else. */
        .cond { font-family: 'Barlow Condensed', 'Inter', sans-serif; }
        .band { position: relative; overflow: hidden; color: #fff; background: {{ $navy }}; }
        /* Inside the band's own box: anything hanging outside it makes Chromium
           shrink every page to fit, even though overflow hides it. */
        .band .stripes { position: absolute; right: 0; top: 0; bottom: 0; width: 90mm; opacity: .16;
                         background: repeating-linear-gradient(115deg, {{ $c }} 0 3mm, transparent 3mm 9mm); }
        .band .slab { position: absolute; left: 0; right: 0; bottom: 0; background: {{ $c }}; }
        .eye { position: relative; font-size: 7.5pt; font-weight: 800; letter-spacing: .28em; text-transform: uppercase; color: {{ $c }}; }
        .fit { display: block; white-space: nowrap; }

        /* The organiser's logo — a bare transparent image at the band's leading
           edge (Design Rule #5: never on a tile). */
        .hlogo { position: relative; flex: 0 0 auto; display: block; }
        /* Drawn from ONE copy of the logo (a background on this class), not an
           <img> per page: inlined on 33 headers it was 4 MB of the page. */
        @if($hostLogo)
        .hlogo { background: url("{{ $hostLogo }}") center / contain no-repeat; }
        @endif
        .pdf-head .hlogo { width: 19mm; height: 19mm; align-self: center; margin-inline-end: -3mm; }
        .page-band .hlogo { width: 15mm; height: 15mm; align-self: center; margin-inline-end: -3mm; }
        .page-band h1 { white-space: nowrap; }
        /* On the cover it stands bottom-right, opposite the figures, so the
           title block keeps its place. */
        .cover-band .hlogo { position: absolute; right: 15mm; bottom: 36mm; width: 30mm; height: 30mm; }

        /* A finished competition says so before anything else on the cover. */
        .done-stamp { position: relative; display: inline-flex; align-items: center; gap: 2mm; margin-bottom: 4mm; padding: 1.6mm 4mm 1.4mm;
                      background: {{ $c }}; color: {{ $navy }}; font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900;
                      font-size: 12pt; letter-spacing: .16em; text-transform: uppercase; clip-path: polygon(2.5mm 0, 100% 0, calc(100% - 2.5mm) 100%, 0 100%); }

        /* Teams — one block per club: crest, name, size, medals; then its athletes. */
        .club { break-inside: avoid; border: .35mm solid #cfd5e0; border-radius: 1.5mm; overflow: hidden; margin-top: 5mm; }
        .club-h { display: flex; align-items: center; gap: 4mm; padding: 2.6mm 4mm; background: {{ $navy }}; color: #fff; }
        .club-h .rk { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-style: italic; font-size: 20pt; line-height: 1; color: {{ $c }}; min-width: 7mm; }
        .club-h .lg { width: 13mm; height: 13mm; flex: 0 0 auto; }
        .club-h .lg img { width: 100%; height: 100%; object-fit: contain; display: block; }
        .club-h .lg.none { display: grid; place-items: center; color: {{ $c }}; font-size: 15pt; }
        .club-h .nm { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-style: italic; text-transform: uppercase;
                      font-size: 17pt; line-height: 1; letter-spacing: .02em; }
        .club-h .nm small { display: block; font-family: 'Inter', sans-serif; font-style: normal; font-weight: 700; font-size: 7pt;
                            letter-spacing: .16em; color: {{ $c }}; margin-top: 1.2mm; }
        .club-h .md { margin-inline-start: auto; display: flex; gap: 3.5mm; align-items: center; }
        .club-h .md span { display: inline-flex; align-items: center; gap: 1.2mm; font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 800; font-size: 14pt; }
        .club-h .md span.zero { opacity: .35; }
        .club-p { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0 5mm; padding: 1.5mm 4mm 2mm; }
        .ath { display: flex; gap: 1.5mm; align-items: flex-start; padding: 1.4mm 0 1.2mm; border-bottom: .25mm solid #e3e6ee; min-width: 0; }
        .ath .t { min-width: 0; flex: 1; }
        .ath .n { font-size: 8.4pt; font-weight: 700; color: {{ $navy }}; line-height: 1.25; overflow-wrap: anywhere; }
        .ath .d { font-size: 6.6pt; color: #6b7590; line-height: 1.3; margin-top: .3mm; }
        .ath .m { display: flex; gap: .6mm; flex: 0 0 auto; padding-top: .3mm; }

        /* Bracket sheet head */
        .pdf-head { flex: 0 0 auto; padding: 7mm 10mm 9mm; display: flex; align-items: flex-end; gap: 8mm; }
        .pdf-head .stripes { width: 70mm; }
        .pdf-head .slab { height: 4mm; clip-path: polygon(0 60%, 100% 0, 100% 100%, 0 100%); }
        .pdf-title { position: relative; font-family: 'Barlow Condensed', 'Inter', sans-serif; font-style: italic; font-weight: 900;
                     text-transform: uppercase; font-size: 30pt; line-height: .95; margin-top: 2mm; }
        .pdf-sub { position: relative; font-size: 8.5pt; color: #c9d0e2; margin-top: 1.8mm; }
        .pdf-count { position: relative; margin-inline-start: auto; text-align: end; font-family: 'Barlow Condensed', 'Inter', sans-serif; text-transform: uppercase; }
        .pdf-count b { display: block; font-size: 38pt; font-weight: 900; line-height: .85; }
        .pdf-count span { font-size: 9pt; font-weight: 700; letter-spacing: .18em; color: {{ $c }}; }

        /* Page 1 */
        .cover-band { height: 168mm; padding: 16mm 15mm 0; }
        .cover-band .slab { height: 30mm; clip-path: polygon(0 45%, 100% 0, 100% 100%, 0 100%); }
        .cover-title { position: relative; max-width: 128mm; margin-top: 6mm; font-family: 'Barlow Condensed', 'Inter', sans-serif;
                       font-weight: 900; font-style: italic; text-transform: uppercase; line-height: .88; }
        .cover-title .l1 { font-size: 66pt; color: #fff; }
        .cover-title .l2 { font-size: 66pt; color: {{ $c }}; }
        .cover-title .l3 { font-size: 40pt; color: #fff; margin-top: 2mm; letter-spacing: .01em; }
        .cover-by { position: relative; margin-top: 7mm; font-size: 10.5pt; color: #c9d0e2; max-width: 128mm; }
        .cover-by b { color: #fff; }
        .bigdate { position: absolute; right: 15mm; top: 16mm; text-align: right; font-family: 'Barlow Condensed', 'Inter', sans-serif; text-transform: uppercase; line-height: .9; }
        .bigdate .d { font-size: 72pt; font-weight: 900; }
        .bigdate .m { font-size: 20pt; font-weight: 800; color: {{ $c }}; letter-spacing: .06em; }
        .bigdate .w { font-size: 11pt; font-weight: 600; color: #c9d0e2; letter-spacing: .2em; margin-top: 1mm; }
        .bandstats { position: absolute; left: 15mm; right: 15mm; bottom: 24mm; display: flex; gap: 10mm; font-family: 'Barlow Condensed', 'Inter', sans-serif; text-transform: uppercase; }
        .bandstats b { display: block; font-size: 34pt; font-weight: 900; line-height: .9; }
        .bandstats span { font-size: 9pt; font-weight: 700; letter-spacing: .18em; color: {{ $c }}; }
        .body { padding: 0 15mm; position: relative; }
        .low { display: grid; grid-template-columns: 1fr 62mm; gap: 10mm; padding-top: 9mm; }
        .h2 { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 800; text-transform: uppercase; font-size: 15pt; letter-spacing: .06em;
              color: {{ $navy }}; margin-bottom: 2.5mm; display: flex; align-items: center; gap: 3mm; }
        .h2::before { content: ''; width: 7mm; height: 2.2mm; background: {{ $c }}; transform: skewX(-20deg); flex: 0 0 auto; }
        .sch div { display: grid; grid-template-columns: 21mm 33mm 1fr; padding: 1.9mm 0; border-bottom: .25mm solid #e3e6ee; font-size: 9pt; color: {{ $navy }}; }
        .sch .dt { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 800; text-transform: uppercase; font-size: 11pt; }
        .sch .tm { font-weight: 800; color: {{ $goldInk }}; font-variant-numeric: tabular-nums; }
        .sch .lb em { font-style: normal; color: #6b7590; }
        .sch .br, .sch .br .tm { color: #9aa3b2; font-style: italic; }
        .side div { padding: 2mm 0; border-bottom: .25mm solid #e3e6ee; }
        .side small { display: block; font-size: 6.8pt; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: {{ $goldInk }}; }
        .side strong { display: block; font-size: 9.5pt; font-weight: 700; color: {{ $navy }}; margin-top: .6mm; line-height: 1.3; }
        /* Cover cards: the page-2 division card (.grp), holding the schedule and the rules. */
        .cv-card { margin-bottom: 0; }
        .cv-card .sch > div, .cv-card .side > div { padding-inline: 3.5mm; }
        .cv-card .sch > div:last-child, .cv-card .side > div:last-child { border-bottom: 0; }
        .offs { margin-top: 7mm; }
        .offs div { display: grid; grid-template-columns: 40mm 1fr; padding: 1.9mm 0; border-bottom: .25mm solid #e3e6ee; font-size: 9pt; color: {{ $navy }}; }
        .offs small { font-size: 6.8pt; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: {{ $goldInk }}; padding-top: .5mm; }
        .offs span { font-weight: 700; }

        /* Page 2 */
        .page-band { padding: 12mm 15mm 16mm; display: flex; align-items: flex-end; gap: 8mm; }
        .page-band .slab { height: 9mm; clip-path: polygon(0 55%, 100% 0, 100% 100%, 0 100%); }
        .page-band h1 { position: relative; margin-top: 2mm; font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-style: italic;
                        text-transform: uppercase; font-size: 40pt; line-height: .9; }
        .page-band h1 span { color: {{ $c }}; }
        .totals { position: relative; margin-inline-start: auto; display: flex; gap: 8mm; font-family: 'Barlow Condensed', 'Inter', sans-serif; text-transform: uppercase; text-align: end; }
        .totals b { display: block; font-size: 34pt; font-weight: 900; line-height: .9; }
        .totals span { font-size: 9pt; font-weight: 700; letter-spacing: .18em; color: {{ $c }}; }
        .legend { position: relative; display: flex; gap: 4mm; margin-top: 3mm; font-size: 7pt; font-weight: 700; color: #c9d0e2; }
        .legend span { display: inline-flex; align-items: center; gap: 1.5mm; }
        .legend i { width: 2.2mm; height: 2.2mm; border-radius: 50%; display: inline-block; }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 9mm; margin-top: 7mm; align-items: start; }
        .grp { break-inside: avoid; margin-bottom: 5mm; border: .35mm solid #cfd5e0; border-radius: 1.5mm; overflow: hidden; background: #fff; }
        .grp .h2 { font-size: 12.5pt; margin-bottom: 0; padding: 2.2mm 3mm 1.8mm; background: #f4f6fa; border-bottom: .6mm solid {{ $navy }}; }
        .grp .drow { padding-inline: 3mm; }
        .grp .drow:last-child { border-bottom: 0; }
        .grp .h2 em { font-style: normal; font-size: 9pt; color: #8a93a8; margin-inline-start: auto; letter-spacing: .08em; }
        .drow { display: flex; align-items: baseline; gap: 2mm; padding: 1.3mm 0 1.1mm; font-size: 8.8pt; font-weight: 600; color: {{ $navy }};
                border-bottom: .25mm solid #e3e6ee; }
        /* Links inside the PDF look like the text they are — no blue, no underline. */
        a.drow, .ath a { color: inherit; text-decoration: none; }
        a.n { display: block; }
        .drow .g { width: 2mm; height: 2mm; border-radius: 50%; background: #9aa3b2; flex: 0 0 auto; transform: translateY(-.3mm); }
        .drow .g.male { background: #2563eb; } .drow .g.female { background: #db2777; }
        .drow .lead { flex: 1; border-bottom: .3mm dotted #c3c9d6; transform: translateY(-1mm); }
        .drow .cnt { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-size: 13pt; line-height: 1; color: {{ $goldInk }}; min-width: 6mm; text-align: end; }

        /* Officials & management — one bordered card per person: the face at
           3:4 (the platform's portrait ratio), the job in gold, the name, the club. */
        .tgrid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 4.5mm 6mm; }
        .tsec + .tsec { margin-top: 7mm; }
        .tsec > .h2 { margin-top: 7mm; }
        .pcard { display: flex; gap: 4mm; border: .35mm solid #cfd5e0; border-radius: 1.5mm; overflow: hidden; background: #fff; min-height: 34mm; }
        .pcard .ph { width: 25.5mm; height: 34mm; flex: 0 0 auto; background: #eef1f6; border-inline-end: .35mm solid #cfd5e0; }
        .pcard .ph img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .pcard .ph img.ph-none { opacity: .55; }
        .pcard .tx { padding: 3mm 3.5mm 2.5mm 0; min-width: 0; display: flex; flex-direction: column; }
        .pcard .job { font-size: 7.2pt; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: {{ $goldInk }}; line-height: 1.25; }
        .pcard .nm { font-size: 12pt; font-weight: 800; color: {{ $navy }}; line-height: 1.2; margin-top: 1mm; overflow-wrap: anywhere; }
        .pcard .tx { flex: 1; }
        .pcard .cl { margin: auto -3.5mm -2.5mm 0; padding: 2mm 3.5mm; display: flex; align-items: center; gap: 1.8mm;
                     background: {{ $navy }}; color: #fff; font-size: 8.6pt; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; line-height: 1.25; }
        .pcard .cl i { font-style: normal; font-size: 6pt; font-weight: 800; letter-spacing: .14em; color: {{ $c }}; }
        .pcard .cl img { width: 6mm; height: 6mm; object-fit: contain; flex: 0 0 auto; }

        .cfoot { position: absolute; inset-inline: 15mm; bottom: 8mm; display: flex; justify-content: space-between;
                 font-size: 7.5pt; color: #6b7590; }
        .cfoot b { color: {{ $goldInk }}; letter-spacing: .06em; }

        /* The board's own navy, edge to edge under the head. */
        .pdf-board { flex: 1 1 auto; position: relative; overflow: hidden; background: #fff; }
        .pdf-board-in { position: absolute; inset: 5mm 6mm 11mm; }
        .pdf-vp { position: absolute !important; inset: 0; overflow: visible !important;
                  background: transparent !important; cursor: default !important; }
        /* A printout is the finished state: no entrance animations, no pulse
           half-way through a frame. */
        .pdf-vp *, .pdf-vp *::before, .pdf-vp *::after { animation: none !important; transition: none !important; }
        /* …and with no entrance, the end state stated outright — the board's
           cards START at opacity 0 and are faded in by `bkIn`. The same reset
           the runtime's own reduced-motion rule applies. */
        .pdf-vp .bk-match, .pdf-vp .bk-champ { opacity: 1 !important; transform: none !important; }

        /* ── Ink-light: what was light-on-navy becomes dark-on-white ───────
           The screen draws on a navy hall; paper is white. Nothing moves and
           nothing is redrawn — shadows become hairlines, solid navy and gold
           fills become outlines, and gold type darkens enough to read on
           white. Everything else on the cards is already ink on white. */
        .pdf-vp { color: #1e2c4f !important; }
        .pdf-vp .bk-round-label { color: #8a6d20 !important; }
        .pdf-vp .bk-match { box-shadow: none !important; border: 1px solid #c9d0db; }
        .pdf-vp .bk-match.is-current, .pdf-vp .bk-match.is-mine { box-shadow: none !important; border: 2px solid #d8b25f; }
        .pdf-vp .bk-av:not(.is-bye):not(.is-pending),
        .pdf-vp .bk-place-av, .pdf-vp .bk-champ-av {
            background: #fff !important; color: #5d6880 !important; border: 1px solid #c9d0db !important; }
        .pdf-vp .bk-champ { background: #fff !important; border: 2px solid #d8b25f; }
        .pdf-vp .bk-conf { display: none !important; }
        .pdf-vp .bk-champ-none { border-color: #9aa3b2 !important; color: #5d6880 !important; }
        .pdf-vp .bk-place { box-shadow: none !important; border: 1px solid #c9d0db; }

        .pdf-foot { position: absolute; inset-inline: 6mm; bottom: 3.5mm; display: flex; align-items: center; gap: 4mm;
                    font-size: 7.5pt; color: #5d6880; }
        .pdf-foot .dot { width: 2mm; height: 2mm; border-radius: 50%; display: inline-block; margin-inline-end: 1.2mm; }
        .pdf-foot .ln { width: 5mm; height: .7mm; display: inline-block; margin-inline-end: 1.4mm; vertical-align: middle; }
        .pdf-foot .pg { margin-inline-start: auto; font-weight: 800; letter-spacing: .06em; color: {{ $goldInk }}; }


        /* The podium strip — only on a division whose every bout is decided.
           Sits under the board, which gives up the height it needs. */
        .has-pod .pdf-board-in { bottom: 2mm; }
        .pdf-podium { flex: 0 0 auto; padding: 0 6mm 9mm; }
        .pdf-podium .ph2 { display: flex; align-items: center; gap: 2.5mm; font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 800;
                           text-transform: uppercase; letter-spacing: .14em; font-size: 10.5pt; color: {{ $navy }}; margin-bottom: 2mm; }
        .pdf-podium .ph2::before { content: ''; width: 7mm; height: 1.4mm; background: {{ $c }}; transform: skewX(-20deg); }
        .pdf-podium .ph2::after { content: ''; flex: 1; height: .35mm; background: #cfd5e0; }
        .pod { display: grid; grid-template-columns: repeat(var(--n), 1fr); gap: 3.5mm; }
        .pod-c { display: flex; border: .35mm solid #cfd5e0; border-radius: 1.5mm; overflow: hidden; background: #fff; min-width: 0; }
        .pod-c.p1 { border: .6mm solid {{ $c }}; }
        .pod-c .ph { width: 15mm; height: 20mm; flex: 0 0 auto; background: #eef1f6; border-inline-end: .35mm solid #cfd5e0; }
        .pod-c .ph img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .pod-c .ph img.ph-none { opacity: .55; }
        .pod-c .tx { flex: 1; min-width: 0; padding: 1.6mm 2.4mm; display: flex; flex-direction: column; gap: .6mm; }
        .pod-c .pl { display: flex; align-items: center; gap: 1.4mm; font-size: 6.8pt; font-weight: 800; letter-spacing: .12em;
                     text-transform: uppercase; color: {{ $goldInk }}; }
        .pod-c .pl svg { flex: 0 0 auto; }
        /* One line each, every card the same height: a long name or club is
           set smaller until it fits (.pfit, below), never wrapped or cut. */
        .pod-c .nm { font-size: 9.5pt; font-weight: 800; color: {{ $navy }}; line-height: 1.2; white-space: nowrap; overflow: hidden; }
        .pod-c.p1 .nm { font-size: 10.5pt; }
        .pod-c .cl { display: flex; align-items: center; gap: 1.4mm; font-size: 7pt; white-space: nowrap; overflow: hidden; font-weight: 700; color: #5d6880; text-transform: uppercase; letter-spacing: .04em; }
        .pod-c .cl .cl-logo { width: 5mm; height: 5mm; object-fit: contain; flex: 0 0 auto; }
        .pod-c .cl .fi { width: 4mm; height: 3mm; flex: 0 0 auto; }

        /* The working board: real size, real layout, never printed. */
        #pdf-work { position: fixed; left: -20000px; top: 0; width: 1600px; height: 1000px; }
        @media print { #pdf-work { display: none !important; } }
    </style>
</head>
<body>
    @if($cover)
        {{-- ═══ Page 1 — the competition ═══ --}}
        @php
            // The title as the poster sets it: first word white, second gold,
            // the rest on a smaller line. Each line is shrunk to fit by the
            // script at the foot of the page, so a long word never runs off.
            $words = preg_split('/\s+/u', trim($title)) ?: [];
            $tl = count($words) >= 3
                ? [$words[0], $words[1], implode(' ', array_slice($words, 2))]
                : [$words[0] ?? '', $words[1] ?? '', ''];
            $factOf = fn (string $icon) => collect($cover['facts'])->firstWhere('icon', $icon)['value'] ?? null;
            $venue = $factOf('bi-geo-alt');
            $hours = $factOf('bi-clock');
        @endphp
        <section class="pdf-page port">
            <div class="band cover-band">
                <span class="stripes"></span>
                @if($hostLogo)<span class="hlogo" role="img" aria-label="{{ $host }}"></span>@endif
                @if($finished)<div class="done-stamp"><i class="bi bi-trophy-fill"></i>{{ __('events::events.draw_print_results') }}</div>@endif
                @if($cover['type'])<div class="eye">{{ $cover['type'] }}</div>@endif
                <h1 class="cover-title">
                    <span class="fit l1">{{ $tl[0] }}</span>
                    @if($tl[1] !== '')<span class="fit l2">{{ $tl[1] }}</span>@endif
                    @if($tl[2] !== '')<span class="fit l3">{{ $tl[2] }}</span>@endif
                </h1>
                @if($host || $venue)
                    <p class="cover-by">@if($host){!! __('events::events.draw_print_organised_by', ['name' => '<b>'.e($host).'</b>']) !!}@endif @if($host && $venue)·@endif {{ $venue }}</p>
                @endif

                @if($cover['when'])
                    <div class="bigdate">
                        <div class="d">{{ str_pad($cover['when']['day'], 2, '0', STR_PAD_LEFT) }}</div>
                        <div class="m">{{ $cover['when']['mon'] }} {{ $cover['when']['year'] }}</div>
                        <div class="w">{{ $cover['when']['wday'] }}</div>
                    </div>
                @endif

                @if($cover['figures'])
                    <div class="bandstats">
                        @foreach($cover['figures'] as $f)
                            <div><b>{{ $f['value'] }}</b><span>{{ $f['label'] }}</span></div>
                        @endforeach
                    </div>
                @endif
                <span class="slab"></span>
            </div>

            <div class="body">
                <div class="low">
                    <div>
                        @if($cover['schedule'])
                          <div class="grp cv-card">
                            <div class="h2">{{ __('events::events.draw_print_schedule') }}</div>
                            <div class="sch">
                                @foreach($cover['schedule'] as $d)
                                    @foreach($d['rows'] as $r)
                                        <div class="{{ $r['break'] ? 'br' : '' }}">
                                            <span class="dt">{{ $loop->first ? $d['short'] : '' }}</span>
                                            <span class="tm">{{ $r['time'] }}</span>
                                            <span class="lb">{{ $r['label'] }}@if($r['sub']) <em>· {{ $r['sub'] }}</em>@endif</span>
                                        </div>
                                    @endforeach
                                @endforeach
                            </div>
                          </div>
                        @endif
                    </div>
                    <div>
                        @if($hours || $cover['rules'] || $cover['requirements'])
                          <div class="grp cv-card">
                            <div class="h2">{{ __('events::events.draw_print_rules') }}</div>
                            <div class="side">
                                @if($hours)<div><small>{{ __('events::events.draw_print_hours') }}</small><strong>{{ $hours }}</strong></div>@endif
                                @foreach($cover['rules'] as $f)
                                    <div><small>{{ $f['label'] }}</small><strong>{{ $f['value'] }}</strong></div>
                                @endforeach
                                @if($cover['requirements'])
                                    <div><small>{{ __('events::events.draw_print_requirements') }}</small><strong>{{ implode(' · ', $cover['requirements']) }}</strong></div>
                                @endif
                            </div>
                          </div>
                        @endif
                    </div>
                </div>

                @if($cover['officials'])
                    <div class="offs">
                        <div class="h2" style="display:flex;border:0;padding:0">{{ __('events::events.draw_print_officials') }}</div>
                        @foreach($cover['officials'] as $g)
                            <div><small>{{ $g['label'] }}</small><span>{{ collect($g['people'])->pluck('name')->implode(' · ') }}</span></div>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="cfoot"><span>{{ $host }}</span><b>1 / {{ $total }}</b></div>
        </section>

        {{-- ═══ Page 2 — divisions and entries ═══ --}}
        <section class="pdf-page port">
            <div class="band page-band">
                <span class="stripes"></span>
                @if($hostLogo)<span class="hlogo" role="img" aria-label="{{ $host }}"></span>@endif
                <div style="position:relative">
                    <div class="eye">{{ $title }}</div>
                    <h1>{{ __('events::events.draw_print_division_list') }}</h1>
                    <div class="legend">
                        <span><i style="background:#2563eb"></i>{{ __('events::events.draw_print_boys') }}</span>
                        <span><i style="background:#db2777"></i>{{ __('events::events.draw_print_girls') }}</span>
                        <span><i style="background:#9aa3b2"></i>{{ __('events::events.draw_print_mixed') }}</span>
                    </div>
                </div>
                <div class="totals">
                    <div><b>{{ count($pages) }}</b><span>{{ __('events::events.draw_print_divisions') }}</span></div>
                    <div><b>{{ $cover['total'] }}</b><span>{{ __('events::events.draw_print_athletes') }}</span></div>
                </div>
                <span class="slab"></span>
            </div>
            <div class="body">
                <div class="grid2">
                    @foreach([0, 1] as $col)
                        <div>
                            @foreach($cover['groups'] as $gi => $g)
                                @continue($gi % 2 !== $col)
                                <div class="grp">
                                    <div class="h2">{{ $g['heading'] ?: __('events::events.draw_print_divisions') }}<em>{{ array_sum(array_column($g['rows'], 'n')) }}</em></div>
                                    @foreach($g['rows'] as $r)
                                        @if(isset($r['id']) && $sheetIds->has($r['id']))
                                            <a class="drow" href="#sheet-{{ $r['id'] }}"><span class="g {{ $r['gender'] }}"></span><span>{{ $r['name'] }}</span><span class="lead"></span><span class="cnt">{{ $r['n'] }}</span></a>
                                        @else
                                            <div class="drow"><span class="g {{ $r['gender'] }}"></span><span>{{ $r['name'] }}</span><span class="lead"></span><span class="cnt">{{ $r['n'] }}</span></div>
                                        @endif
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="cfoot"><span>{{ $host }}</span><b>2 / {{ $total }}</b></div>
        </section>

        {{-- ═══ Officials & management — only when anybody is appointed ═══ --}}
        {{-- ═══ Teams — every club that took part and who came with it ═══ --}}
        @foreach($clubPages as $ci => $cp)
            <section class="pdf-page port">
                <div class="band page-band">
                    <span class="stripes"></span>
                    @if($hostLogo)<span class="hlogo" role="img" aria-label="{{ $host }}"></span>@endif
                    <div style="position:relative">
                        <div class="eye">{{ $title }}</div>
                        <h1>{{ __('events::events.draw_print_teams_title') }}</h1>
                    </div>
                    @if($ci === 0)
                        <div class="totals">
                            <div><b>{{ collect($clubPages)->flatten(1)->reject(fn ($b) => $b['continued'] || $b['none'])->count() }}</b><span>{{ __('events::events.draw_print_clubs') }}</span></div>
                            <div><b>{{ collect($clubPages)->flatten(1)->sum(fn ($b) => count($b['people'])) }}</b><span>{{ __('events::events.draw_print_athletes') }}</span></div>
                        </div>
                    @endif
                    <span class="slab"></span>
                </div>
                <div class="body">
                    @foreach($cp as $club)
                        <div class="club">
                            <div class="club-h">
                                @if($club['rank'])<span class="rk">{{ $club['rank'] }}</span>@endif
                                @if($club['logo'])
                                    <span class="lg"><img src="{{ $club['logo'] }}" alt=""></span>
                                @else
                                    <span class="lg none"><i class="bi bi-shield-fill"></i></span>
                                @endif
                                <div class="nm">{{ $club['name'] }}
                                    <small>{{ trans_choice('events::events.draw_print_club_athletes', $club['count'], ['n' => $club['count']]) }}@if($club['continued']) · {{ __('events::events.draw_print_continued') }}@endif</small>
                                </div>
                                @if(array_sum($club['medals']) > 0 && ! $club['continued'])
                                    <div class="md">
                                        @foreach([1, 2, 3] as $pl)
                                            <span class="{{ $club['medals'][$pl] ? '' : 'zero' }}"><x-medal-icon :place="$pl" :size="15" :numeral="false" />{{ $club['medals'][$pl] }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <div class="club-p">
                                @foreach($club['people'] as $a)
                                    <div class="ath">
                                        <div class="t">
                                            @php $linked = collect($a['divisions'])->filter(fn ($d) => $sheetIds->has($d['id']))->values(); @endphp
                                            {{-- The name opens their first bracket; each division its own. --}}
                                            @if($linked->isNotEmpty())
                                                <a class="n" dir="auto" href="#sheet-{{ $linked[0]['id'] }}">{{ $a['name'] }}</a>
                                            @else
                                                <div class="n" dir="auto">{{ $a['name'] }}</div>
                                            @endif
                                            @if($a['divisions'])
                                                <div class="d">@foreach($a['divisions'] as $d)@if(! $loop->first) · @endif @if($sheetIds->has($d['id']))<a href="#sheet-{{ $d['id'] }}">{{ $d['name'] }}</a>@else{{ $d['name'] }}@endif @endforeach</div>
                                            @endif
                                        </div>
                                        @if($a['medals'])
                                            <div class="m">@foreach($a['medals'] as $pl)<x-medal-icon :place="$pl" :size="11" :numeral="false" />@endforeach</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="cfoot"><span>{{ $host }}</span><b>{{ 3 + $ci }} / {{ $total }}</b></div>
            </section>
        @endforeach

        @foreach($teamPages as $ti => $tp)
            <section class="pdf-page port">
                <div class="band page-band">
                    <span class="stripes"></span>
                    @if($hostLogo)<span class="hlogo" role="img" aria-label="{{ $host }}"></span>@endif
                    <div style="position:relative">
                        <div class="eye">{{ $title }}</div>
                        <h1>{{ __('events::events.draw_print_team_title') }}</h1>
                    </div>
                    @if($ti === 0)
                        <div class="totals">
                            <div><b>{{ collect($teamPages)->flatten(1)->sum(fn ($s) => count($s['cards'])) }}</b><span>{{ __('events::events.draw_print_people') }}</span></div>
                        </div>
                    @endif
                    <span class="slab"></span>
                </div>
                <div class="body">
                    @foreach($tp as $sec)
                        <div class="tsec">
                            <div class="h2">{{ $sec['heading'] }}</div>
                            <div class="tgrid">
                                @foreach($sec['cards'] as $m)
                                    <div class="pcard">
                                        <div class="ph">
                                            @if($m['photo'])<img src="{{ $m['photo'] }}" alt="">@else<img class="ph-none" src="{{ $m['placeholder'] }}" alt="">@endif
                                        </div>
                                        <div class="tx">
                                            <div class="job">{{ $m['job'] }}</div>
                                            <div class="nm" dir="auto">{{ $m['name'] }}</div>
                                            @if($m['club'])
                                                <div class="cl">@if($m['logo'])<img src="{{ $m['logo'] }}" alt="">@else<i>{{ __('events::events.draw_print_club') }}</i>@endif<span>{{ $m['club'] }}</span></div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="cfoot"><span>{{ $host }}</span><b>{{ 3 + count($clubPages) + $ti }} / {{ $total }}</b></div>
            </section>
        @endforeach
    @endif

    @foreach($pages as $i => $p)
        <section class="pdf-page {{ $p['landscape'] ? 'land' : 'port' }}{{ $p['podium'] ? ' has-pod' : '' }}" id="sheet-{{ $p['id'] }}" data-division="{{ $p['id'] }}" data-prov="{{ $p['provisional'] ? 1 : 0 }}">
            <header class="pdf-head band">
                <span class="stripes"></span>
                @if($hostLogo)<span class="hlogo" role="img" aria-label="{{ $host }}"></span>@endif
                <div style="position:relative; min-width:0;">
                    <div class="eye">{{ $p['podium'] ? __('events::events.bracket_pdf_eyebrow_result') : __('events::events.bracket_pdf_eyebrow') }} · {{ $title }}</div>
                    <div class="pdf-title">{{ $p['name'] }}</div>
                    @if($date || $host)
                        <div class="pdf-sub">{{ collect([$date, $host])->filter()->implode(' · ') }}</div>
                    @endif
                </div>
                <div class="pdf-count"><b>{{ $p['entrants'] }}</b><span>{{ __('events::events.draw_print_athletes') }}</span></div>
                <span class="slab"></span>
            </header>

            <div class="pdf-board">
                <div class="pdf-board-in"></div>
            </div>

            @if($p['podium'])
                <div class="pdf-podium">
                    <div class="ph2">{{ __('events::events.draw_print_podium') }}</div>
                    <div class="pod" style="--n: {{ count($p['podium']) }}">
                        @foreach($p['podium'] as $w)
                            <div class="pod-c p{{ $w['place'] }}">
                                <div class="ph">
                                    @if($w['photo'])<img src="{{ $w['photo'] }}" alt="">@else<img class="ph-none" src="{{ $w['placeholder'] }}" alt="">@endif
                                </div>
                                <div class="tx">
                                    <div class="pl"><x-medal-icon :place="$w['place']" :size="13" />{{ __('events::events.draw_print_place_'.$w['place']) }}</div>
                                    <div class="nm pfit" dir="auto">{{ $w['name'] }}</div>
                                    @if($w['club'] || $w['country'])
                                        <div class="cl">
                                            @if($w['country'] && \App\Support\Flag::iso2($w['country']))<span class="fi fi-{{ strtolower(\App\Support\Flag::iso2($w['country'])) }}"></span>@endif
                                            @if(! empty($w['club_logo']))<img class="cl-logo" src="{{ $w['club_logo'] }}" alt="">@endif
                                            <span class="pfit" style="min-width:0; overflow:hidden">{{ $w['club'] }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="pdf-foot">
                {{-- Says what is ON this sheet, in the colours it is drawn in:
                     a gold line is a decided bout, a slate one still to fight. --}}
                <span><span class="ln" style="background:#d8b25f"></span>{{ __('events::events.bracket_legend_done') }}</span>
                @unless($p['podium'])
                    <span><span class="ln" style="background:#42527d"></span>{{ __('events::events.bracket_legend_pending') }}</span>
                @endunless
                @if($p['provisional'])
                    <span><span class="dot" style="background:#d8b25f"></span>{{ __('events::events.bracket_legend_provisional') }}</span>
                @endif
                <span class="pg">{{ $i + 1 + $offset }} / {{ $total }}</span>
            </div>
        </section>
    @endforeach

    <script>
        // Podium names and clubs: one line, set smaller until it fits its card.
        document.fonts.ready.then(function () {
            document.querySelectorAll('.pfit').forEach(function (el) {
                var size = parseFloat(getComputedStyle(el).fontSize);
                while (el.scrollWidth > el.clientWidth + 0.5 && size > 5) { size -= 0.25; el.style.fontSize = size + 'px'; }
            });
        });

        // Poster lines are shrunk until they fit their column, in the real font.
        document.fonts.ready.then(function () {
            document.querySelectorAll('.fit').forEach(function (el) {
                var box = el.parentElement.clientWidth, size = parseFloat(getComputedStyle(el).fontSize);
                while (el.scrollWidth > box && size > 14) { size -= 1; el.style.fontSize = size + 'px'; }
            });
        });
    </script>

    {{-- The real board, off the page. Every sheet above is a copy of what it draws. --}}
    {{-- Every picture the boards draw, once each; the payload refers to them. --}}
    <script>window.BracketMedia = @json($media ?? [], JSON_UNESCAPED_SLASHES);</script>
    <div id="pdf-work">
        <x-tournament-bracket id="pdfboard" :data-url="$dataUrl" :show-divisions="false" :bare="true" height="1000px" />
    </div>

    <script>
    (function () {
        const LAYOUT = @json($layout), THIRD = @json($third);
        const vp = document.getElementById('pdfboard-viewport');
        let done = false;

        vp.addEventListener('bracket:loaded', async function () {
            if (done) return;
            done = true;

            // The board measures names in the real font; measure only once it is here.
            try { await document.fonts.ready; } catch (e) { /* print with what we have */ }

            const BB = window.BracketBoard;
            // The viewer's own view of the draw, as they had it on screen.
            BB.setView('desktop');
            BB.setLayout(LAYOUT);
            BB.setThird(THIRD);

            document.querySelectorAll('.pdf-page').forEach(function (page) {
                BB.show(page.dataset.division);

                const canvas = vp.querySelector('.bk-canvas');
                const layer = canvas && canvas.querySelector('.bk-layer');
                const box = page.querySelector('.pdf-board-in');
                if (!layer || !box) return;

                /* No podium on paper (owner, 2026-10-07). The sheet is cropped
                   to the bouts themselves — every card, the bronze bout
                   included, and the round titles — so the column the podium
                   stood in does not leave a blank strip. */
                const rel = function (el) {
                    let x = 0, y = 0, n = el;
                    while (n && n !== layer) { x += n.offsetLeft; y += n.offsetTop; n = n.offsetParent; }
                    return { x: x, y: y, r: x + el.offsetWidth, b: y + el.offsetHeight };
                };
                let minX = Infinity, minY = Infinity, maxX = 0, maxY = 0;
                layer.querySelectorAll('.bk-match, .bk-round-label').forEach(function (el) {
                    if (el.closest('.bk-podium')) return;
                    const q = rel(el);
                    minX = Math.min(minX, q.x); minY = Math.min(minY, q.y);
                    maxX = Math.max(maxX, q.r); maxY = Math.max(maxY, q.b);
                });
                if (!isFinite(minX)) { minX = 0; minY = 0; maxX = layer.offsetWidth; maxY = layer.offsetHeight; }
                const PAD = 28;
                minX = Math.max(0, minX - PAD); minY = Math.max(0, minY - PAD);
                maxX = Math.min(layer.offsetWidth, maxX + PAD); maxY = Math.min(layer.offsetHeight, maxY + PAD);

                const w = maxX - minX, h = maxY - minY;
                const bw = box.clientWidth, bh = box.clientHeight;
                if (!w || !h) return;

                // Fitted like the board's own "fit" button: whole draw, centred,
                // never blown up past a little over life size.
                const s = Math.min(bw / w, bh / h, 1.15);

                /* Scaled with `zoom`, NOT a transform. A transformed canvas is
                   printed as a composited layer, and Chromium clipped that
                   layer partway down the page — the bottom bouts of every
                   bracket were cut off. `zoom` lays the copy out at its real
                   printed size instead. The zoom sits on a wrapper so the crop
                   offset inside it is scaled with everything else. */
                const clone = canvas.cloneNode(true);
                clone.querySelectorAll('.bk-podium').forEach(function (el) { el.remove(); });
                // A settled division: who had paid or weighed in no longer matters.
                if (page.dataset.prov === '0') clone.querySelectorAll('.bk-prov').forEach(function (el) { el.remove(); });
                clone.style.transform = 'none';
                clone.style.willChange = 'auto';
                clone.style.position = 'absolute';
                clone.style.left = (-minX) + 'px';
                clone.style.top = (-minY) + 'px';
                clone.style.width = layer.offsetWidth + 'px';
                clone.style.height = layer.offsetHeight + 'px';

                const crop = document.createElement('div');
                crop.style.cssText = 'position:relative;overflow:hidden;width:' + w + 'px;height:' + h + 'px;zoom:' + s;
                crop.appendChild(clone);

                const host = document.createElement('div');
                host.className = 'bk-viewport pdf-vp';
                host.style.inset = 'auto';
                host.style.left = ((bw - w * s) / 2) + 'px';
                host.style.top = ((bh - h * s) / 2) + 'px';
                host.style.width = (w * s) + 'px';
                host.style.height = (h * s) + 'px';
                host.appendChild(crop);
                box.appendChild(host);
            });

            document.documentElement.setAttribute('data-ready', '1');
        });
    })();
    </script>
</body>
</html>
