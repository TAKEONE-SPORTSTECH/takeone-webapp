{{--
    The competition report, part A — the front matter (FederationCompetitionReport):
    cover, the competition, contents, officials, medal tables, clubs, divisions.
    Every part's page count was decided by the composer before printing; this
    view follows the same plan, so the contents page and the footers agree.
--}}
@php
    $c = '#d8b25f';
    $navy = '#16213d';
    $goldInk = '#a8862f';
    $pg = 0;
    $metal = [1 => '#d4a940', 2 => '#a9b2c1', 3 => '#c9895b'];
    $kpis = [
        ['bi-people-fill', $athletes, 'Athletes', '#2a78d6'],
        ['bi-shield-fill', count(array_filter($clubs, fn ($x) => ! $x['none'])), 'Clubs', '#eb6834'],
        ['bi-flag-fill', count(array_filter($nations, fn ($n) => $n['iso'])), 'Nations', '#1baf7a'],
        ['bi-list-ol', $divisionCount, 'Divisions', '#eda100'],
        ['bi-lightning-charge-fill', $stats['decided'] ?: ($cover['figures'][3]['value'] ?? 0), 'Bouts fought', '#2a78d6'],
        ['bi-trophy-fill', array_sum(array_map(fn ($x) => array_sum($x['medals']), array_filter($clubs, fn ($x) => ! $x['none']))), 'Medals awarded', '#d8b25f'],
    ];
    $words = preg_split('/\s+/u', trim($title)) ?: [];
    $tl = count($words) >= 3 ? [$words[0], $words[1], implode(' ', array_slice($words, 2))] : [$words[0] ?? '', $words[1] ?? '', ''];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    @include('federations::report.partials.head')
    <style>
        /* ── Cover (generated when the organiser uploaded none) ── */
        .cv { background: {{ $navy }}; color: #fff; padding: 22mm 18mm 0; display: flex; flex-direction: column; }
        .cv .stripes { position: absolute; right: 0; top: 0; bottom: 0; width: 120mm; opacity: .13;
                       background: repeating-linear-gradient(115deg, {{ $c }} 0 4mm, transparent 4mm 12mm); }
        .cv .slab { position: absolute; left: 0; right: 0; bottom: 0; height: 70mm; background: {{ $c }}; clip-path: polygon(0 40%, 100% 0, 100% 100%, 0 100%); }
        .cv .kicker { position: relative; display: inline-flex; align-self: flex-start; padding: 1.8mm 4mm 1.5mm; background: {{ $c }}; color: {{ $navy }};
                      font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-size: 12pt; letter-spacing: .2em; text-transform: uppercase;
                      clip-path: polygon(2.5mm 0, 100% 0, calc(100% - 2.5mm) 100%, 0 100%); }
        .cv .type { position: relative; margin-top: 8mm; font-size: 9pt; font-weight: 800; letter-spacing: .3em; text-transform: uppercase; color: {{ $c }}; }
        .cv h1 { position: relative; margin: 4mm 0 0; max-width: 125mm; font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-style: italic; text-transform: uppercase; line-height: .86; }
        .cv h1 .l1 { display: block; font-size: 70pt; } .cv h1 .l2 { display: block; font-size: 70pt; color: {{ $c }}; } .cv h1 .l3 { display: block; font-size: 34pt; margin-top: 3mm; }
        .cv .by { position: relative; margin-top: 8mm; font-size: 11pt; color: #c9d0e2; } .cv .by b { color: #fff; }
        .cv .when { position: absolute; right: 18mm; top: 22mm; text-align: right; font-family: 'Barlow Condensed', 'Inter', sans-serif; text-transform: uppercase; line-height: .88; }
        .cv .when .d { font-size: 80pt; font-weight: 900; } .cv .when .m { font-size: 20pt; font-weight: 800; color: {{ $c }}; } .cv .when .w { font-size: 10pt; font-weight: 700; letter-spacing: .24em; color: #c9d0e2; margin-top: 1.5mm; }
        .cv .figs { position: absolute; left: 18mm; right: 18mm; bottom: 26mm; display: flex; gap: 12mm; color: {{ $navy }}; font-family: 'Barlow Condensed', 'Inter', sans-serif; text-transform: uppercase; }
        .cv .figs b { display: block; font-size: 40pt; font-weight: 900; line-height: .9; } .cv .figs span { font-size: 9pt; font-weight: 800; letter-spacing: .2em; }
        .cv .logo { position: absolute; right: 18mm; bottom: 74mm; width: 36mm; height: 36mm; }
        @if($hostLogo) .cv .logo { background: url("{{ $hostLogo }}") center / contain no-repeat; } @endif

        /* ── The competition ── */
        .facts .f { display: grid; grid-template-columns: 7mm 1fr; align-items: center; padding: 2.2mm 0; border-bottom: .25mm solid #eef1f6; }
        .facts .f:last-child { border-bottom: 0; }
        .facts .f i { color: {{ $goldInk }}; font-size: 11pt; }
        .facts small { display: block; font-size: 6.4pt; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: #8a93a8; }
        .facts strong { display: block; font-size: 9pt; font-weight: 700; color: {{ $navy }}; line-height: 1.3; }
        .sched .day { display: grid; grid-template-columns: 17mm 1fr; gap: 3mm; padding: 2mm 0; border-bottom: .25mm solid #eef1f6; }
        .sched .day:last-child { border-bottom: 0; }
        .sched .dt { text-align: center; background: {{ $navy }}; color: #fff; border-radius: 1.5mm; padding: 1.4mm 0 1.2mm; font-family: 'Barlow Condensed', 'Inter', sans-serif; line-height: 1; }
        .sched .dt b { display: block; font-size: 15pt; font-weight: 900; } .sched .dt span { font-size: 6.6pt; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: {{ $c }}; }
        .sched .row { display: flex; gap: 3mm; align-items: baseline; font-size: 8pt; padding: .6mm 0; color: {{ $navy }}; }
        .sched .row .tm { font-weight: 800; color: {{ $goldInk }}; min-width: 30mm; font-variant-numeric: tabular-nums; }
        .sched .row em { font-style: normal; color: #8a93a8; font-size: 7pt; }

        /* ── Contents ── */
        .toc .part { margin: 5mm 0 2mm; font-size: 6.6pt; font-weight: 800; letter-spacing: .24em; text-transform: uppercase; color: #8a93a8; }
        .toc .part:first-child { margin-top: 0; }
        .toc .e { display: flex; align-items: center; gap: 4mm; padding: 2.6mm 0; border-bottom: .25mm solid #eef1f6; color: {{ $navy }}; }
        .toc .e .ic { width: 9mm; height: 9mm; border-radius: 2mm; display: grid; place-items: center; color: #fff; font-size: 11pt; flex: 0 0 auto; }
        .toc .e .tx { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 800; font-size: 15pt; text-transform: uppercase; letter-spacing: .02em; }
        .toc .e .ld { flex: 1; border-bottom: .4mm dotted #c3c9d6; transform: translateY(1.5mm); }
        .toc .e .p { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-size: 18pt; color: {{ $goldInk }}; min-width: 10mm; text-align: end; }

        /* ── Divisions ── */
        .dg { break-inside: avoid; margin-bottom: 4mm; border: .35mm solid #e3e6ee; border-radius: 2mm; overflow: hidden; }
        .dg h4 { margin: 0; padding: 2.2mm 3.5mm; background: #f4f6fa; border-bottom: .6mm solid {{ $navy }}; display: flex; align-items: center; gap: 2mm;
                 font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 800; text-transform: uppercase; font-size: 11pt; color: {{ $navy }}; }
        .dg h4 em { margin-inline-start: auto; font-style: normal; color: {{ $goldInk }}; font-size: 12pt; font-weight: 900; }
        .dg .r { display: flex; align-items: center; gap: 2mm; height: 7mm; padding: 0 3.5mm; border-bottom: .25mm solid #eef1f6; font-size: 8pt; font-weight: 600; color: {{ $navy }}; }
        .dg .r:last-child { border-bottom: 0; }
        .dg .r i { width: 2.2mm; height: 2.2mm; border-radius: 50%; background: #9aa3b2; flex: 0 0 auto; } .dg .r i.male { background: #2563eb; } .dg .r i.female { background: #db2777; }
        .dg .r .ld { flex: 1; border-bottom: .3mm dotted #c3c9d6; }
        .dg .r b { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-size: 12pt; color: {{ $goldInk }}; }
    </style>
</head>
<body>

{{-- ═══ Cover ═══ --}}
@php $pg++; @endphp
@if($coverImage)
    <section class="pdf-page" style="background:#fff"><img src="{{ $coverImage }}" alt="" style="display:block;width:100%;height:100%;object-fit:fill"></section>
@else
    <section class="pdf-page cv">
        <span class="stripes"></span>
        <span class="kicker">Official competition report</span>
        @if($cover['type'])<div class="type">{{ $cover['type'] }}</div>@endif
        <h1><span class="l1">{{ $tl[0] }}</span>@if($tl[1] !== '')<span class="l2">{{ $tl[1] }}</span>@endif @if($tl[2] !== '')<span class="l3">{{ $tl[2] }}</span>@endif</h1>
        @if($host)<div class="by">Organised by <b>{{ $host }}</b></div>@endif
        @if($cover['when'])
            <div class="when"><div class="d">{{ str_pad($cover['when']['day'], 2, '0', STR_PAD_LEFT) }}</div><div class="m">{{ $cover['when']['mon'] }} {{ $cover['when']['year'] }}</div><div class="w">{{ $cover['when']['wday'] }}</div></div>
        @endif
        <span class="slab"></span>
        @if($hostLogo)<span class="logo"></span>@endif
        <div class="figs">
            @foreach(array_slice($kpis, 0, 4) as $k)<div><b>{{ $k[1] }}</b><span>{{ $k[2] }}</span></div>@endforeach
        </div>
    </section>
@endif

{{-- ═══ The competition ═══ --}}
@php $pg++; @endphp
<section class="pdf-page">
    @include('federations::report.partials.band', ['heading' => 'The competition', 'eyebrow' => $cover['type'] ?: $title, 'figures' => []])
    <div class="body">
        <div class="grid g3" style="margin-bottom:5mm">
            @foreach($kpis as $k)
                <div class="kpi" style="--k: {{ $k[3] }}"><i class="bi {{ $k[0] }} ic"></i><b>{{ $k[1] }}</b><span>{{ $k[2] }}</span></div>
            @endforeach
        </div>
        <div class="grid g2" style="margin-bottom:5mm">
            <div class="card facts">
                <div class="sec">The event</div>
                @foreach($cover['facts'] as $f)
                    <div class="f"><i class="bi {{ $f['icon'] }}"></i><div><small>{{ $f['label'] }}</small><strong>{{ $f['value'] }}</strong></div></div>
                @endforeach
            </div>
            <div class="card facts">
                <div class="sec">Rules it runs under</div>
                @foreach($cover['rules'] as $f)
                    <div class="f"><i class="bi {{ $f['icon'] ?? 'bi-check2-circle' }}"></i><div><small>{{ $f['label'] }}</small><strong>{{ $f['value'] }}</strong></div></div>
                @endforeach
                @if($cover['requirements'])
                    <div class="f"><i class="bi bi-clipboard-check"></i><div><small>Requirements</small><strong>{{ implode(' · ', $cover['requirements']) }}</strong></div></div>
                @endif
            </div>
        </div>
        @if($cover['schedule'])
            <div class="card sched">
                <div class="sec">Schedule <small>as it ran</small></div>
                @foreach(array_slice($cover['schedule'], 0, 6) as $d)
                    <div class="day">
                        <div class="dt"><b>{{ $d['day'] }}</b><span>{{ $d['mon'] }}</span></div>
                        <div>@foreach($d['rows'] as $r)<div class="row"><span class="tm">{{ $r['time'] ?: '—' }}</span><span>{{ $r['label'] }} @if($r['sub'])<em>· {{ $r['sub'] }}</em>@endif</span></div>@endforeach</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @include('federations::report.partials.foot', ['part' => 'The competition', 'page' => $pg])
</section>

{{-- ═══ Contents ═══ --}}
@php $pg++; $lastPart = null; @endphp
<section class="pdf-page">
    @include('federations::report.partials.band', ['heading' => 'Contents', 'figures' => [[$total, 'Pages']]])
    <div class="body toc">
        @foreach($toc as $e)
            @if($e['part'] !== $lastPart)
                <div class="part">{{ ['Front' => 'The championship', 'Draw' => 'The draw', 'Analytics' => 'Statistics & analytics'][$e['part']] ?? 'Closing' }}</div>
                @php $lastPart = $e['part']; @endphp
            @endif
            <div class="e">
                <span class="ic" style="background: {{ $e['color'] }}"><i class="{{ \App\Support\Icon::bi($e['icon']) }}"></i></span>
                <span class="tx">{{ $e['title'] }}</span><span class="ld"></span><span class="p">{{ $e['page'] }}</span>
            </div>
        @endforeach
    </div>
    @include('federations::report.partials.foot', ['part' => 'Contents', 'page' => $pg])
</section>

{{-- ═══ Officials ═══ --}}
@foreach($team as $ti => $tp)
    @php $pg++; @endphp
    <section class="pdf-page">
        @include('federations::report.partials.band', ['heading' => 'Competition officials', 'figures' => $ti === 0 ? [[collect($team)->flatten(1)->sum(fn ($s) => count($s['cards'])), 'People']] : []])
        <div class="body">@include('events::pdf-team-blocks', ['sections' => $tp])</div>
        @include('federations::report.partials.foot', ['part' => 'Officials', 'page' => $pg])
    </section>
@endforeach

{{-- ═══ Medal tables ═══ --}}
@foreach($medalPages as $mi => $page)
    @php $pg++; @endphp
    <section class="pdf-page">
        @include('federations::report.partials.band', ['heading' => 'Medal tables', 'figures' => []])
        <div class="body">
            @foreach($page as $t)
                @php $max = max(1, collect($t['rows'])->max(fn ($r) => array_sum($r['medals']))); @endphp
                <div class="sec" style="margin-top: {{ $loop->first ? 0 : 6 }}mm">{{ $t['title'] }} @if($t['continued'])<small>continued</small>@endif</div>
                <table class="t">
                    <thead><tr>
                        <th class="c" style="width:10mm">#</th><th>{{ $t['kind'] === 'clubs' ? 'Club' : 'Nation' }}</th><th class="c" style="width:14mm">Athletes</th>
                        @foreach([1, 2, 3] as $pl)<th class="c" style="width:10mm"><x-medal-icon :place="$pl" :size="11" :numeral="false" /></th>@endforeach
                        <th class="c" style="width:11mm">Total</th><th style="width:34mm">Share</th>
                    </tr></thead>
                    <tbody>
                    @foreach($t['chunk'] as $r)
                        @php $sum = array_sum($r['medals']); @endphp
                        <tr>
                            <td class="c">@if($r['rank'])<span class="rk {{ $r['rank'] <= 3 ? 'r'.$r['rank'] : '' }}">{{ $r['rank'] }}</span>@else<span class="muted">—</span>@endif</td>
                            <td><div class="who">
                                @if($t['kind'] === 'clubs')
                                    @if($r['logo'])<img class="lg" src="{{ $r['logo'] }}" alt="">@else<i class="bi bi-shield-fill" style="color:#c3c9d6;font-size:12pt"></i>@endif
                                @else
                                    @if($r['iso'])<span class="fi fi-{{ $r['iso'] }}"></span>@endif
                                @endif
                                <span>{{ $r['name'] }}</span></div></td>
                            <td class="c">{{ $t['kind'] === 'clubs' ? $r['count'] : $r['athletes'] }}</td>
                            @foreach([1, 2, 3] as $pl)<td class="c n" style="{{ $r['medals'][$pl] ? '' : 'color:#c3c9d6' }}">{{ $r['medals'][$pl] }}</td>@endforeach
                            <td class="c n">{{ $sum }}</td>
                            <td>@if($sum)<div class="mbar" style="width: {{ max(8, round($sum / $max * 100)) }}%">@foreach([1, 2, 3] as $pl)@if($r['medals'][$pl])<i style="flex: {{ $r['medals'][$pl] }}; background: {{ $metal[$pl] }}"></i>@endif @endforeach</div>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endforeach
        </div>
        @include('federations::report.partials.foot', ['part' => 'Medal tables', 'page' => $pg])
    </section>
@endforeach

{{-- ═══ Clubs & their athletes ═══ --}}
@foreach($clubPages as $ci => $cp)
    @php $pg++; @endphp
    <section class="pdf-page">
        @include('federations::report.partials.band', ['heading' => 'Clubs & medals', 'figures' => $ci === 0 ? [[count(array_filter($clubs, fn ($x) => ! $x['none'])), 'Clubs'], [$athletes, 'Athletes']] : []])
        <div class="body" style="padding-top:1mm">@include('events::pdf-club-blocks', ['clubs' => $cp, 'sheetIds' => collect()])</div>
        @include('federations::report.partials.foot', ['part' => 'Clubs', 'page' => $pg])
    </section>
@endforeach

{{-- ═══ Divisions ═══ --}}
@foreach($divisionPages as $di => $groups)
    @php $pg++; $half = (int) ceil(count($groups) / 2); @endphp
    <section class="pdf-page">
        @include('federations::report.partials.band', ['heading' => 'Divisions', 'figures' => $di === 0 ? [[$divisionCount, 'Divisions'], [$cover['total'] ?? $athletes, 'Entries']] : []])
        <div class="body">
            <div class="grid g2" style="align-items:start">
                @foreach([array_slice($groups, 0, $half), array_slice($groups, $half)] as $col)
                    <div>
                        @foreach($col as $g)
                            <div class="dg">
                                <h4>{{ $g['heading'] ?: 'Divisions' }}<em>{{ array_sum(array_column($g['rows'], 'n')) }}</em></h4>
                                @foreach($g['rows'] as $r)
                                    <div class="r"><i class="{{ $r['gender'] }}"></i><span>{{ $r['name'] }}</span><span class="ld"></span><b>{{ $r['n'] }}</b></div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
        @include('federations::report.partials.foot', ['part' => 'Divisions', 'page' => $pg])
    </section>
@endforeach

<script>
    document.fonts.ready.then(function () {
        document.querySelectorAll('.pfit, .ath.with-face .n').forEach(function (el) {
            var size = parseFloat(getComputedStyle(el).fontSize);
            while (el.scrollWidth > el.clientWidth + 0.5 && size > 5) { size -= 0.25; el.style.fontSize = size + 'px'; }
        });
    });
</script>
</body>
</html>
