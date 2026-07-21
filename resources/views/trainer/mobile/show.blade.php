@extends('layouts.app')

@section('hide-navbar', true)
@section('title', $user->full_name)

@push('styles')
<style>
    .tr-hero {
        background:
            radial-gradient(130% 90% at 12% 0%, hsl(250 75% 70%) 0%, transparent 55%),
            radial-gradient(120% 90% at 92% 8%, hsl(285 70% 62%) 0%, transparent 50%),
            linear-gradient(165deg, hsl(250 65% 58%), hsl(258 62% 46%));
    }
    .tr-hero::after { content:""; position:absolute; inset:0; opacity:.10; pointer-events:none;
        background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E"); }
    .tr-glow { position:absolute; width:230px; height:230px; border-radius:50%; filter:blur(64px); opacity:.45; pointer-events:none; }
    .tr-ring { background:conic-gradient(from 200deg, #fff, hsl(250 80% 85%), #fff, hsl(285 80% 85%), #fff);
        padding:3px; border-radius:30px; box-shadow:0 16px 40px rgba(60,20,120,.5); }
    .tr-chip { background:linear-gradient(135deg, hsl(250 60% 92%), hsl(285 60% 93%)); }
</style>
@endpush

@section('content')
@php
    use Illuminate\Support\Carbon;
    $instructor = $user->clubInstructors->first();
    $role = $instructor?->role;
    $initial = mb_strtoupper(mb_substr($user->full_name ?? 'T', 0, 1, 'UTF-8'), 'UTF-8');
    $dayShort = ['saturday'=>'Sat','sunday'=>'Sun','monday'=>'Mon','tuesday'=>'Tue','wednesday'=>'Wed','thursday'=>'Thu','friday'=>'Fri'];
    $dayOrder = ['saturday'=>0,'sunday'=>1,'monday'=>2,'tuesday'=>3,'wednesday'=>4,'thursday'=>5,'friday'=>6];
    // Group schedule slots by weekday.
    $byDay = [];
    foreach ($scheduleSlots as $s) {
        foreach ($s['days'] as $d) { $byDay[$d][] = $s; }
    }
    uksort($byDay, fn($a, $b) => ($dayOrder[$a] ?? 9) <=> ($dayOrder[$b] ?? 9));
    $clubs = $user->clubInstructors->filter(fn($i) => $i->tenant);
    $statCards = [
        ['bi-people-fill', $stats['clients'], __('trainer.clients')],
        ['bi-lightning-charge-fill', $stats['sessions'], __('trainer.sessions_per_month')],
        ['bi-star-fill', number_format($stats['rating'], 1), __('trainer.rating')],
        ['bi-award-fill', $stats['certifications'], __('trainer.skills')],
    ];
@endphp
<div x-data="{ tab: 'about' }" class="min-h-screen bg-background pb-16">

    {{-- ===== Hero ===== --}}
    <div class="tr-hero relative overflow-hidden text-white text-center px-6 pt-16 pb-12">
        <div class="tr-glow" style="background:#fff; top:-70px; left:-50px;"></div>
        <div class="tr-glow" style="background:hsl(285 80% 70%); bottom:-90px; right:-50px;"></div>

        {{-- back --}}
        <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('clubs.explore') }}')"
                class="m-press absolute top-4 left-4 w-10 h-10 rounded-full bg-white/20 backdrop-blur flex items-center justify-center border border-white/30" aria-label="{{ __('shared.back') }}">
            <i class="bi bi-arrow-left text-lg"></i>
        </button>
        <button type="button" onclick="navigator.share ? navigator.share({title: @js($user->full_name), url: location.href}) : (window.showToast && window.showToast('info',@js(__('trainer.link_copied'))), navigator.clipboard?.writeText(location.href))"
                class="m-press absolute top-4 right-4 w-10 h-10 rounded-full bg-white/20 backdrop-blur flex items-center justify-center border border-white/30" aria-label="{{ __('trainer.share') }}">
            <i class="bi bi-share text-base"></i>
        </button>

        {{-- avatar --}}
        <div class="relative inline-block">
            <div class="tr-ring inline-block">
                <span class="block w-28 h-28 rounded-[27px] overflow-hidden bg-white/10">
                    @if($user->profile_picture)
                        <img src="{{ asset('storage/'.$user->profile_picture) }}?v={{ optional($user->updated_at)->timestamp }}" alt="" class="w-full h-full object-cover">
                    @else
                        <span class="w-full h-full flex items-center justify-center text-4xl font-black">{{ $initial }}</span>
                    @endif
                </span>
            </div>
            @if($user->is_personal_trainer)
                <span class="absolute -bottom-1 -right-1 px-2.5 py-1 rounded-full bg-white text-primary text-[10px] font-extrabold shadow-lg flex items-center gap-1"><i class="bi bi-patch-check-fill"></i> PT</span>
            @endif
        </div>

        <h1 class="text-2xl font-extrabold mt-4 leading-tight">{{ $user->full_name }}</h1>
        <p class="text-white/80 text-sm mt-1">{{ $role ?: __('trainer.coach') }}@if($instructor?->tenant) · {{ $instructor->tenant->club_name }}@endif</p>

        {{-- rating + experience --}}
        <div class="flex items-center justify-center gap-2 mt-3 flex-wrap">
            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-white/15 backdrop-blur border border-white/25 text-xs font-semibold">
                <i class="bi bi-star-fill text-amber-300"></i> {{ number_format($stats['rating'], 1) }}
                <span class="text-white/60">({{ $reviews->count() }})</span>
            </span>
            @if($user->experience_years)
                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-white/15 backdrop-blur border border-white/25 text-xs font-semibold">
                    <i class="bi bi-fire text-amber-300"></i> {{ $user->experience_years }} {{ $user->experience_years == 1 ? __('trainer.yr') : __('trainer.yrs') }}
                </span>
            @endif
        </div>
    </div>

    {{-- ===== Stat strip (overlaps hero) ===== --}}
    <div class="px-4 -mt-7 relative z-10">
        <div class="grid grid-cols-4 bg-white rounded-2xl shadow-lg border border-gray-100 divide-x divide-gray-100 overflow-hidden">
            @foreach($statCards as [$ic, $val, $lbl])
                <div class="py-3 text-center">
                    <i class="bi {{ $ic }} text-primary text-sm"></i>
                    <p class="text-lg font-extrabold text-foreground leading-none mt-1">{{ $val }}</p>
                    <p class="text-[10px] text-muted-foreground mt-0.5">{{ $lbl }}</p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ===== Tabs ===== --}}
    <div class="sticky top-0 z-20 bg-background/95 backdrop-blur mt-5 px-4 py-2">
        <div class="flex gap-1.5 bg-muted rounded-full p-1">
            @foreach(['about'=>__('trainer.tab_about'),'schedule'=>__('trainer.tab_schedule'),'reviews'=>__('trainer.tab_reviews')] as $key => $label)
                <button type="button" @click="tab='{{ $key }}'"
                        class="m-press flex-1 py-2 rounded-full text-[13px] font-semibold transition-colors"
                        :class="tab==='{{ $key }}' ? 'bg-primary text-white shadow' : 'text-muted-foreground'">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div class="px-4 mt-4 space-y-4">
        {{-- ===== About ===== --}}
        <div x-show="tab==='about'" x-transition.opacity class="space-y-4 mobile-stagger">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground/80 mb-1.5">{{ __('trainer.bio') }}</p>
                <p class="text-[13px] text-foreground/90 whitespace-pre-line">{{ $user->bio ?: __('trainer.no_bio') }}</p>
            </div>

            @if(($skills ?? collect())->count())
                <div>
                    <p class="px-1 mb-2 text-[11px] font-bold uppercase tracking-wide text-muted-foreground/80">{{ __('trainer.specialities') }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($skills as $skill)
                            <span class="tr-chip px-3 py-1.5 rounded-full text-[12px] font-semibold text-primary"><i class="bi bi-patch-check mr-1"></i>{{ $skill }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($clubs->isNotEmpty())
                <div>
                    <p class="px-1 mb-2 text-[11px] font-bold uppercase tracking-wide text-muted-foreground/80">{{ __('trainer.trains_at') }}</p>
                    <div class="space-y-2.5">
                        @foreach($clubs as $ci)
                            <a href="{{ $ci->tenant->slug && $ci->tenant->country ? route('clubs.show', ['country'=>strtolower($ci->tenant->country), 'slug'=>$ci->tenant->slug]) : '#' }}"
                               class="m-press flex items-center gap-3 bg-white rounded-2xl shadow-sm border border-gray-100 p-3">
                                <span class="w-12 h-12 rounded-xl bg-muted overflow-hidden flex items-center justify-center flex-shrink-0">
                                    @if($ci->tenant->logo)<img src="{{ asset('storage/'.$ci->tenant->logo) }}" alt="" class="w-12 h-12 object-contain p-1">@else<i class="bi bi-buildings text-muted-foreground text-lg"></i>@endif
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-foreground truncate">{{ $ci->tenant->club_name }}</p>
                                    <p class="text-[12px] text-muted-foreground truncate">{{ $ci->role ?: __('trainer.coach') }}@if($ci->rating) · <i class="bi bi-star-fill text-amber-400"></i> {{ number_format($ci->rating,1) }}@endif</p>
                                </div>
                                <i class="bi bi-chevron-right text-muted-foreground/60"></i>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- ===== Schedule ===== --}}
        <div x-show="tab==='schedule'" x-cloak x-transition.opacity class="space-y-3">
            @forelse($byDay as $day => $slots)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="flex items-center gap-2 px-4 py-2.5 bg-muted/50 border-b border-gray-100">
                        <span class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center text-[11px] font-extrabold">{{ $dayShort[$day] ?? ucfirst(substr($day,0,3)) }}</span>
                        <span class="text-sm font-bold text-foreground capitalize">{{ $day }}</span>
                        <span class="ml-auto text-[11px] text-muted-foreground">{{ count($slots) }} {{ __('trainer.classes_count') }}</span>
                    </div>
                    <div class="divide-y divide-gray-50">
                        @foreach($slots as $s)
                            <div class="flex items-center gap-3 px-4 py-3">
                                <span class="text-[13px] font-bold text-primary w-16 flex-shrink-0">{{ $s['start'] }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-foreground truncate">{{ $s['activity_name'] }}</p>
                                    <p class="text-[11px] text-muted-foreground truncate">{{ collect([$s['club_name'], $s['facility_name']])->filter()->join(' · ') }}</p>
                                </div>
                                <span class="text-[11px] text-muted-foreground flex-shrink-0">{{ $s['duration'] }}m</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-12 text-center">
                    <i class="bi bi-calendar-week text-4xl text-gray-300 m-float inline-block"></i>
                    <p class="text-sm font-semibold text-foreground mt-3">{{ __('trainer.no_schedule') }}</p>
                    <p class="text-[12px] text-muted-foreground mt-1">{{ __('trainer.no_schedule_note') }}</p>
                </div>
            @endforelse
        </div>

        {{-- ===== Reviews ===== --}}
        <div x-show="tab==='reviews'" x-cloak x-transition.opacity class="space-y-3">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center gap-4">
                <div class="text-center">
                    <p class="text-3xl font-extrabold text-foreground leading-none">{{ number_format($stats['rating'], 1) }}</p>
                    <div class="flex gap-0.5 mt-1 text-amber-400 text-[12px]">@for($i=1;$i<=5;$i++)<i class="bi {{ $i <= round($stats['rating']) ? 'bi-star-fill' : 'bi-star' }}"></i>@endfor</div>
                </div>
                <div class="flex-1 border-l border-gray-100 pl-4">
                    <p class="text-sm font-semibold text-foreground">{{ $reviews->count() }} {{ __('trainer.reviews_count') }}</p>
                    <p class="text-[12px] text-muted-foreground">{{ __('trainer.from_athletes') }}</p>
                </div>
            </div>

            {{-- Class reactions (emojis athletes left) --}}
            @if(!empty($reactionTotal) && $reactionTotal > 0)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <p class="text-[12px] font-semibold text-muted-foreground mb-2.5"><i class="bi bi-emoji-smile mr-1"></i> Class reactions · {{ $reactionTotal }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($reactions as $emoji => $cnt)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-muted">
                                <span class="text-xl leading-none">{{ $emoji }}</span>
                                <span class="text-xs font-bold text-foreground">{{ $cnt }}</span>
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            @forelse($reviews as $review)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <div class="flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-full bg-gradient-to-br from-primary to-purple-400 text-white flex items-center justify-center font-bold flex-shrink-0">{{ mb_strtoupper(mb_substr($review->reviewer->full_name ?? 'A', 0, 1, 'UTF-8'), 'UTF-8') }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-foreground truncate">{{ $review->reviewer->full_name ?? __('trainer.anonymous') }}</p>
                            <div class="flex gap-0.5 text-amber-400 text-[10px]">@for($i=1;$i<=5;$i++)<i class="bi {{ $i <= $review->rating ? 'bi-star-fill' : 'bi-star' }}"></i>@endfor</div>
                        </div>
                        <span class="text-[11px] text-muted-foreground">{{ $review->formatted_date ?? optional($review->created_at)->diffForHumans() }}</span>
                    </div>
                    @if($review->comment)<p class="text-[13px] text-foreground/90 mt-2.5">{{ $review->comment }}</p>@endif
                </div>
            @empty
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-12 text-center">
                    <i class="bi bi-chat-square-heart text-4xl text-gray-300 m-float inline-block"></i>
                    <p class="text-sm font-semibold text-foreground mt-3">{{ __('trainer.no_reviews') }}</p>
                    <p class="text-[12px] text-muted-foreground mt-1">{{ __('trainer.no_reviews_note') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
