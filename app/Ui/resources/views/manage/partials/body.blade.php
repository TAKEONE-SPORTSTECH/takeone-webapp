{{-- The Manage shell body: sidebar (picker + menu) and the overview.
     Needs $orgs, $current, $overview. Menu entries open the EXISTING admin
     screens, which keep their own authorisation. --}}
@php
    $o = $current;
    $nav = [];
    if ($o && $o['kind'] === 'club') {
        $slug = $o['club']->slug;
        $nav = [
            ['overview', __('ui::manage.overview'), 'bi-grid', route('me.manage', ['org' => $o['key']])],
            ['members', __('ui::manage.members'), 'bi-people', route('admin.club.members', $slug)],
            ['packages', __('ui::manage.packages'), 'bi-calendar-week', route('admin.club.packages', $slug)],
            ['finance', __('ui::manage.finance'), 'bi-cash-coin', route('admin.club.financials', $slug)],
            ['events', __('ui::manage.events'), 'bi-calendar-event', route('admin.club.events', $slug)],
            ['shop', __('ui::manage.shop'), 'bi-shop', route('admin.club.shop', $slug)],
            ['staff', __('ui::manage.staff'), 'bi-person-badge', route('admin.club.instructors', $slug)],
            ['settings', __('ui::manage.settings'), 'bi-gear', route('admin.club.details', $slug)],
        ];
    } elseif ($o && $o['kind'] === 'business') {
        $nav = [['overview', __('ui::manage.overview'), 'bi-grid', route('me.manage', ['org' => $o['key']])],
                ['dash', __('ui::manage.full_dashboard'), 'bi-bar-chart', route('business.dashboard')]];
        foreach ($o['clubs'] as $c) {
            $nav[] = ['club-'.$c->id, $c->club_name, 'bi-building', route('me.manage', ['org' => 'c-'.$c->slug])];
        }
    } elseif ($o && $o['kind'] === 'federation') {
        $nav = [['overview', __('ui::manage.overview'), 'bi-grid', route('me.manage', ['org' => $o['key']])],
                ['fed', __('ui::manage.open_federation'), 'bi-box-arrow-up-right', route('federations.dashboard', $o['federation']->uuid)]];
    }
    $isBiz = $o && $o['kind'] === 'business';
    $t = $overview['totals'] ?? null;
    $cur = $t['currency'] ?? 'BHD';
    $money = fn ($v) => (strtoupper($cur ?: 'BHD') === 'BHD' ? 'BD' : strtoupper($cur)).' '.number_format((float) $v, fmod((float) $v, 1.0) == 0.0 ? 0 : 3);
@endphp
<div class="t1 t1-page">
    <div class="t1-mlay" style="--oc:{{ $o['color'] ?? '#7c3aed' }}">
        <aside class="t1-msb">
            <div class="t1-orgwrap">@include('ui::manage.partials.picker')</div>
            <nav class="t1-mnav" aria-label="{{ $o['name'] ?? '' }}">
                @foreach($nav as [$k, $label, $icon, $url])
                    <a href="{{ $url }}" class="{{ $k === 'overview' ? 'on' : '' }}"><i class="bi {{ $icon }}"></i>{{ $label }}</a>
                @endforeach
                @if(auth()->user()->isSuperAdmin())
                    <a href="{{ route('admin.platform.index') }}"><i class="bi bi-shield-check"></i>{{ __('ui::manage.platform') }}</a>
                @endif
            </nav>
            <div class="t1-msbfoot">{{ __('ui::manage.signed_in_as', ['name' => auth()->user()->full_name ?? auth()->user()->name]) }}</div>
        </aside>

        <div class="t1-mmain">
            <div class="t1-scope">@include('ui::manage.partials.picker')</div>

            @if(! $o)
                <div class="t1-box t1-empty" style="margin-top:18px"><b>{{ __('ui::manage.nothing') }}</b>{{ __('ui::manage.nothing_sub') }}</div>
            @else
                <div class="t1-mpad">
                    <header class="t1-band t1-oband" style="--c:{{ $o['color'] }}">
                        <span class="t1-c1"></span><span class="t1-c2"></span>
                        <div class="t1-band-in">
                            <span class="t1-chipw"><i class="bi {{ $o['kind'] === 'federation' ? 'bi-bank' : ($isBiz ? 'bi-briefcase' : 'bi-building') }}"></i>{{ __('ui::manage.'.$o['kind']) }}</span>
                            @if($o['parent'])
                                @php $p = collect($orgs)->firstWhere('key', $o['parent']); @endphp
                                <a class="t1-chipw" style="margin-inline-start:6px;text-decoration:none" href="{{ route('me.manage', ['org' => $o['parent']]) }}"><i class="bi bi-chevron-left rtl:rotate-180"></i>{{ $p['name'] ?? '' }}</a>
                            @endif
                            <h2>{{ $o['name'] }}</h2>
                            <p class="t1-sub" style="color:rgb(255 255 255/.86);margin:6px 0 0;font-size:13px"><i class="bi bi-person"></i> {{ __('ui::manage.you_are', ['role' => mb_strtolower(__('ui::manage.role_'.$o['role']))]) }}</p>
                        </div>
                    </header>

                    @if($o['kind'] === 'federation')
                        <div class="t1-box t1-after" style="display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap">
                            <span style="color:var(--t1-soft);font-size:13.5px">{{ __('ui::manage.fed_note') }}</span>
                            <a class="t1-cta" href="{{ route('federations.dashboard', $o['federation']->uuid) }}">{{ __('ui::manage.open_federation') }}</a>
                        </div>
                    @else
                        <div class="t1-kpis t1-after">
                            <div class="t1-kpi"><span>{{ __('ui::manage.kpi_revenue') }}</span><b>{{ $money($overview['thisMonth']) }}</b><small>{{ __('ui::manage.net') }} {{ $money($t['net']) }}</small>@include('ui::manage.partials.spark', ['values' => $overview['months'], 'color' => $o['color']])</div>
                            <div class="t1-kpi"><span>{{ __('ui::manage.kpi_members') }}</span><b>{{ number_format($t['members']) }}</b><small>{{ trans_choice('ui::explore.n_clubs', $t['clubs'], ['count' => $t['clubs']]) }}</small>@include('ui::manage.partials.spark', ['values' => [0, $t['members']], 'color' => $o['color']])</div>
                            <div class="t1-kpi"><span>{{ __('ui::manage.kpi_cash') }}</span><b>{{ $money($t['cash_to_collect']) }}</b><small class="{{ $t['cash_to_collect'] > 0 ? 'warn' : '' }}">&nbsp;</small>@include('ui::manage.partials.spark', ['values' => [$t['cash_to_collect'], $t['cash_to_collect']], 'color' => '#d97706'])</div>
                            <div class="t1-kpi"><span>{{ __('ui::manage.kpi_subs') }}</span><b>{{ number_format($t['active_subs']) }}</b><small>&nbsp;</small>@include('ui::manage.partials.spark', ['values' => [0, $t['active_subs']], 'color' => $o['color']])</div>
                        </div>

                        <div class="t1-bcols t1-after">
                            <section class="t1-box">
                                <h4><i class="bi bi-bell"></i>{{ __('ui::manage.needs_you') }}@if(count($overview['needs']))<span class="t1-alert" style="margin-inline-start:auto">{{ count($overview['needs']) }}</span>@endif</h4>
                                @forelse($overview['needs'] as $n)
                                    <div class="t1-row">@if($n['org'])@include('ui::manage.partials.tile', ['o' => $n['org'], 's' => 30])@endif
                                        <span class="t1-tx"><b>{{ $n['text'] }}</b><small>{{ $n['org']['name'] ?? '' }}</small></span>
                                        <a class="t1-sbtn" href="{{ $n['url'] }}">{{ $n['cta'] }}</a></div>
                                @empty
                                    <div class="t1-row"><span class="t1-tx"><b>{{ __('ui::manage.all_clear') }}</b><small>{{ __('ui::manage.all_clear_sub') }}</small></span></div>
                                @endforelse
                            </section>
                            <section class="t1-box">
                                <h4><i class="bi bi-building"></i>{{ __('ui::manage.clubs') }}</h4>
                                @foreach($overview['rows'] as $r)
                                    @php $co = $overview['clubOrgs'][$r['id']] ?? null; @endphp
                                    <a class="t1-row" href="{{ $isBiz ? route('me.manage', ['org' => 'c-'.$r['slug']]) : route('admin.club.dashboard', $r['slug']) }}">
                                        @if($co)@include('ui::manage.partials.tile', ['o' => $co, 's' => 30])@endif
                                        <span class="t1-tx"><b>{{ $r['name'] }}</b><small>{{ trans_choice('ui::explore.members', $r['members'], ['n' => $r['members']]) }} · {{ __('ui::manage.revenue') }} {{ $money($r['revenue']) }}</small></span>
                                        <i class="bi bi-chevron-right rtl:rotate-180"></i></a>
                                @endforeach
                            </section>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
