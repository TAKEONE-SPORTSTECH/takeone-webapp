{{-- The organisation picker: where you are managing. Needs $orgs, $current. --}}
@php
    $groups = [
        'g_business' => collect($orgs)->filter(fn ($o) => $o['kind'] === 'business' || $o['parent']),
        'g_clubs' => collect($orgs)->filter(fn ($o) => $o['kind'] === 'club' && ! $o['parent'] && $o['role'] === 'owner'),
        'g_staff' => collect($orgs)->filter(fn ($o) => $o['kind'] === 'club' && ! $o['parent'] && $o['role'] !== 'owner'),
        'g_feds' => collect($orgs)->filter(fn ($o) => $o['kind'] === 'federation'),
    ];
    $parentName = $current && $current['parent'] ? (collect($orgs)->firstWhere('key', $current['parent'])['name'] ?? null) : null;
@endphp
<div style="position:relative" x-data="{ open:false }" @click.outside="open=false" @keydown.escape.window="open=false">
    <button type="button" class="t1-orgbtn" @click="open=!open" :aria-expanded="open">
        @if($current)@include('ui::manage.partials.tile', ['o' => $current, 's' => 36])@endif
        <span class="t1-tx"><b>{{ $current ? ($current['kind'] === 'business' ? $current['name'] : $current['name']) : __('ui::manage.manage') }}</b>
            <small>{{ $parentName ? __('ui::manage.in', ['name' => $parentName]) : ($current ? __('ui::manage.'.$current['kind']).' · '.__('ui::manage.role_'.$current['role']) : '') }}</small></span>
        <i class="bi bi-chevron-expand" style="color:var(--t1-soft)"></i>
    </button>
    <div class="t1-orgdd" x-show="open" x-cloak x-transition.opacity>
        <div class="t1-orglist">
            @foreach($groups as $label => $list)
                @if($list->isNotEmpty())
                    <div class="t1-orgg">{{ __('ui::manage.'.$label) }}</div>
                    @foreach($list as $o)
                        <a class="t1-orgrow {{ $current && $o['key'] === $current['key'] ? 'cur' : '' }} {{ $o['parent'] ? 'child' : '' }}" href="{{ route('me.manage', ['org' => $o['key']]) }}">
                            @include('ui::manage.partials.tile', ['o' => $o, 's' => 30])
                            <span class="t1-tx"><b>{{ $o['kind'] === 'business' ? __('ui::manage.everything') : $o['name'] }}</b><small>{{ $o['kind'] === 'business' ? $o['name'].' · ' : '' }}{{ __('ui::manage.role_'.$o['role']) }}</small></span>
                            @if($current && $o['key'] === $current['key'])<i class="bi bi-check-lg" style="color:var(--t1-primary-ink)"></i>@endif
                        </a>
                    @endforeach
                @endif
            @endforeach
        </div>
        <div class="t1-orgfoot"><a class="t1-orgnew" href="{{ route('business.setup') }}"><i class="bi bi-plus-lg"></i>{{ __('ui::manage.add') }}</a></div>
    </div>
</div>
