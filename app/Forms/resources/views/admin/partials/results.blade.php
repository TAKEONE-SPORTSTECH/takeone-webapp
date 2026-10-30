{{-- The results: summary, answers over time, what stands out, then one
     chart per question in page order. Server-drawn from SurveyAnalytics, so
     it is the same on mobile and desktop and can be re-fetched as one piece
     (?fragment=results) when a new answer arrives. Every string is escaped. --}}
@php
    $ar = app()->getLocale() === 'ar';
    $L = fn (array $o, string $k = 'label') => $ar && ! empty($o[$k.'_ar']) ? $o[$k.'_ar'] : ($o[$k] ?? '');
    $s = $stats['summary'];
    $n = $stats['n'];
    $tlMax = max(1, max(array_column($stats['timeline'], 'count') ?: [0]));
    $fmtDay = fn ($d) => \Illuminate\Support\Carbon::parse($d)->translatedFormat('j M');
@endphp
<div id="fa-results" class="space-y-4">
    @if($example)
        <div class="fa-example"><i class="bi bi-eye"></i><span class="flex-1">{{ __('forms::admin.example_on') }}</span>
            <a href="{{ route('admin.forms.show', $form->uuid) }}" class="fa-btn ghost" style="padding:6px 10px">{{ __('forms::admin.example_hide') }}</a></div>
    @endif

    @if($n === 0)
        <div class="fa-card fa-empty">
            <div class="ic"><i class="bi bi-bar-chart-line"></i></div>
            <h3>{{ __('forms::admin.no_results_title') }}</h3>
            <p>{{ __('forms::admin.no_results_body') }}</p>
            @if($canExample)
                <a href="{{ route('admin.forms.show', $form->uuid) }}?example=1" class="fa-btn solid" style="margin-top:16px"><i class="bi bi-magic"></i>{{ __('forms::admin.example_show') }}</a>
            @endif
        </div>
    @else
        <div class="fa-kpis">
            <div class="fa-card fa-kpi"><div class="fa-eb">{{ __('forms::admin.kpi_answers') }}</div><b class="num">{{ $s['total'] }}</b><small>+{{ $s['week'] }} {{ __('forms::admin.kpi_week') }}</small></div>
            <div class="fa-card fa-kpi"><div class="fa-eb">{{ __('forms::admin.kpi_visits') }}</div><b class="num">{{ $s['visits'] }}</b><small>{{ $n ? round($s['visits'] / $n * 100) : 0 }}%</small></div>
            <div class="fa-card fa-kpi"><div class="fa-eb">{{ __('forms::admin.kpi_arabic') }}</div><b class="num">{{ $s['arabic'] }}</b><small>{{ $n ? round($s['arabic'] / $n * 100) : 0 }}%</small></div>
            <div class="fa-card fa-kpi"><div class="fa-eb">{{ __('forms::admin.kpi_last') }}</div><b style="font-size:17px;margin-top:6px">{{ $s['last'] ? \Illuminate\Support\Carbon::parse($s['last'])->diffForHumans() : '—' }}</b>
                <small>{{ $s['last'] ? \Illuminate\Support\Carbon::parse($s['last'])->translatedFormat('j M Y, H:i') : '' }}</small></div>
        </div>

        <div class="fa-card fa-pad">
            <div class="fa-h">{{ __('forms::admin.per_day') }}</div>
            <div class="fa-tl" role="img" aria-label="{{ __('forms::admin.per_day') }}">
                @foreach($stats['timeline'] as $d)
                    <i class="{{ $d['count'] ? 'has' : '' }}" style="height:{{ max(3, $d['count'] / $tlMax * 100) }}%" title="{{ $fmtDay($d['date']) }} · {{ $d['count'] }}"></i>
                @endforeach
            </div>
            <div class="fa-tl-ax"><span>{{ $fmtDay($stats['timeline'][0]['date']) }}</span><span>{{ $fmtDay(end($stats['timeline'])['date']) }}</span></div>
        </div>

        @if($stats['highlights'])
            <div>
                <div class="fa-eb" style="margin:6px 2px 8px">{{ __('forms::admin.highlights') }}</div>
                <div class="fa-hl">
                    @foreach(array_slice($stats['highlights'], 0, 3) as $h)
                        <div>
                            @if($h['kind'] === 'scale')
                                <b class="num">{{ $h['value'] }} / {{ $h['q']['max'] }}</b>
                                <p>{{ __('forms::admin.hl_avg', ['n' => $h['q']['answered']]) }}<br><span style="color:var(--ink3);font-size:12px">{{ $L($h['q']) }}</span></p>
                            @else
                                <b class="num">{{ $h['row']['pct'] }}%</b>
                                <p>{{ __('forms::admin.hl_chose', ['answer' => $L($h['row']), 'n' => $h['q']['answered']]) }}<br><span style="color:var(--ink3);font-size:12px">{{ $L($h['q']) }}</span></p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @foreach($stats['pages'] as $pi => $page)
            <div class="fa-sec"><span class="num">{{ $pi + 1 }}</span><b>{{ $L($page, 'title') }}</b>
                @if($page['private'])<em class="fa-tag warn">{{ __('forms::admin.visitor_only') }}</em>@endif</div>
            <div class="fa-qs">
                @foreach($page['questions'] as $q)
                    <div class="fa-card fa-q {{ in_array($q['chart'], ['grid']) ? 'wide' : '' }}">
                        <div class="fa-h">{{ $L($q) }}</div>
                        <div class="meta"><span>{{ __('forms::admin.answered', ['n' => $q['answered'], 'of' => $q['of']]) }}</span>
                            @if($q['private'])<em class="fa-tag warn">{{ __('forms::admin.visitor_only') }}</em>@endif</div>

                        @switch($q['chart'])
                            @case('bars')
                                <div class="fa-bars">
                                    @foreach($q['rows'] as $r)
                                        <div class="fa-bar" title="{{ $r['count'] }} · {{ $r['pct'] }}%">
                                            <div class="t"><span>{{ $L($r) }}</span><b class="num">{{ $r['count'] }}<em>{{ $r['pct'] }}%</em></b></div>
                                            <div class="fa-track"><i style="width:{{ $r['pct'] }}%"></i></div>
                                        </div>
                                    @endforeach
                                </div>
                                @if($q['texts'])
                                    <div class="fa-sub2">{{ __('forms::admin.other_answers') }}</div>
                                    <div class="fa-quotes">@foreach($q['texts'] as $t)<div class="fa-quote" dir="auto">{{ $t['text'] }}@if($t['who'])<small>{{ $t['who'] }}</small>@endif</div>@endforeach</div>
                                @endif
                                @if($q['notes'])
                                    <div class="fa-sub2">{{ __('forms::admin.notes') }}</div>
                                    <div class="fa-quotes">@foreach($q['notes'] as $t)<div class="fa-quote" dir="auto">{{ $t['text'] }}@if($t['who'])<small>{{ $t['who'] }}</small>@endif</div>@endforeach</div>
                                @endif
                                @break

                            @case('scale')
                                @php $dmax = max(1, max(array_column($q['dist'], 'count'))); @endphp
                                <div class="fa-scale">
                                    <div class="fa-mean"><b class="num">{{ $q['mean'] ?? '—' }}</b><small>{{ __('forms::admin.avg') }}</small></div>
                                    <div class="fa-cols">
                                        @foreach($q['dist'] as $d)
                                            <div title="{{ $d['value'] }}: {{ $d['count'] }}"><em class="num">{{ $d['count'] }}</em>
                                                <i class="{{ $d['count'] === $dmax ? 'top' : '' }}" style="height:{{ max(4, $d['count'] / $dmax * 70) }}%"></i><span class="num">{{ $d['value'] }}</span></div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="fa-ends"><span>{{ $L($q, 'low') }}</span><span>{{ $L($q, 'high') }}</span></div>
                                @if($q['notes'])
                                    <div class="fa-sub2">{{ __('forms::admin.notes') }}</div>
                                    <div class="fa-quotes">@foreach($q['notes'] as $t)<div class="fa-quote" dir="auto">{{ $t['text'] }}@if($t['who'])<small>{{ $t['who'] }}</small>@endif</div>@endforeach</div>
                                @endif
                                @break

                            @case('numbers')
                                <div class="fa-nums">
                                    @foreach($q['rows'] as $r)
                                        <div><span>{{ $L($r) }}</span><b class="num">{{ $r['median'] !== null ? rtrim(rtrim(number_format($r['median'], 1), '0'), '.') : '—' }}</b>
                                            <small class="num">{{ __('forms::admin.median') }}@if($r['min'] !== null) · {{ $r['min'] + 0 }}–{{ $r['max'] + 0 }}@endif</small></div>
                                    @endforeach
                                </div>
                                @break

                            @case('grid')
                                @php $pal = ['var(--p)', 'var(--pl)', 'hsl(220 12% 82%)']; @endphp
                                <div class="fa-legend">@foreach($q['columns'] as $ci => $c)<span><i style="background:{{ $pal[$ci] ?? 'var(--mut)' }}"></i>{{ $L($c) }}</span>@endforeach
                                    <span style="margin-inline-start:auto">{{ __('forms::admin.score') }} = ({{ $L($q['columns'][0] ?? []) }} + 0.4 × {{ $L($q['columns'][1] ?? []) }}) ÷ {{ $q['answered'] }}</span></div>
                                <div class="fa-grid">
                                    @foreach($q['rows'] as $r)
                                        @php $tot = max(1, $q['answered']); @endphp
                                        <div class="fa-grow" title="@foreach($q['columns'] as $c){{ $L($c) }}: {{ $r['counts'][$c['value']] ?? 0 }}  @endforeach">
                                            <span class="l">{{ $L($r) }}</span>
                                            <b class="s num">{{ $r['score'] }}</b>
                                            <span class="fa-stack">@foreach($q['columns'] as $ci => $c)<i style="width:{{ ($r['counts'][$c['value']] ?? 0) / $tot * 100 }}%;background:{{ $pal[$ci] ?? 'var(--mut)' }}"></i>@endforeach</span>
                                        </div>
                                    @endforeach
                                </div>
                                @break

                            @case('rank')
                                @php $pmax = max(1, $q['rows'][0]['points'] ?? 1); @endphp
                                <div class="fa-rank">
                                    @foreach($q['rows'] as $i => $r)
                                        <div><span class="n num">{{ $i + 1 }}</span>
                                            <span><span>{{ $L($r) }}</span><small>{{ __('forms::admin.firsts', ['n' => $r['firsts']]) }}</small>
                                                <span class="fa-track" style="display:block"><i style="width:{{ $r['points'] / $pmax * 100 }}%"></i></span></span>
                                            <b class="num">{{ $r['points'] }} <small style="display:inline">{{ __('forms::admin.pts') }}</small></b></div>
                                    @endforeach
                                </div>
                                @break

                            @default
                                @if($q['texts'])
                                    <div class="fa-quotes">@foreach($q['texts'] as $t)<div class="fa-quote" dir="auto">{{ $t['text'] }}@if($t['who'])<small>{{ $t['who'] }}</small>@endif</div>@endforeach</div>
                                @else
                                    <p class="fa-sub" style="margin-top:10px">—</p>
                                @endif
                        @endswitch
                    </div>
                @endforeach
            </div>
        @endforeach
    @endif
</div>
