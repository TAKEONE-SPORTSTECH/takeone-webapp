{{--
    The competition report's head (FederationCompetitionReport): fonts, flags,
    the draw report's sheet (participants-pdf-style), the shared club and
    officials blocks, and this report's own pieces — cards, figure tiles,
    tables and charts. Charts are plain HTML/SVG: no library, nothing fetched.

    Colour by job (validated, dataviz method): brand navy + gold; fixed
    conventions — corners red/blue, gender blue/pink, medals metal; categories
    in fixed order #2a78d6 #eb6834 #1baf7a #eda100; ordinal ramps blue (score
    types) and orange (penalty levels). Every value is printed beside its mark.
--}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
{{-- Our own light page: never let a browser, WebView or extension force-darken it (see layouts/tailwind). --}}
<meta name="color-scheme" content="light">
<meta name="darkreader-lock">
<style>html { color-scheme: only light; }</style>
<title>{{ $title }} · Competition report</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,600;0,700;0,800;0,900;1,800;1,900&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="{{ $flagCss }}">
@include('events::participants-pdf-style')
<style>
    @include('events::pdf-club-style')
    @include('events::pdf-team-style')
    @include('federations::partials.club-faces-style')
    .bracket-icon { display: inline-block; transform: rotate(90deg); }
    .cond { font-family: 'Barlow Condensed', 'Inter', sans-serif; }
    .body { padding: 6mm 15mm 0; }
    .sec { display: flex; align-items: center; gap: 2.5mm; font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 800; text-transform: uppercase;
           letter-spacing: .08em; font-size: 11pt; color: {{ $navy }}; margin: 0 0 2.5mm; }
    .sec::before { content: ''; width: 7mm; height: 2mm; background: {{ $c }}; transform: skewX(-20deg); flex: 0 0 auto; }
    .sec small { margin-inline-start: auto; font-family: 'Inter', sans-serif; font-size: 6.6pt; font-weight: 700; letter-spacing: .08em; color: #8a93a8; }
    .card { border: .35mm solid #e3e6ee; border-radius: 2mm; padding: 4mm; background: #fff; break-inside: avoid; }
    .grid { display: grid; gap: 4mm; }
    .g2 { grid-template-columns: 1fr 1fr; } .g3 { grid-template-columns: repeat(3, 1fr); } .g4 { grid-template-columns: repeat(4, 1fr); }
    .muted { color: #6b7590; } .ink { color: {{ $navy }}; }
    .note { font-size: 7.4pt; line-height: 1.5; color: #4a5470; }
    .note b { color: {{ $navy }}; }

    /* Figure tiles: a coloured edge, the number, what it counts. */
    .kpi { position: relative; overflow: hidden; border: .35mm solid #e3e6ee; border-radius: 2mm; padding: 3.2mm 3.5mm 3mm 4.6mm; background: #fff; }
    .kpi::before { content: ''; position: absolute; inset-inline-start: 0; top: 0; bottom: 0; width: 1.4mm; background: var(--k, {{ $c }}); }
    .kpi b { display: block; font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-size: 24pt; line-height: .95; color: {{ $navy }}; }
    .kpi b small { font-size: 11pt; font-weight: 800; color: #8a93a8; margin-inline-start: .8mm; }
    .kpi span { display: block; margin-top: 1.2mm; font-size: 6.6pt; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: #6b7590; }
    .kpi i.ic { position: absolute; inset-inline-end: 3mm; top: 2.6mm; font-size: 12pt; color: var(--k, {{ $c }}); opacity: .85; }
    .kpi.dark { background: {{ $navy }}; border-color: {{ $navy }}; }
    .kpi.dark b { color: #fff; } .kpi.dark span { color: {{ $c }}; }

    /* Horizontal bars: label · track · value. */
    .hb { display: grid; grid-template-columns: var(--lw, 34mm) minmax(0, 1fr) 12mm; align-items: center; column-gap: 2.5mm; height: var(--rh, 6.4mm); }
    .hb .lb { display: flex; align-items: center; gap: 1.6mm; min-width: 0; font-size: 7.4pt; font-weight: 700; color: {{ $navy }}; white-space: nowrap; overflow: hidden; }
    .hb .lb .fi { width: 4.2mm; height: 3.15mm; flex: 0 0 auto; border-radius: .4mm; box-shadow: 0 0 0 .2mm rgba(11,19,43,.12); }
    .hb .lb img { width: 4.6mm; height: 4.6mm; object-fit: contain; flex: 0 0 auto; }
    .hb .tr { height: 3.4mm; border-radius: 1.7mm; background: #f0f2f7; overflow: hidden; display: flex; }
    .hb .tr i { display: block; height: 100%; }
    .hb .tr i + i { box-shadow: -.5mm 0 0 #fff; }
    .hb .vl { font-size: 7.6pt; font-weight: 800; color: {{ $navy }}; text-align: end; font-variant-numeric: tabular-nums; white-space: nowrap; }

    /* Columns: value on top, bar, label beneath — values printed on every column. */
    .cols { display: flex; align-items: flex-end; gap: 1.6mm; height: var(--ch, 40mm); padding-top: 5mm; border-bottom: .35mm solid #c9d0db; }
    .cols > div { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; min-width: 0; }
    .cols .v { font-size: 6.8pt; font-weight: 800; color: {{ $navy }}; margin-bottom: .8mm; font-variant-numeric: tabular-nums; }
    .cols .bar { width: 100%; max-width: 13mm; border-radius: 1mm 1mm 0 0; min-height: .6mm; }
    .xl { display: flex; gap: 1.6mm; margin-top: 1.4mm; }
    .xl span { flex: 1; text-align: center; font-size: 6.2pt; font-weight: 700; color: #6b7590; white-space: nowrap; }

    /* Legend chips. */
    .lg { display: flex; flex-wrap: wrap; gap: 1.6mm 3.5mm; margin-top: 2.4mm; }
    .lg span { display: inline-flex; align-items: center; gap: 1.2mm; font-size: 6.8pt; font-weight: 700; color: #4a5470; }
    .lg i { width: 2.8mm; height: 2.8mm; border-radius: .7mm; display: inline-block; }

    /* Donut + its legend side by side. */
    .dn { display: flex; align-items: center; gap: 4mm; }
    .dn svg { flex: 0 0 auto; }
    .dn .rows { flex: 1; min-width: 0; }
    .dn .r { display: grid; grid-template-columns: 2.8mm minmax(0, 1fr) auto 9mm; align-items: center; column-gap: 1.8mm; padding: 1.1mm 0; border-bottom: .25mm solid #eef1f6; font-size: 7.4pt; }
    .dn .r:last-child { border-bottom: 0; }
    .dn .r i { width: 2.8mm; height: 2.8mm; border-radius: .7mm; }
    .dn .r span { font-weight: 700; color: {{ $navy }}; white-space: nowrap; overflow: hidden; }
    .dn .r b { font-weight: 800; color: {{ $navy }}; font-variant-numeric: tabular-nums; text-align: end; }
    .dn .r em { font-style: normal; font-weight: 700; color: #8a93a8; text-align: end; font-size: 6.8pt; }

    /* Tables. */
    table.t { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 7.6pt; }
    table.t th { background: {{ $navy }}; color: #fff; font-size: 6.2pt; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; padding: 0 2mm; height: 7mm; text-align: start; }
    table.t th:first-child { border-radius: 1.5mm 0 0 0; } table.t th:last-child { border-radius: 0 1.5mm 0 0; }
    table.t th.c, table.t td.c { text-align: center; }
    table.t td { height: 9mm; padding: 0 2mm; border-bottom: .25mm solid #e3e6ee; color: {{ $navy }}; vertical-align: middle; }
    table.t tr:nth-child(even) td { background: #fafbfd; }
    table.t td.n { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-size: 12pt; font-variant-numeric: tabular-nums; }
    table.t .who { display: flex; align-items: center; gap: 2mm; font-weight: 800; white-space: nowrap; }
    table.t .who img.lg { width: 6.5mm; height: 6.5mm; object-fit: contain; }
    table.t .who .fi { width: 5.2mm; height: 3.9mm; border-radius: .5mm; box-shadow: 0 0 0 .2mm rgba(11,19,43,.15); flex: 0 0 auto; }
    .rk { display: inline-grid; place-items: center; width: 6.6mm; height: 6.6mm; border-radius: 50%; font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-size: 10pt; color: {{ $navy }}; background: #eef1f6; }
    .rk.r1 { background: linear-gradient(140deg,#f8e4a0,#d4a940 55%,#b8892a); } .rk.r2 { background: linear-gradient(140deg,#f4f6fa,#b9c1ce 55%,#97a1b4); } .rk.r3 { background: linear-gradient(140deg,#f0c49f,#c9895b 55%,#a2643a); }
    .mbar { display: flex; height: 2.6mm; border-radius: 1.3mm; overflow: hidden; background: #f0f2f7; min-width: 18mm; }
    .mbar i { display: block; height: 100%; } .mbar i + i { box-shadow: -.4mm 0 0 #fff; }

    /* Faces. */
    .face { width: 7.5mm; height: 10mm; border-radius: .8mm; object-fit: cover; object-position: center top; background: #eef1f6; flex: 0 0 auto; border: .25mm solid #dfe3ea; }
    .face.big { width: 15mm; height: 20mm; border-radius: 1.4mm; }
    .face.none { opacity: .6; }
</style>
