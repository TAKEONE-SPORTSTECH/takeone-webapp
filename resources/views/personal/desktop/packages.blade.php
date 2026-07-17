@extends('layouts.app')

@section('title', __('nav.my_packages'))

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-6">
    @php
        $dayOrder = ['saturday','sunday','monday','tuesday','wednesday','thursday','friday'];
        $dayAbbr  = ['saturday'=>'Sat','sunday'=>'Sun','monday'=>'Mon','tuesday'=>'Tue','wednesday'=>'Wed','thursday'=>'Thu','friday'=>'Fri'];
        $activeSubs = $subscriptions->where('status', 'active')->count();
        $subClubs   = $subscriptions->pluck('tenant_id')->filter()->unique()->count();
    @endphp

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">{{ __('nav.my_packages') }}</h1>
            <p class="text-sm text-muted-foreground mt-1">{{ __('personal.membership') }}</p>
        </div>
        <a href="{{ route('clubs.explore') }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium">
            <i class="bi bi-plus-lg mr-2"></i>{{ __('nav.explore_clubs') }}
        </a>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-2xl font-bold text-foreground">{{ $activeSubs }}</p>
            <p class="text-xs text-muted-foreground mt-1">{{ __('personal.aff_active_badge') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-2xl font-bold text-foreground">{{ $subClubs }}</p>
            <p class="text-xs text-muted-foreground mt-1">{{ __('personal.active_clubs') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 col-span-2 sm:col-span-2">
            <p class="text-2xl font-bold text-foreground">{{ $subscriptions->count() }}</p>
            <p class="text-xs text-muted-foreground mt-1">{{ __('personal.aff_total') }}</p>
        </div>
    </div>

    @if($subscriptions->isEmpty())
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-16 text-center">
            <i class="bi bi-box text-4xl text-gray-300"></i>
            <p class="text-sm text-muted-foreground mt-3">{{ __('personal.no_packages_yet') }}</p>
            <a href="{{ route('clubs.explore') }}" class="inline-block mt-3 bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary/90 transition-colors">{{ __('personal.explore_clubs') }}</a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($subscriptions as $sub)
            @php
                $cur  = $sub->tenant->currency ?? '';
                $acts = $sub->package?->packageActivities ?? collect();
            @endphp
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                @if($sub->package && $sub->package->cover_image)
                    <img src="{{ asset('storage/'.$sub->package->cover_image) }}" alt="" class="w-full h-36 object-cover">
                @endif
                <div class="p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="font-bold text-foreground truncate">{{ $sub->package?->tr('name') ?? __('personal.membership') }}</h3>
                            <p class="text-xs text-muted-foreground truncate flex items-center gap-1 mt-0.5">
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
                                            <a href="{{ route('trainer.show', $coach->id) }}" class="flex items-center gap-1.5 bg-accent rounded-full pl-0.5 pr-2 py-0.5 flex-shrink-0 hover:bg-accent/70 transition-colors">
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
        @endforeach
        </div>
    @endif
</div>
@endsection
