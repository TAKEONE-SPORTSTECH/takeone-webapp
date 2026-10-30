{{--
    One bout's stat sheet, as a PDF (App\Media\BoutStats via
    BoutVideoController::statsPdf). Two A4 pages in the competition report's
    "Statistics & analytics" dress — its own head, band and footer partials, so
    the two read as one family: page 1 the bout in summary (the face-off, the
    figures, how the score moved, red against blue); page 2 the bout in thirds,
    every action, and the mat's tempo. Every value is printed beside its mark
    (a PDF has no hover).
--}}
@php
    $c = '#d8b25f';
    $navy = '#16213d';
    $goldInk = '#a8862f';
    $red = '#d43c3c';
    $blue = '#2a6fd6';
    $s = $stats;
    $total = 2;
    $mmss = fn ($v) => $v === null ? '—' : sprintf('%d:%02d', intdiv((int) round($v), 60), (int) round($v) % 60);
    $ink = ['red' => $red, 'blue' => $blue];
    $other = fn ($k) => $k === 'red' ? 'blue' : 'red';

    // The one sentence that tells the bout.
    $story = [];
    if ($s['winner']) {
        $w = $s['winner'];
        $story[] = '<b>'.e($corners[$w]['label']).'</b> won '.$s['score'][$w].'–'.$s['score'][$other($w)];
        if ($s['level_at_end']) {
            $story[] = 'on a level score';
        } elseif ($s['decided_left'] !== null && $s['decided_left'] <= 1) {
            $story[] = 'with the winning score on the bell';
        } elseif ($s['decided_at'] !== null) {
            $story[] = 'and led for good from '.$mmss($s['decided_at']);
        }
        if ($s['comeback']) {
            $story[] = 'after trailing';
        }
    }
    $sentence = implode(' ', $story).($story ? '.' : '');
    if ($s['lead_changes'] > 0) {
        $sentence .= ' The lead changed hands '.$s['lead_changes'].' '.($s['lead_changes'] === 1 ? 'time' : 'times').'.';
    }

    // How the score moved, as SVG in millimetres (180 × 44).
    $W = 180; $H = 44; $L = 8; $R = 3; $T = 3; $B = 7;
    $len = max(1, $s['length']);
    $max = max(2, $s['score']['red'], $s['score']['blue']);
    $max = (int) (ceil($max / 2) * 2);
    $x = fn ($t) => round($L + ($t / $len) * ($W - $L - $R), 2);
    $y = fn ($v) => round($H - $B - ($v / $max) * ($H - $T - $B), 2);
    $steps = $s['steps'];
    $path = function (string $k) use ($steps, $x, $y, $len) {
        $d = 'M '.$x(0).' '.$y(0);
        foreach (array_slice($steps, 1) as $st) { $d .= ' H '.$x($st['at']).' V '.$y($st[$k]); }
        return $d.' H '.$x($len);
    };
    $tick = $len > 150 ? 60 : 30;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    @include('federations::report.partials.head')
    <style>
        .ins { display: flex; gap: 3mm; align-items: flex-start; padding: 3mm 3.5mm; border-radius: 2mm; background: #fbf6e8; border: .35mm solid #efdfb6; }
        .ins i { color: {{ $goldInk }}; font-size: 12pt; line-height: 1; }
        .ins p { margin: 0; font-size: 8.2pt; line-height: 1.55; color: #4a5470; } .ins p b { color: {{ $navy }}; }

        /* The face-off: two corners, the score between them. */
        .fo { display: grid; grid-template-columns: 1fr 34mm 1fr; align-items: stretch; border: .35mm solid #e3e6ee; border-radius: 2mm; overflow: hidden; margin-bottom: 3.5mm; }
        .fo .cn { display: flex; align-items: center; gap: 3mm; padding: 4mm 5mm; min-width: 0; position: relative; }
        .fo .cn::before { content: ''; position: absolute; top: 0; bottom: 0; width: 1.6mm; }
        .fo .cn.red::before { inset-inline-start: 0; background: {{ $red }}; }
        .fo .cn.blue { flex-direction: row-reverse; text-align: end; }
        .fo .cn.blue::before { inset-inline-end: 0; background: {{ $blue }}; }
        .fo .who { min-width: 0; display: flex; flex-direction: column; gap: .8mm; }
        .fo .tag { font-size: 6.4pt; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; }
        .fo .nm { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-size: 14pt; line-height: 1.05; color: {{ $navy }}; text-transform: uppercase; }
        .fo .cl { display: flex; align-items: center; gap: 1.4mm; font-size: 7pt; font-weight: 700; color: #6b7590; min-width: 0; }
        .fo .cn.blue .cl { flex-direction: row-reverse; }
        .fo .cl img { width: 5mm; height: 5mm; object-fit: contain; }
        .fo .cl .fi { width: 4.6mm; height: 3.45mm; border-radius: .4mm; box-shadow: 0 0 0 .2mm rgba(11,19,43,.15); flex: 0 0 auto; }
        .fo .won { display: inline-flex; align-self: flex-start; padding: .6mm 2mm; border-radius: 1mm; background: {{ $c }}; color: {{ $navy }}; font-size: 6.2pt; font-weight: 900; letter-spacing: .16em; }
        .fo .cn.blue .won { align-self: flex-end; }
        .fo .sc { text-align: center; background: {{ $navy }}; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; }
        .fo .sc b { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-size: 30pt; line-height: 1; font-variant-numeric: tabular-nums; }
        .fo .sc span { font-size: 6pt; font-weight: 800; letter-spacing: .2em; color: {{ $c }}; text-transform: uppercase; margin-top: 1mm; }

        /* Red against blue: value · two-sided bar · value. */
        .vs { display: grid; grid-template-columns: 14mm minmax(0, 1fr) 14mm; align-items: center; column-gap: 3mm; height: 6.3mm; border-bottom: .25mm solid #eef1f6; }
        .vs:last-child { border-bottom: 0; }
        .vs .a, .vs .b { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-size: 13pt; font-variant-numeric: tabular-nums; }
        .vs .a { color: {{ $red }}; } .vs .b { color: {{ $blue }}; text-align: end; }
        .vs .m { display: flex; flex-direction: column; gap: 1mm; align-items: center; }
        .vs .m span { font-size: 6.6pt; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: #6b7590; }
        .vs .m .tr { display: flex; width: 100%; height: 2.4mm; border-radius: 1.2mm; overflow: hidden; background: #f0f2f7; }
        .vs .m .tr i { display: block; height: 100%; }

        /* The bout in thirds: paired columns. */
        .th { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4mm; }
        .th .card { display: flex; flex-direction: column; gap: 2mm; }
        .th .when { font-size: 6.6pt; font-weight: 800; letter-spacing: .14em; color: #8a93a8; }
        .th .pair { display: flex; align-items: flex-end; gap: 2mm; height: 22mm; border-bottom: .35mm solid #c9d0db; }
        .th .pair div { flex: 1; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; height: 100%; }
        .th .pair b { font-size: 8pt; font-weight: 900; color: {{ $navy }}; margin-bottom: .8mm; }
        .th .pair i { display: block; width: 100%; border-radius: 1mm 1mm 0 0; min-height: .6mm; }
        .th .sum { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-size: 16pt; color: {{ $navy }}; }
        .th .sum em { font-style: normal; color: #8a93a8; font-size: 11pt; }

        table.t td .dot { display: inline-block; width: 2.6mm; height: 2.6mm; border-radius: 50%; vertical-align: middle; margin-inline-end: 1.6mm; }
        table.t td.sc2 { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-size: 12pt; font-variant-numeric: tabular-nums; }
        table.t tr.key td { background: #fbf6e8 !important; }
    </style>
</head>
<body>

{{-- ═══ 1 · The bout in summary ═══ --}}
<section class="pdf-page">
    @include('federations::report.partials.band', [
        'heading' => $heading,
        'eyebrow' => 'Bout statistics',
        'figures' => [[$s['score']['red'].'–'.$s['score']['blue'], 'Final score'], [$mmss($s['fought'] ?? $s['length']), 'Bout time']],
    ])
    <div class="body">
        <div class="fo">
            @foreach(['red', 'blue'] as $k)
                @if($k === 'blue')
                    <div class="sc"><b>{{ $s['score']['red'] }}–{{ $s['score']['blue'] }}</b><span>{{ $s['level_at_end'] ? 'Decision' : 'Final' }}</span></div>
                @endif
                @php $cn = $corners[$k]; @endphp
                <div class="cn {{ $k }}">
                    <img class="face big {{ $cn['face'] ? '' : 'none' }}" src="{{ $cn['face'] ?? $cn['fallback'] }}" alt="">
                    <div class="who">
                        <span class="tag" style="color: {{ $ink[$k] }}">{{ $cn['label'] }}</span>
                        <span class="nm"><bdi>{{ $cn['name'] }}</bdi></span>
                        @if($cn['club'] || $cn['iso'])
                            <span class="cl">@if($cn['iso'])<span class="fi fi-{{ $cn['iso'] }}"></span>@endif @if($cn['logo'])<img src="{{ $cn['logo'] }}" alt="">@endif<span>{{ $cn['club'] }}</span></span>
                        @endif
                        @if($s['winner'] === $k)<span class="won">WINNER</span>@endif
                    </div>
                </div>
            @endforeach
        </div>

        @if($sentence)
            <div class="ins" style="margin-bottom:4mm"><i class="bi bi-lightning-charge-fill"></i><p>{!! $sentence !!}</p></div>
        @endif

        <div class="grid g4" style="margin-bottom:4mm">
            @php
                $first = $s['first'];
                $tiles = [
                    ['bi-1-circle-fill', $first ? $mmss($first['at']) : '—', $first ? 'First score · '.$corners[$first['corner']]['label'] : 'First score', $first ? $ink[$first['corner']] : '#2a78d6'],
                    ['bi-arrow-left-right', $s['lead_changes'], 'Lead changes', '#6250d6'],
                    ['bi-flag-fill', $s['level_at_end'] ? 'Level' : ($s['decided_left'] !== null ? $mmss($s['decided_left']) : '—'), $s['level_at_end'] ? 'Settled by decision' : 'Left when decided', '#d8b25f'],
                    ['bi-hourglass-bottom', $s['late_share'].'%', 'Points in the last third', '#eb6834'],
                ];
            @endphp
            @foreach($tiles as $k)
                <div class="kpi {{ $loop->index === 2 ? 'dark' : '' }}" style="--k: {{ $k[3] }}"><i class="bi {{ $k[0] }} ic"></i><b>{{ $k[1] }}</b><span>{{ $k[2] }}</span></div>
            @endforeach
        </div>

        <div class="card" style="margin-bottom:4mm">
            <div class="sec">How the score moved <small>shading shows who was leading</small></div>
            <svg width="{{ $W }}mm" height="{{ $H }}mm" viewBox="0 0 {{ $W }} {{ $H }}" style="display:block">
                @foreach($steps as $i => $st)
                    @php $end = $steps[$i + 1]['at'] ?? $len; $d = $st['red'] - $st['blue']; @endphp
                    @if($d !== 0)
                        <rect x="{{ $x($st['at']) }}" y="{{ $T }}" width="{{ max(0, $x($end) - $x($st['at'])) }}" height="{{ $H - $T - $B }}" fill="{{ $d > 0 ? '#fbe3e3' : '#e1ebfa' }}"/>
                    @endif
                @endforeach
                @for($v = 0; $v <= $max; $v += 2)
                    <line x1="{{ $L }}" x2="{{ $W - $R }}" y1="{{ $y($v) }}" y2="{{ $y($v) }}" stroke="#e6e9f0" stroke-width=".25"/>
                    <text x="{{ $L - 1.5 }}" y="{{ $y($v) + 1.1 }}" text-anchor="end" font-size="3" font-weight="700" fill="#8a93a8" font-family="Inter, sans-serif">{{ $v }}</text>
                @endfor
                @for($t = 0; $t <= $len; $t += $tick)
                    <text x="{{ $x($t) }}" y="{{ $H - 2.5 }}" text-anchor="middle" font-size="3" font-weight="700" fill="#8a93a8" font-family="Inter, sans-serif">{{ $mmss($t) }}</text>
                @endfor
                <path d="{{ $path('blue') }}" fill="none" stroke="{{ $blue }}" stroke-width=".9" stroke-linejoin="round"/>
                <path d="{{ $path('red') }}" fill="none" stroke="{{ $red }}" stroke-width=".9" stroke-linejoin="round"/>
                @foreach($s['events'] as $e)
                    @if($e['kind'] === 'penalty')
                        <circle cx="{{ $x($e['at']) }}" cy="{{ $y(0) }}" r="1.3" fill="#eb6834"/>
                    @else
                        <circle cx="{{ $x($e['at']) }}" cy="{{ $y($e[$e['corner']]) }}" r="1.2" fill="#fff" stroke="{{ $ink[$e['corner']] }}" stroke-width=".7"/>
                    @endif
                @endforeach
            </svg>
            <div class="lg"><span><i style="background:{{ $red }}"></i>{{ $corners['red']['label'] }}</span><span><i style="background:{{ $blue }}"></i>{{ $corners['blue']['label'] }}</span><span><i style="background:#eb6834;border-radius:50%"></i>Penalty</span></div>
        </div>

        <div class="card">
            <div class="sec">{{ $corners['red']['label'] }} against {{ $corners['blue']['label'] }}</div>
            @php
                $rows = [['Points', $s['red']['points'], $s['blue']['points']], ['Scoring actions', $s['red']['scores'], $s['blue']['scores']]];
                foreach ($values as $n => $label) {
                    if (($s['red']['by_value'][$n] ?? 0) || ($s['blue']['by_value'][$n] ?? 0)) {
                        $rows[] = [$label, $s['red']['by_value'][$n] ?? 0, $s['blue']['by_value'][$n] ?? 0];
                    }
                }
                $rows = array_merge($rows, [
                    ['Penalties', $s['red']['penalties'], $s['blue']['penalties']],
                    ['Points a minute', $s['red']['per_minute'], $s['blue']['per_minute']],
                    ['Time in the lead', [$s['red']['lead_seconds'], $mmss($s['red']['lead_seconds'])], [$s['blue']['lead_seconds'], $mmss($s['blue']['lead_seconds'])]],
                    ['Biggest lead', $s['red']['biggest_lead'], $s['blue']['biggest_lead']],
                    ['Longest unanswered run', $s['red']['longest_run'], $s['blue']['longest_run']],
                ]);
            @endphp
            @foreach($rows as [$label, $a, $b])
                @php
                    [$av, $as] = is_array($a) ? $a : [$a, $a];
                    [$bv, $bs] = is_array($b) ? $b : [$b, $b];
                    $sum = max(0.0001, (float) $av + (float) $bv);
                @endphp
                <div class="vs">
                    <span class="a">{{ $as }}</span>
                    <div class="m"><span>{{ $label }}</span><div class="tr"><i style="width: {{ round($av / $sum * 100) }}%; background: {{ $red }}"></i><i style="margin-inline-start:auto; width: {{ round($bv / $sum * 100) }}%; background: {{ $blue }}"></i></div></div>
                    <span class="b">{{ $bs }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @include('federations::report.partials.foot', ['part' => 'Bout statistics', 'page' => 1])
</section>

{{-- ═══ 2 · The bout in thirds, every action, the mat's tempo ═══ --}}
<section class="pdf-page">
    @include('federations::report.partials.band', [
        'heading' => 'Every action',
        'eyebrow' => 'Bout statistics · '.$heading,
        'figures' => [[count(array_filter($s['events'], fn ($e) => $e['kind'] === 'point')), 'Scores'], [count(array_filter($s['events'], fn ($e) => $e['kind'] === 'penalty')), 'Penalties']],
    ])
    <div class="body">
        <div class="sec">The bout in thirds</div>
        @php $tMax = max(1, collect($s['thirds'])->flatMap(fn ($t) => [$t['red'], $t['blue']])->max()); @endphp
        <div class="th" style="margin-bottom:5mm">
            @foreach($s['thirds'] as $t)
                <div class="card">
                    <span class="when">{{ $mmss($t['from']) }} – {{ $mmss($t['to']) }}</span>
                    <div class="pair">
                        <div><b>{{ $t['red'] }}</b><i style="height: {{ round($t['red'] / $tMax * 100) }}%; background: {{ $red }}"></i></div>
                        <div><b>{{ $t['blue'] }}</b><i style="height: {{ round($t['blue'] / $tMax * 100) }}%; background: {{ $blue }}"></i></div>
                    </div>
                    <span class="sum"><span style="color:{{ $red }}">{{ $t['red'] }}</span> <em>–</em> <span style="color:{{ $blue }}">{{ $t['blue'] }}</span></span>
                </div>
            @endforeach
        </div>

        <div class="sec">Every action <small>bout clock</small></div>
        <table class="t" style="margin-bottom:5mm">
            <thead><tr><th style="width:18mm">Time</th><th>Corner</th><th>Action</th><th class="c" style="width:26mm">Score</th></tr></thead>
            <tbody>
                @foreach(array_slice($s['events'], 0, 16) as $e)
                    @php $key = $s['decided_at'] !== null && abs($e['at'] - $s['decided_at']) < .05 && $e['kind'] === 'point'; @endphp
                    <tr class="{{ $key ? 'key' : '' }}">
                        <td class="n">{{ $mmss($e['at']) }}</td>
                        <td><span class="dot" style="background: {{ $ink[$e['corner']] }}"></span><b>{{ $corners[$e['corner']]['label'] }}</b></td>
                        <td>
                            @if($e['kind'] === 'point')
                                {{ ! empty($e['label']) ? $e['label'].' · ' : '' }}+{{ $e['n'] }}{{ $key ? ' · decisive' : '' }}
                            @else
                                Penalty · level {{ $e['level'] }}
                            @endif
                        </td>
                        <td class="c sc2">@if($e['kind'] === 'point')<span style="color:{{ $red }}">{{ $e['red'] }}</span>–<span style="color:{{ $blue }}">{{ $e['blue'] }}</span>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if(count($s['events']) > 16)
            <p class="note" style="margin:-3mm 0 4mm">And {{ count($s['events']) - 16 }} more actions, on the bout page.</p>
        @endif

        <div class="sec">Tempo on the mat</div>
        <div class="grid g4">
            @php $share = $s['wall'] ? (int) round(($s['fought'] ?? $s['length']) / $s['wall'] * 100) : null; @endphp
            @foreach([
                ['bi-stopwatch-fill', $mmss($s['wall']), 'Real time on the mat', '#2a78d6'],
                ['bi-lightning-charge-fill', $share !== null ? $share.'%' : '—', 'Of it was fighting', '#1baf7a'],
                ['bi-pause-circle-fill', $s['stoppages'], 'Stoppages', '#eda100'],
                ['bi-hourglass-split', $s['award_seconds'] !== null ? $s['award_seconds'].' s' : '—', 'To give a point', '#6250d6'],
            ] as $k)
                <div class="kpi" style="--k: {{ $k[3] }}"><i class="bi {{ $k[0] }} ic"></i><b>{{ $k[1] }}</b><span>{{ $k[2] }}</span></div>
            @endforeach
        </div>
        <p class="note" style="margin-top:4mm">Every figure is read from the mat's own scoring log for this bout — the points, penalties and clock as the officials recorded them.{{ $s['encho'] ? ' The bout went to an extension (encho); times run on through it.' : '' }}</p>
    </div>
    @include('federations::report.partials.foot', ['part' => 'Bout statistics', 'page' => 2])
</section>

</body>
</html>
