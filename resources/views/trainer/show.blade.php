@extends('layouts.app')

@section('title', ($user->full_name ?? 'Trainer') . ' — ' . ($user->clubInstructors->first()?->tenant->club_name ?? 'Trainer'))

@push('styles')
@if($user->profile_picture)
<link rel="icon" type="image/png" href="{{ asset('storage/' . $user->profile_picture) }}">
@elseif($user->clubInstructors->first()?->tenant->logo)
<link rel="icon" type="image/png" href="{{ asset('storage/' . $user->clubInstructors->first()->tenant->logo) }}">
@endif
@if(request()->routeIs('trainer.show.public'))
<style>@media (max-width: 768px) { nav { display: none !important; } }</style>
@endif
<style>
    /* ── Trainer profile — desktop, full-width magazine layout ─────────── */
    .tp-hero {
        position: relative; overflow: hidden;
        background:
            radial-gradient(1100px 480px at 82% -10%, hsl(262 70% 62% / .55), transparent 60%),
            radial-gradient(760px 420px at 8% 120%, hsl(250 80% 55% / .5), transparent 60%),
            linear-gradient(135deg, hsl(250 66% 56%), hsl(262 60% 48%));
    }
    .tp-hero::after { /* subtle dot grid */
        content: ""; position: absolute; inset: 0; opacity: .12; pointer-events: none;
        background-image: radial-gradient(#fff 1px, transparent 1.4px);
        background-size: 22px 22px;
    }
    .tp-hero-blob { position: absolute; border-radius: 9999px; filter: blur(6px); background: #fff; opacity: .06; pointer-events: none; }
    .tp-glass { background: hsl(0 0% 100% / .14); border: 1px solid hsl(0 0% 100% / .22); backdrop-filter: blur(8px); }
    .tp-avatar-ring { box-shadow: 0 0 0 6px #fff, 0 22px 48px -14px hsl(250 60% 30% / .55); }
    @media (min-width: 1024px) { .tp-sticky { position: sticky; top: 1.5rem; } }
    .tp-stat { transition: transform .25s ease, box-shadow .25s ease; }
    .tp-stat:hover { transform: translateY(-3px); }
    .tp-tab { position: relative; }
    .tp-tab.is-active { color: hsl(250 55% 50%); }
    .tp-tab.is-active::after { content: ""; position: absolute; inset-inline: .5rem; bottom: -1px; height: 3px; border-radius: 3px; background: hsl(250 65% 60%); }
    .tp-section-kicker { display: inline-flex; align-items: center; gap: .5rem; font-size: .72rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: hsl(250 40% 55%); }
    .tp-section-kicker::before { content: ""; height: 2px; width: 26px; border-radius: 3px; background: linear-gradient(90deg, hsl(250 65% 62%), hsl(262 60% 55%)); }
</style>
@endpush

@section('content')
@php
    $primaryInstructor = $user->clubInstructors->first();
    $club = $primaryInstructor?->tenant;
    $role = $primaryInstructor?->tr('role');
    // Distinct clubs this person works at
    $clubs = $user->clubInstructors->map->tenant->filter()->unique('id')->values();
    $skills = is_array($user->skills) ? array_values(array_filter($user->skills)) : [];
    $initial = mb_strtoupper(mb_substr($user->full_name ?? '?', 0, 1, 'UTF-8'), 'UTF-8');

    $statCards = [
        ['label' => __('trainer.trainer_show_clients'),        'value' => $stats['clients'],        'icon' => 'bi-people-fill'],
        ['label' => __('trainer.trainer_show_sessions'),       'value' => $stats['sessions'],       'icon' => 'bi-activity'],
        ['label' => __('trainer.trainer_show_rating'),         'value' => $stats['rating'] > 0 ? $stats['rating'] : '—', 'icon' => 'bi-star-fill'],
        ['label' => __('trainer.trainer_show_certifications'), 'value' => $stats['certifications'], 'icon' => 'bi-patch-check-fill'],
    ];

    $trainerPhone   = $user->mobile_formatted ?? null;
    $trainerEmail   = $user->email ?? null;
    $trainerSocials = $user->social_links ?? [];
    $socialIcons = [
        'facebook'=>['icon'=>'bi-facebook','label'=>'Facebook'],'instagram'=>['icon'=>'bi-instagram','label'=>'Instagram'],
        'twitter'=>['icon'=>'bi-twitter-x','label'=>'Twitter/X'],'linkedin'=>['icon'=>'bi-linkedin','label'=>'LinkedIn'],
        'youtube'=>['icon'=>'bi-youtube','label'=>'YouTube'],'tiktok'=>['icon'=>'bi-tiktok','label'=>'TikTok'],
        'snapchat'=>['icon'=>'bi-snapchat','label'=>'Snapchat'],'whatsapp'=>['icon'=>'bi-whatsapp','label'=>'WhatsApp'],
        'telegram'=>['icon'=>'bi-telegram','label'=>'Telegram'],'discord'=>['icon'=>'bi-discord','label'=>'Discord'],
        'github'=>['icon'=>'bi-github','label'=>'GitHub'],'spotify'=>['icon'=>'bi-spotify','label'=>'Spotify'],
    ];
    $hasContact = $trainerPhone || $trainerEmail || !empty(array_filter($trainerSocials));

    $dayOrder = ['saturday','sunday','monday','tuesday','wednesday','thursday','friday'];
    $dayAbbr  = ['saturday'=>'Sat','sunday'=>'Sun','monday'=>'Mon','tuesday'=>'Tue','wednesday'=>'Wed','thursday'=>'Thu','friday'=>'Fri'];
    $todayKey = strtolower(now()->format('l'));
    $todayIndex = array_search($todayKey, $dayOrder);
    $visibleDays = array_slice($dayOrder, $todayIndex !== false ? $todayIndex : 0);
@endphp

<div class="bg-background min-h-screen pb-16" x-data="{ activeTab: 'about' }">

    {{-- ══════════ HERO ══════════ --}}
    <section class="tp-hero text-white">
        <span class="tp-hero-blob" style="width:260px;height:260px;top:-70px;right:16%;"></span>
        <span class="tp-hero-blob" style="width:180px;height:180px;bottom:-60px;left:6%;"></span>

        <div class="relative mx-auto max-w-[1400px] px-6 lg:px-10 pt-8 pb-12">
            @if(!request()->routeIs('trainer.show.public'))
            <a href="{{ url()->previous() }}" class="inline-flex items-center gap-2 text-sm text-white/80 hover:text-white transition-colors mb-6">
                <i class="bi bi-arrow-left"></i><span>{{ __('shared.back') }}</span>
            </a>
            @endif

            <div class="flex flex-col lg:flex-row lg:items-center gap-8">
                {{-- Identity --}}
                <div class="flex-1 min-w-0">
                    @if($role)
                        <span class="tp-glass inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold text-white mb-4">
                            <i class="bi bi-mortarboard-fill"></i> {{ $role }}
                        </span>
                    @endif
                    <h1 class="text-4xl lg:text-6xl font-black tracking-tight leading-[1.05] mb-4">{{ $user->full_name }}</h1>

                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-white/85">
                        <span class="inline-flex items-center gap-1.5">
                            <i class="bi bi-star-fill text-amber-300"></i>
                            <span class="font-bold text-white">{{ $stats['rating'] > 0 ? $stats['rating'] : 'N/A' }}</span>
                            <span class="text-white/70">({{ $reviews->count() }} {{ __('trainer.trainer_show_tab_reviews') }})</span>
                        </span>
                        @if($user->experience_years)
                            <span class="inline-flex items-center gap-1.5">
                                <i class="bi bi-graph-up-arrow"></i>
                                {{ $user->experience_years }} {{ $user->experience_years == 1 ? __('trainer.trainer_show_year') : __('trainer.trainer_show_years') }} {{ __('trainer.trainer_show_experience_word') }}
                            </span>
                        @endif
                        @if($club)
                            <span class="inline-flex items-center gap-1.5"><i class="bi bi-geo-alt-fill"></i>{{ $club->club_name }}</span>
                        @endif
                    </div>
                </div>

                {{-- Stat strip --}}
                <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 lg:min-w-[440px]">
                    @foreach($statCards as $s)
                        <div class="tp-stat tp-glass rounded-2xl p-4 text-center">
                            <i class="bi {{ $s['icon'] }} text-white/70 mb-1.5"></i>
                            <p class="text-2xl font-black text-white leading-none">{{ $s['value'] }}</p>
                            <p class="text-[11px] uppercase tracking-wide text-white/70 mt-1.5">{{ $s['label'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ══════════ BODY ══════════ --}}
    <div class="mx-auto max-w-[1400px] px-6 lg:px-10 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            {{-- ───── SIDEBAR ───── --}}
            <aside class="lg:col-span-4 xl:col-span-3 space-y-5 tp-sticky self-start">

                {{-- Portrait (always 3:4) + club logo --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <div class="flex items-center gap-4">
                        <div class="w-32 flex-shrink-0">
                            <div class="aspect-[3/4] rounded-xl overflow-hidden bg-accent ring-1 ring-gray-100">
                                @if($user->profile_picture)
                                    <img src="{{ asset('storage/' . $user->profile_picture) }}" alt="{{ $user->full_name }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center">
                                        <span class="text-5xl font-black text-primary">{{ $initial }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @if($club?->logo)
                            <div class="flex-1 flex items-center justify-center min-w-0">
                                <img src="{{ asset('storage/' . $club->logo) }}" alt="{{ $club->club_name }}" class="max-h-24 max-w-full object-contain">
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Certifications / skills --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <p class="tp-section-kicker mb-3">{{ __('trainer.trainer_show_certifications') }}</p>
                    @if(count($skills) > 0)
                        <div class="flex flex-wrap gap-2">
                            @foreach($skills as $skill)
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-accent text-primary">
                                    <i class="bi bi-patch-check-fill"></i>{{ $skill }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-muted-foreground">{{ __('trainer.trainer_show_no_certifications') }}</p>
                    @endif
                </div>

                {{-- Clubs --}}
                @if($clubs->count() > 0)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <p class="tp-section-kicker mb-3">{{ __('trainer.trainer_show_clients') }}</p>
                    <div class="space-y-2">
                        @foreach($clubs as $c)
                            <a href="{{ $c->url ?? '#' }}" class="flex items-center gap-3 p-2 -mx-1 rounded-xl hover:bg-muted/60 transition-colors no-underline">
                                <span class="w-9 h-9 flex-shrink-0 flex items-center justify-center">
                                    @if($c->logo)
                                        <img src="{{ asset('storage/' . $c->logo) }}" class="w-full h-full object-contain" alt="{{ $c->club_name }}">
                                    @else
                                        <span class="w-9 h-9 rounded-lg bg-accent flex items-center justify-center text-primary font-bold text-sm">{{ mb_substr($c->club_name,0,1) }}</span>
                                    @endif
                                </span>
                                <span class="text-sm font-medium text-foreground truncate">{{ $c->club_name }}</span>
                                <i class="bi bi-chevron-right ms-auto text-xs text-muted-foreground"></i>
                            </a>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Contact --}}
                @if($hasContact)
                <div id="trainer-contact" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <p class="tp-section-kicker mb-3">{{ __('trainer.trainer_show_contact_information') }}</p>
                    <div class="space-y-2.5">
                        @if($trainerPhone)
                            <a href="tel:{{ $trainerPhone }}" class="flex items-center gap-3 text-sm text-foreground hover:text-primary transition-colors">
                                <span class="w-9 h-9 rounded-lg bg-accent flex items-center justify-center text-primary"><i class="bi bi-telephone-fill"></i></span>
                                <span class="font-medium">{{ $trainerPhone }}</span>
                            </a>
                        @endif
                        @if($trainerEmail)
                            <a href="mailto:{{ $trainerEmail }}" class="flex items-center gap-3 text-sm text-foreground hover:text-primary transition-colors break-all">
                                <span class="w-9 h-9 rounded-lg bg-accent flex items-center justify-center text-primary flex-shrink-0"><i class="bi bi-envelope-fill"></i></span>
                                <span class="font-medium">{{ $trainerEmail }}</span>
                            </a>
                        @endif
                    </div>
                    @if(!empty(array_filter($trainerSocials)))
                        <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-gray-100">
                            @foreach($trainerSocials as $platform => $url)
                                @if(!empty($url) && isset($socialIcons[$platform]))
                                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" title="{{ $socialIcons[$platform]['label'] }}"
                                       class="w-10 h-10 rounded-xl border border-gray-200 flex items-center justify-center text-foreground hover:bg-primary hover:text-white hover:border-primary transition-all">
                                        <i class="bi {{ $socialIcons[$platform]['icon'] }}"></i>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
                @endif
            </aside>

            {{-- ───── MAIN ───── --}}
            <main class="lg:col-span-8 xl:col-span-9 space-y-6">

                {{-- Tabs --}}
                <div x-ref="tabs" id="trainer-tabs" class="bg-white rounded-2xl shadow-sm border border-gray-100 px-2">
                    <nav class="flex items-center gap-1 overflow-x-auto">
                        @foreach([['about','trainer_show_tab_about','bi-person-badge'],['schedule','trainer_show_tab_schedule','bi-calendar-week'],['reviews','trainer_show_tab_reviews','bi-star']] as $t)
                            <button @click="activeTab = '{{ $t[0] }}'"
                                    :class="activeTab === '{{ $t[0] }}' ? 'is-active' : 'text-muted-foreground hover:text-foreground'"
                                    class="tp-tab flex items-center gap-2 px-5 py-4 text-sm font-semibold whitespace-nowrap transition-colors">
                                <i class="bi {{ $t[2] }}"></i>{{ __('trainer.'.$t[1]) }}
                            </button>
                        @endforeach
                    </nav>
                </div>

                {{-- ===== ABOUT ===== --}}
                <div x-show="activeTab === 'about'" x-cloak class="space-y-6">
                    {{-- Bio --}}
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 lg:p-8">
                        <p class="tp-section-kicker mb-4">{{ __('trainer.trainer_show_biography') }}</p>
                        <p class="text-foreground/90 leading-relaxed text-lg">{{ $user->bio ?? __('trainer.trainer_show_no_biography') }}</p>

                        @if($activities->count() > 0)
                            <div class="mt-8 pt-6 border-t border-gray-100">
                                <p class="tp-section-kicker mb-4">{{ __('trainer.trainer_show_classes_taught') }}</p>
                                <div class="flex flex-wrap gap-2.5">
                                    @foreach($activities as $activity)
                                        <span class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium border-2 border-gray-100 rounded-full hover:border-primary hover:bg-accent hover:text-primary transition-all cursor-default">
                                            <i class="bi bi-lightning-charge-fill text-xs"></i>{{ $activity->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Highlights --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        @php
                            $highlights = [
                                ['label'=>__('trainer.trainer_show_specialty'),   'value'=>$role ?? __('trainer.trainer_show_trainer_fallback'), 'icon'=>'bi-award-fill'],
                                ['label'=>__('trainer.trainer_show_experience'),  'value'=>($user->experience_years ?? 0).' '.(($user->experience_years ?? 0) == 1 ? __('trainer.trainer_show_year') : __('trainer.trainer_show_years')), 'icon'=>'bi-graph-up-arrow'],
                                ['label'=>__('trainer.trainer_show_rating'),      'value'=>($stats['rating'] > 0 ? $stats['rating'] : '—'), 'icon'=>'bi-star-fill'],
                            ];
                        @endphp
                        @foreach($highlights as $h)
                            <div class="group relative overflow-hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-all">
                                <div class="absolute -top-8 -end-8 w-24 h-24 rounded-full bg-accent/60 transition-transform group-hover:scale-125"></div>
                                <div class="relative">
                                    <div class="w-11 h-11 rounded-xl bg-accent flex items-center justify-center text-primary mb-3"><i class="bi {{ $h['icon'] }} text-lg"></i></div>
                                    <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">{{ $h['label'] }}</p>
                                    <p class="text-lg font-bold text-foreground mt-0.5">{{ $h['value'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- ===== SCHEDULE ===== --}}
                <div x-show="activeTab === 'schedule'" x-cloak
                     x-data="{
                         activeDay: '{{ $todayKey }}',
                         getStatus(start, end) {
                             const now = new Date(); const pad = n => String(n).padStart(2,'0');
                             const t = pad(now.getHours()) + ':' + pad(now.getMinutes());
                             if (t < start) return 'upcoming'; if (t <= end) return 'live'; return 'finished';
                         }
                     }">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 lg:p-8">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                            <div>
                                <h3 class="text-xl font-bold text-foreground">{{ __('trainer.trainer_show_weekly_schedule') }}</h3>
                                <p class="text-sm text-muted-foreground">{{ __('trainer.trainer_show_schedule_subtitle') }}</p>
                            </div>
                        </div>

                        @if(count($scheduleSlots) > 0)
                            <div class="flex flex-wrap gap-2 mb-6">
                                @foreach($visibleDays as $dayKey)
                                    <button type="button" @click="activeDay = '{{ $dayKey }}'"
                                            :class="activeDay === '{{ $dayKey }}' ? 'bg-primary text-white border-primary' : 'bg-white text-muted-foreground border-gray-200 hover:border-primary hover:text-primary'"
                                            class="px-5 py-2 rounded-full border text-sm font-semibold transition-colors {{ $dayKey === $todayKey ? 'ring-2 ring-primary/30' : '' }}">
                                        {{ $dayAbbr[$dayKey] }}
                                    </button>
                                @endforeach
                            </div>

                            <div class="grid grid-cols-1 xl:grid-cols-2 gap-3">
                                @foreach($scheduleSlots as $slot)
                                    <div class="class-card"
                                         x-show="@js(array_map('strval', $slot['days'])).includes(activeDay)"
                                         :class="activeDay === '{{ $todayKey }}' ? getStatus('{{ $slot['start'] }}', '{{ $slot['end'] }}') + '-card' : ''"
                                         :style="{ order: activeDay === '{{ $todayKey }}' ? ({'live':0,'upcoming':1,'finished':2}[getStatus('{{ $slot['start'] }}', '{{ $slot['end'] }}')] ?? 0) : null }"
                                         x-cloak>
                                        <div class="class-thumb">
                                            @if($slot['picture_url'])
                                                <img src="{{ asset('storage/' . $slot['picture_url']) }}" alt="{{ $slot['activity_name'] }}" class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full bg-gradient-to-br from-primary to-primary/70 flex items-center justify-center min-h-[80px]"><i class="bi bi-activity text-white text-xl"></i></div>
                                            @endif
                                        </div>
                                        <div class="flex-grow flex flex-col">
                                            <div class="flex justify-between items-start mb-1">
                                                <div>
                                                    <h6 class="text-base font-bold mb-0">{{ $slot['activity_name'] }}</h6>
                                                    <div class="class-meta text-muted-foreground flex items-center gap-x-4 mt-0.5 text-sm">
                                                        <span><i class="bi bi-clock me-1"></i>{{ \Carbon\Carbon::parse($slot['start'])->format('g:i A') }} – {{ \Carbon\Carbon::parse($slot['end'])->format('g:i A') }}</span>
                                                        <span class="flex items-center gap-1 ms-2"><i class="bi bi-stopwatch"></i>{{ $slot['duration'] }} {{ __('trainer.trainer_show_min') }}</span>
                                                    </div>
                                                    @if($slot['facility_name'])
                                                        <div class="text-sm text-muted-foreground mt-0.5"><i class="bi bi-geo-alt me-1"></i>{{ $slot['facility_name'] }}</div>
                                                    @endif
                                                </div>
                                                <div class="flex flex-col items-end gap-2 shrink-0">
                                                    <div x-show="activeDay === '{{ $todayKey }}'">
                                                        <span x-show="getStatus('{{ $slot['start'] }}', '{{ $slot['end'] }}') === 'live'" class="status-chip status-ongoing"><span class="live-dot"></span> {{ __('trainer.trainer_show_ongoing') }}</span>
                                                        <span x-show="getStatus('{{ $slot['start'] }}', '{{ $slot['end'] }}') === 'upcoming'" class="status-chip status-bookable"><i class="bi bi-clock-fill"></i> {{ __('trainer.trainer_show_upcoming') }}</span>
                                                        <span x-show="getStatus('{{ $slot['start'] }}', '{{ $slot['end'] }}') === 'finished'" class="status-chip status-finished"><i class="bi bi-check-circle-fill"></i> {{ __('trainer.trainer_show_finished') }}</span>
                                                    </div>
                                                    @if($slot['club_name'])
                                                        <a href="{{ $slot['club_url'] ?? '#' }}" class="text-xs font-medium text-primary hover:underline flex items-center gap-1"><i class="bi bi-building"></i> {{ $slot['club_name'] }}</a>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap gap-1 mt-2">
                                                @if($slot['package_name'])<span class="pill-tag">{{ $slot['package_name'] }}</span>@endif
                                                @foreach($slot['days'] as $d)<span class="pill-tag">{{ $dayAbbr[$d] ?? ucfirst(substr($d,0,3)) }}</span>@endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                <div x-show="!@js(collect($scheduleSlots)->map(fn($s) => $s['days'])->flatten()->unique()->values()->toArray()).includes(activeDay)" class="text-center py-16 xl:col-span-2">
                                    <i class="bi bi-calendar-x text-muted-foreground text-5xl"></i>
                                    <p class="text-lg font-medium mt-4">{{ __('trainer.trainer_show_no_classes_day') }}</p>
                                    <p class="text-sm text-muted-foreground mt-2">{{ __('trainer.trainer_show_try_different_day') }}</p>
                                </div>
                            </div>
                        @else
                            <div class="text-center py-12">
                                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-muted flex items-center justify-center"><i class="bi bi-calendar-x text-2xl text-muted-foreground"></i></div>
                                <p class="text-muted-foreground">{{ __('trainer.trainer_show_no_classes_scheduled') }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- ===== REVIEWS ===== --}}
                <div x-show="activeTab === 'reviews'" x-cloak class="space-y-6">
                    @if(!empty($reactionTotal) && $reactionTotal > 0)
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <p class="tp-section-kicker mb-3"><i class="bi bi-emoji-smile"></i>{{ __('trainer.trainer_show_class_reactions') }} · {{ $reactionTotal }}</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach($reactions as $emoji => $cnt)
                                    <span class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full bg-muted">
                                        <span class="text-xl leading-none">{{ $emoji }}</span><span class="text-sm font-bold text-foreground">{{ $cnt }}</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($reviews->count() > 0)
                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                            @foreach($reviews as $review)
                                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-all">
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-11 h-11 rounded-full bg-gradient-to-br from-primary to-[hsl(262_60%_55%)] flex items-center justify-center shadow-sm">
                                                <span class="text-base font-bold text-white">{{ mb_strtoupper(mb_substr($review->reviewer->full_name ?? 'A', 0, 1, 'UTF-8'), 'UTF-8') }}</span>
                                            </div>
                                            <div>
                                                <p class="font-bold text-sm text-foreground">{{ $review->reviewer->full_name ?? __('trainer.trainer_show_anonymous') }}</p>
                                                <p class="text-xs text-muted-foreground inline-flex items-center gap-1"><i class="bi bi-clock"></i>{{ $review->formatted_date }}</p>
                                            </div>
                                        </div>
                                        <div class="inline-flex items-center gap-0.5 px-2.5 py-1 bg-amber-50 rounded-lg border border-amber-100">
                                            @for($i = 0; $i < $review->rating; $i++)<i class="bi bi-star-fill text-amber-400 text-xs"></i>@endfor
                                        </div>
                                    </div>
                                    @if($review->comment)<p class="text-foreground/80 leading-relaxed text-sm">{{ $review->comment }}</p>@endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 text-center py-16">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-muted flex items-center justify-center"><i class="bi bi-chat text-2xl text-muted-foreground"></i></div>
                            <p class="text-muted-foreground">{{ __('trainer.trainer_show_no_reviews') }}</p>
                        </div>
                    @endif
                </div>

            </main>
        </div>
    </div>
</div>
@endsection
