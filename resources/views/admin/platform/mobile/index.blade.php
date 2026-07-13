@extends('layouts.app')

@section('hide-navbar', true)
@section('title', 'Platform Admin')

@section('content')
<div class="min-h-screen bg-background pb-20">

    {{-- ===== Header ===== --}}
    <header class="sticky top-0 z-40 bg-white border-b border-border">
        <div class="flex items-center gap-2 px-3 h-14">
            <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('me.home') }}')"
                    class="m-press w-10 h-10 -ml-1 rounded-xl flex items-center justify-center text-foreground" aria-label="{{ __('shared.back') }}">
                <i class="bi bi-arrow-left text-xl"></i>
            </button>
            <p class="flex-1 min-w-0 text-base font-bold text-primary truncate">{{ __('platform.platform_admin') }}</p>
        </div>
    </header>

    {{-- ===== Hero ===== --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-start justify-between gap-3 relative z-10">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">{{ __('platform.platform_admin') }}</p>
                <h1 class="text-2xl font-black mt-0.5 leading-tight">{{ __('platform.control_center') }}</h1>
                <p class="mt-1.5 text-sm text-white/85">{{ __('platform.control_center_desc') }}</p>
            </div>
            <div class="w-12 h-12 shrink-0 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                <i class="bi bi-shield-lock text-xl m-float"></i>
            </div>
        </div>
    </header>

    {{-- ===== KPIs ===== --}}
    <div class="px-4 pt-5 relative z-10 grid grid-cols-2 gap-3 mobile-stagger">
        @foreach([
            ['bi-building',  __('platform.kpi_clubs'),      $stats['clubs'],      'text-primary',  'bg-accent',    'admin.platform.clubs'],
            ['bi-people',    __('platform.kpi_members'),    $stats['members'],    'text-blue-600', 'bg-blue-50',   'admin.platform.members'],
            ['bi-buildings', __('platform.kpi_businesses'), $stats['businesses'], 'text-green-600','bg-green-50',  'admin.platform.businesses'],
            ['bi-hourglass-split', __('platform.kpi_pending'), $stats['businessesPending'], 'text-amber-600', 'bg-amber-50', 'admin.platform.businesses'],
        ] as [$icon, $label, $value, $color, $bg, $route])
            <a href="{{ route($route) }}" class="block bg-white rounded-2xl p-4 shadow-sm border border-gray-100 m-card m-press hover:bg-muted/40 transition-colors">
                <span class="w-10 h-10 rounded-xl {{ $bg }} {{ $color }} flex items-center justify-center"><i class="bi {{ $icon }} text-lg"></i></span>
                <p class="mt-3 text-2xl font-extrabold text-foreground leading-none">{{ number_format($value) }}</p>
                <p class="mt-1 text-[12px] text-muted-foreground">{{ $label }}</p>
            </a>
        @endforeach
    </div>

    {{-- ===== Pending approvals alert ===== --}}
    @if($stats['businessesPending'] > 0)
        <div class="px-4 mt-3">
            <a href="{{ route('admin.platform.businesses') }}" class="m-press flex items-center gap-3 rounded-2xl bg-amber-50 border border-amber-200 px-4 py-3">
                <span class="w-9 h-9 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center flex-shrink-0"><i class="bi bi-bell text-lg"></i></span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-amber-800">{{ $stats['businessesPending'] === 1 ? __('platform.awaiting_approval_one', ['count' => $stats['businessesPending']]) : __('platform.awaiting_approval_many', ['count' => $stats['businessesPending']]) }}</p>
                    <p class="text-[12px] text-amber-700/80">{{ __('platform.tap_to_review') }}</p>
                </div>
                <i class="bi bi-chevron-right text-amber-600"></i>
            </a>
        </div>
    @endif

    {{-- ===== Sections ===== --}}
    <div class="px-4 mt-5 mobile-stagger">
        <p class="px-1 mb-2 text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80">{{ __('platform.manage') }}</p>
        <div class="bg-white rounded-2xl overflow-hidden divide-y divide-gray-100 shadow-sm border border-gray-100">
            @php
                $sections = [
                    ['admin.platform.clubs',     'bi-building',     __('platform.section_clubs_title'),      __('platform.section_clubs_desc'), null],
                    ['admin.platform.members',   'bi-people',       __('platform.section_members_title'),    __('platform.section_members_desc'), null],
                    ['admin.platform.businesses','bi-buildings',    __('platform.section_businesses_title'), __('platform.section_businesses_desc'), $stats['businessesPending'] ?: null],
                    ['admin.platform.audit-log', 'bi-journal-text', __('platform.section_audit_title'),      __('platform.section_audit_desc'), null],
                    ['admin.platform.backup',    'bi-database',     __('platform.section_backup_title'),     __('platform.section_backup_desc'), null],
                ];
            @endphp
            @foreach($sections as [$route, $icon, $title, $desc, $badge])
                <a href="{{ route($route) }}" class="m-press flex items-center gap-3 px-4 py-3.5 hover:bg-muted/60 transition-colors">
                    <span class="w-10 h-10 rounded-xl bg-accent text-primary flex items-center justify-center flex-shrink-0"><i class="bi {{ $icon }} text-lg"></i></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-foreground">{{ $title }}</p>
                        <p class="text-[12px] text-muted-foreground truncate">{{ $desc }}</p>
                    </div>
                    @if($badge)
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">{{ $badge }}</span>
                    @endif
                    <i class="bi bi-chevron-right text-muted-foreground/60 flex-shrink-0"></i>
                </a>
            @endforeach
        </div>
    </div>
</div>
@endsection
