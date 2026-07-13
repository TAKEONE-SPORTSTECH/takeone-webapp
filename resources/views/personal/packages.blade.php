{{-- Inside the personal mobile shell: header (avatar → drawer), notifications,
     chat and bottom tabs come from the shell. -mx-4 -mt-4 cancels <main>'s padding. --}}
@extends('layouts.personal-mobile')

@section('title', __('nav.my_packages'))

@section('personal-content')
<div class="-mx-4 -mt-4">

    {{-- Full-bleed (Facebook-style): each package is an edge-to-edge white block. --}}
    @php
        $dayOrder = ['saturday','sunday','monday','tuesday','wednesday','thursday','friday'];
        $dayAbbr  = ['saturday'=>'Sat','sunday'=>'Sun','monday'=>'Mon','tuesday'=>'Tue','wednesday'=>'Wed','thursday'=>'Thu','friday'=>'Fri'];
        $activeSubs = $subscriptions->where('status', 'active')->count();
        $subClubs   = $subscriptions->pluck('tenant_id')->filter()->unique()->count();
    @endphp

    {{-- ===== Hero summary ===== --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-10">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">{{ __('personal.membership') }}</p>
                <h1 class="text-2xl font-black mt-0.5">{{ __('nav.my_packages') }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('clubs.explore') }}" class="m-press w-12 h-12 rounded-2xl bg-white/20 border border-white/30 backdrop-blur grid place-items-center active:scale-95 transition-transform" aria-label="{{ __('nav.explore_clubs') }}">
                    <i class="bi bi-plus-lg text-xl"></i>
                </a>
                <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                    <i class="bi bi-box-seam text-xl m-float"></i>
                </div>
            </div>
        </div>

        <div class="flex gap-2 mt-5 relative z-10">
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ $activeSubs }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('personal.aff_active_badge') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ $subClubs }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('personal.active_clubs') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ $subscriptions->count() }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('personal.aff_total') }}</p>
            </div>
        </div>
    </header>

    <div class="px-4 pt-5 relative z-10 space-y-4 mobile-stagger">
    @forelse($subscriptions as $sub)
        @php
            $cur  = $sub->tenant->currency ?? '';
            $acts = $sub->package?->packageActivities ?? collect();
        @endphp
        <div class="m-card bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            @if($sub->package && $sub->package->cover_image)
                <img src="{{ asset('storage/'.$sub->package->cover_image) }}" alt="" class="w-full h-32 object-cover">
            @endif
            <div class="p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h3 class="font-bold text-foreground truncate">{{ $sub->package?->tr('name') ?? __('personal.membership') }}</h3>
                        <p class="text-xs text-muted-foreground truncate flex items-center gap-1">
                            @if($sub->tenant?->logo)<img src="{{ asset('storage/'.$sub->tenant->logo) }}" class="w-4 h-4 object-contain flex-shrink-0" alt="">@endif
                            {{ $sub->tenant?->tr('club_name') ?? '' }}
                        </p>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold flex-shrink-0 capitalize {{ $sub->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">{{ $sub->status }}</span>
                </div>

                <div class="flex items-center justify-between mt-3 text-xs">
                    <span class="text-base font-bold text-primary">{{ $cur }} {{ number_format((float)($sub->package->price ?? 0), 0) }}</span>
                    <span class="text-muted-foreground capitalize">{{ str_replace('_',' ', $sub->payment_status ?? '') }}</span>
                </div>

                {{-- Schedule + coach per included activity --}}
                @if($acts->count())
                    <div class="mt-3 pt-3 border-t border-gray-100 space-y-3">
                        @foreach($acts as $pa)
                            @php
                                $sched = is_array($pa->schedule) ? $pa->schedule : (json_decode($pa->schedule ?? '[]', true) ?: []);
                                $coach = $pa->instructor?->user;
                                $slots = [];
                                foreach ($sched as $s) {
                                    $st = $s['start_time'] ?? ''; $et = $s['end_time'] ?? ''; $d = strtolower($s['day'] ?? '');
                                    if (!$st || !$et || !$d) continue;
                                    $k = $st.'-'.$et;
                                    $slots[$k]['start'] = $st; $slots[$k]['end'] = $et;
                                    $slots[$k]['days'][$d] = true;
                                }
                            @endphp
                            <div>
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-foreground truncate flex items-center gap-1.5 min-w-0">
                                        <i class="bi bi-lightning-charge-fill text-primary text-xs flex-shrink-0"></i>{{ $pa->activity?->tr('name') ?? $pa->activity?->name }}
                                    </p>
                                    @if($coach)
                                        <a href="{{ route('trainer.show', $coach->id) }}" class="m-press flex items-center gap-1.5 bg-accent rounded-full pl-0.5 pr-2 py-0.5 flex-shrink-0">
                                            @if($coach->profile_picture)
                                                <img src="{{ asset('storage/'.$coach->profile_picture) }}?v={{ optional($coach->updated_at)->timestamp }}" class="w-5 h-5 rounded-full object-cover" alt="">
                                            @else
                                                <span class="w-5 h-5 rounded-full bg-primary/20 text-primary text-[9px] font-bold grid place-items-center">{{ mb_strtoupper(mb_substr($coach->full_name, 0, 1)) }}</span>
                                            @endif
                                            <span class="text-[10px] font-semibold text-primary truncate max-w-[92px]">{{ $coach->full_name }}</span>
                                        </a>
                                    @endif
                                </div>
                                @foreach($slots as $slot)
                                    @php $days = array_keys($slot['days']); usort($days, fn($a,$b)=>array_search($a,$dayOrder)-array_search($b,$dayOrder)); @endphp
                                    <div class="mt-1.5 flex items-center flex-wrap gap-1.5">
                                        @foreach($days as $d)
                                            <span class="px-1.5 py-0.5 rounded bg-primary/10 text-primary font-semibold text-[10px]">{{ $dayAbbr[$d] ?? ucfirst(substr($d,0,3)) }}</span>
                                        @endforeach
                                        <span class="text-[11px] text-muted-foreground flex items-center gap-1">
                                            <i class="bi bi-clock text-gray-400"></i>{{ \Carbon\Carbon::parse($slot['start'])->format('g:i A') }} – {{ \Carbon\Carbon::parse($slot['end'])->format('g:i A') }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-12 text-center">
            <i class="bi bi-box text-4xl text-gray-300"></i>
            <p class="text-sm text-muted-foreground mt-3">{{ __('personal.no_packages_yet') }}</p>
            <a href="{{ route('clubs.explore') }}" class="inline-block mt-3 bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('personal.explore_clubs') }}</a>
        </div>
    @endforelse
    </div>
</div>
@endsection
