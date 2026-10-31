{{--
    The bout page's Analytics tab — one bout's statistics, read from the mat's
    own log (App\Media\BoutStats). Docs: docs/events/bout-stat-sheet.md

    It draws inside the tab strip the page already has, in that page's own
    classes, so nothing about the existing tabs changes:
      · variant="mobile"  — the portrait Highlights view and the fullscreen
                            drawer (.mrv-rhead, the .hlp-* point row)
      · variant="desktop" — the desktop rail (.rhead, the .pt-* point row)

    A tap on a key moment or a chart dot replays it exactly like a tap in
    Points: mobile carries `data-mrv-seek` (the page's own handler calls
    playPointReplay — normal speed, then slow motion), desktop carries
    `data-seek` (the rail wires it to its own seek()). The video time comes from
    the bout's timeline (App\Media\BoutTimeline), matched by the score after
    the point; a bout with no footage simply has nothing to tap.

    Decides nothing about who may see it — the page has already checked who
    may watch. Every value is escaped.
--}}
@props(['stats', 'timeline' => [], 'variant' => 'mobile'])

@php
    $s = $stats;
    $mobile = $variant !== 'desktop';
    $R = $mobile ? 'oklch(0.72 0.17 25)' : 'var(--red-soft)';
    $B = $mobile ? 'oklch(0.72 0.15 255)' : 'var(--blue-soft)';
    $RB = $mobile ? 'oklch(0.62 0.19 25)' : 'var(--red)';
    $BB = $mobile ? 'oklch(0.62 0.19 255)' : 'var(--blue)';
    $card = $mobile ? 'background:#12151b;border:1px solid #1c212a;border-radius:14px' : 'background:var(--card-2);border:1px solid var(--line-2);border-radius:10px';
    $muted = $mobile ? '#6b7482' : 'var(--muted)';
    $soft = $mobile ? '#9aa3b0' : 'var(--ink-2)';
    $track = $mobile ? '#20252d' : 'var(--line-2)';
    $mono = "font-family:ui-monospace,'JetBrains Mono',monospace";
    $gut = $mobile ? 'margin:12px 16px 4px' : 'margin:10px 0 6px';
    $seekAttr = $mobile ? 'data-mrv-seek' : 'data-seek';

    $corners = $s['corners'] ?? ['red' => 'Red', 'blue' => 'Blue'];
    $length = max(1, (int) ($s['length'] ?? 0));
    $clock = fn ($t) => intdiv((int) round($t), 60).':'.str_pad((string) ((int) round($t) % 60), 2, '0', STR_PAD_LEFT);

    // Video time for a point, from the timeline: the moment whose running
    // score is the score after this point.
    $videoAt = [];
    foreach (($timeline['moments'] ?? []) as $m) {
        $videoAt[$m['score_red'].'-'.$m['score_blue']] = $m['t'];
    }
    $seek = fn (array $p) => $videoAt[($p['red'] ?? -1).'-'.($p['blue'] ?? -1)] ?? null;

    $winner = $s['winner'] ?? null;
    $lead = ['red' => (int) ($s['red']['lead_seconds'] ?? 0), 'level' => (int) ($s['level_seconds'] ?? 0), 'blue' => (int) ($s['blue']['lead_seconds'] ?? 0)];
    $leadSum = max(1, array_sum($lead));

    // Score values the bout actually had, with the sport's own word for each.
    $words = [];
    foreach (($s['events'] ?? []) as $e) {
        if (($e['kind'] ?? '') === 'point' && ! empty($e['label'])) {
            $words[$e['n']] = $e['label'];
        }
    }
    $values = array_unique(array_merge(array_keys($s['red']['by_value'] ?? []), array_keys($s['blue']['by_value'] ?? [])));
    sort($values);

    $rows = [[__('events::events.bout_an_points'), $s['score']['red'], $s['score']['blue'], $s['score']['red'], $s['score']['blue']],
             [__('events::events.bout_an_scores'), $s['red']['scores'], $s['blue']['scores'], $s['red']['scores'], $s['blue']['scores']]];
    foreach ($values as $v) {
        $a = (int) ($s['red']['by_value'][$v] ?? 0);
        $b = (int) ($s['blue']['by_value'][$v] ?? 0);
        $rows[] = [isset($words[$v]) ? $words[$v].' ('.$v.')' : '+'.$v, $a, $b, $a, $b];
    }
    $rows[] = [__('events::events.bout_an_penalties'), $s['red']['penalties'], $s['blue']['penalties'], $s['red']['penalties'], $s['blue']['penalties']];
    $rows[] = [__('events::events.bout_an_per_min'), number_format($s['red']['per_minute'], 1), number_format($s['blue']['per_minute'], 1), $s['red']['per_minute'], $s['blue']['per_minute']];
    $rows[] = [__('events::events.bout_an_lead_time'), $lead['red'].' s', $lead['blue'].' s', $lead['red'], $lead['blue']];
    $rows[] = [__('events::events.bout_an_biggest'), '+'.$s['red']['biggest_lead'], '+'.$s['blue']['biggest_lead'], $s['red']['biggest_lead'], $s['blue']['biggest_lead']];
    $rows[] = [__('events::events.bout_an_run'), $s['red']['longest_run'], $s['blue']['longest_run'], $s['red']['longest_run'], $s['blue']['longest_run']];
    $rows[] = [__('events::events.bout_an_last_third'), $s['red']['last_third'], $s['blue']['last_third'], $s['red']['last_third'], $s['blue']['last_third']];

    // Key moments, worded.
    $momentRows = [];
    foreach (($s['moments'] ?? []) as $m) {
        $title = match ($m['kind']) {
            'first' => __('events::events.bout_an_m_first'),
            'run' => __('events::events.bout_an_m_run', ['n' => $m['run'] ?? $m['n']]),
            'behind' => __('events::events.bout_an_m_behind', [
                'corner' => $corners[$m['corner']] ?? $m['corner'],
                'n' => abs($m['red'] - $m['blue']),
                'left' => rtrim(rtrim(number_format(max(0, $length - $m['at']), 1), '0'), '.'),
            ]),
            default => __('events::events.bout_an_m_decider'),
        };
        if (! empty($m['at_bell'])) {
            $title .= ' — '.__('events::events.bout_an_at_bell');
        }
        $momentRows[] = $m + ['title' => $title, 'who' => ($corners[$m['corner']] ?? $m['corner']).' · '.($m['label'] ?? '').' +'.$m['n'], 't' => $seek($m)];
    }

    // The chart: the running score of each corner as steps over the bout.
    $W = 640; $H = 240; $L = 30; $Rt = 12; $T = 12; $Bm = 28;
    $top = max(2, (int) $s['score']['red'], (int) $s['score']['blue']);
    $top += $top % 2;
    $x = fn ($t) => round($L + (min($t, $length) / $length) * ($W - $L - $Rt), 1);
    $y = fn ($v) => round($H - $Bm - ($v / $top) * ($H - $T - $Bm), 1);
    $steps = $s['steps'] ?? [['at' => 0, 'red' => 0, 'blue' => 0]];
    $path = function (string $c) use ($steps, $x, $y, $length) {
        $d = 'M'.$x(0).' '.$y(0);
        foreach (array_slice($steps, 1) as $st) {
            $d .= ' H'.$x($st['at']).' V'.$y($st[$c]);
        }
        return $d.' H'.$x($length);
    };
    $shades = [];
    foreach ($steps as $i => $st) {
        $end = $steps[$i + 1]['at'] ?? $length;
        $d = $st['red'] - $st['blue'];
        if ($d !== 0 && $end > $st['at']) {
            $shades[] = ['x' => $x($st['at']), 'w' => max(0, $x($end) - $x($st['at'])), 'c' => $d > 0 ? '#ef6b5f' : '#6f9cf0'];
        }
    }
    $dots = [];
    foreach (($s['events'] ?? []) as $e) {
        if ($e['kind'] === 'point') {
            $dots[] = ['x' => $x($e['at']), 'y' => $y($e[$e['corner']]), 'c' => $e['corner'] === 'red' ? '#ef6b5f' : '#6f9cf0', 't' => $seek($e), 'last' => ! empty($e['at_bell'])];
        } elseif ($e['kind'] === 'penalty') {
            $dots[] = ['x' => $x($e['at']), 'y' => $y(0), 'c' => '#d8b25f', 't' => null, 'pen' => true];
        }
    }
    $yTicks = [0, $top / 2, $top];
    $xTicks = [0, $length / 3, $length * 2 / 3, $length];

    $tempo = [];
    if (! empty($s['wall'])) {
        $tempo[] = [$clock($s['wall']), __('events::events.bout_an_wall', ['len' => $clock($length)])];
        if (! empty($s['fought'])) {
            $tempo[] = [round($s['fought'] / max(1, $s['wall']) * 100).'%', __('events::events.bout_an_fighting')];
        }
    }
    $tempo[] = [(string) ($s['stoppages'] ?? 0), __('events::events.bout_an_stoppages')];
    if (($s['award_seconds'] ?? null) !== null) {
        $tempo[] = [$s['award_seconds'].' s', __('events::events.bout_an_award')];
    }

    $head = function (string $name, string $note = '') use ($mobile) {
        $name = e($name); $note = e($note);
        return $mobile
            ? '<div class="mrv-rhead"><div class="mrv-rhead-l"><span class="mrv-rname">'.$name.'</span><span class="mrv-rcount">'.$note.'</span></div></div>'
            : '<div class="rhead"><span class="rname">'.$name.'</span><span class="rcount">'.$note.'</span></div>';
    };
@endphp

@once
<style>
    /* The Analytics pane takes the Points pane's edge-to-edge treatment. */
    .mrv-pane[data-mrv-panel="analytics"] .mrv-scroll { padding-left: 0 !important; padding-right: 0 !important; }
    .mrv-pane[data-mrv-panel="analytics"] .mrv-rhead { margin-left: 0 !important; margin-right: 0 !important; padding-left: 18px !important; padding-right: 18px !important; }
    .fsh-list[data-fsh-panel="analytics"] .mrv-rhead { position: static; margin: 6px 0 0; padding: 8px 12px; background: transparent; }
    /* Five tabs: each as wide as its own word, so none is cut to "Comme…". */
    .mrv-tabs.has-analytics .mrv-tab { flex: 1 1 auto !important; }
    .ban-dot { cursor: pointer; }
</style>
@endonce

<div class="ban" data-bout-analytics>
    {!! $head(__('events::events.bout_an_result'), __('events::events.bout_an_from_log')) !!}
    <div style="{{ $card }};{{ $gut }};padding:14px;display:flex;flex-direction:column;gap:12px">
        @if ($winner)
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px">
                <span style="font-size:11px;font-weight:800;letter-spacing:1.5px;color:{{ $winner === 'red' ? $R : $B }}">{{ mb_strtoupper(__('events::events.bout_an_wins', ['corner' => $corners[$winner] ?? $winner])) }}</span>
                <span style="{{ $mono }};font-size:20px;font-weight:700"><span style="color:{{ $R }}">{{ $s['score']['red'] }}</span> <span style="color:{{ $muted }}">–</span> <span style="color:{{ $B }}">{{ $s['score']['blue'] }}</span></span>
            </div>
        @endif
        @if ($s['win_reason'] ?? null)
            <div style="font-size:12px;color:{{ $soft }}">{{ __('events::events.bout_an_how', ['reason' => \Illuminate\Support\Str::headline($s['win_reason'])]) }}@if ($s['win_note'] ?? null) · {{ $s['win_note'] }}@endif</div>
        @endif
        <div style="font-size:13px;line-height:1.5;color:{{ $mobile ? '#c6ccd4' : 'var(--ink-2)' }}">{{ \App\Media\BoutStats::verdict($s) }}</div>
        <div style="display:flex;height:6px;border-radius:9px;overflow:hidden;background:{{ $track }}">
            <span style="width:{{ $lead['red'] / $leadSum * 100 }}%;background:{{ $RB }}"></span>
            <span style="width:{{ $lead['level'] / $leadSum * 100 }}%;background:#3a4250"></span>
            <span style="width:{{ $lead['blue'] / $leadSum * 100 }}%;background:{{ $BB }}"></span>
        </div>
        <div style="display:flex;justify-content:space-between;gap:8px;font-size:11px;color:{{ $muted }}">
            <span>{{ __('events::events.bout_an_led', ['corner' => $corners['red'], 's' => $lead['red']]) }}</span>
            <span>{{ __('events::events.bout_an_level', ['s' => $lead['level']]) }}</span>
            <span>{{ __('events::events.bout_an_led', ['corner' => $corners['blue'], 's' => $lead['blue']]) }}</span>
        </div>
    </div>

    @if ($momentRows)
        {!! $head(__('events::events.bout_an_moments'), $videoAt ? __('events::events.bout_an_moments_hint') : '') !!}
        @foreach ($momentRows as $m)
            @if ($mobile)
                <div class="hlp-row mrv-hlp" @if ($m['t'] !== null) data-mrv-seek="{{ $m['t'] }}" @endif>
                    <span class="hlp-time mrv-hlp-time">{{ $clock($m['at']) }}</span>
                    <span class="hlp-bar hlp-bar-{{ $m['corner'] }}"></span>
                    <span class="hlp-what"><span class="hlp-label">{{ $m['title'] }}</span><span class="hlp-who">{{ $m['who'] }}</span></span>
                    <span class="hlp-run"><span class="hlp-run-red">{{ $m['red'] }}</span><span class="hlp-run-sep">–</span><span class="hlp-run-blue">{{ $m['blue'] }}</span></span>
                    <span></span>
                </div>
            @else
                <div class="pt-row" @if ($m['t'] !== null) data-seek="{{ $m['t'] }}" @endif>
                    <span class="pt-time">{{ $clock($m['at']) }}</span>
                    <span class="pt-dot {{ $m['corner'] === 'red' ? 'aka' : 'ao' }}"></span>
                    <span style="min-width:0"><span class="pt-action">{{ $m['title'] }}</span><span class="pt-who" style="display:block">{{ $m['who'] }}</span></span>
                    <span class="pt-score">{{ $m['red'] }}–{{ $m['blue'] }}</span>
                </div>
            @endif
        @endforeach
    @endif

    {!! $head(__('events::events.bout_an_flow'), $videoAt ? __('events::events.bout_an_flow_hint') : '') !!}
    <div style="{{ $card }};{{ $gut }};padding:10px 6px 6px">
        <svg role="img" aria-label="{{ __('events::events.bout_an_flow') }}" viewBox="0 0 {{ $W }} {{ $H }}" style="display:block;width:100%;height:auto">
            @foreach ($shades as $sh)
                <rect x="{{ $sh['x'] }}" y="{{ $T }}" width="{{ $sh['w'] }}" height="{{ $H - $T - $Bm }}" fill="{{ $sh['c'] }}" fill-opacity=".12"></rect>
            @endforeach
            <g stroke="{{ $mobile ? '#1c212a' : '#262626' }}" stroke-width="1.5">
                @foreach ($yTicks as $v)<line x1="{{ $L }}" x2="{{ $W - $Rt }}" y1="{{ $y($v) }}" y2="{{ $y($v) }}"></line>@endforeach
                @foreach (array_slice($xTicks, 1, 2) as $t)<line x1="{{ $x($t) }}" x2="{{ $x($t) }}" y1="{{ $T }}" y2="{{ $H - $Bm }}"></line>@endforeach
            </g>
            <g fill="{{ $mobile ? '#6b7482' : '#757575' }}" font-size="16" font-family="ui-monospace, monospace">
                @foreach ($yTicks as $v)<text x="{{ $L - 8 }}" y="{{ $y($v) + 5 }}" text-anchor="end">{{ $v }}</text>@endforeach
                @foreach ($xTicks as $i => $t)<text x="{{ $x($t) }}" y="{{ $H - 4 }}" text-anchor="{{ $i === 0 ? 'start' : ($i === 3 ? 'end' : 'middle') }}">{{ $clock($t) }}</text>@endforeach
            </g>
            <path d="{{ $path('blue') }}" fill="none" stroke="#6f9cf0" stroke-width="3.5" stroke-linejoin="round"></path>
            <path d="{{ $path('red') }}" fill="none" stroke="#ef6b5f" stroke-width="3.5" stroke-linejoin="round"></path>
            @foreach ($dots as $d)
                @if (! empty($d['pen']))
                    <circle cx="{{ $d['x'] }}" cy="{{ $d['y'] }}" r="6" fill="{{ $d['c'] }}"></circle>
                @else
                    <g @if ($d['t'] !== null) class="ban-dot" {{ $seekAttr }}="{{ $d['t'] }}" @endif>
                        <circle cx="{{ $d['x'] }}" cy="{{ $d['y'] }}" r="16" fill="transparent"></circle>
                        <circle cx="{{ $d['x'] }}" cy="{{ $d['y'] }}" r="{{ $d['last'] ? 7 : 6 }}" stroke="{{ $d['c'] }}" stroke-width="3" fill="{{ $d['last'] ? $d['c'] : ($mobile ? '#12151b' : '#161616') }}"></circle>
                    </g>
                @endif
            @endforeach
        </svg>
        <div style="display:flex;flex-wrap:wrap;gap:12px;padding:6px 8px 2px;font-size:11px;color:{{ $muted }}">
            <span>{{ __('events::events.bout_an_shading') }}</span>
            <span><span style="color:#d8b25f">●</span> {{ __('events::events.bout_an_penalty') }}</span>
        </div>
    </div>

    {!! $head(mb_strtoupper(__('events::events.bout_an_vs', ['red' => $corners['red'], 'blue' => $corners['blue']]))) !!}
    @foreach ($rows as [$label, $a, $b, $na, $nb])
        @php $sum = ((float) $na + (float) $nb) ?: 1; @endphp
        <div style="display:grid;grid-template-columns:44px 1fr 44px;align-items:center;gap:10px;padding:{{ $mobile ? '10px 18px' : '9px 2px' }};border-bottom:1px solid {{ $mobile ? '#171b21' : 'var(--card-2)' }}">
            <span style="{{ $mono }};font-size:14px;font-weight:700;color:{{ $R }}">{{ $a }}</span>
            <span style="display:flex;flex-direction:column;gap:5px;align-items:center">
                <span style="font-size:12px;color:{{ $soft }};text-align:center">{{ $label }}</span>
                <span style="display:flex;width:100%;height:4px;border-radius:9px;overflow:hidden;background:{{ $track }}">
                    <span style="width:{{ (float) $na / $sum * 100 }}%;background:{{ $RB }}"></span>
                    <span style="width:{{ (float) $nb / $sum * 100 }}%;background:{{ $BB }};margin-inline-start:auto"></span>
                </span>
            </span>
            <span style="{{ $mono }};font-size:14px;font-weight:700;color:{{ $B }};text-align:end">{{ $b }}</span>
        </div>
    @endforeach

    {!! $head(__('events::events.bout_an_thirds')) !!}
    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;{{ $gut }}">
        @foreach ($s['thirds'] as $t)
            <div style="{{ $card }};padding:12px 4px;display:flex;flex-direction:column;align-items:center;gap:6px">
                <span style="{{ $mono }};font-size:11px;color:{{ $muted }}">{{ $clock($t['from']) }}–{{ $clock($t['to']) }}</span>
                <span style="{{ $mono }};font-size:18px;font-weight:700"><span style="color:{{ $R }}">{{ $t['red'] }}</span> <span style="color:{{ $muted }}">–</span> <span style="color:{{ $B }}">{{ $t['blue'] }}</span></span>
            </div>
        @endforeach
    </div>

    {!! $head(__('events::events.bout_an_tempo')) !!}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;{{ $gut }}">
        @foreach ($tempo as [$v, $l])
            <div style="{{ $card }};padding:12px 14px;display:flex;flex-direction:column;gap:4px">
                <span style="{{ $mono }};font-size:18px;font-weight:700">{{ $v }}</span>
                <span style="font-size:11px;line-height:1.4;color:{{ $muted }}">{{ $l }}</span>
            </div>
        @endforeach
    </div>
    <div style="height:24px"></div>
</div>
