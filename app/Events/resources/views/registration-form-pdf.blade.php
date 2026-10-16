{{--
    The registration form, as pages to print — App\Events\Support\RegistrationFormPdf
    feeds this to headless Chromium (through BracketPdf::render). A BLANK entry
    form, two A4 pages, every label in English AND Arabic side by side.

    The draw and entry-list PDFs' "Fight night" look — navy and gold, Barlow
    Condensed, the gold diagonal under the band, hairline grids — so the three
    read as one set. Design source: drafts/The Gost Team Tournament Reg. Form.doc.

    Never served over HTTP; the logo arrives inlined. Inline styles throughout:
    it is a print document, and the Tailwind bundle is prebuilt.
--}}
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <title>{{ $titleEn }} · {{ __('events::regform.eyebrow', [], 'en') }}</title>
    {{-- Google Fonts is the grandfathered outside sheet the other event PDFs
         load; Cairo carries the Arabic, with the box's own Noto Kufi behind it. --}}
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,600;0,700;0,800;0,900;1,800;1,900&family=Inter:wght@400;500;600;700;800&family=Cairo:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        @page { size: A4 portrait; margin: 0; }
        :root {
            --navy: #16213d;
            --gold: #d8b25f;
            --gold-ink: #a8862f;
            --ink: #1e2c4f;
            --muted: #6b7590;
            --rule: #cfd5e0;
            --rule-soft: #e3e6ee;
            --tint: #f4f6fa;
            --paper: #ffffff;
            --desk: #232a3b;
            --desk-ink: #c9d0e2;
            --male: #2563eb;
            --female: #db2777;
            --f-cond: 'Barlow Condensed', 'Arial Narrow', 'Inter', sans-serif;
            --f-body: 'Inter', system-ui, sans-serif;
            --f-ar: 'Cairo', 'Noto Kufi Arabic', 'Noto Sans Arabic', 'Segoe UI', Tahoma, sans-serif;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body { background: var(--paper); color: var(--ink); font-family: var(--f-body);
               -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        /* ── The A4 sheet ─────────────────────────────────────────────────────── */
        .page { position: relative; overflow: hidden; width: 210mm; height: 297mm; background: var(--paper);
                break-after: page; page-break-after: always; }
        .page:last-child { break-after: auto; page-break-after: auto; }
        .ar { font-family: var(--f-ar); direction: rtl; }
        .cond { font-family: var(--f-cond); }

        /* Band */
        .band { position: relative; overflow: hidden; color: #fff; background: var(--navy); height: 54mm; padding: 10mm 15mm 0;
                display: grid; grid-template-columns: auto minmax(0, 1fr) minmax(0, 1fr); column-gap: 6mm; align-items: start; }
        .band.short { height: 38mm; padding-top: 7mm; }
        .band .stripes { position: absolute; right: 0; top: 0; bottom: 0; width: 110mm; opacity: .14;
                         background: repeating-linear-gradient(115deg, var(--gold) 0 3mm, transparent 3mm 9mm); }
        .band .slab { position: absolute; left: 0; right: 0; bottom: 0; height: 9mm; background: var(--gold);
                      clip-path: polygon(0 55%, 100% 0, 100% 100%, 0 100%); }
        .short .slab { height: 7mm; }
        .hlogo { position: relative; width: 20mm; height: 20mm; margin-top: 1mm; display: block; }
        .short .hlogo { width: 14mm; height: 14mm; }
        /* The two halves are locked to the SAME three rows — eyebrow, title,
           date — so English and Arabic sit level whatever their scripts and
           sizes. Each item is centred in its row; the titles shrink to fit
           their row (the script at the foot), never push the rows apart. */
        .who, .who-ar { position: relative; min-width: 0; display: grid; grid-template-rows: 5mm 21mm 6mm; row-gap: 1.2mm; align-items: center; }
        .short .who, .short .who-ar { grid-template-rows: 5mm 12mm; }
        .short .who .eye { letter-spacing: .1em; }
        .who > *, .who-ar > * { margin: 0; min-width: 0; }
        .who-ar { direction: rtl; text-align: right; }
        .eye { font-size: 7.2pt; line-height: 5mm; font-weight: 800; letter-spacing: .28em; text-transform: uppercase; color: var(--gold); white-space: nowrap; overflow: hidden; }
        .band h1 { font-family: var(--f-cond); font-weight: 900; font-style: italic; text-transform: uppercase;
                   font-size: 30pt; line-height: .95; letter-spacing: -.01em; }
        .short h1 { font-size: 26pt; }
        .when { display: flex; align-items: center; gap: 2mm; font-family: var(--f-cond); font-weight: 800;
                font-size: 11pt; line-height: 6mm; letter-spacing: .1em; text-transform: uppercase; white-space: nowrap; }
        .when i { width: 6mm; height: .5mm; background: var(--gold); display: inline-block; }
        .who-ar .eye { font-family: var(--f-ar); letter-spacing: 0; font-size: 8.5pt; font-weight: 700; }
        .who-ar .t { font-family: var(--f-ar); font-weight: 900; font-size: 19pt; line-height: 1.3; }
        .who-ar .t b { display: block; color: var(--gold); font-weight: 900; }
        .who-ar .d { font-family: var(--f-ar); font-weight: 700; font-size: 10.5pt; line-height: 6mm; color: #fff; white-space: nowrap; }
        .short .who-ar .t { font-size: 15pt; }

        /* Photo frame riding over the band's tail */
        .top { display: grid; grid-template-columns: minmax(0,1fr) 31mm; gap: .3mm; background: var(--rule-soft); border-bottom: .3mm solid var(--rule-soft); }
        .top .g .f { min-height: 20mm; }
        .photo { background: var(--paper); padding: 2mm; display: flex; }
        .photo > div { flex: 1; }
        .photo > div { position: relative; border: .3mm dashed var(--rule); display: flex; flex-direction: column;
                     align-items: center; justify-content: flex-end; padding-bottom: 2mm; overflow: hidden; background: var(--tint); }
        .photo svg { position: absolute; left: 50%; bottom: 0; transform: translateX(-50%); width: 96%; }
        .photo .cap { position: relative; text-align: center; line-height: 1.15; background: rgba(255,255,255,.92); padding: .8mm 1.6mm; border-radius: .6mm; }
        .photo .cap .en { font-family: var(--f-cond); font-weight: 800; font-size: 7pt; letter-spacing: .16em; text-transform: uppercase; color: var(--gold-ink); }
        .photo .cap .ar { font-weight: 700; font-size: 7.5pt; color: var(--navy); }
        .photo .cap small { display: block; font-family: var(--f-body); font-size: 5.5pt; color: var(--muted); font-weight: 600; letter-spacing: .04em; }

        .body { padding: 5mm 15mm 0; display: flex; flex-direction: column; gap: 4.2mm; }

        /* Section: a bar, then its hairline grid */
        .sec { border: .35mm solid var(--rule); border-radius: 1.5mm; overflow: hidden; }
        .sh { height: 9mm; display: flex; align-items: center; gap: 2.6mm; padding: 0 3.5mm; background: var(--tint); border-bottom: .6mm solid var(--navy); }
        .sh .n { font-family: var(--f-cond); font-weight: 900; font-size: 15pt; color: var(--gold-ink); font-variant-numeric: tabular-nums; }
        .sh .en { font-family: var(--f-cond); font-weight: 800; text-transform: uppercase; font-size: 12.5pt; letter-spacing: .05em; color: var(--navy); white-space: nowrap; }
        .sh .ar { margin-left: auto; font-weight: 800; font-size: 11pt; color: var(--navy); white-space: nowrap; }
        .sh .tag { font-size: 6.4pt; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: var(--muted); }

        /* The grid of fields: the gaps ARE the hairlines */
        .g { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: .3mm; background: var(--rule-soft); }
        .f { background: var(--paper); padding: 1.5mm 2.4mm 1.4mm; min-height: 13.5mm; display: flex; flex-direction: column; gap: 1mm; min-width: 0; }
        .f.tall { min-height: 15mm; }
        .c2 { grid-column: span 2; } .c3 { grid-column: span 3; } .c4 { grid-column: span 4; } .c5 { grid-column: span 5; }
        .c6 { grid-column: span 6; } .c7 { grid-column: span 7; } .c8 { grid-column: span 8; } .c12 { grid-column: span 12; }
        .lb { display: flex; justify-content: space-between; align-items: baseline; gap: 2mm; white-space: nowrap; }
        .lb .en { font-family: var(--f-cond); font-weight: 700; font-size: 7pt; letter-spacing: .14em; text-transform: uppercase; color: var(--gold-ink); }
        .lb .ar { font-weight: 700; font-size: 7.6pt; color: var(--navy); }
        .lb .en small { font-family: var(--f-body); font-weight: 600; font-size: 5.4pt; letter-spacing: .03em; text-transform: none; color: var(--muted); margin-inline-start: 1mm; }
        .lb .ar small { font-weight: 600; font-size: 6pt; color: var(--muted); margin-inline-start: 1mm; }
        .wr { flex: 1; min-height: 5.5mm; border-bottom: .3mm dotted #b7bfce; display: flex; align-items: flex-end; justify-content: flex-start; gap: 1.6mm; padding-bottom: .6mm; min-width: 0; }
        .wr .u { margin-inline-start: auto; }
        .wr .u { font-family: var(--f-cond); font-weight: 800; font-size: 8pt; letter-spacing: .08em; color: #9aa3b2; text-transform: uppercase; }
        .wr.ar-in { direction: rtl; }

        /* Name in three parts, as it will be printed on the certificate */
        .parts { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 2.4mm; flex: 1; }
        .parts > span { border-bottom: .3mm dotted #b7bfce; display: flex; flex-direction: column; justify-content: flex-end; align-items: flex-start; gap: .6mm; padding-bottom: .5mm; min-width: 0;
                      font-size: 5.6pt; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #9aa3b2; min-height: 6mm; }
        .parts.ar-in { direction: rtl; }
        .parts.ar-in > span { font-family: var(--f-ar); letter-spacing: 0; font-size: 6.4pt; }

        /* Boxes to write one character in */
        .digits { display: flex; align-items: flex-end; gap: .9mm; flex: 1; direction: ltr; }
        .digits span { width: 4.6mm; height: 6.2mm; border: .3mm solid var(--rule); border-radius: .5mm; background: #fbfcfe;
                        display: grid; place-items: center; font-family: var(--f-body); font-weight: 700; font-size: 9pt; color: var(--navy); }
        .digits i { font-style: normal; font-family: var(--f-cond); font-weight: 800; font-size: 11pt; color: #b7bfce; padding: 0 .3mm; line-height: 6mm; }
        .digits .hint { display: flex; gap: .9mm; }

        /* Tick boxes */
        .opts { display: flex; align-items: center; gap: 3.4mm; flex: 1; flex-wrap: wrap; }
        .opt { display: inline-flex; align-items: center; gap: 1.4mm; white-space: nowrap; }
        .ck { width: 3.8mm; height: 3.8mm; border: .45mm solid var(--navy); border-radius: .6mm; flex: 0 0 auto; display: grid; place-items: center; }
        /* A filled form's answers: the hand that wrote them is the platform's, in the navy ink. */
        .ck.on { background: var(--navy); }
        .ck.on svg { width: 80%; height: 80%; }
        .opt.male .ck.on { background: var(--male); } .opt.female .ck.on { background: var(--female); }
        .fv { font-family: var(--f-body); font-weight: 700; font-size: 9.5pt; color: var(--navy); line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: clip; min-width: 0; letter-spacing: 0; text-transform: none; }
        .fv.ar, .ar .fv, .ar-in .fv { font-family: var(--f-ar); font-size: 10.5pt; }
        .fv-logo { width: 5mm; height: 5mm; object-fit: contain; flex: 0 0 auto; }
        .fv-belt { width: 7mm; height: 2.4mm; border-radius: .5mm; flex: 0 0 auto; align-self: center; }
        .fv-belt.pale { box-shadow: inset 0 0 0 .25mm #9aa3b2; }
        .photo img.face { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: center top; }
        .dvs { display: flex; flex-direction: column; gap: .5mm; min-width: 0; }
        .dvs .fv { font-size: 8pt; }
        .opt .en { font-family: var(--f-cond); font-weight: 800; font-size: 9pt; letter-spacing: .06em; text-transform: uppercase; color: var(--navy); }
        .opt .ar { font-weight: 700; font-size: 8.5pt; color: var(--navy); }
        .opt .sl { color: #b7bfce; font-size: 8pt; }
        .opt.male .en, .opt.male .ar { color: var(--male); } .opt.male .ck { border-color: var(--male); }
        .opt.female .en, .opt.female .ar { color: var(--female); } .opt.female .ck { border-color: var(--female); }

        /* Belt ladder strip (rank) */
        .belts { display: flex; gap: .7mm; margin-top: .4mm; }
        .belts i { height: 1.4mm; flex: 1; border-radius: .3mm; }

        /* Discipline cards */
        .disc { display: grid; grid-template-columns: 1fr 1fr 1.3fr; gap: .3mm; background: var(--rule-soft); }
        .dc { background: var(--paper); padding: 2.6mm 3.2mm; display: flex; align-items: center; gap: 2.6mm; min-height: 17mm; }
        .dc .ck { width: 5mm; height: 5mm; border-radius: .8mm; }
        .dc .t { line-height: 1.05; }
        .dc .t .en { font-family: var(--f-cond); font-weight: 900; font-style: italic; font-size: 16pt; text-transform: uppercase; color: var(--navy); }
        .dc .t .ar { display: block; font-weight: 800; font-size: 9.5pt; color: var(--gold-ink); margin-top: .6mm; }
        .dc .t small { display: block; font-size: 6pt; font-weight: 600; color: var(--muted); margin-top: .8mm; letter-spacing: .02em; }
        .dc.office { background: var(--tint); flex-direction: column; align-items: stretch; gap: 1mm; }

        /* Bilingual rows: English | marker | Arabic */
        .bi { display: grid; grid-template-columns: minmax(0,1fr) 9mm minmax(0,1fr); align-items: center; column-gap: 2.6mm;
              padding: 0 3.5mm; border-bottom: .25mm solid var(--rule-soft); }
        .bi:last-child { border-bottom: 0; }
        .bi:nth-child(even) { background: #fafbfd; }
        .bi .en { font-size: 7.8pt; line-height: 1.35; color: var(--ink); font-weight: 500; }
        .bi .en b { font-weight: 800; color: var(--navy); }
        .bi .ar { font-size: 8.4pt; line-height: 1.55; color: var(--ink); font-weight: 600; text-align: right; }
        .bi .mk { justify-self: center; width: 7mm; height: 7mm; border-radius: 50%; display: grid; place-items: center;
                  border: .35mm solid var(--gold); color: var(--navy); }
        .bi .mk svg { width: 3.8mm; height: 3.8mm; }
        .bi .mk.num { font-family: var(--f-cond); font-weight: 900; font-size: 11pt; background: var(--navy); color: var(--gold); border-color: var(--navy); }
        .req .bi { min-height: 11.2mm; }
        .terms .bi { min-height: 11.6mm; }

        /* Declaration */
        .decl { display: grid; grid-template-columns: 1fr 1fr; gap: .3mm; background: var(--rule-soft); }
        .dp { background: var(--paper); padding: 3mm 4mm 3.2mm; display: flex; flex-direction: column; gap: 2.6mm; }
        .dp .lead { font-size: 8.2pt; line-height: 1.5; color: var(--ink); font-weight: 500; }
        .dp .lead b { color: var(--navy); font-weight: 800; }
        .dp.ar .lead { font-size: 9pt; font-weight: 600; line-height: 1.7; }
        .sig { display: grid; grid-template-columns: auto 1fr; align-items: end; gap: 2mm; }
        .sig .k { font-family: var(--f-cond); font-weight: 800; font-size: 8pt; letter-spacing: .12em; text-transform: uppercase; color: var(--gold-ink); white-space: nowrap; }
        .dp.ar .sig .k { font-family: var(--f-ar); letter-spacing: 0; font-size: 8.6pt; font-weight: 800; color: var(--navy); }
        .sig .l { height: 7mm; border-bottom: .35mm solid var(--navy); }
        .sig.sign .l { height: 9mm; }
        .guard { grid-column: span 2; background: var(--tint); padding: 2.6mm 4mm 3.2mm; display: grid; grid-template-columns: 1fr 1fr; gap: 3mm 8mm; }
        .guard .hd { grid-column: span 2; display: flex; justify-content: space-between; align-items: center; }
        .guard .hd .en { font-family: var(--f-cond); font-weight: 800; font-size: 9pt; letter-spacing: .12em; text-transform: uppercase; color: var(--navy); }
        .guard .hd .ar { font-weight: 800; font-size: 9pt; color: var(--navy); }
        .u18 { display: inline-flex; align-items: center; gap: 1mm; background: var(--navy); color: var(--gold); font-family: var(--f-cond);
               font-weight: 900; font-size: 8pt; padding: .4mm 1.8mm; border-radius: .6mm; margin-inline-end: 2mm; letter-spacing: .06em; }
        .guard .sig .k { font-size: 7pt; }

        /* Office use */
        .office-sec { border-color: var(--navy); }
        .office-sec .sh { background: var(--navy); border-bottom-color: var(--gold); }
        .office-sec .sh .en, .office-sec .sh .ar { color: #fff; }
        .office-sec .sh .n { color: var(--gold); }
        .office-sec .sh .tag { color: var(--gold); display: inline-flex; align-items: center; gap: 1mm; }
        .ofc { display: grid; grid-template-columns: 1fr 46mm; gap: .3mm; background: var(--rule-soft); }
        .ofc-l { display: grid; grid-template-columns: 1fr 1fr; gap: .3mm; }
        .ofc-l .f { min-height: 12.5mm; }
        .chk { background: var(--paper); padding: 2.2mm 2.4mm; display: flex; flex-direction: column; gap: 1.8mm; grid-column: span 2; }
        .chk .opts { gap: 2.4mm 5mm; }
        .stamp { background: var(--paper); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2mm; padding: 3mm; }
        .stamp .ring { width: 27mm; height: 27mm; border-radius: 50%; border: .5mm dashed var(--gold-ink); display: grid; place-items: center; position: relative; }
        .stamp .ring::after { content: ""; position: absolute; inset: 2.2mm; border-radius: 50%; border: .25mm solid var(--rule); }
        .stamp .ring span { font-family: var(--f-cond); font-weight: 800; font-size: 6.5pt; letter-spacing: .2em; color: #b7bfce; text-transform: uppercase; }
        .stamp .cap { text-align: center; line-height: 1.25; }
        .stamp .cap .en { display: block; font-family: var(--f-cond); font-weight: 800; font-size: 7.4pt; letter-spacing: .12em; text-transform: uppercase; color: var(--navy); }
        .stamp .cap .ar { display: block; font-weight: 800; font-size: 8pt; color: var(--gold-ink); }

        /* Footer */
        .foot { position: absolute; left: 15mm; right: 15mm; bottom: 7mm; display: flex; align-items: center; gap: 4mm; font-size: 7pt; color: var(--muted); }
        .foot .x { display: inline-flex; align-items: center; gap: 1.6mm; font-weight: 700; color: var(--navy); }
        .foot .x .ar { font-weight: 700; font-size: 7.6pt; }
        .foot .x i { width: 1.2mm; height: 1.2mm; background: var(--gold); transform: rotate(45deg); display: inline-block; }
        .foot .pg { margin-inline-start: auto; font-family: var(--f-cond); font-weight: 800; font-size: 9pt; letter-spacing: .1em; color: var(--gold-ink); }
        .foot .ref { font-family: var(--f-cond); font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        .foot .ref b { display: inline-block; width: 22mm; border-bottom: .3mm solid var(--rule); margin-inline-start: 1.4mm; }

        @if($hostLogo)
        .hlogo { background: url("{{ $hostLogo }}") center / contain no-repeat; }
        @endif
    </style>
</head>
<body>
@php
    // Every label twice — English, then Arabic — read with an explicit locale.
    $en = fn (string $k) => __('events::regform.'.$k, [], 'en');
    $ar = fn (string $k) => __('events::regform.'.$k, [], 'ar');
    // The lang strings carry <b> for emphasis and nothing else: escape the
    // whole string, then give back exactly that one tag (the interface strings
    // can be corrected from the database, so they are not trusted as HTML).
    $rich = fn (string $k, string $l) => str_replace(['&lt;b&gt;', '&lt;/b&gt;'], ['<b>', '</b>'], e(__('events::regform.'.$k, [], $l)));
    $lb = function (string $k, ?string $hintEn = null, ?string $hintAr = null) use ($en, $ar) {
        $he = $hintEn !== null ? $en($hintEn) : '';
        $ha = $hintAr !== null ? $ar($hintAr) : '';

        return '<div class="lb"><span class="en">'.e($en($k)).($he !== '' ? '<small>'.e($he).'</small>' : '').'</span>'
             .'<span class="ar">'.e($ar($k)).($ha !== '' ? '<small>'.e($ha).'</small>' : '').'</span></div>';
    };
    // A filled form (RegistrationFormPdf::fill) or a blank one — the same boxes either way.
    $fill = $fill ?? null;
    $fv = fn ($v) => ($v !== null && $v !== '') ? '<span class="fv">'.e($v).'</span>' : '';
    $tick = '<svg viewBox="0 0 16 16" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3.2 8.4 6.6 11.6 12.8 4.6"/></svg>';
    $ck = fn (bool $on) => $on ? '<span class="ck on">'.$tick.'</span>' : '<span class="ck"></span>';
    $date = function (?array $dmy = null) {
        $box = fn (string $s) => implode('', array_map(fn ($c) => '<span>'.e($c).'</span>', str_split($s)));
        [$d, $m, $y] = $dmy ?? ['  ', '  ', '    '];

        return '<div class="digits">'.$box($d).'<i>/</i>'.$box($m).'<i>/</i>'.$box($y).'</div>';
    };
    $icons = [
        '<path d="M8 1.5 2.5 3.6v4.1c0 3.3 2.3 5.8 5.5 6.8 3.2-1 5.5-3.5 5.5-6.8V3.6z"/>',
        '<rect x="1.8" y="4" width="12.4" height="9.2" rx="1.4"/><path d="M5.6 4 6.6 2.4h2.8L10.4 4"/><circle cx="8" cy="8.6" r="2.3"/>',
        '<path d="M8 2v12M2 8h12"/>',
        '<path d="M10.8 2.2 13.8 5.2 5.6 13.4H2.6v-3z"/>',
        '<circle cx="8" cy="8" r="6"/><path d="M3.8 12.2 12.2 3.8"/>',
    ];
@endphp

{{-- ═══════════════ PAGE 1 — the applicant ═══════════════ --}}
<section class="page">
    <header class="band">
        <span class="stripes"></span>
        @if($hostLogo)<span class="hlogo" role="img" aria-label="{{ $host }}"></span>@else<span></span>@endif
        <div class="who">
            <div class="eye">{{ $en('eyebrow') }}</div>
            <h1 class="fit" data-lines="2">{{ $titleEn }}</h1>
            <div class="when">{{ $dateEn }}@if($sportEn)<i></i>{{ $sportEn }}@endif</div>
        </div>
        <div class="who-ar">
            <div class="eye">{{ $ar('eyebrow') }}</div>
            @if($titleAr)
                <div class="t fit" data-lines="2"><bdi>{{ $titleAr }}</bdi></div>
            @else
                <div class="t fit" data-lines="2">{{ $ar('title') }}@if($sportAr)<b>{{ $sportAr }}</b>@endif</div>
            @endif
            <div class="d">{{ $dateAr }}</div>
        </div>
        <span class="slab"></span>
    </header>

    <div class="body">
        {{-- 01 Applicant --}}
        <section class="sec">
            <div class="sh"><span class="n">01</span><span class="en">{{ $en('s_applicant') }}</span><span class="ar">{{ $ar('s_applicant') }}</span></div>
            <div class="top">
                <div class="g">
                    <div class="f tall c12">
                        {!! $lb('name_en', 'name_en_hint') !!}
                        <div class="parts">@foreach(['given', 'middle', 'surname'] as $i => $k)<span>{!! $fv($fill['name_en'][$i] ?? null) !!}{{ $en($k) }}</span>@endforeach</div>
                    </div>
                    <div class="f tall c12">
                        {!! $lb('name_ar', null, 'name_ar_hint') !!}
                        <div class="parts ar-in">@foreach(['given', 'middle', 'surname'] as $i => $k)<span>{!! $fv($fill['name_ar'][$i] ?? null) !!}{{ $ar($k) }}</span>@endforeach</div>
                    </div>
                </div>
                <div class="photo">
                    <div>
                        @if($fill['photo'] ?? null)
                            <img class="face" src="{{ $fill['photo'] }}" alt="">
                        @else
                            <svg viewBox="0 0 60 64" aria-hidden="true"><circle cx="30" cy="22" r="12" fill="#dde2eb"/><path d="M4 64c2-15 13-22 26-22s24 7 26 22z" fill="#dde2eb"/></svg>
                            <div class="cap"><div class="en">{{ $en('photo') }}</div><div class="ar">{{ $ar('photo') }}</div><small>{{ $en('photo_sub') }}</small></div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="g">
                <div class="f c5">{!! $lb('dob') !!}{!! $date($fill['dob'] ?? null) !!}</div>
                <div class="f c4">
                    {!! $lb('gender') !!}
                    <div class="opts">
                        <span class="opt male">{!! $ck(($fill['gender'] ?? null) === 'male') !!}<span class="en">{{ $en('male') }}</span><span class="sl">/</span><span class="ar">{{ $ar('male') }}</span></span>
                        <span class="opt female">{!! $ck(($fill['gender'] ?? null) === 'female') !!}<span class="en">{{ $en('female') }}</span><span class="sl">/</span><span class="ar">{{ $ar('female') }}</span></span>
                    </div>
                </div>
                <div class="f c3">{!! $lb('nationality') !!}<div class="wr">{!! $fv($fill['nationality'] ?? null) !!}</div></div>

                <div class="f c7">
                    {!! $lb('id_no') !!}
                    <div class="digits">@for($i = 0; $i < 12; $i++)<span></span>@endfor</div>
                </div>
                <div class="f c5">{!! $lb('app_date') !!}{!! $date($fill['applied'] ?? null) !!}</div>

                <div class="f c4">{!! $lb('style') !!}<div class="wr"></div></div>
                <div class="f c3">{!! $lb('rank', $rankHint ? 'rank_hint' : null) !!}<div class="wr">@if($fill['rank'] ?? null)<span class="fv-belt {{ $fill['rank']['pale'] ? 'pale' : '' }}" style="background: {{ preg_match('/^#[0-9A-Fa-f]{3,8}$/', $fill['rank']['colour']) ? $fill['rank']['colour'] : '#9aa3b2' }}"></span>{!! $fv($fill['rank']['label']) !!}@endif</div></div>
                <div class="f c5">{!! $lb('club') !!}<div class="wr">@if($fill['club_logo'] ?? null)<img class="fv-logo" src="{{ $fill['club_logo'] }}" alt="">@endif{!! $fv($fill['club'] ?? null) !!}</div></div>

                <div class="f c2">{!! $lb('height') !!}<div class="wr">{!! $fv($fill['height'] ?? null) !!}<span class="u">cm</span></div></div>
                <div class="f c2">{!! $lb('weight') !!}<div class="wr">{!! $fv($fill['weight'] ?? null) !!}<span class="u">kg</span></div></div>
                <div class="f c4">{!! $lb('mobile') !!}<div class="wr"><bdi>{!! $fv($fill['mobile'] ?? null) !!}</bdi></div></div>
                <div class="f c4">{!! $lb('phone') !!}<div class="wr"></div></div>

                <div class="f c5">{!! $lb('emergency') !!}<div class="wr">{!! $fv($fill['emergency']['name'] ?? null) !!}</div></div>
                <div class="f c3">{!! $lb('relation') !!}<div class="wr">{!! $fv($fill['emergency']['relation'] ?? null) !!}</div></div>
                <div class="f c4">{!! $lb('their_mobile') !!}<div class="wr"><bdi>{!! $fv($fill['emergency']['phone'] ?? null) !!}</bdi></div></div>
            </div>
        </section>

        {{-- 02 Entry — the sport's own disciplines, or age group + weight category --}}
        <section class="sec">
            <div class="sh"><span class="n">02</span><span class="en">{{ $en('s_entry') }}</span>@if($disciplines)<span class="tag">{{ $en('tick_all') }}</span>@endif<span class="ar">{{ $ar('s_entry') }}</span></div>
            <div class="disc" @if(count($disciplines) !== 2) style="grid-template-columns: repeat({{ max(1, count($disciplines)) }}, 1fr) 1.3fr" @endif>
                @forelse($disciplines as $d)
                    <div class="dc">{!! $ck($fill !== null && collect($fill['divisions'])->contains(fn ($n) => mb_stripos($n, $d['en']) !== false)) !!}<div class="t"><span class="en">{{ $d['en'] }}</span><span class="ar">{{ $d['ar'] }}</span><small>{{ $d['sub'] }}</small></div></div>
                @empty
                    <div class="dc office" style="background:var(--paper)">{!! $lb('age_group') !!}<div class="wr"></div></div>
                    <div class="dc office" style="background:var(--paper)">{!! $lb('weight_class') !!}<div class="wr"><span class="u">kg</span></div></div>
                @endforelse
                <div class="dc office">{!! $lb('division', 'division_hint') !!}@if($fill && $fill['divisions'])<div class="dvs">@foreach($fill['divisions'] as $dn)<span class="fv"><bdi>{{ $dn }}</bdi></span>@endforeach</div>@else<div class="wr"></div>@endif</div>
            </div>
        </section>

        {{-- 03 Requirements --}}
        <section class="sec req">
            <div class="sh"><span class="n">03</span><span class="en">{{ $en('s_requirements') }}</span><span class="ar">{{ $ar('s_requirements') }}</span></div>
            <div>
                @foreach($icons as $i => $svg)
                    <div class="bi"><span class="en">{!! $rich('req_'.($i + 1), 'en') !!}</span><span class="mk"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $svg !!}</svg></span><span class="ar">{!! $rich('req_'.($i + 1), 'ar') !!}</span></div>
                @endforeach
            </div>
        </section>
    </div>

    <footer class="foot">
        <span class="x"><i></i>{{ $fee ? __('events::regform.fee', ['amount' => $fee], 'en').' · ' : '' }}{{ $en('fees_advance') }} <span class="ar">· {{ $ar('fees_advance') }}</span></span>
        <span class="ref">{{ $en('entry_no') }}<b>@if($fill)<span class="fv" style="font-size:8pt">{{ $fill['entry_no'] }}</span>@endif</b></span>
        <span class="pg">1 / 2</span>
    </footer>
</section>

{{-- ═══════════════ PAGE 2 — terms, declaration, the office ═══════════════ --}}
<section class="page">
    <header class="band short">
        <span class="stripes"></span>
        @if($hostLogo)<span class="hlogo" role="img" aria-label="{{ $host }}"></span>@else<span></span>@endif
        <div class="who">
            <div class="eye fit-line">{{ $titleEn }} · {{ $dateEn }}</div>
            <h1>{{ $en('terms_title') }}</h1>
        </div>
        <div class="who-ar">
            <div class="eye fit-line"><bdi>{{ $titleAr ?? $ar('eyebrow') }}</bdi> · {{ $dateAr }}</div>
            <div class="t">{{ $ar('terms_title') }}</div>
        </div>
        <span class="slab"></span>
    </header>

    <div class="body">
        {{-- 04 Terms --}}
        <section class="sec terms">
            <div class="sh"><span class="n">04</span><span class="en">{{ $en('s_terms') }}</span><span class="ar">{{ $ar('s_terms') }}</span></div>
            <div>
                @for($i = 1; $i <= 7; $i++)
                    <div class="bi"><span class="en">{!! $rich('term_'.$i, 'en') !!}</span><span class="mk num">{{ $i }}</span><span class="ar">{!! $rich('term_'.$i, 'ar') !!}</span></div>
                @endfor
            </div>
        </section>

        {{-- 05 Declaration --}}
        <section class="sec">
            <div class="sh"><span class="n">05</span><span class="en">{{ $en('s_declaration') }}</span><span class="ar">{{ $ar('s_declaration') }}</span></div>
            <div class="decl">
                <div class="dp">
                    <p class="lead" style="margin:0">{!! $rich('declaration', 'en') !!}</p>
                    <div class="sig"><span class="k">{{ $en('name') }}</span><span class="l"></span></div>
                    <div class="sig sign"><span class="k">{{ $en('signature') }}</span><span class="l"></span></div>
                </div>
                <div class="dp ar">
                    <p class="lead" style="margin:0">{!! $rich('declaration', 'ar') !!}</p>
                    <div class="sig"><span class="k">{{ $ar('name') }}</span><span class="l"></span></div>
                    <div class="sig sign"><span class="k">{{ $ar('signature') }}</span><span class="l"></span></div>
                </div>
                <div class="guard">
                    <div class="hd"><span class="en"><span class="u18">U18</span>{{ $en('guardian') }}</span><span class="ar">{{ $ar('guardian') }}</span></div>
                    <div class="sig"><span class="k">{{ $en('name') }} · <span class="ar">{{ $ar('name') }}</span></span><span class="l"></span></div>
                    <div class="sig"><span class="k">{{ $en('signature') }} · <span class="ar">{{ $ar('signature') }}</span></span><span class="l"></span></div>
                </div>
            </div>
        </section>

        {{-- 06 Office --}}
        <section class="sec office-sec">
            <div class="sh"><span class="n">06</span><span class="en">{{ $en('s_office') }}</span><span class="tag"><svg viewBox="0 0 16 16" width="9" height="9" fill="currentColor"><path d="M4.5 7V5a3.5 3.5 0 0 1 7 0v2h.5A1.5 1.5 0 0 1 13.5 8.5v5A1.5 1.5 0 0 1 12 15H4a1.5 1.5 0 0 1-1.5-1.5v-5A1.5 1.5 0 0 1 4 7zm1.5 0h4V5a2 2 0 0 0-4 0z"/></svg>{{ $en('office_only') }}</span><span class="ar">{{ $ar('s_office') }}</span></div>
            <div class="ofc">
                <div class="ofc-l">
                    <div class="chk">
                        {!! $lb('received') !!}
                        <div class="opts">
                            @foreach(['fee_paid', 'photo', 'passport', 'equipment'] as $k)
                                <span class="opt">{!! $ck(match ($k) { 'fee_paid' => (bool) ($fill['paid'] ?? false), 'photo' => ! empty($fill['photo']), default => false }) !!}<span class="en">{{ $en($k) }}</span><span class="ar">{{ $ar($k) }}</span></span>
                            @endforeach
                        </div>
                    </div>
                    <div class="f">{!! $lb('receipt') !!}<div class="wr"></div></div>
                    <div class="f">{!! $lb('weigh_in') !!}<div class="wr">{!! ($fill['weighed'] ?? false) ? $fv($fill['weight']) : '' !!}<span class="u">kg</span></div></div>
                    <div class="f">{!! $lb('received_by') !!}<div class="wr"></div></div>
                    <div class="f">{!! $lb('date') !!}{!! $date() !!}</div>
                </div>
                <div class="stamp">
                    <div class="ring"><span>{{ $en('stamp') }}</span></div>
                    <div class="cap"><span class="en">{{ $en('stamp_approval') }}</span><span class="ar">{{ $ar('stamp_approval') }}</span></div>
                </div>
            </div>
        </section>
    </div>

    <footer class="foot">
        <span class="x"><i></i>{{ $en('fees_nonrefundable') }} <span class="ar">· {{ $ar('fees_nonrefundable') }}</span></span>
        <span class="ref">{{ $en('entry_no') }}<b>@if($fill)<span class="fv" style="font-size:8pt">{{ $fill['entry_no'] }}</span>@endif</b></span>
        <span class="pg">2 / 2</span>
    </footer>
</section>

<script>
    // Event titles are long and come in two scripts: each title is set smaller
    // until it fits its lines, never cut. One-line eyebrows likewise.
    document.fonts.ready.then(function () {
        document.querySelectorAll('.fit').forEach(function (el) {
            var lines = parseInt(el.dataset.lines || '1', 10);
            var size = parseFloat(getComputedStyle(el).fontSize);
            var lh = function () { return parseFloat(getComputedStyle(el).lineHeight) || size * 1.1; };
            while (el.offsetHeight > lh() * lines + 1 && size > 10) { size -= 0.5; el.style.fontSize = size + 'px'; }
        });
        document.querySelectorAll('.fit-line').forEach(function (el) {
            var size = parseFloat(getComputedStyle(el).fontSize);
            while (el.scrollWidth > el.clientWidth + 0.5 && size > 5) { size -= 0.25; el.style.fontSize = size + 'px'; }
        });
    });
</script>
</body>
</html>
