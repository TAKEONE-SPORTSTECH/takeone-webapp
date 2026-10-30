{{-- Proposed decisions (SurveyAnalytics::decisions). Rules, not judgement:
     the page says so, shows the formula, and every card carries its numbers. --}}
@php
    $ar = app()->getLocale() === 'ar';
    $L = fn (array $o, string $k = 'label') => $ar && ! empty($o[$k.'_ar']) ? $o[$k.'_ar'] : ($o[$k] ?? '');
    $d = $stats['decisions'];
    $lanes = [['now', 'var(--now)'], ['next', 'var(--next)'], ['later', 'var(--later)'], ['show', 'var(--show)']];
    $laneColor = ['now' => 'var(--now)', 'next' => 'var(--next)', 'later' => 'var(--later)', 'show' => 'var(--show)', 'keep' => 'var(--keep)', 'park' => 'var(--keep)'];
    $laneOf = [];
    foreach (['now', 'next', 'later', 'show'] as $k) { foreach (($d['lanes'][$k] ?? []) as $it) { $laneOf[$it['value']] = $k; } }
@endphp
<div class="space-y-4">
    @if($example)
        <div class="fa-example"><i class="bi bi-eye"></i><span class="flex-1">{{ __('forms::admin.example_on') }}</span></div>
    @endif
    @if(! $d)
        <div class="fa-card fa-empty"><div class="ic"><i class="bi bi-signpost-split"></i></div>
            <h3>{{ __('forms::admin.dec_title') }}</h3>
            <p>{{ $stats['n'] ? __('forms::admin.no_decisions') : __('forms::admin.no_results_body') }}</p>
            @if(! $stats['n'] && $canExample)
                <a href="{{ route('admin.forms.show', $form->uuid) }}?example=1#decisions" class="fa-btn solid" style="margin-top:16px"><i class="bi bi-magic"></i>{{ __('forms::admin.example_show') }}</a>
            @endif
        </div>
    @else
        <div class="fa-card" style="overflow:hidden">
            <div class="fa-dec-h"><span class="fa-tag" style="background:rgba(255,255,255,.15);color:#fff">✦ {{ $d['n'] }} {{ __('forms::admin.answers') }}</span>
                <h3>{{ __('forms::admin.dec_title') }}</h3><p>{{ __('forms::admin.dec_sub') }}</p><code>{{ __('forms::admin.dec_method') }}</code></div>
            <div class="fa-lanes">
                @foreach($lanes as [$k, $c])
                    <div class="fa-lane" style="--c:{{ $c }}">
                        <div class="lh"><i></i>{{ __('forms::admin.lane_'.$k) }}<em class="num">{{ count($d['lanes'][$k]) }}</em></div>
                        <div class="ls">{{ __('forms::admin.lane_'.$k.'_sub') }}</div>
                        @forelse($d['lanes'][$k] as $it)
                            <div class="fa-dc">
                                <b>{{ $L($it) }}</b>
                                <p>{{ __('forms::admin.why', ['must' => $it['must'], 'n' => $d['n'], 'nice' => $it['nice']]) }}</p>
                                <div class="m"><span>{{ __('forms::admin.score') }} {{ $it['demand'] }}</span>
                                    @if($k !== 'show')<span>{{ __('forms::admin.st_'.$it['status']) }}</span><span>{{ __('forms::admin.effort_'.$it['effort']) }}</span>@endif</div>
                            </div>
                        @empty
                            <p class="fa-empty-lane">—</p>
                        @endforelse
                    </div>
                @endforeach
            </div>
        </div>

        <div class="fa-card fa-pad">
            <div class="fa-h">{{ __('forms::admin.all_scores') }}</div>
            <div style="overflow-x:auto;margin-top:8px">
                <table class="fa-table">
                    <thead><tr><th></th><th class="num">{{ __('forms::admin.score') }}</th><th>Must</th><th>Nice</th><th></th><th></th></tr></thead>
                    <tbody>
                        @foreach($d['items'] as $it)
                            @php $lane = $laneOf[$it['value']] ?? $it['lane']; @endphp
                            <tr>
                                <td style="min-width:180px">{{ $L($it) }}</td>
                                <td class="num"><b>{{ $it['demand'] }}</b></td>
                                <td class="num">{{ $it['must'] }}</td>
                                <td class="num">{{ $it['nice'] }}</td>
                                <td style="color:var(--ink3);white-space:nowrap">{{ __('forms::admin.st_'.$it['status']) }}</td>
                                <td><span class="fa-pill" style="background:{{ $laneColor[$lane] }}">{{ in_array($lane, ['keep', 'park']) ? '—' : __('forms::admin.lane_'.$lane) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
