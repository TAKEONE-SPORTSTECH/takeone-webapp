{{--
    The participants PDF's page (A4, the draw's navy and gold) — shared by the
    officials' PDF (events::officials-pdf). Expects \$c, \$navy, \$goldInk and \$hostLogo.
--}}
    <style>
        @page { size: A4 portrait; margin: 0; }
        html, body { margin: 0; padding: 0; background: #fff; }
        body { font-family: 'Inter', system-ui, 'Noto Sans Arabic', sans-serif; color: #1e2c4f;
               -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        * { box-sizing: border-box; }

        .pdf-page { position: relative; overflow: hidden; width: 210mm; height: 297mm;
                    break-after: page; page-break-after: always; }
        .pdf-page:last-child { break-after: auto; page-break-after: auto; }

        .cond { font-family: 'Barlow Condensed', 'Inter', sans-serif; }
        .band { position: relative; overflow: hidden; color: #fff; background: {{ $navy }};
                height: 44mm; padding: 11mm 15mm 0; display: flex; align-items: flex-start; gap: 8mm; }
        /* Inside the band's own box: anything hanging outside it makes Chromium
           shrink every page to fit, even though overflow hides it. */
        .band .stripes { position: absolute; right: 0; top: 0; bottom: 0; width: 90mm; opacity: .16;
                         background: repeating-linear-gradient(115deg, {{ $c }} 0 3mm, transparent 3mm 9mm); }
        .band .slab { position: absolute; left: 0; right: 0; bottom: 0; height: 9mm; background: {{ $c }};
                      clip-path: polygon(0 55%, 100% 0, 100% 100%, 0 100%); }
        .eye { position: relative; font-size: 7.5pt; font-weight: 800; letter-spacing: .28em; text-transform: uppercase; color: {{ $c }};
               white-space: nowrap; overflow: hidden; }
        .band h1 { position: relative; margin: 2mm 0 0; font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-style: italic;
                   text-transform: uppercase; font-size: 36pt; line-height: .9; white-space: nowrap; }
        .band .sub { position: relative; font-size: 8.5pt; color: #c9d0e2; margin-top: 2mm; white-space: nowrap; }
        .band .who { position: relative; min-width: 0; flex: 1; }
        /* The organiser's logo, bare (Design Rule #5: never on a tile). One copy, as a background. */
        .hlogo { position: relative; flex: 0 0 auto; width: 15mm; height: 15mm; margin-top: 3mm; display: block; }
        @if($hostLogo)
        .hlogo { background: url("{{ $hostLogo }}") center / contain no-repeat; }
        @endif
        .totals { position: relative; margin-inline-start: auto; display: flex; gap: 7mm; font-family: 'Barlow Condensed', 'Inter', sans-serif;
                  text-transform: uppercase; text-align: end; padding-top: 4mm; }
        .totals b { display: block; font-size: 30pt; font-weight: 900; line-height: .9; }
        .totals span { font-size: 8.5pt; font-weight: 700; letter-spacing: .18em; color: {{ $c }}; }

        .body { padding: 1mm 15mm 0; }

        /* One block per division: a bar with its name and size, the column
           heads, then its people. Heights are FIXED — the server paginates by them. */
        .dv { margin-top: 5mm; border: .35mm solid #cfd5e0; border-radius: 1.5mm; overflow: hidden; }
        .dv-h { height: 9mm; display: flex; align-items: center; gap: 2.5mm; padding: 0 3.5mm; background: #f4f6fa; border-bottom: .6mm solid {{ $navy }}; }
        .dv-h .g { width: 2.4mm; height: 2.4mm; border-radius: 50%; background: #9aa3b2; flex: 0 0 auto; }
        .dv-h .g.male { background: #2563eb; } .dv-h .g.female { background: #db2777; }
        .dv-h .nm { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 800; text-transform: uppercase; font-size: 13pt;
                    letter-spacing: .05em; color: {{ $navy }}; white-space: nowrap; overflow: hidden; min-width: 0; }
        .dv-h .cont { font-size: 7pt; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: #8a93a8; white-space: nowrap; }
        .dv-h .n { margin-inline-start: auto; font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-size: 15pt; color: {{ $goldInk }}; }
        .dv-c, .r { display: grid; align-items: center; column-gap: 2.5mm; padding: 0 3.5mm; }
        .dv-c { height: 5mm; font-size: 6.2pt; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: {{ $goldInk }};
                border-bottom: .25mm solid #e3e6ee; }
        .r { border-bottom: .25mm solid #e3e6ee; }
        .r:last-child { border-bottom: 0; }
        .r:nth-child(even) { background: #fafbfd; }
        .grid-list { grid-template-columns: 6mm 7.5mm minmax(0, 1fr) 40mm 11mm 10mm 16mm 12mm; }
        .grid-checkin { grid-template-columns: 7mm 6mm 7.5mm minmax(0, 1fr) 33mm 11mm 10mm 11mm 20mm 10mm; }
        /* By club: the club is the block's heading, so its column carries the divisions instead. */
        .grid-checkin.by-club { grid-template-columns: 7mm 6mm 7.5mm minmax(0, 1fr) minmax(0, 1fr) 11mm 8mm 11mm 18mm 9mm; }
        .dvn { min-width: 0; display: flex; flex-direction: column; justify-content: center; gap: .3mm; }
        .dvn span { font-size: 6.6pt; font-weight: 700; color: #5d6880; text-transform: uppercase; letter-spacing: .02em;
                    white-space: nowrap; overflow: hidden; line-height: 1.35; }
        .dv-h .lg { width: 6.5mm; height: 6.5mm; object-fit: contain; flex: 0 0 auto; }
        /* Gender: the platform's blue and pink, icon and a short word. */
        .sx { display: flex; align-items: center; justify-content: center; gap: .8mm; font-size: 7pt; font-weight: 800; white-space: nowrap; }
        .sx.male { color: #2563eb; } .sx.female { color: #db2777; } .sx.none { color: #b4bac7; }
        .sx i { font-size: 8pt; }
        .r.grid-list { height: 12.5mm; }
        .r.grid-checkin { height: 11mm; }

        .no { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 800; font-size: 10pt; color: #9aa3b2; font-variant-numeric: tabular-nums; }
        /* The face, at the platform's 3:4. */
        .ph { width: 7.5mm; height: 10mm; background: #eef1f6; overflow: hidden; border: .25mm solid #cfd5e0; }
        .ph img { width: 100%; height: 100%; object-fit: cover; object-position: center top; display: block; }
        .ph img.none { opacity: .55; }
        .who-c { min-width: 0; }
        /* The flag, then the name, aligned to the row whatever script the name
           is written in (the <bdi> keeps an Arabic name's own direction). */
        .an { display: flex; align-items: center; gap: 1.6mm; font-weight: 800; color: {{ $navy }}; white-space: nowrap; overflow: hidden; line-height: 1.6; }
        .grid-list .an { font-size: 10pt; }
        .grid-checkin .an { font-size: 9pt; }
        .an .fi { width: 4.2mm; height: 3.15mm; flex: 0 0 auto; border-radius: .4mm; box-shadow: 0 0 0 .2mm rgba(11,19,43,.12); }
        .an bdi { flex: 0 0 auto; }
        .as { font-size: 7pt; color: #6b7590; font-weight: 600; line-height: 1.3; }
        .cl { display: flex; align-items: center; gap: 1.6mm; min-width: 0; font-weight: 700; color: #5d6880; text-transform: uppercase;
              letter-spacing: .03em; white-space: nowrap; overflow: hidden; line-height: 1.6; }
        .grid-list .cl { font-size: 7.6pt; }
        .grid-checkin .cl { font-size: 7pt; }
        .cl img { width: 6mm; height: 6mm; object-fit: contain; flex: 0 0 auto; }
        .grid-checkin .cl img { width: 4.6mm; height: 4.6mm; }
        .cl span { min-width: 0; overflow: hidden; }
        .bt { display: flex; align-items: center; justify-content: center; }
        .bt svg { width: 11mm; height: auto; }
        .grid-checkin .bt svg { width: 9mm; }
        .ag .kg { font-size: 5.8pt; font-weight: 700; color: #8a93a8; margin-inline-start: .6mm; text-transform: uppercase; }
        .ag { font-size: 8.5pt; font-weight: 700; color: {{ $navy }}; text-align: center; font-variant-numeric: tabular-nums; }

        /* The desk's boxes: drawn empty to be ticked or written in by hand. */
        .box { width: 4.6mm; height: 4.6mm; border: .45mm solid {{ $navy }}; border-radius: .6mm; display: grid; place-items: center; margin: 0 auto; }
        .box.on { background: {{ $navy }}; color: #fff; }
        .box.on i { font-size: 8pt; line-height: 1; }
        .wt { height: 5.6mm; border: .3mm solid #cfd5e0; border-radius: .6mm; display: flex; align-items: center; justify-content: center; gap: 1mm;
              font-size: 8.5pt; font-weight: 800; color: {{ $navy }}; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .wt.signed { border: .45mm solid {{ $c }}; }
        .wt.declared { border: .35mm dashed #8a93a8; color: #5d6880; }
        .wt small { font-size: 5.6pt; font-weight: 700; color: #8a93a8; text-transform: uppercase; letter-spacing: .06em; }
        .ctr { text-align: center; }

        .foot { position: absolute; inset-inline: 15mm; bottom: 8mm; display: flex; align-items: center; gap: 5mm; font-size: 7.5pt; color: #6b7590; }
        .foot .pg { margin-inline-start: auto; font-weight: 800; letter-spacing: .06em; color: {{ $goldInk }}; }
        .foot .lg { display: inline-flex; align-items: center; gap: 1.4mm; }
        .foot .lg i { width: 4mm; height: 2.6mm; border: .45mm solid {{ $c }}; border-radius: .4mm; display: inline-block; }
        .foot .lg i.dash { border: .35mm dashed #8a93a8; }
        .foot .staff { font-weight: 800; color: {{ $navy }}; display: inline-flex; align-items: center; gap: 1.4mm; }
    </style>
