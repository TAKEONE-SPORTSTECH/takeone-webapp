{{--
    The competition report, part C — statistics & analytics
    (FederationCompetitionReport, data from FederationBoutAnalytics). Six
    analytics pages and the sign-off; page numbers continue from $base.
    Charts are HTML/SVG drawn here from numbers the server computed; every
    value is printed beside its mark (a PDF has no hover).
--}}
@php
    $c = '#d8b25f';
    $navy = '#16213d';
    $goldInk = '#a8862f';
    $pg = $base;
    $s = $stats;
    $cat = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#6250d6'];
    $techInk = [1 => '#86b6ef', 2 => '#2a78d6', 3 => '#104281'];
    $techName = [1 => 'Yuko', 2 => 'Waza-ari', 3 => 'Ippon'];
    $foulInk = [1 => '#f4b48f', 2 => '#eb6834', 3 => '#c4511f', 4 => '#8f3813', 5 => '#5c230b'];
    $foulName = [1 => 'Chui 1', 2 => 'Chui 2', 3 => 'Chui 3', 4 => 'Hansoku-chui', 5 => 'Hansoku'];
    $reasonInk = ['Points' => '#2a78d6', 'Decision' => '#eb6834', 'Withdrawal' => '#1baf7a', 'Disqualification' => '#eda100', 'Ippon' => '#6250d6'];
    $mmss = fn ($v) => $v === null ? '—' : sprintf('%d:%02d', intdiv((int) $v, 60), (int) $v % 60);
    $hm = fn ($v) => sprintf('%dh %02dm', intdiv((int) $v, 3600), intdiv((int) $v % 3600, 60));
    $pct = fn ($a, $b) => $b ? (int) round($a / $b * 100) : 0;
    // A donut from [[label, value, colour], …] — SVG, 2px surface gap between slices.
    $donut = function (array $parts, float $mm, string $big, string $small) use ($navy) {
        $total = max(1, array_sum(array_column($parts, 1)));
        $r = 15.9155; $out = '';
        $off = 25;
        foreach ($parts as $p) {
            if ($p[1] <= 0) { continue; }
            $len = $p[1] / $total * 100;
            $gap = count(array_filter($parts, fn ($x) => $x[1] > 0)) > 1 ? .8 : 0;
            $out .= '<circle cx="21" cy="21" r="'.$r.'" fill="none" stroke="'.e($p[2]).'" stroke-width="6.2" stroke-dasharray="'.max(0, $len - $gap).' '.(100 - max(0, $len - $gap)).'" stroke-dashoffset="'.$off.'"/>';
            $off -= $len;
        }

        return '<svg width="'.$mm.'mm" height="'.$mm.'mm" viewBox="0 0 42 42"><circle cx="21" cy="21" r="'.$r.'" fill="none" stroke="#f0f2f7" stroke-width="6.2"/>'.$out
            .'<text x="21" y="21.5" text-anchor="middle" font-family="Barlow Condensed, Inter, sans-serif" font-weight="900" font-size="9" fill="'.$navy.'">'.e($big).'</text>'
            .'<text x="21" y="27" text-anchor="middle" font-family="Inter, sans-serif" font-weight="800" font-size="2.6" letter-spacing=".3" fill="#8a93a8">'.e(strtoupper($small)).'</text></svg>';
    };
    // Entries per age group (male / female) and one colour per group, in age order everywhere.
    $ageCounts = $ages;
    $ageMax = max(1, collect($ageCounts)->max(fn ($m) => ($m['M'] ?? 0) + ($m['F'] ?? 0)));
    $rankAge = fn ($a) => app(\App\Federations\Services\FederationBoutAnalytics::class)->ageRank($a);
    $ageList = array_keys($s['techByAge'] + $ageCounts);
    usort($ageList, fn ($x, $y) => $rankAge($x) <=> $rankAge($y));
    $ageInk = [];
    foreach ($ageList as $i => $a) { $ageInk[$a] = $cat[$i % count($cat)]; }
    $face = fn ($p, $cls = '') => '<img class="face '.$cls.(empty($p['face']) ? ' none' : '').'" src="'.e($p['face'] ?? $silhouette).'" alt="">';
    $flag = fn ($p) => ! empty($p['iso']) ? '<span class="fi fi-'.e($p['iso']).'"></span>' : '';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    @include('federations::report.partials.head')
    <style>
        .ins { display: flex; gap: 3mm; align-items: flex-start; padding: 3mm 3.5mm; border-radius: 2mm; background: #fbf6e8; border: .35mm solid #efdfb6; }
        .ins i { color: {{ $goldInk }}; font-size: 12pt; line-height: 1; }
        .ins p { margin: 0; font-size: 7.6pt; line-height: 1.55; color: #4a5470; } .ins p b { color: {{ $navy }}; }
        .split { display: flex; height: 12mm; border-radius: 2mm; overflow: hidden; font-family: 'Barlow Condensed', 'Inter', sans-serif; color: #fff; }
        .split div { display: flex; align-items: center; justify-content: space-between; padding: 0 4mm; font-weight: 900; font-size: 14pt; }
        .split div span { font-size: 7pt; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; opacity: .9; }
        .split div + div { box-shadow: -.6mm 0 0 #fff; }
        .tl { position: relative; height: 13mm; border-radius: 1.5mm; background: #f4f6fa; overflow: hidden; }
        .tl i { position: absolute; top: 1.6mm; bottom: 1.6mm; border-radius: .6mm; }
        .tl-ax { position: relative; height: 4.5mm; margin-top: 1mm; }
        .tl-ax span { position: absolute; transform: translateX(-50%); font-size: 6.2pt; font-weight: 700; color: #6b7590; }
        .tl-row { display: grid; grid-template-columns: 14mm 1fr; align-items: center; gap: 2.5mm; margin-bottom: 1.5mm; }
        .tl-row b { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-size: 11pt; color: {{ $navy }}; }
        .star { display: grid; grid-template-columns: 7mm 8mm minmax(0, 1fr) 40mm 16mm 12mm; align-items: center; gap: 2.5mm; height: 12mm; padding: 0 2mm; border-bottom: .25mm solid #e3e6ee; }
        .star:nth-child(even) { background: #fafbfd; }
        .star .nm { display: flex; align-items: center; gap: 1.6mm; font-size: 8.6pt; font-weight: 800; color: {{ $navy }}; white-space: nowrap; overflow: hidden; min-width: 0; }
        .star .nm .fi, .mini .fi, .bout .fi { width: 4.4mm; height: 3.3mm; border-radius: .4mm; flex: 0 0 auto; box-shadow: 0 0 0 .2mm rgba(11,19,43,.12); }
        .star .cl { font-size: 6.6pt; color: #6b7590; font-weight: 700; text-transform: uppercase; white-space: nowrap; overflow: hidden; }
        .star .pts { display: flex; align-items: center; gap: 2mm; }
        .star .pts .tr { flex: 1; height: 3mm; border-radius: 1.5mm; background: #f0f2f7; overflow: hidden; }
        .star .pts .tr i { display: block; height: 100%; background: linear-gradient(90deg, #d4a940, #b8892a); }
        .star .pts b, .star .wb, .star .ip { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-size: 12pt; color: {{ $navy }}; text-align: center; }
        .star .wb small, .star .ip small { display: block; font-family: 'Inter', sans-serif; font-size: 5.6pt; font-weight: 800; letter-spacing: .1em; color: #8a93a8; }
        .mini { display: flex; align-items: center; gap: 2.2mm; padding: 1.6mm 0; border-bottom: .25mm solid #eef1f6; }
        .mini:last-child { border-bottom: 0; }
        .mini .t { min-width: 0; flex: 1; }
        .mini .t div { display: flex; align-items: center; gap: 1.4mm; font-size: 8pt; font-weight: 800; color: {{ $navy }}; white-space: nowrap; overflow: hidden; }
        .mini .t small { display: block; font-size: 6.4pt; color: #6b7590; font-weight: 700; text-transform: uppercase; white-space: nowrap; overflow: hidden; }
        .mini .v { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-size: 14pt; color: {{ $goldInk }}; }
        .bout { border: .35mm solid #e3e6ee; border-radius: 2mm; overflow: hidden; }
        .bout .hd { display: flex; align-items: center; gap: 2mm; padding: 1.8mm 3mm; background: {{ $navy }}; color: #fff; font-size: 6.6pt; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
        .bout .hd i { color: {{ $c }}; font-size: 9pt; }
        .bout .bd { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 2mm; padding: 2.4mm 3mm 1mm; }
        .bout .side { display: flex; align-items: center; gap: 1.6mm; min-width: 0; font-size: 7.4pt; font-weight: 800; color: {{ $navy }}; }
        .bout .side.b { flex-direction: row-reverse; text-align: end; }
        .bout .side span { white-space: nowrap; overflow: hidden; min-width: 0; }
        .bout .side .bar { width: 1.2mm; height: 8mm; border-radius: .6mm; flex: 0 0 auto; }
        .bout .sc { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-size: 18pt; color: {{ $navy }}; white-space: nowrap; }
        .bout .ft { padding: 0 3mm 2.2mm; font-size: 6.4pt; color: #6b7590; font-weight: 700; display: flex; justify-content: space-between; gap: 2mm; }
        .rkc-cls { display: flex; align-items: baseline; justify-content: space-between; gap: 3mm; padding-bottom: 1.4mm; margin-bottom: 2mm; border-bottom: .35mm solid #e3e6ee; }
        .rkc-cls b { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-size: 11.5pt; color: {{ $navy }}; text-transform: uppercase; letter-spacing: .02em; }
        .rkc-cls small { font-size: 6.4pt; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: #8a93a8; }
        .rkc { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 3mm; margin-bottom: 5.5mm; break-inside: avoid; }
        .rkcard { position: relative; overflow: hidden; display: flex; align-items: stretch; gap: 2.5mm; min-height: 26mm; box-sizing: border-box; padding: 2.6mm 3mm; border-radius: 2.2mm; background: #fff; border: .35mm solid #e3e6ee; color: {{ $navy }}; }
        .rkcard.p1 { background: linear-gradient(150deg, #16213d, #2c3f6e); border-color: #16213d; color: #fff; }
        .rkcard.p2 { border-top: 1.1mm solid #a9b2c3; } .rkcard.p3 { border-top: 1.1mm solid #c9895b; }
        .rkcard .glow { position: absolute; right: -9mm; top: -9mm; width: 26mm; height: 26mm; border-radius: 50%; background: rgba(22,33,61,.04); }
        .rkcard.p1 .glow { background: rgba(237,205,133,.16); }
        .rkcard .face { width: 13.5mm; height: 18mm; position: relative; align-self: center; }
        .rkcard .tx { position: relative; min-width: 0; flex: 1; display: flex; flex-direction: column; }
        .rkcard .lbl { display: flex; align-items: center; gap: 1.4mm; font-size: 5.8pt; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: #8a93a8; }
        .rkcard.p1 .lbl { color: #edcd85; }
        .rkcard .lbl .rk { width: 5.6mm; height: 5.6mm; font-size: 8.6pt; }
        .rkcard .nm { display: flex; align-items: flex-start; gap: 1.2mm; margin-top: 1.4mm; font-size: 7.8pt; font-weight: 800; line-height: 1.2; overflow-wrap: anywhere; }
        .rkcard .nm .fi { margin-top: .5mm; }
        .rkcard .pt { padding-top: 1.2mm; }
        .rkcard .nm .fi { width: 4mm; height: 3mm; border-radius: .4mm; flex: 0 0 auto; }
        .rkcard .cl { font-size: 5.9pt; font-weight: 700; text-transform: uppercase; color: #6b7590; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .rkcard.p1 .cl { color: #c9d3e6; }
        .rkcard .pt { margin-top: auto; display: flex; align-items: baseline; justify-content: space-between; gap: 1.5mm; }
        .rkcard .pt b { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-size: 15pt; line-height: 1; }
        .rkcard.p1 .pt b { color: #edcd85; font-size: 17pt; }
        .rkcard .pt span { font-size: 5.8pt; font-weight: 700; color: #8a93a8; white-space: nowrap; overflow: hidden; }
        .rkcard.p1 .pt span { color: #c9d3e6; }
        .sig { border: .35mm solid #e3e6ee; border-radius: 2mm; padding: 4mm; height: 44mm; display: flex; flex-direction: column; }
        .sig small { font-size: 6.6pt; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: {{ $goldInk }}; }
        .sig .ln { margin-top: auto; border-bottom: .35mm solid #8a93a8; height: 10mm; }
        .sig .lb { display: flex; justify-content: space-between; margin-top: 1.4mm; font-size: 6.4pt; color: #8a93a8; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; }
    </style>
</head>
<body>

{{-- ═══ 1 · The championship in numbers ═══ --}}
@php $pg++; @endphp
<section class="pdf-page">
    @include('federations::report.partials.band', ['heading' => 'In numbers', 'eyebrow' => 'Statistics & analytics', 'figures' => [[$s['decided'], 'Bouts'], [$s['scored'], 'Points']]])
    <div class="body">
        <div class="grid g4" style="margin-bottom:5mm">
            @foreach([
                ['bi-lightning-charge-fill', $s['decided'], 'Bouts on the mat', '#2a78d6', ''],
                ['bi-bullseye', $s['scored'], 'Points scored', '#eb6834', ''],
                ['bi-star-fill', $s['tech']['Ippon'], 'Ippons', '#104281', ''],
                ['bi-flag-fill', $s['foulsTotal'], 'Fouls called', '#c4511f', ''],
                ['bi-stopwatch-fill', $hm($s['fight']['total']), 'Total fight time', '#1baf7a', ''],
                ['bi-hourglass-split', $mmss($s['fight']['avg']), 'Average bout', '#eda100', ''],
                ['bi-1-circle-fill', $pct($s['first']['won'], $s['first']['of']), 'First point wins', '#d8b25f', '%'],
                ['bi-arrow-repeat', $s['comebacks'], 'Comebacks', '#6250d6', ''],
            ] as $k)
                <div class="kpi {{ $loop->index === 6 ? 'dark' : '' }}" style="--k: {{ $k[3] }}"><i class="bi {{ $k[0] }} ic"></i><b>{{ $k[1] }}@if($k[4])<small>{{ $k[4] }}</small>@endif</b><span>{{ $k[2] }}</span></div>
            @endforeach
        </div>

        <div class="grid" style="grid-template-columns: 62mm 1fr; margin-bottom:5mm">
            <div class="card">
                <div class="sec">Athletes</div>
                <div class="dn" style="flex-direction:column;align-items:stretch">
                    <div style="display:flex;justify-content:center">{!! $donut([['Male', $gender['Male'], '#2563eb'], ['Female', $gender['Female'], '#db2777']], 30, (string) $athletes, 'athletes') !!}</div>
                    <div class="rows" style="margin-top:2mm">
                        @foreach(['Male' => '#2563eb', 'Female' => '#db2777'] as $g => $ink)
                            <div class="r"><i style="background:{{ $ink }}"></i><span>{{ $g }}</span><b>{{ $gender[$g] }}</b><em>{{ $pct($gender[$g], $athletes) }}%</em></div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="sec">Entries by age group <small>male · female</small></div>
                @foreach($ageCounts as $a => $mf)
                    @php $tot = ($mf['M'] ?? 0) + ($mf['F'] ?? 0); @endphp
                    <div class="hb" style="--lw: 30mm; --rh: 7mm">
                        <span class="lb">{{ $a }}</span>
                        <span class="tr" style="width: {{ max(4, round($tot / $ageMax * 100)) }}%">
                            @if($mf['M'] ?? 0)<i style="flex: {{ $mf['M'] }}; background:#2563eb"></i>@endif
                            @if($mf['F'] ?? 0)<i style="flex: {{ $mf['F'] }}; background:#db2777"></i>@endif
                        </span>
                        <span class="vl">{{ $mf['M'] ?? 0 }} · {{ $mf['F'] ?? 0 }}</span>
                    </div>
                @endforeach
                <div class="lg"><span><i style="background:#2563eb"></i>Male</span><span><i style="background:#db2777"></i>Female</span></div>
            </div>
        </div>

        <div class="grid g2">
            <div class="card">
                <div class="sec">Athletes by nation</div>
                @php $nTop = collect($nations)->filter(fn ($n) => $n['iso'])->sortByDesc('athletes')->take(8)->values(); $nMax = max(1, $nTop->max('athletes')); @endphp
                @foreach($nTop as $n)
                    <div class="hb" style="--lw: 30mm"><span class="lb"><span class="fi fi-{{ $n['iso'] }}"></span><span class="pfit" style="min-width:0;overflow:hidden">{{ $n['name'] }}</span></span><span class="tr"><i style="width: {{ round($n['athletes'] / $nMax * 100) }}%; background:#1baf7a"></i></span><span class="vl">{{ $n['athletes'] }}</span></div>
                @endforeach
            </div>
            <div class="card">
                <div class="sec">Athletes by club</div>
                @php $cTop = collect($clubs)->reject(fn ($x) => $x['none'])->sortByDesc('count')->take(8)->values(); $cMax = max(1, $cTop->max('count')); @endphp
                @foreach($cTop as $x)
                    <div class="hb" style="--lw: 34mm"><span class="lb">@if($x['logo'])<img src="{{ $x['logo'] }}" alt="">@endif<span class="pfit" style="min-width:0;overflow:hidden">{{ $x['name'] }}</span></span><span class="tr"><i style="width: {{ round($x['count'] / $cMax * 100) }}%; background:#2a78d6"></i></span><span class="vl">{{ $x['count'] }}</span></div>
                @endforeach
            </div>
        </div>
    </div>
    @include('federations::report.partials.foot', ['part' => 'Analytics', 'page' => $pg])
</section>

{{-- ═══ 2 · How bouts are won ═══ --}}
@php $pg++; $techTotal = max(1, array_sum($s['tech'])); @endphp
<section class="pdf-page">
    @include('federations::report.partials.band', ['heading' => 'How bouts are won', 'eyebrow' => 'Statistics & analytics', 'figures' => [[$s['pointsPerBout'], 'Points a bout']]])
    <div class="body">
        <div class="grid g2" style="margin-bottom:5mm">
            <div class="card">
                <div class="sec">How they were decided</div>
                @php $rs = collect($s['reasons'])->map(fn ($v, $k) => [$k, $v, $reasonInk[$k] ?? '#6250d6'])->values()->all(); @endphp
                <div class="dn">{!! $donut($rs, 32, (string) $s['decided'], 'bouts') !!}
                    <div class="rows">@foreach($rs as $r)<div class="r"><i style="background:{{ $r[2] }}"></i><span>{{ $r[0] }}</span><b>{{ $r[1] }}</b><em>{{ $pct($r[1], $s['decided']) }}%</em></div>@endforeach</div>
                </div>
            </div>
            <div class="card">
                <div class="sec">Every score, by type</div>
                @php $ts = collect([1, 2, 3])->map(fn ($n) => [$techName[$n], $s['tech'][$techName[$n]], $techInk[$n]])->all(); @endphp
                <div class="dn">{!! $donut($ts, 32, (string) $s['points'], 'scores') !!}
                    <div class="rows">@foreach($ts as $i => $r)<div class="r"><i style="background:{{ $r[2] }}"></i><span>{{ $r[0] }} <span class="muted" style="font-weight:600">· {{ $i + 1 }} pt</span></span><b>{{ $r[1] }}</b><em>{{ $pct($r[1], $techTotal) }}%</em></div>@endforeach</div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom:5mm">
            <div class="sec">Score types by age group <small>share of each group's scores</small></div>
            @foreach($s['techByAge'] as $a => $tb)
                @php $sum = array_sum($tb); @endphp
                <div class="hb" style="--lw: 30mm; --rh: 7.4mm">
                    <span class="lb"><i style="width:2.4mm;height:2.4mm;border-radius:50%;background:{{ $ageInk[$a] ?? '#9aa3b2' }};display:inline-block"></i>{{ $a }}</span>
                    <span class="tr" style="height:4.4mm">@foreach([1, 2, 3] as $n)@if($tb[$n] ?? 0)<i style="flex: {{ $tb[$n] }}; background: {{ $techInk[$n] }}"></i>@endif @endforeach</span>
                    <span class="vl">{{ $pct($tb[3] ?? 0, $sum) }}% <span class="muted" style="font-weight:700">ippon</span></span>
                </div>
            @endforeach
            <div class="lg">@foreach([1, 2, 3] as $n)<span><i style="background:{{ $techInk[$n] }}"></i>{{ $techName[$n] }}</span>@endforeach</div>
        </div>

        <div class="grid g2">
            <div class="card">
                <div class="sec">Winning margins <small>points apart</small></div>
                @php $mMax = max(1, max($s['margins'] ?: [1])); @endphp
                <div class="cols" style="--ch: 46mm">@foreach($s['margins'] as $k => $v)<div><span class="v">{{ $v }}</span><span class="bar" style="height: {{ max(2, round($v / $mMax * 100)) }}%; background: {{ (string) $k === '0' ? '#eb6834' : '#2a78d6' }}"></span></div>@endforeach</div>
                <div class="xl">@foreach($s['margins'] as $k => $v)<span>{{ (string) $k === '0' ? 'Level' : $k }}</span>@endforeach</div>
            </div>
            <div class="grid" style="gap:3mm">
                <div class="ins"><i class="bi bi-lightbulb-fill"></i><p><b>{{ $pct($s['tech']['Ippon'], $techTotal) }}% of scores were Ippons</b> — {{ $s['tech']['Ippon'] }} three-point techniques across {{ $s['decided'] }} bouts, worth {{ $s['tech']['Ippon'] * 3 }} of the {{ $s['scored'] }} points scored.</p></div>
                <div class="ins"><i class="bi bi-lightning-fill"></i><p><b>{{ $s['early'] }} bouts ended before the bell</b> — stopped on the points gap, by disqualification or by withdrawal — {{ $pct($s['early'], $s['decided']) }}% of all bouts.</p></div>
                <div class="ins"><i class="bi bi-scale" style="font-size:11pt"></i><p><b>{{ $s['margins']['0'] ?? 0 }} bouts finished level</b> and were settled by senshu or the referees' decision; {{ ($s['margins']['1'] ?? 0) }} more were won by a single point.</p></div>
            </div>
        </div>
    </div>
    @include('federations::report.partials.foot', ['part' => 'Analytics', 'page' => $pg])
</section>

{{-- ═══ 3 · Time ═══ --}}
@php
    $pg++;
    $phMax = max(1, max($s['phase']));
    $from = $s['day']['from']; $to = $s['day']['to'];
    $h0 = $from ? (int) $from->format('H') : 9; $h1 = $to ? (int) $to->format('H') + 1 : 18;
    $span = max(1, ($h1 - $h0) * 3600);
    $posOf = fn ($t) => $from ? max(0, min(100, (($t->getTimestamp() - $from->copy()->setTime($h0, 0)->getTimestamp()) / $span) * 100)) : 0;
@endphp
<section class="pdf-page">
    @include('federations::report.partials.band', ['heading' => 'Time on the mat', 'eyebrow' => 'Statistics & analytics', 'figures' => $from ? [[$from->format('H:i'), 'First bout'], [$to->format('H:i'), 'Last bell']] : []])
    <div class="body">
        <div class="card" style="margin-bottom:5mm">
            <div class="sec">When in the bout the points land <small>share of the regulation time</small></div>
            <div class="cols" style="--ch: 50mm">@foreach($s['phase'] as $i => $v)<div><span class="v">{{ $v }}</span><span class="bar" style="height: {{ max(2, round($v / $phMax * 100)) }}%; background: {{ $i === 9 ? '#d8b25f' : '#2a78d6' }}"></span></div>@endforeach</div>
            <div class="xl">@foreach($s['phase'] as $i => $v)<span>{{ $i * 10 }}–{{ ($i + 1) * 10 }}%</span>@endforeach</div>
            <div class="ins" style="margin-top:3mm"><i class="bi bi-alarm-fill"></i><p><b>{{ $s['lastSeconds'] }} points came in the final 15 seconds</b> of a bout — {{ $pct($s['lastSeconds'], $s['points']) }}% of all scores. The gold column is the last tenth of the clock.</p></div>
        </div>

        <div class="grid g3" style="margin-bottom:5mm">
            <div class="kpi" style="--k:#1baf7a"><i class="bi bi-stopwatch ic"></i><b>{{ $mmss($s['fight']['avg']) }}</b><span>Fight time a bout</span></div>
            <div class="kpi" style="--k:#eda100"><i class="bi bi-clock-history ic"></i><b>{{ $mmss($s['wall']['avg']) }}</b><span>On the mat a bout</span></div>
            <div class="kpi dark" style="--k:#d8b25f"><i class="bi bi-speedometer2 ic"></i><b>{{ $pct($s['fight']['avg'], $s['wall']['avg']) }}<small>%</small></b><span>Clock running</span></div>
        </div>

        <div class="card">
            <div class="sec">The day, bout by bout <small>coloured by age group</small></div>
            @foreach($s['mats'] as $m)
                <div class="tl-row"><b>{{ $m['mat'] }}</b>
                    <div class="tl">@foreach($m['blocks'] as $bk)<i style="left: {{ round($posOf($bk['from']), 2) }}%; width: {{ max(.35, round($posOf($bk['to']) - $posOf($bk['from']), 2)) }}%; background: {{ $ageInk[$bk['age']] ?? '#9aa3b2' }}"></i>@endforeach</div>
                </div>
            @endforeach
            <div class="tl-row"><span></span><div class="tl-ax">@for($h = $h0; $h <= $h1; $h++)<span style="left: {{ round(($h - $h0) * 3600 / $span * 100, 2) }}%">{{ sprintf('%02d:00', $h) }}</span>@endfor</div></div>
            <div class="tl-row" style="margin-top:3mm"><span class="muted" style="font-size:6.4pt;font-weight:800;letter-spacing:.1em">BOUTS / HR</span>
                <div>
                    @php $hrMax = max(1, max($s['perHour'] ?: [1])); @endphp
                    <div class="cols" style="--ch: 24mm; padding-top:4mm">@for($h = $h0; $h < $h1; $h++)@php $v = $s['perHour'][sprintf('%02d', $h)] ?? 0; @endphp<div><span class="v">{{ $v ?: '' }}</span><span class="bar" style="height: {{ $v ? max(4, round($v / $hrMax * 100)) : 0 }}%; background:#1baf7a"></span></div>@endfor</div>
                </div>
            </div>
            <div class="lg">@foreach($ageInk as $a => $ink)<span><i style="background:{{ $ink }}"></i>{{ $a }}</span>@endforeach</div>
            @if($s['mats'] && $s['mats'][0]['changeover'])<p class="note" style="margin:2.5mm 0 0">Average changeover between bouts: <b>{{ $mmss($s['mats'][0]['changeover']) }}</b>@if($s['exhibitions']) · {{ $s['exhibitions'] }} exhibition bouts were fought outside the draw and are not counted here @endif</p>@endif
        </div>
    </div>
    @include('federations::report.partials.foot', ['part' => 'Analytics', 'page' => $pg])
</section>

{{-- ═══ 4 · Discipline & the corners ═══ --}}
@php $pg++; $fMax = max(1, max($s['fouls'])); $fpb = max(.01, collect($s['foulsByAge'])->max('perBout')); $cornerTotal = max(1, $s['corner']['a'] + $s['corner']['b']); @endphp
<section class="pdf-page">
    @include('federations::report.partials.band', ['heading' => 'Discipline & corners', 'eyebrow' => 'Statistics & analytics', 'figures' => [[$s['foulsTotal'], 'Fouls'], [round($s['foulsTotal'] / max(1, $s['decided']), 1), 'A bout']]])
    <div class="body">
        <div class="grid g2" style="margin-bottom:5mm">
            <div class="card">
                <div class="sec">Fouls by level</div>
                <div class="cols" style="--ch: 40mm">@foreach($s['fouls'] as $l => $v)<div><span class="v">{{ $v }}</span><span class="bar" style="height: {{ max(2, round($v / $fMax * 100)) }}%; background: {{ $foulInk[$l] }}"></span></div>@endforeach</div>
                <div class="xl">@foreach($s['fouls'] as $l => $v)<span>{{ $foulName[$l] }}</span>@endforeach</div>
            </div>
            <div class="card">
                <div class="sec">Fouls a bout, by age group</div>
                @foreach($s['foulsByAge'] as $r)
                    <div class="hb" style="--lw: 30mm; --rh: 7.6mm"><span class="lb">{{ $r['age'] }}</span><span class="tr"><i style="width: {{ round($r['perBout'] / $fpb * 100) }}%; background:#eb6834"></i></span><span class="vl">{{ number_format($r['perBout'], 2) }}</span></div>
                @endforeach
            </div>
        </div>

        <div class="card" style="margin-bottom:5mm">
            <div class="sec">Red against blue <small>bouts won from each corner</small></div>
            <div class="split">
                <div style="flex: {{ max(1, $s['corner']['a']) }}; background:#dc2626"><span>Aka · red</span>{{ $s['corner']['a'] }} · {{ $pct($s['corner']['a'], $cornerTotal) }}%</div>
                <div style="flex: {{ max(1, $s['corner']['b']) }}; background:#2563eb">{{ $s['corner']['b'] }} · {{ $pct($s['corner']['b'], $cornerTotal) }}%<span>Ao · blue</span></div>
            </div>
            <div class="grid g3" style="margin-top:4mm">
                <div class="kpi dark"><i class="bi bi-1-circle-fill ic"></i><b>{{ $s['first']['won'] }}<small>/ {{ $s['first']['of'] }}</small></b><span>Scored first, won</span></div>
                <div class="kpi" style="--k:#6250d6"><i class="bi bi-award-fill ic"></i><b>{{ $s['senshu']['won'] }}<small>/ {{ $s['senshu']['of'] }}</small></b><span>Senshu holders won</span></div>
                <div class="kpi" style="--k:#1baf7a"><i class="bi bi-arrow-repeat ic"></i><b>{{ $s['comebacks'] }}</b><span>Won from behind</span></div>
            </div>
        </div>

        <div class="sec">Bouts to remember</div>
        <div class="grid g2">
            @foreach(['highest' => ['bi-fire', 'Highest scoring'], 'widest' => ['bi-arrows-expand', 'Widest margin'], 'fastest' => ['bi-lightning-charge-fill', 'Fastest win'], 'closest' => ['bi-hourglass-bottom', 'Closest finish']] as $k => [$ic, $lbl])
                @if($h = $s['highlights'][$k] ?? null)
                    <div class="bout">
                        <div class="hd"><i class="bi {{ $ic }}"></i>{{ $lbl }}<span style="margin-inline-start:auto;color:#c9d0e2">Bout {{ $h['no'] }}</span></div>
                        <div class="bd">
                            <div class="side"><span class="bar" style="background:#dc2626"></span>{!! $flag($h['A'] ?? []) !!}<span class="pfit">{{ $h['A']['name'] ?? '—' }}</span></div>
                            <div class="sc">{{ $h['sa'] }} – {{ $h['sb'] }}</div>
                            <div class="side b"><span class="bar" style="background:#2563eb"></span>{!! $flag($h['B'] ?? []) !!}<span class="pfit">{{ $h['B']['name'] ?? '—' }}</span></div>
                        </div>
                        <div class="ft"><span>{{ $h['division'] }}</span><span>{{ $h['round'] }} · {{ $mmss($h['fight']) }}</span></div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
    @include('federations::report.partials.foot', ['part' => 'Analytics', 'page' => $pg])
</section>

{{-- ═══ 5 · Stars ═══ --}}
@php $pg++; $tsMax = max(1, collect($s['topScorers'])->max('points')); @endphp
<section class="pdf-page">
    @include('federations::report.partials.band', ['heading' => 'Stars of the event', 'eyebrow' => 'Statistics & analytics', 'figures' => []])
    <div class="body">
        <div class="sec">Top scorers <small>points scored across every bout</small></div>
        <div class="card" style="padding:0;overflow:hidden;margin-bottom:5mm">
            @foreach($s['topScorers'] as $i => $r)
                <div class="star">
                    <span class="rk {{ $i < 3 ? 'r'.($i + 1) : '' }}">{{ $i + 1 }}</span>
                    {!! $face($r['person']) !!}
                    <div style="min-width:0"><div class="nm">{!! $flag($r['person'] ?? []) !!}<span>{{ $r['person']['name'] ?? '—' }}</span></div><div class="cl">{{ $r['person']['club'] ?? '' }}</div></div>
                    <div class="pts"><span class="tr"><i style="width: {{ round($r['points'] / $tsMax * 100) }}%"></i></span><b>{{ $r['points'] }}</b></div>
                    <div class="wb">{{ $r['wins'] }}/{{ $r['bouts'] }}<small>WON</small></div>
                    <div class="ip">{{ $r['ippon'] }}<small>IPPON</small></div>
                </div>
            @endforeach
        </div>
        <div class="grid g2">
            <div class="card">
                <div class="sec">Ippon specialists</div>
                @foreach($s['ipponMasters'] as $r)
                    <div class="mini">{!! $face($r['person']) !!}<div class="t"><div>{!! $flag($r['person'] ?? []) !!}<span>{{ $r['person']['name'] ?? '—' }}</span></div><small>{{ $r['person']['club'] ?? '' }}</small></div><span class="v">{{ $r['ippon'] }}</span></div>
                @endforeach
            </div>
            <div class="card">
                <div class="sec">Unbeaten</div>
                @foreach($s['unbeaten'] as $r)
                    <div class="mini">{!! $face($r['person']) !!}<div class="t"><div>{!! $flag($r['person'] ?? []) !!}<span>{{ $r['person']['name'] ?? '—' }}</span></div><small>{{ $r['person']['club'] ?? '' }}</small></div><span class="v">{{ $r['wins'] }}–0</span></div>
                @endforeach
            </div>
        </div>
    </div>
    @include('federations::report.partials.foot', ['part' => 'Analytics', 'page' => $pg])
</section>

{{-- ═══ 6 · Club performance ═══ --}}
@foreach($perfPages as $pi => $rows)
    @php $pg++; @endphp
    <section class="pdf-page">
        @include('federations::report.partials.band', ['heading' => 'Club performance', 'eyebrow' => 'Statistics & analytics', 'figures' => []])
        <div class="body">
            <table class="t">
                <thead><tr>
                    <th>Club</th><th class="c" style="width:13mm">Athletes</th><th class="c" style="width:11mm">Bouts</th><th class="c" style="width:10mm">Won</th>
                    <th style="width:28mm">Win rate</th><th class="c" style="width:21mm">Points</th>
                    @foreach([1, 2, 3] as $pl)<th class="c" style="width:8mm"><x-medal-icon :place="$pl" :size="10" :numeral="false" /></th>@endforeach
                    <th class="c" style="width:15mm">Per athlete</th>
                </tr></thead>
                <tbody>
                @foreach($rows as $r)
                    @php $wr = $pct($r['wins'], $r['bouts']); @endphp
                    <tr>
                        <td><div class="who">@if($r['logo'])<img class="lg" src="{{ $r['logo'] }}" alt="">@else<i class="bi bi-shield-fill" style="color:#c3c9d6;font-size:12pt"></i>@endif<span>{{ $r['name'] }}</span></div></td>
                        <td class="c">{{ $r['athletes'] }}</td><td class="c">{{ $r['bouts'] }}</td><td class="c">{{ $r['wins'] }}</td>
                        <td><div style="display:flex;align-items:center;gap:1.6mm"><div class="mbar" style="flex:1;min-width:0"><i style="width: {{ $wr }}%; background:#1baf7a"></i></div><b style="font-size:7.4pt">{{ $wr }}%</b></div></td>
                        <td class="c" style="white-space:nowrap"><b>{{ $r['for'] }}</b><span class="muted"> – {{ $r['against'] }}</span></td>
                        @foreach([1, 2, 3] as $pl)<td class="c n" style="{{ $r['medals'][$pl] ? '' : 'color:#c3c9d6' }}">{{ $r['medals'][$pl] }}</td>@endforeach
                        <td class="c n">{{ number_format(array_sum($r['medals']) / max(1, $r['athletes']), 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <p class="note" style="margin-top:3mm">Bouts, wins and points come from the officiating log of every bout fought; a bye is not a bout. Points are scored – conceded.</p>
            @if($pi === count($perfPages) - 1)
                @php $all = collect($perfPages)->flatten(1)->map(fn ($r) => $r + ['eff' => array_sum($r['medals']) / max(1, $r['athletes'])])->sortByDesc('eff')->values(); $eMax = max(.01, $all->max('eff')); @endphp
                <div class="grid g2" style="margin-top:6mm">
                    <div class="card">
                        <div class="sec">Medals per athlete <small>how well each club converted</small></div>
                        @foreach($all as $r)
                            <div class="hb" style="--lw: 36mm"><span class="lb">@if($r['logo'])<img src="{{ $r['logo'] }}" alt="">@endif<span class="pfit" style="min-width:0;overflow:hidden">{{ $r['name'] }}</span></span><span class="tr"><i style="width: {{ round($r['eff'] / $eMax * 100) }}%; background:#d8b25f"></i></span><span class="vl">{{ number_format($r['eff'], 2) }}</span></div>
                        @endforeach
                    </div>
                    <div class="card">
                        <div class="sec">Win rate <small>bouts won of bouts fought</small></div>
                        @foreach(collect($perfPages)->flatten(1)->filter(fn ($r) => $r['bouts'] > 0)->sortByDesc(fn ($r) => $r['wins'] / $r['bouts'])->values() as $r)
                            <div class="hb" style="--lw: 36mm"><span class="lb">@if($r['logo'])<img src="{{ $r['logo'] }}" alt="">@endif<span class="pfit" style="min-width:0;overflow:hidden">{{ $r['name'] }}</span></span><span class="tr"><i style="width: {{ $pct($r['wins'], $r['bouts']) }}%; background:#1baf7a"></i></span><span class="vl">{{ $pct($r['wins'], $r['bouts']) }}%</span></div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
        @include('federations::report.partials.foot', ['part' => 'Analytics', 'page' => $pg])
    </section>
@endforeach

{{-- ═══ 7 · National ranking — the top three in each weight class (App\Rankings) ═══ --}}
@php $nth = fn (int $n) => $n.(in_array($n % 100, [11, 12, 13], true) ? 'th' : ([1 => 'st', 2 => 'nd', 3 => 'rd'][$n % 10] ?? 'th')); @endphp
@foreach($rankingPages as $ri => $classes)
    @php $pg++; @endphp
    <section class="pdf-page">
        @include('federations::report.partials.band', ['heading' => 'National ranking', 'eyebrow' => 'Top three in each weight class', 'figures' => $ri === 0 ? [[collect($rankingPages)->flatten(1)->count(), 'Weight classes']] : []])
        <div class="body">
            @foreach($classes as $cl)
                <div class="rkc-cls"><b>{{ $cl['name'] }}</b><small>{{ $cl['discipline'] === 'kata' ? 'Kata' : 'Kumite' }} · {{ $cl['athletes'] }} ranked</small></div>
                <div class="rkc">
                    @foreach($cl['top'] as $i => $t)
                        @php $won = collect(['gold' => $t['gold'], 'silver' => $t['silver'], 'bronze' => $t['bronze']])->filter()->map(fn ($n, $m) => $n.' '.$m)->implode(' · '); @endphp
                        <div class="rkcard p{{ min(3, $i + 1) }}">
                            <span class="glow"></span>
                            {!! $face($t['person']) !!}
                            <div class="tx">
                                <div class="lbl"><span class="rk r{{ min(3, $i + 1) }}">{{ $t['position'] }}</span>Ranked {{ $nth((int) $t['position']) }}</div>
                                <div class="nm">{!! $flag($t['person'] ?? []) !!}<span>{{ $t['person']['name'] ?? '—' }}</span></div>
                                <div class="cl">{{ $t['person']['club'] ?? '' }}</div>
                                <div class="pt"><b>{{ $t['points'] }}<small style="font-size:6pt;font-weight:800;margin-inline-start:.8mm">PTS</small></b><span>{{ $won }}</span></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
            @if($ri === count($rankingPages) - 1)
                <p class="note">Ranking points follow the WKF World Ranking formula: <b>(5 for taking part + 10 per bout won + place points) × 6</b>, over every finished federation event in this rule book. Place points: 1st 100 · 2nd 70 · 3rd 40 · lost bronze bout 30 · quarter-final 20. Points count in full for 12 months and half for the next 12. Ties are broken by golds, silvers, bronzes, then bouts won.</p>
            @endif
        </div>
        @include('federations::report.partials.foot', ['part' => 'Analytics', 'page' => $pg])
    </section>
@endforeach

{{-- ═══ Approval ═══ --}}
@php $pg++; @endphp
<section class="pdf-page">
    @include('federations::report.partials.band', ['heading' => 'Approval', 'figures' => []])
    <div class="body">
        <div class="card" style="margin-bottom:6mm">
            <div class="sec">Summary</div>
            <p class="note" style="font-size:8.6pt;margin:0">
                <b>{{ $title }}</b> brought together <b>{{ $athletes }} athletes</b> from <b>{{ count(array_filter($clubs, fn ($x) => ! $x['none'])) }} clubs</b>
                and <b>{{ count(array_filter($nations, fn ($n) => $n['iso'])) }} nations</b> in <b>{{ $divisionCount }} divisions</b>.
                The mats logged <b>{{ $s['decided'] }} bouts</b> and <b>{{ $s['scored'] }} points</b> — {{ $s['tech']['Ippon'] }} of them Ippons — in
                {{ $hm($s['fight']['total']) }} of fighting{{ $s['day']['from'] ? ', from '.$s['day']['from']->format('H:i').' to '.$s['day']['to']->format('H:i') : '' }}.
                @php $lead = collect($clubs)->reject(fn ($x) => $x['none'])->first(); @endphp
                @if($lead && array_sum($lead['medals']))<b>{{ $lead['name'] }}</b> topped the medal table with {{ $lead['medals'][1] }} gold, {{ $lead['medals'][2] }} silver and {{ $lead['medals'][3] }} bronze.@endif
            </p>
        </div>
        <div class="grid g3">
            @foreach(['Chief referee', 'Tournament director', 'For the organiser'] as $who)
                <div class="sig"><small>{{ $who }}</small><div class="ln"></div><div class="lb"><span>Name &amp; signature</span><span>Date</span></div></div>
            @endforeach
        </div>
        <div style="margin-top:8mm" class="ins"><i class="bi bi-shield-check"></i><p>Every result and figure in this report was drawn from the competition's own records — the draw, the podiums and the officiating log the mats wrote as each bout was fought. Nothing was typed in by hand. Generated {{ $generatedAt }}.</p></div>
    </div>
    @include('federations::report.partials.foot', ['part' => 'Approval', 'page' => $pg])
</section>

<script>
    document.fonts.ready.then(function () {
        document.querySelectorAll('.pfit').forEach(function (el) {
            var size = parseFloat(getComputedStyle(el).fontSize);
            while (el.scrollWidth > el.clientWidth + 0.5 && size > 5) { size -= 0.25; el.style.fontSize = size + 'px'; }
        });
    });
</script>
</body>
</html>
