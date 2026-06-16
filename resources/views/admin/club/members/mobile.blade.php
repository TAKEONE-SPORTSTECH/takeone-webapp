@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? 'Club') . ' · Members')

@section('club-admin-content')
<div class="space-y-4">

    {{-- Filter tabs (reload with ?filter=) --}}
    <div class="grid grid-cols-3 gap-2">
        @php
            $tabs = [
                ['active', 'Active', $activeCount ?? 0, 'text-green-600'],
                ['not_active', 'Not active', $notActiveCount ?? 0, 'text-amber-600'],
                ['all', 'All', $allCount ?? 0, 'text-foreground'],
            ];
        @endphp
        @foreach($tabs as [$key, $label, $count, $color])
            <a href="{{ route('admin.club.members', $club->slug) }}?filter={{ $key }}"
               class="m-press rounded-xl border p-3 text-center transition-colors {{ ($filter ?? 'active') === $key ? 'border-primary bg-accent' : 'border-gray-100 bg-white' }}">
                <p class="text-xl font-bold {{ $color }}" data-countup="{{ (int) $count }}">{{ $count }}</p>
                <p class="text-[11px] text-muted-foreground mt-0.5">{{ $label }}</p>
            </a>
        @endforeach
    </div>

    {{-- Roster --}}
    @if($mobileMembers->isEmpty())
        <div class="m-card p-8 text-center">
            <i class="bi bi-people text-3xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-2">No members in this filter.</p>
        </div>
    @else
        <div class="space-y-2.5 mobile-stagger">
            @foreach($mobileMembers as $m)
                @php
                    $u = $m->user;
                    if (!$u) continue;
                    $age = $u->birthdate ? \Carbon\Carbon::parse($u->birthdate)->age : null;
                    $subs = $mobileSubscriptions->get($u->id);
                    $sub = $subs ? $subs->first() : null;
                    $pkgName = $sub && $sub->package ? $sub->package->name : null;
                @endphp
                <a href="{{ route('member.show', $u->uuid) }}"
                   class="m-press flex items-center gap-3 m-card p-3 active:bg-muted/40 transition-colors">
                    <span class="w-11 h-11 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                        @if($u->profile_picture)
                            <img src="{{ asset('storage/'.$u->profile_picture) }}?v={{ optional($u->updated_at)->timestamp }}" alt="" class="w-11 h-11 object-cover">
                        @else
                            <i class="bi bi-person text-muted-foreground text-lg"></i>
                        @endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-foreground truncate">{{ $u->full_name }}</p>
                        <p class="text-xs text-muted-foreground truncate">
                            @if($age){{ $age }} yrs @endif
                            @if($u->gender) · {{ ucfirst($u->gender) }}@endif
                            @if($pkgName) · {{ $pkgName }}@endif
                        </p>
                    </div>
                    @if($pkgName)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-green-100 text-green-700 flex-shrink-0">Active</span>
                    @else
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-100 text-amber-700 flex-shrink-0">No sub</span>
                    @endif
                    <i class="bi bi-chevron-right text-muted-foreground flex-shrink-0"></i>
                </a>
            @endforeach
        </div>
        <p class="text-[11px] text-muted-foreground text-center">{{ $mobileMembers->count() }} shown</p>
    @endif

    {{-- Demographics --}}
    @if(!empty($ageGroupCounts) && array_sum($ageGroupCounts) > 0)
    <div class="m-card p-4">
        <h3 class="font-semibold text-foreground mb-3">Age groups</h3>
        @php $maxAg = max(1, collect($ageGroupCounts)->max() ?: 1); @endphp
        <div class="space-y-2.5 mobile-stagger">
            @foreach($ageGroupCounts as $label => $count)
                <div>
                    <div class="flex justify-between text-xs mb-1"><span class="text-muted-foreground">{{ $label }}</span><span class="font-semibold text-foreground">{{ $count }}</span></div>
                    <div class="h-2 rounded-full bg-muted overflow-hidden"><div class="m-bar-fill h-full bg-primary rounded-full" style="width: {{ $count > 0 ? max(4, round($count/$maxAg*100)) : 0 }}%"></div></div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <p class="text-xs text-muted-foreground text-center px-4">Enrolment &amp; payment approvals are on the desktop view.</p>
</div>
@endsection
