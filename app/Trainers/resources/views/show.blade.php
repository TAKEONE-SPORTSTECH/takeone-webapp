@extends('layouts.app')

@section('hide-navbar', true)
@section('title', ($user->full_name ?? 'Trainer') . ' — ' . ($user->clubInstructors->first()?->tenant->club_name ?? 'Trainer'))

@push('styles')
@if($user->profile_picture && ($user->profile_picture_is_public ?? true))
<link rel="icon" type="image/png" href="{{ file_url($user->profile_picture) }}">
@elseif($user->clubInstructors->first()?->tenant->logo)
<link rel="icon" type="image/png" href="{{ file_url($user->clubInstructors->first()->tenant->logo) }}">
@endif
@endpush

{{--
    A trainer's profile — DESKTOP. Design source: drafts/Club Pages & Design/
    Trainners/emperor-coach-desktop.html, drawn in the club front page's language
    (clubs::public.partials.page-style) and in the colour of the club they coach at.

    Real data only. The draft's private-session booking is not drawn: the platform
    has no trainer rates, slots or bookings yet, so it would be a button to nowhere.
    The phone keeps trainers::mobile.show.
--}}
@section('content')
@php
    $primaryInstructor = $user->clubInstructors->first();
    $club = $primaryInstructor?->tenant;
    $role = $primaryInstructor?->tr('role');
    $clubs = $user->clubInstructors->map->tenant->filter()->unique('id')->values();

    $brand = $club ? data_get($club->settings, 'brand_color') : null;
    $cpAccent = is_string($brand) && preg_match('/^#[0-9a-fA-F]{6}$/', $brand) ? $brand : '#C8102E';

    $name = $user->full_name ?: $user->name;
    $firstName = \Illuminate\Support\Str::of($name)->explode(' ')->first();
    $showFace = $user->profile_picture && ($user->profile_picture_is_public ?? true);
    $face = $showFace ? file_url($user->profile_picture).'?v='.($user->updated_at?->timestamp ?? 1) : null;
    $cover = $club?->cover_image ? file_url($club->cover_image) : null;
    $experienceLabel = \App\Support\TrainerExperience::label($user);
    $onPlatform = $user->created_at ? $user->created_at->diffForHumans(null, \Carbon\CarbonInterface::DIFF_ABSOLUTE) : null;
    $country = $club?->country ? \App\Support\Countries::name($club->country) : null;

    $viewer = auth()->user();
    $canMessage = $viewer && $viewer->id !== $user->id && $viewer->canMessage($user);

    // Contact — exactly what this page has always shown.
    $trainerPhone = $user->mobile_formatted ?? null;
    $trainerEmail = $user->email ?? null;
    $socialIcons = ['facebook' => 'bi-facebook', 'instagram' => 'bi-instagram', 'twitter' => 'bi-twitter-x', 'linkedin' => 'bi-linkedin',
        'youtube' => 'bi-youtube', 'tiktok' => 'bi-tiktok', 'snapchat' => 'bi-snapchat', 'whatsapp' => 'bi-whatsapp',
        'telegram' => 'bi-telegram', 'discord' => 'bi-discord', 'github' => 'bi-github', 'spotify' => 'bi-spotify'];
    $trainerSocials = collect($user->social_links ?? [])
        ->filter(fn ($url, $platform) => is_string($url) && preg_match('#^https?://#i', $url) && isset($socialIcons[$platform]));

    // What they teach — each discipline opens its directory page when the catalog has one.
    $activityLinks = \App\Platform\Models\ActivityCatalog::linksForNames($activities->pluck('name'));

    // Classes: one card per programme they coach, its days and times gathered.
    $dayOrder = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    $dayShort = fn ($d) => \Carbon\Carbon::parse($d)->locale(app()->getLocale())->translatedFormat('D');
    $classes = collect($scheduleSlots)->groupBy(fn ($s) => ($s['club_slug'] ?? '').'|'.($s['package_name'] ?? '').'|'.$s['activity_name'])
        ->map(function ($rows) use ($dayOrder, $dayShort) {
            $first = $rows->first();
            $days = $rows->flatMap(fn ($r) => $r['days'])->unique()->sortBy(fn ($d) => array_search($d, $dayOrder))->values();
            return [
                'title' => $first['activity_name'],
                'package' => $first['package_name'],
                'club' => $first['club_name'],
                'club_url' => $first['club_url'] ? $first['club_url'].'#packages' : null,
                'days' => $days->map($dayShort)->implode(' · '),
                'time' => \Carbon\Carbon::parse($rows->min('start'))->format('g:i A'),
                'image' => $first['picture_url'] ? file_url($first['picture_url']) : null,
            ];
        })->values();

    $week = collect($dayOrder)->map(fn ($d) => [
        'key' => $d,
        'label' => $dayShort($d),
        'slots' => collect($scheduleSlots)->filter(fn ($s) => in_array($d, $s['days'], true))->sortBy('start')->values(),
    ]);
    $today = strtolower(now()->format('l'));
    if (! count($week->firstWhere('key', $today)['slots'])) {
        $today = optional($week->first(fn ($d) => count($d['slots'])))['key'] ?? $today;
    }

    $bio = trim((string) $user->bio);
    $bioShort = \Illuminate\Support\Str::limit($bio, 320);
@endphp
@include('clubs::public.partials.page-style')
<style>
    .tp-head { background: var(--cp-card); border-bottom: 1px solid var(--cp-line); }
    .tp-shell { max-width: 1200px; margin: 0 auto; padding-inline: 20px; }
    .tp-cover { height: 400px; position: relative; background-color: #171719; background-size: cover; background-position: center; }
    .tp-cover::after { content: ''; position: absolute; inset: 0; background: linear-gradient(0deg, rgba(0,0,0,.6), rgba(0,0,0,0) 70%); }
    .tp-face { width: 150px; border-radius: 26px; border: 5px solid var(--cp-card); outline: 4px solid color-mix(in srgb, var(--cp-accent) 55%, #b49b4e); box-shadow: 0 14px 34px rgba(0,0,0,.2); }
    .tp-tabs { position: sticky; top: 0; z-index: 30; background: var(--cp-card); border-block: 1px solid var(--cp-line); }
    .tp-tab { padding: 17px 14px; white-space: nowrap; font-size: 13px; font-weight: 800; border-bottom: 3px solid transparent !important; min-height: 50px; color: var(--cp-muted); }
    .tp-tab.is-on { border-color: var(--cp-accent) !important; color: var(--cp-accent); }
    .tp-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 32px; }
    .tp-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    .tp-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
    .tp-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    .cp section[id] { scroll-margin-top: 70px; }
    @media (max-width: 960px) { .tp-grid { grid-template-columns: 1fr; } .tp-aside { display: none; } .tp-3 { grid-template-columns: 1fr 1fr; } }
</style>

<div class="cp" :class="dark && 'cp-dark'"
     x-data="{
        dark: false, tab: 'about', bioOpen: false, day: @js($today),
        init() { try { this.dark = localStorage.getItem('cp-dark') === '1'; } catch (e) {} },
        toggleDark() { this.dark = !this.dark; try { localStorage.setItem('cp-dark', this.dark ? '1' : '0'); } catch (e) {} },
        go(id) { const el = document.getElementById(id); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' }); this.tab = id; },
        share() {
            const url = window.location.href;
            if (navigator.share) { navigator.share({ title: @js($name), url }).catch(() => {}); return; }
            if (navigator.clipboard) navigator.clipboard.writeText(url).then(() => window.showToast && window.showToast('success', @js(__('trainer.tp_link_copied'))));
        }
     }">

{{-- ===================== HEADER ===================== --}}
<header class="tp-head">
    <div class="tp-shell" style="height:72px;display:flex;align-items:center;gap:12px;">
        <button type="button" class="cp-round" style="border:1px solid var(--cp-line);" onclick="history.length > 1 ? history.back() : (window.location.href='{{ $club?->url ?? route('clubs.explore') }}')" aria-label="{{ __('shared.back') }}" title="{{ __('shared.back') }}">
            <i class="bi bi-chevron-left rtl:rotate-180"></i>
        </button>
        @if($club)
            <a href="{{ $club->url }}" class="cp-display" style="font-weight:800;font-size:18px;display:flex;align-items:center;gap:10px;min-width:0;">
                @if($club->logo)<span style="width:34px;height:34px;flex-shrink:0;"><img src="{{ file_url($club->logo) }}" alt="" style="width:100%;height:100%;object-fit:contain;"></span>@endif
                <span style="text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $club->tr('club_name') ?: $club->club_name }}</span>
            </a>
        @endif
        <div style="margin-inline-start:auto;display:flex;gap:8px;align-items:center;">
            <button type="button" class="cp-pill" @click="toggleDark()" :aria-pressed="dark" aria-label="{{ __('clubs::page.theme') }}"><i class="bi" :class="dark ? 'bi-sun' : 'bi-moon-stars'"></i></button>
            <form method="POST" action="{{ route('locale.set') }}" style="margin:0;">
                @csrf @method('PUT')
                <input type="hidden" name="locale" value="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}">
                <button type="submit" class="cp-pill">{{ app()->getLocale() === 'ar' ? 'English' : 'عربي' }}</button>
            </form>
        </div>
    </div>
</header>

<main id="top">
{{-- ===================== COVER ===================== --}}
<div class="tp-cover" style="{{ $cover ? "background-image:url('".e($cover)."')" : 'background:linear-gradient(135deg, '.$cpAccent.', #171719)' }}">
    <div class="tp-shell" style="height:100%;position:relative;z-index:1;">
        @if($role)<span class="cp-badge" style="position:absolute;top:20px;inset-inline-start:20px;background:var(--cp-accent);color:#fff;padding:8px 16px;font-size:12px;"><i class="bi bi-patch-check-fill"></i>{{ $role }}</span>@endif
        @if($club?->tr('slogan'))<span class="cp-tag" style="position:absolute;bottom:20px;inset-inline-end:20px;color:rgba(255,255,255,.9);">{{ $club->tr('slogan') }}</span>@endif
    </div>
</div>

{{-- ===================== IDENTITY ===================== --}}
<div class="tp-shell">
    <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:24px;flex-wrap:wrap;">
        <div style="min-width:0;flex:1;">
            <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-top:-100px;position:relative;z-index:2;gap:16px;">
                <span class="cp-portrait tp-face">
                    @if($face)<img src="{{ $face }}" alt="{{ $name }}">@else<x-gender-avatar :gender="$user->gender" class="w-full h-full" />@endif
                </span>
                <div style="display:flex;gap:8px;margin-bottom:8px;">
                    <button type="button" class="cp-pill" style="background:var(--cp-card);" @click="share()"><i class="bi bi-share"></i>{{ __('clubs::page.share') }}</button>
                </div>
            </div>
            <div style="margin-top:20px;">
                <p class="cp-tag cp-red">{{ collect([$role ?: __('trainer.trainer_show_trainer_fallback'), $country])->filter()->implode(' · ') }}</p>
                <h1 style="font-size:clamp(40px,5vw,64px);font-weight:700;margin-top:8px;line-height:1;">{{ $name }}<span class="cp-red">.</span></h1>
                <p class="cp-muted" style="margin-top:10px;">
                    {{ collect([
                        $stats['rating'] > 0 ? '★ '.$stats['rating'].' · '.trans_choice('clubs::page.reviews_n', $reviews->count(), ['n' => $reviews->count()]) : null,
                        $experienceLabel ? $experienceLabel.' '.__('trainer.trainer_show_experience_word') : null,
                    ])->filter()->implode(' · ') }}
                </p>
                @if($club)
                    <a href="{{ $club->url }}" style="display:inline-flex;align-items:center;gap:10px;margin-top:14px;font-weight:700;font-size:14px;">
                        <span style="width:32px;height:32px;flex-shrink:0;display:grid;place-items:center;">
                            @if($club->logo)<img src="{{ file_url($club->logo) }}" alt="" style="width:100%;height:100%;object-fit:contain;">@else<i class="bi bi-building cp-red"></i>@endif
                        </span>
                        {{ $club->tr('club_name') ?: $club->club_name }} <i class="bi bi-arrow-up-right cp-flip cp-muted"></i>
                    </a>
                @endif
                @if($onPlatform)<p class="cp-muted" style="font-size:14px;margin-top:6px;"><i class="bi bi-geo-alt"></i> {{ collect([$club?->tr('address'), __('trainer.tp_on_platform', ['time' => $onPlatform])])->filter()->implode(' · ') }}</p>@endif
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
            @if($canMessage)
                <form method="POST" action="{{ route('messages.start', $user) }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="cp-cta"><i class="bi bi-chat-dots"></i>{{ __('trainer.tp_message', ['name' => $firstName]) }}</button>
                </form>
            @elseif(! auth()->check())
                <a href="{{ route('login') }}" class="cp-cta"><i class="bi bi-chat-dots"></i>{{ __('trainer.tp_sign_in_message') }}</a>
            @endif
            @if($classes->isNotEmpty())<button type="button" class="cp-pill" @click="go('classes')">{{ __('trainer.tp_explore_classes') }}</button>@endif
        </div>
    </div>

    <div class="tp-stats" style="margin:28px 0 32px;">
        <div class="cp-card" style="padding:16px;"><b class="cp-display" style="font-size:26px;">{{ number_format($stats['clients']) }}</b><p class="cp-muted" style="font-size:12px;margin-top:2px;">{{ __('trainer.tp_stat_students') }}</p></div>
        <div class="cp-card" style="padding:16px;"><b class="cp-display" style="font-size:26px;">{{ $stats['sessions'] }}</b><p class="cp-muted" style="font-size:12px;margin-top:2px;">{{ __('trainer.sessions_per_week') }}</p></div>
        <div class="cp-card" style="padding:16px;"><b class="cp-display" style="font-size:26px;">{{ $stats['rating'] > 0 ? $stats['rating'] : '—' }}</b><p class="cp-muted" style="font-size:12px;margin-top:2px;">{{ __('trainer.trainer_show_rating') }}</p></div>
        <div class="cp-card" style="padding:16px;"><b class="cp-display" style="font-size:26px;">{{ $skillCards->count() }}</b><p class="cp-muted" style="font-size:12px;margin-top:2px;">{{ __('trainer.trainer_show_certifications') }}</p></div>
    </div>
</div>

{{-- ===================== TABS ===================== --}}
<div class="tp-tabs">
    <nav class="tp-shell cp-scroll-x" style="display:flex;" aria-label="{{ $name }}">
        <button type="button" class="tp-tab" :class="tab === 'about' && 'is-on'" @click="go('about')">{{ __('trainer.trainer_show_tab_about') }}</button>
        @if($classes->isNotEmpty())<button type="button" class="tp-tab" :class="tab === 'classes' && 'is-on'" @click="go('classes')">{{ __('trainer.tp_tab_classes') }}</button>@endif
        @if($skillCards->isNotEmpty())<button type="button" class="tp-tab" :class="tab === 'skills' && 'is-on'" @click="go('skills')">{{ __('trainer.trainer_show_certifications') }}</button>@endif
        <button type="button" class="tp-tab" :class="tab === 'reviews' && 'is-on'" @click="go('reviews')">{{ __('trainer.trainer_show_tab_reviews') }}</button>
    </nav>
</div>

<div class="tp-shell tp-grid" style="padding-block:36px;">
    <div style="min-width:0;">

        {{-- ===================== ABOUT ===================== --}}
        <section id="about" style="margin-bottom:56px;">
            <p class="cp-tag cp-red">{{ __('trainer.tp_about_eyebrow') }}</p>
            <h2 style="font-size:40px;font-weight:700;margin-top:8px;">{{ __('trainer.tp_about_title') }}</h2>
            <div class="cp-card" style="padding:24px;margin-top:24px;">
                <h3 style="font-size:20px;font-weight:700;">{{ __('trainer.trainer_show_biography') }}</h3>
                @if($bio !== '')
                    <p class="cp-muted" style="line-height:1.75;margin-top:12px;white-space:pre-line;" x-show="!bioOpen">{{ $bioShort }}</p>
                    @if($bioShort !== $bio)
                        <p class="cp-muted" style="line-height:1.75;margin-top:12px;white-space:pre-line;" x-show="bioOpen" x-cloak>{{ $bio }}</p>
                        <button type="button" class="cp-red" style="font-weight:700;font-size:14px;margin-top:10px;" @click="bioOpen = !bioOpen">
                            <span x-text="bioOpen ? @js(__('trainer.tp_read_less')) : @js(__('trainer.tp_read_more'))"></span>
                            <i class="bi" :class="bioOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                        </button>
                    @endif
                @else
                    <p class="cp-muted" style="margin-top:12px;">{{ __('trainer.trainer_show_no_biography') }}</p>
                @endif
            </div>

            @if($activities->isNotEmpty())
                <h3 style="font-size:24px;font-weight:700;margin-top:32px;">{{ __('trainer.tp_teaches', ['name' => $firstName]) }}</h3>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;">
                    @foreach($activities->unique('name') as $activity)
                        @php $link = $activityLinks[mb_strtolower(trim($activity->name))] ?? null; @endphp
                        @if($link)
                            <a href="{{ $link }}" class="cp-pill">{{ $activity->name }} <i class="bi bi-arrow-up-right cp-flip" style="font-size:10px;"></i></a>
                        @else
                            <span class="cp-pill" style="pointer-events:none;">{{ $activity->name }}</span>
                        @endif
                    @endforeach
                </div>
            @endif

            @foreach($clubs as $c)
                <a href="{{ $c->url }}" class="cp-card cp-lift" style="margin-top:{{ $loop->first ? '28px' : '12px' }};padding:20px;display:flex;gap:16px;align-items:center;">
                    <span style="width:64px;height:64px;flex-shrink:0;display:grid;place-items:center;">
                        @if($c->logo)<img src="{{ file_url($c->logo) }}" alt="" style="width:100%;height:100%;object-fit:contain;">@else<span class="cp-icon-tile" style="width:64px;height:64px;"><i class="bi bi-building" style="font-size:26px;"></i></span>@endif
                    </span>
                    <div style="flex:1;min-width:0;">
                        <p class="cp-tag cp-red">{{ __('trainer.tp_trains_at') }}</p>
                        <h3 style="font-weight:700;font-size:18px;">{{ $c->tr('club_name') ?: $c->club_name }}</h3>
                        @if($c->tr('address'))<p class="cp-muted" style="font-size:14px;">{{ $c->tr('address') }}</p>@endif
                    </div>
                    <span class="cp-pill">{{ __('trainer.tp_visit') }} <i class="bi bi-arrow-up-right cp-flip"></i></span>
                </a>
            @endforeach
        </section>

        {{-- ===================== CLASSES ===================== --}}
        @if($classes->isNotEmpty())
        <section id="classes" style="margin-bottom:56px;">
            <p class="cp-tag cp-red">{{ __('trainer.tp_classes_eyebrow') }}</p>
            <h2 style="font-size:40px;font-weight:700;margin-top:8px;">{{ __('trainer.tp_classes_title') }}</h2>
            <div class="tp-2" style="margin-top:24px;">
                @foreach($classes as $cl)
                    <article class="cp-card cp-lift" style="display:flex;flex-direction:column;">
                        @if($cl['image'])<div style="height:120px;"><img src="{{ $cl['image'] }}" alt="" class="cp-cover"></div>@endif
                        <div style="padding:20px;display:flex;flex-direction:column;flex:1;">
                            <div style="display:flex;justify-content:space-between;gap:10px;">
                                <span class="cp-tag cp-red">{{ __('trainer.tp_group_class') }}</span>
                                @if($cl['club'])<span class="cp-muted" style="font-size:12px;">{{ $cl['club'] }}</span>@endif
                            </div>
                            <h3 style="font-size:22px;font-weight:700;margin-top:10px;">{{ $cl['title'] }}</h3>
                            <p class="cp-muted" style="font-size:14px;margin-top:6px;">{{ collect([$cl['package'] !== $cl['title'] ? $cl['package'] : null, $cl['days'], $cl['time']])->filter()->implode(' · ') }}</p>
                            @if($cl['club_url'])
                                <div style="margin-top:auto;padding-top:18px;"><a href="{{ $cl['club_url'] }}" class="cp-cta">{{ __('clubs::page.join') }} <i class="bi bi-arrow-up-right cp-flip"></i></a></div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            <h3 style="font-size:24px;font-weight:700;margin-top:36px;">{{ __('trainer.trainer_show_weekly_schedule') }}</h3>
            <div class="cp-scroll-x" style="display:flex;gap:8px;padding:16px 0;">
                @foreach($week as $d)
                    <button type="button" class="cp-pill" :class="day === '{{ $d['key'] }}' && 'is-on'" @click="day = '{{ $d['key'] }}'">{{ $d['label'] }}</button>
                @endforeach
            </div>
            @foreach($week as $d)
                <div x-show="day === '{{ $d['key'] }}'" @if($d['key'] !== $today) x-cloak @endif class="cp-fade">
                    @forelse($d['slots'] as $s)
                        <div class="cp-card" style="padding:16px 18px;margin-bottom:8px;display:flex;justify-content:space-between;gap:12px;align-items:center;">
                            <div style="min-width:0;">
                                <b>{{ $s['activity_name'] }}</b>
                                <p class="cp-muted" style="font-size:12px;">{{ collect([$s['club_name'], $s['facility_name']])->filter()->implode(' · ') }}</p>
                            </div>
                            <b style="font-size:14px;white-space:nowrap;">{{ \Carbon\Carbon::parse($s['start'])->format('g:i A') }} – {{ \Carbon\Carbon::parse($s['end'])->format('g:i A') }}</b>
                        </div>
                    @empty
                        <div class="cp-card cp-muted" style="padding:24px;text-align:center;">{{ __('trainer.trainer_show_no_classes_day') }}</div>
                    @endforelse
                </div>
            @endforeach
        </section>
        @endif

        {{-- ===================== SKILLS ===================== --}}
        @if($skillCards->isNotEmpty())
        <section id="skills" style="margin-bottom:56px;">
            <p class="cp-tag cp-red">{{ __('trainer.tp_skills_eyebrow') }}</p>
            <h2 style="font-size:40px;font-weight:700;margin-top:8px;">{{ __('trainer.tp_skills_title') }}</h2>
            <div class="tp-3" style="margin-top:24px;">
                @foreach($skillCards as $sk)
                    @php $tag = $sk['uuid'] ? 'a' : 'div'; @endphp
                    <{{ $tag }} @if($sk['uuid']) href="{{ route('activity.show', $sk['uuid']) }}" @endif class="cp-card {{ $sk['uuid'] ? 'cp-lift' : '' }}" style="padding:16px;display:block;">
                        <span class="cp-icon-tile" style="width:44px;height:44px;border-radius:14px;overflow:hidden;">
                            @if($sk['image'])<img src="{{ $sk['image'] }}" alt="" class="cp-cover">@else<i class="bi bi-patch-check-fill" style="font-size:20px;"></i>@endif
                        </span>
                        <h4 style="font-weight:700;margin-top:12px;">{{ $sk['name'] }}</h4>
                        @if($sk['years'])<p class="cp-muted" style="font-size:12px;margin-top:4px;"><i class="bi bi-hourglass-split"></i> {{ $sk['years'] }}</p>@endif
                    </{{ $tag }}>
                @endforeach
            </div>
        </section>
        @endif

        {{-- ===================== REVIEWS ===================== --}}
        <section id="reviews" style="margin-bottom:24px;">
            <p class="cp-tag cp-red">{{ __('trainer.tp_reviews_eyebrow') }}</p>
            <h2 style="font-size:40px;font-weight:700;margin-top:8px;">{{ __('trainer.tp_reviews_title') }}</h2>

            @if(! empty($reactionTotal))
                <div class="cp-card" style="padding:20px;margin-top:24px;">
                    <p class="cp-tag cp-muted">{{ __('trainer.trainer_show_class_reactions') }} · {{ $reactionTotal }}</p>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;">
                        @foreach($reactions as $emoji => $cnt)
                            <span class="cp-pill" style="pointer-events:none;"><span style="font-size:18px;line-height:1;">{{ $emoji }}</span><b>{{ $cnt }}</b></span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($reviews->count())
                <div class="tp-2" style="margin-top:16px;">
                    @foreach($reviews as $review)
                        <figure class="cp-card" style="margin:0;padding:20px;">
                            <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;">
                                <figcaption style="display:flex;gap:10px;align-items:center;min-width:0;">
                                    <span class="cp-round" style="background:var(--cp-accent);color:#fff;font-weight:800;">{{ mb_strtoupper(mb_substr($review->reviewer->full_name ?? 'A', 0, 1, 'UTF-8'), 'UTF-8') }}</span>
                                    <span style="min-width:0;"><b style="display:block;font-size:14px;">{{ $review->reviewer->full_name ?? __('trainer.trainer_show_anonymous') }}</b><small class="cp-muted">{{ $review->formatted_date }}</small></span>
                                </figcaption>
                                <span style="color:#f59e0b;font-size:12px;white-space:nowrap;">@for($i = 1; $i <= 5; $i++)<i class="bi {{ $i <= $review->rating ? 'bi-star-fill' : 'bi-star' }}"></i>@endfor</span>
                            </div>
                            @if($review->comment)<blockquote class="cp-muted" style="margin:12px 0 0;line-height:1.65;font-size:14px;">{{ $review->comment }}</blockquote>@endif
                        </figure>
                    @endforeach
                </div>
            @else
                <div class="cp-card" style="padding:36px;text-align:center;margin-top:24px;">
                    <span class="cp-icon-tile" style="width:60px;height:60px;margin:0 auto;"><i class="bi bi-stars" style="font-size:26px;"></i></span>
                    <h3 style="font-size:22px;font-weight:700;margin-top:14px;">{{ __('trainer.tp_no_reviews_title') }}</h3>
                    <p class="cp-muted" style="margin-top:6px;">{{ __('trainer.tp_no_reviews_sub', ['name' => $firstName]) }}</p>
                </div>
            @endif
        </section>
    </div>

    {{-- ===================== ASIDE ===================== --}}
    <aside class="tp-aside">
        <div style="position:sticky;top:76px;display:grid;gap:16px;">
            <div class="cp-card" style="padding:24px;">
                <p class="cp-tag cp-red">{{ __('trainer.tp_aside_eyebrow') }}</p>
                <h3 style="font-size:28px;font-weight:700;margin-top:10px;line-height:1.1;">{{ __('trainer.tp_aside_title', ['name' => $firstName]) }}</h3>
                <p class="cp-muted" style="margin-top:8px;font-size:14px;">{{ trans_choice('trainer.tp_aside_classes', $classes->count(), ['n' => $classes->count()]) }}</p>
                @if($classes->isNotEmpty())<button type="button" class="cp-cta" style="width:100%;margin-top:18px;" @click="go('classes')">{{ __('trainer.tp_explore_classes') }}</button>@endif
                @if($canMessage)
                    <form method="POST" action="{{ route('messages.start', $user) }}" style="margin:8px 0 0;">
                        @csrf
                        <button type="submit" class="cp-pill" style="width:100%;"><i class="bi bi-chat-dots"></i>{{ __('trainer.tp_message', ['name' => $firstName]) }}</button>
                    </form>
                @endif
            </div>

            @if($trainerPhone || $trainerEmail || $trainerSocials->isNotEmpty())
                <div class="cp-card" style="padding:20px;" id="trainer-contact">
                    <p class="cp-tag cp-muted">{{ __('trainer.trainer_show_contact_information') }}</p>
                    <div style="display:grid;gap:10px;margin-top:12px;font-size:14px;">
                        @if($trainerPhone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $trainerPhone) }}" style="display:flex;gap:10px;align-items:center;"><span class="cp-icon-tile" style="width:36px;height:36px;border-radius:12px;"><i class="bi bi-telephone-fill"></i></span>{{ $trainerPhone }}</a>@endif
                        @if($trainerEmail)<a href="mailto:{{ $trainerEmail }}" style="display:flex;gap:10px;align-items:center;word-break:break-all;"><span class="cp-icon-tile" style="width:36px;height:36px;border-radius:12px;"><i class="bi bi-envelope-fill"></i></span>{{ $trainerEmail }}</a>@endif
                    </div>
                    @if($trainerSocials->isNotEmpty())
                        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;padding-top:14px;border-top:1px solid var(--cp-line);">
                            @foreach($trainerSocials as $platform => $url)
                                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="cp-round" style="border:1px solid var(--cp-line);" aria-label="{{ ucfirst($platform) }}"><i class="bi {{ $socialIcons[$platform] }}"></i></a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </aside>
</div>

{{-- ===================== CLOSING CTA ===================== --}}
<div class="tp-shell" style="padding-bottom:64px;">
    <div style="border-radius:28px;padding:48px;color:#fff;background:var(--cp-accent);position:relative;overflow:hidden;">
        <p class="cp-tag">{{ __('clubs::page.cta_eyebrow') }}</p>
        <h2 style="font-size:clamp(40px,5vw,64px);font-weight:700;margin-top:14px;line-height:1;">{{ __('trainer.tp_cta_title', ['name' => $firstName]) }}</h2>
        <p style="margin-top:12px;">{{ __('trainer.tp_cta_sub') }}</p>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:24px;">
            @if($classes->isNotEmpty())<button type="button" class="cp-cta cp-cta-white" @click="go('classes')">{{ __('trainer.tp_explore_classes') }} <i class="bi bi-arrow-up-right cp-flip"></i></button>@endif
            @if($club)<a href="{{ $club->url }}" class="cp-ghost">{{ __('trainer.tp_visit_club') }}</a>@endif
        </div>
    </div>
</div>
</main>
</div>
@endsection
