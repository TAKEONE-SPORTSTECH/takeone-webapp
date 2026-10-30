@extends('layouts.app')

@section('hide-navbar', true)
@section('title', $club->club_name)

@php $clubIcon = $club->logo ?: $club->favicon; @endphp
@if($clubIcon)
@section('favicon')
@php $clubIconUrl = file_url($clubIcon) . '?v=' . ($club->updated_at?->timestamp ?? '1'); @endphp
<link rel="icon" type="image/png" href="{{ $clubIconUrl }}">
<link rel="shortcut icon" type="image/png" href="{{ $clubIconUrl }}">
<link rel="apple-touch-icon" href="{{ $clubIconUrl }}">
@endsection
@endif

{{--
    The club's front page — DESKTOP. Design source: drafts/Club Pages & Design/Club/
    emperor-academy-desktop-complete.html. Its phone twin is clubs::public.mobile.show;
    both read the same data (App\Clubs\Support\ClubFrontPage), styles (page-style), behaviour
    (page-runtime) and overlays (page-overlays).

    Real data only: a section whose data is empty is not drawn.
--}}
@section('content')
@php extract(\App\Clubs\Support\ClubFrontPage::data(compact('club', 'achievementsAll', 'reviews', 'likedPostIds', 'joinedEventIds'))); @endphp
@include('clubs::public.partials.page-style')
@php $cpMobile = false; @endphp
<style>
    .cpd-head { position: sticky; top: 0; z-index: 40; background: var(--cp-card); border-bottom: 1px solid var(--cp-line); }
    .cpd-head-in { height: 82px; display: flex; align-items: center; gap: 16px; }
    .cpd-nav { margin-inline-start: auto; display: flex; align-items: center; gap: clamp(10px, 1.4vw, 22px); }
    .cpd-nav a.cpd-link { font-weight: 700; font-size: 12.5px; white-space: nowrap; }
    .cpd-nav a.cpd-link:hover { color: var(--cp-accent); }
    .cpd-hero { min-height: 690px; display: flex; align-items: center; }
    .cpd-hero-shade { position: absolute; inset: 0; background: linear-gradient(90deg, rgba(8,8,8,.95) 0%, rgba(8,8,8,.63) 43%, rgba(8,8,8,.07) 100%); }
    [dir=rtl] .cpd-hero-shade { background: linear-gradient(-90deg, rgba(8,8,8,.95) 0%, rgba(8,8,8,.63) 43%, rgba(8,8,8,.07) 100%); }
    .cpd-grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; }
    .cpd-grid-4 { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 20px; }
    .cpd-grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; }
    .cpd-split { display: grid; grid-template-columns: 5fr 7fr; gap: 48px; align-items: start; }
    .cpd-perks { display: grid; grid-template-columns: 7fr 5fr; gap: 24px; }
    .cp section[id] { scroll-margin-top: 96px; }
    @media (max-width: 1180px) { .cpd-nav a.cpd-link { display: none; } .cpd-grid-4 { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 900px) { .cpd-grid-3, .cpd-grid-4, .cpd-grid-2 { grid-template-columns: 1fr 1fr; } .cpd-split, .cpd-perks { grid-template-columns: 1fr; } }
</style>

<div class="cp" x-data="clubPage" :class="dark && 'cp-dark'" id="clubPage">
<script type="application/json" id="cp-json">@json($cpJson)</script>

{{-- ===================== HEADER ===================== --}}
<header class="cpd-head">
    <div class="cp-wrap cpd-head-in">
        <a href="{{ route('clubs.explore') }}" class="cp-round" style="border:1px solid var(--cp-line);" aria-label="{{ __('clubs::page.back_explore') }}" title="{{ __('clubs::page.back_explore') }}">
            <i class="bi bi-chevron-left rtl:rotate-180"></i>
        </a>
        <a href="#top" class="cp-display" style="font-weight:700;font-size:22px;display:flex;align-items:center;gap:10px;min-width:0;">
            @if($cpLogo)<span style="width:40px;height:40px;flex-shrink:0;"><img src="{{ $cpLogo }}" alt="" style="width:100%;height:100%;object-fit:contain;"></span>@endif
            <span style="text-transform:uppercase;white-space:nowrap;">{{ $cpNameHead }} @if($cpNameTail)<span class="cp-red">{{ $cpNameTail }}</span>@endif</span>
        </a>
        <nav class="cpd-nav" aria-label="{{ $cpName }}">
            <a class="cpd-link" href="#overview">{{ __('clubs::page.nav_about') }}</a>
            @if($cpPackages->isNotEmpty())<a class="cpd-link" href="#packages">{{ __('clubs::page.nav_classes') }}</a>@endif
            @if($cpPerks->isNotEmpty())<a class="cpd-link" href="#offers">{{ __('clubs::page.nav_offers') }}</a>@endif
            @if($cpProducts->isNotEmpty())<a class="cpd-link" href="#shop">{{ __('clubs::page.nav_shop') }}</a>@endif
            @if($cpAchievements->isNotEmpty())<a class="cpd-link" href="#medals">{{ __('clubs::page.nav_medals') }}</a>@endif
            @if($cpGallery->isNotEmpty() || $cpYoutube)<a class="cpd-link" href="#media">{{ __('clubs::page.nav_gallery') }}</a>@endif
            @if($cpWeek->contains(fn ($d) => count($d['slots'])))<a class="cpd-link" href="#schedule">{{ __('clubs::page.nav_schedule') }}</a>@endif

            <button type="button" class="cp-pill" @click="toggleDark()" :aria-pressed="dark" aria-label="{{ __('clubs::page.theme') }}" title="{{ __('clubs::page.theme') }}">
                <i class="bi" :class="dark ? 'bi-sun' : 'bi-moon-stars'"></i>
            </button>
            <form method="POST" action="{{ route('locale.set') }}" style="margin:0;">
                @csrf @method('PUT')
                <input type="hidden" name="locale" value="{{ $cpNextLocale }}">
                <button type="submit" class="cp-pill">{{ $cpNextLocale === 'ar' ? 'عربي' : 'English' }}</button>
            </form>
            @if($canManage)
                @if(auth()->user()?->isSuperAdmin())
                    <button type="button" class="cp-pill" onclick="window.dispatchEvent(new CustomEvent('open-club-modal', { detail: { mode: 'edit', clubId: {{ (int) $club->id }} } }))">
                        <i class="bi bi-pencil-square"></i>{{ __('clubs::page.edit_club') }}
                    </button>
                @else
                    <a href="{{ route('admin.club.details', $club->slug) }}" class="cp-pill"><i class="bi bi-pencil-square"></i>{{ __('clubs::page.edit_club') }}</a>
                @endif
            @endif
            <button type="button" class="cp-cta" @click="join()">{{ __('clubs::page.become_member') }} <i class="bi bi-arrow-up-right cp-flip"></i></button>
        </nav>
    </div>
</header>

<main id="top">
{{-- ===================== HERO ===================== --}}
<section class="cp-hero cpd-hero" style="{{ $cpCover ? "background-image:url('".e($cpCover)."')" : 'background:linear-gradient(135deg, '.$cpAccent.', #111)' }}">
    <div class="cpd-hero-shade"></div>
    <div class="cp-wrap" style="position:relative;width:100%;padding-block:80px;">
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;" class="cp-tag">
            @if($cpSports->isNotEmpty())<span style="background:var(--cp-accent);padding:8px 16px;border-radius:99px;">{{ $cpSports->take(2)->implode(' · ') }}</span>@endif
            @if($cpPlace)<span><i class="bi bi-geo-alt-fill"></i> {{ $cpPlace }}</span>@endif
            @if($club->established_date)<span>· {{ __('clubs::page.since', ['year' => \Carbon\Carbon::parse($club->established_date)->format('Y')]) }}</span>@endif
        </div>

        <h1 class="cp-editorial" style="margin-top:40px;max-width:1050px;">
            @if(count($cpHeadline))
                @foreach($cpHeadline as $i => $line)
                    <span class="{{ $i === 1 ? 'cp-outline' : '' }}">{{ $line }}</span>@if(! $loop->last)<br>@endif
                @endforeach<span class="cp-red">.</span>
            @else
                {{ $cpName }}<span class="cp-red">.</span>
            @endif
        </h1>

        <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:40px;margin-top:48px;flex-wrap:wrap;">
            <div style="max-width:640px;">
                @if(count($cpHeadline))<p style="font-size:24px;font-weight:700;">{{ $cpName }}</p>@endif
                <p style="color:rgba(255,255,255,.72);margin-top:8px;">
                    {{ collect([$cpSports->implode(', ') ?: null, $cpAddress, $cpFrom ? __('clubs::page.from_price', ['price' => $cpFrom, 'currency' => $cpCurrency]) : null])->filter()->implode(' · ') }}
                </p>
                <div style="display:flex;gap:12px;margin-top:28px;flex-wrap:wrap;">
                    <button type="button" class="cp-cta" @click="join()">{{ __('clubs::page.join_club') }} <i class="bi bi-arrow-up-right cp-flip"></i></button>
                    <x-qr-code label="{{ __('clubs::page.share_qr') }}" icon="bi-qr-code" button-class="cp-ghost" :targets="$cpQrTargets" />
                    @if($cpWhatsapp)
                        <a href="{{ $cpWhatsapp }}" target="_blank" rel="noopener" class="cp-ghost"><i class="bi bi-whatsapp"></i>{{ __('clubs::page.whatsapp') }}</a>
                    @endif
                </div>
            </div>
            <div style="display:flex;gap:40px;">
                <div><b class="cp-display" style="font-size:48px;">{{ number_format($activeMembersCount) }}</b><p class="cp-tag" style="margin-top:4px;">{{ __('clubs::page.stat_members') }}</p></div>
                @if($cpCoachesCount)<div><b class="cp-display" style="font-size:48px;">{{ str_pad($cpCoachesCount, 2, '0', STR_PAD_LEFT) }}</b><p class="cp-tag" style="margin-top:4px;">{{ __('clubs::page.stat_coaches') }}</p></div>@endif
                @if($cpMedals)<div><b class="cp-display" style="font-size:48px;">{{ str_pad($cpMedals, 2, '0', STR_PAD_LEFT) }}</b><p class="cp-tag" style="margin-top:4px;">{{ __('clubs::page.stat_medals') }}</p></div>@endif
            </div>
        </div>
    </div>
    <a href="#overview" class="cp-tag" style="position:absolute;bottom:20px;inset-inline-end:56px;color:rgba(255,255,255,.7);">{{ __('clubs::page.scroll') }} <i class="bi bi-arrow-down"></i></a>
</section>

<div class="cp-marquee" aria-hidden="true">
    @php $cpMarq = collect([$cpSlogan ?: null, $cpName])->merge($cpSports->take(3))->filter()->implode('   /   '); @endphp
    <span class="cp-marquee-track"><i class="bi bi-stars"></i> {{ $cpMarq }}   /   {{ $cpMarq }}   /   {{ $cpMarq }}</span>
</div>

{{-- ===================== MEDALS ===================== --}}
@if($cpAchievements->isNotEmpty())
<section id="medals" style="background:#171719;color:#fff;" class="cp-section">
    <div class="cp-wrap">
        <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:20px;">
            <div>
                <p class="cp-tag" style="color:#ff8197;">{{ __('clubs::page.medals_eyebrow') }}</p>
                <h2 class="cp-h-lg" style="margin-top:16px;">{{ __('clubs::page.medals_title') }}</h2>
            </div>
            @if($cpAchievements->count() > 3)
                <div style="display:flex;gap:8px;">
                    <button type="button" class="cp-pill cp-pill-light" onclick="document.getElementById('cpAchTrack').scrollBy({left: (document.dir === 'rtl' ? 420 : -420), behavior: 'smooth'})" aria-label="{{ __('clubs::page.previous') }}"><i class="bi bi-arrow-left rtl:rotate-180"></i></button>
                    <button type="button" class="cp-pill cp-pill-light" onclick="document.getElementById('cpAchTrack').scrollBy({left: (document.dir === 'rtl' ? -420 : 420), behavior: 'smooth'})" aria-label="{{ __('clubs::page.next') }}"><i class="bi bi-arrow-right rtl:rotate-180"></i></button>
                </div>
            @endif
        </div>
        <div class="cp-scroll-x" id="cpAchTrack" style="display:flex;gap:20px;margin-top:40px;padding-bottom:16px;">
            @foreach($cpAchievements as $i => $a)
                <article style="width:385px;border-radius:24px;overflow:hidden;background:#27272a;position:relative;">
                    @if($canManage)
                        <a href="{{ route('admin.club.achievements', $club) }}?edit={{ $a['id'] }}" class="cp-round" style="position:absolute;top:12px;inset-inline-start:12px;z-index:2;width:34px;height:34px;background:rgba(0,0,0,.55);color:#fff;" aria-label="{{ __('shared.edit') }}"><i class="bi bi-pencil-fill" style="font-size:12px;"></i></a>
                    @endif
                    @if($a['image'])
                        <img src="{{ $a['image'] }}" alt="{{ $a['title'] }}" style="width:100%;height:208px;object-fit:cover;display:block;">
                    @else
                        <div style="height:208px;display:grid;place-items:center;background:linear-gradient(135deg, {{ $cpAccent }}, #27272a);"><i class="bi bi-trophy-fill" style="font-size:56px;opacity:.5;"></i></div>
                    @endif
                    <div style="padding:24px;">
                        @if($a['location'])<p class="cp-tag" style="color:#ff8197;">{{ $a['location'] }}</p>@endif
                        <h3 style="font-size:24px;font-weight:700;margin-top:12px;line-height:1.15;">{{ $a['short'] }}</h3>
                        @if($a['date'])<p style="color:#9ca3af;margin-top:8px;">{{ $a['date'] }}</p>@endif
                        @if($a['gold'] + $a['silver'] + $a['bronze'])
                            <p style="margin-top:16px;font-weight:700;display:flex;gap:14px;align-items:center;flex-wrap:wrap;">
                                @foreach([1 => $a['gold'], 2 => $a['silver'], 3 => $a['bronze']] as $place => $count)
                                    @if($count)<span style="display:inline-flex;align-items:center;gap:6px;"><x-medal-icon :place="$place" :size="16" :numeral="false" /> {{ $count }} {{ __('clubs::page.medal_'.$place) }}</span>@endif
                                @endforeach
                            </p>
                        @elseif($a['tag'])
                            <p style="margin-top:16px;font-weight:700;">{{ $a['tag'] }}</p>
                        @endif
                        <button type="button" style="margin-top:20px;text-decoration:underline;" @click="openAch({{ $i }})">{{ __('clubs::page.view_story') }} <i class="bi bi-arrow-up-right cp-flip"></i></button>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ===================== READY TO BEGIN ===================== --}}
<section style="background:var(--cp-accent);color:#fff;" class="cp-section">
    <div class="cp-wrap" style="display:flex;align-items:flex-end;justify-content:space-between;gap:32px;flex-wrap:wrap;">
        <div>
            <p class="cp-tag">{{ __('clubs::page.cta_eyebrow') }}</p>
            <h2 style="font-size:clamp(64px,7vw,100px);line-height:.95;font-weight:700;margin-top:20px;text-transform:uppercase;">{!! nl2br(e(__('clubs::page.cta_title'))) !!}</h2>
        </div>
        <div style="text-align:end;">
            @if($cpFrom)<p style="font-size:30px;font-weight:700;" class="cp-display">{{ __('clubs::page.from_price', ['price' => $cpFrom, 'currency' => $cpCurrency]) }}</p>@endif
            <button type="button" class="cp-cta cp-cta-white" style="margin-top:24px;padding:20px 40px;" @click="join()">{{ __('clubs::page.join_named', ['club' => $cpName]) }} <i class="bi bi-arrow-up-right cp-flip"></i></button>
        </div>
    </div>
</section>

{{-- ===================== WHO IS TRAINING ===================== --}}
@if($cpWho->count() > 1)
<section style="background:var(--cp-card);" class="cp-section" id="find-program">
    <div class="cp-wrap">
        <p class="cp-tag cp-red">{{ __('clubs::page.who_eyebrow') }}</p>
        <h2 class="cp-h-lg" style="margin-top:12px;">{{ __('clubs::page.who_title') }}</h2>
        <p class="cp-muted" style="margin-top:12px;max-width:560px;">{{ __('clubs::page.who_sub') }}</p>
        <div style="display:grid;grid-template-columns:repeat({{ $cpWho->count() }}, minmax(0,1fr));gap:20px;margin-top:28px;">
            @foreach($cpWho as $w)
                <button type="button" class="cp-card cp-lift" style="padding:32px;text-align:center;" @click="pick('{{ $w['cat'] }}')">
                    <span class="cp-icon-tile" style="width:72px;height:72px;margin:0 auto 14px;border-radius:22px;"><i class="bi {{ $w['icon'] }}" style="font-size:32px;"></i></span>
                    <b style="font-size:24px;" class="cp-display">{{ $w['label'] }}</b>
                    <p class="cp-muted" style="font-size:13px;margin-top:4px;">{{ $w['sub'] }}</p>
                </button>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ===================== PROGRAMMES ===================== --}}
<section class="cp-wrap cp-section" id="packages">
    <p class="cp-tag cp-red">{{ __('clubs::page.packages_eyebrow') }}</p>
    <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:24px;flex-wrap:wrap;">
        <h2 class="cp-h-xl" style="margin-top:16px;">{!! nl2br(e(__('clubs::page.packages_title'))) !!}</h2>
        <p class="cp-muted cp-lead" style="max-width:320px;">{{ __('clubs::page.packages_sub') }}</p>
    </div>
    @if($cpPackages->isNotEmpty())
        <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;margin:48px 0 28px;flex-wrap:wrap;">
            @if($cpCats->count() > 1)
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" class="cp-pill" :class="cat === 'all' && 'is-on'" @click="cat = 'all'">{{ __('clubs::page.cat_all') }}</button>
                    @foreach($cpCats as $c)
                        <button type="button" class="cp-pill" :class="cat === '{{ $c }}' && 'is-on'" @click="cat = '{{ $c }}'">{{ __('clubs::page.cat_'.$c) }}</button>
                    @endforeach
                </div>
            @else
                <span></span>
            @endif
            @if(auth()->check() && $cpPackages->contains(fn ($p) => ! $p['applicable']))
                <button type="button" class="cp-pill" :class="eligibleOnly && 'is-on'" @click="eligibleOnly = !eligibleOnly" :aria-pressed="eligibleOnly">
                    <i class="bi" :class="eligibleOnly ? 'bi-check-circle-fill' : 'bi-circle'"></i>{{ __('clubs::page.eligible_family') }}
                </button>
            @endif
        </div>
        <div class="cpd-grid-3">
            @foreach($cpPackages as $p)
                @include('clubs::public.partials.page-package-card')
            @endforeach
        </div>
    @else
        <div class="cp-card" style="padding:56px;text-align:center;margin-top:40px;">
            <span class="cp-icon-tile" style="width:64px;height:64px;margin:0 auto;"><i class="bi bi-box" style="font-size:26px;"></i></span>
            <p style="font-weight:700;margin-top:14px;">{{ __('clubs::club.no_packages') }}</p>
            <p class="cp-muted" style="font-size:14px;margin-top:4px;">{{ __('clubs::club.check_back_later') }}</p>
        </div>
    @endif
</section>

{{-- ===================== THE CLUB ===================== --}}
<section class="cp-wrap cp-section" id="overview" style="padding-top:24px;">
    <div class="cpd-split">
        <div>
            <p class="cp-tag cp-red">{{ __('clubs::page.about_eyebrow') }}</p>
            <h2 style="font-size:clamp(52px,5.4vw,76px);line-height:.98;font-weight:700;margin-top:28px;text-transform:uppercase;">{!! nl2br(e(__('clubs::page.about_title'))) !!}</h2>
            @if($club->tr('description'))
                <div class="cp-muted cp-lead" style="margin-top:36px;max-width:460px;"><x-prose-text :text="$club->tr('description')" /></div>
            @endif
            @if($cpSocial->isNotEmpty() || $cpTel)
                <div style="display:flex;gap:10px;margin-top:32px;flex-wrap:wrap;">
                    @foreach($cpSocial as $s)
                        <a href="{{ $s['url'] }}" target="_blank" rel="noopener" class="cp-pill"><i class="bi {{ $s['icon'] }}"></i>{{ $s['label'] }}</a>
                    @endforeach
                    @if($cpTel)<a href="{{ $cpTel }}" class="cp-pill"><i class="bi bi-telephone"></i>{{ __('clubs::page.call') }}</a>@endif
                </div>
            @endif
            <button type="button" class="cp-pill" style="margin-top:12px;" @click="openStats()"><i class="bi bi-bar-chart"></i>{{ __('clubs::page.in_numbers') }}</button>
        </div>
        <div>
            <div style="position:relative;height:520px;border-radius:30px;overflow:hidden;background:{{ $cpAccent }};">
                @if($cpHomeImage ?: $cpCover)
                    <img src="{{ $cpHomeImage ?: $cpCover }}" alt="{{ $cpHome?->tr('name') ?: $cpName }}" class="cp-cover">
                @else
                    <div style="position:absolute;inset:0;background:linear-gradient(135deg, {{ $cpAccent }}, #171719);"></div>
                @endif
                @if($cpHome || $cpAddress)
                    <div style="position:absolute;bottom:24px;inset-inline:24px;padding:22px 24px;background:rgba(255,255,255,.96);color:#171719;border-radius:18px;display:flex;justify-content:space-between;align-items:center;gap:16px;">
                        <div style="min-width:0;">
                            <p class="cp-tag cp-red">{{ __('clubs::page.our_home') }}</p>
                            <h3 style="font-size:24px;font-weight:700;">{{ $cpHome?->tr('name') ?: $cpName }}</h3>
                            @if($cpHome?->tr('address') ?: $cpAddress)<p style="font-size:14px;color:#6b7280;">{{ $cpHome?->tr('address') ?: $cpAddress }}</p>@endif
                        </div>
                        @if($cpMapsUrl)<a href="{{ $cpMapsUrl }}" target="_blank" rel="noopener" class="cp-cta">{{ __('clubs::page.directions') }} <i class="bi bi-arrow-up-right cp-flip"></i></a>@endif
                    </div>
                @endif
            </div>
            @if($cpFacilities->count() > 1)
                <div class="cp-scroll-x" style="display:flex;gap:10px;margin-top:12px;">
                    @foreach($cpFacilities->slice(1) as $f)
                        @if($cpSafeUrl($f->maps_url))
                            <a href="{{ $f->maps_url }}" target="_blank" rel="noopener" class="cp-pill"><i class="bi bi-geo-alt"></i>{{ $f->tr('name') }}</a>
                        @else
                            <span class="cp-pill"><i class="bi bi-building"></i>{{ $f->tr('name') }}</span>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if($cpCoaches->isNotEmpty())
        <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-top:96px;gap:16px;">
            <div>
                <p class="cp-tag cp-red">{{ __('clubs::page.coaches_eyebrow') }}</p>
                <h2 class="cp-h-md" style="margin-top:12px;">{{ __('clubs::page.coaches_title') }}</h2>
            </div>
            <span class="cp-muted cp-tag">{{ __('clubs::page.coaches_aside') }}</span>
        </div>
        <div class="cpd-grid-2" style="margin-top:32px;">
            @foreach($cpCoaches as $c)
                <a href="{{ $c['url'] }}" class="cp-card cp-lift" style="padding:24px;display:flex;gap:24px;align-items:center;">
                    <span class="cp-portrait" style="width:96px;border-radius:18px;">
                        @if($c['photo'])<img src="{{ $c['photo'] }}" alt="{{ $c['name'] }}">@else<x-gender-avatar :gender="$c['gender']" class="w-full h-full" />@endif
                    </span>
                    <div style="min-width:0;">
                        <p class="cp-tag cp-red">{{ $c['role'] }}</p>
                        <h3 style="font-size:28px;font-weight:700;margin-top:6px;line-height:1.1;">{{ $c['name'] }}</h3>
                        @if($c['reviews'])<p class="cp-muted" style="font-size:13px;margin-top:4px;"><i class="bi bi-star-fill" style="color:#f59e0b;"></i> {{ number_format($c['rating'], 1) }} · {{ trans_choice('clubs::page.reviews_n', $c['reviews'], ['n' => $c['reviews']]) }}</p>@endif
                        @if($c['bio'])<p class="cp-muted" style="font-size:14px;margin-top:6px;">{{ $c['bio'] }}</p>@endif
                        <span class="cp-red" style="font-weight:700;display:inline-block;margin-top:12px;">{{ __('clubs::page.view_profile') }} <i class="bi bi-arrow-up-right cp-flip"></i></span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</section>

{{-- ===================== NEWS ===================== --}}
@if($cpPosts->isNotEmpty())
<section id="latest" class="cp-section" style="background:#19191d;color:#fff;">
    <div class="cp-wrap">
        <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:16px;">
            <div>
                <p class="cp-tag" style="color:#fa8197;">{{ __('clubs::page.news_eyebrow') }}</p>
                <h2 class="cp-h-lg" style="margin-top:12px;">{{ __('clubs::page.news_title') }}</h2>
                <p style="color:rgba(255,255,255,.6);margin-top:12px;">{{ __('clubs::page.news_sub') }}</p>
            </div>
            @if($cpPosts->count() > 3)<button type="button" class="cp-pill cp-pill-light" @click="openNews()">{{ __('clubs::page.all_updates') }} <i class="bi bi-arrow-up-right cp-flip"></i></button>@endif
        </div>
        <div class="cpd-grid-3" style="margin-top:32px;gap:16px;">
            @foreach($cpPosts->take(3) as $i => $p)
                <article style="border-radius:24px;overflow:hidden;background:#29292d;display:flex;flex-direction:column;" id="post-{{ $p['id'] }}">
                    @if($p['image'])<img src="{{ $p['image'] }}" alt="" style="width:100%;height:176px;object-fit:cover;display:block;">@endif
                    <div style="padding:20px;display:flex;flex-direction:column;flex:1;">
                        <span class="cp-tag" style="color:#ff8197;">{{ collect([$p['category'], $p['date']])->filter()->implode(' · ') }}</span>
                        <h3 style="font-size:22px;font-weight:700;margin-top:12px;line-height:1.2;">{{ $p['title'] }}</h3>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:auto;padding-top:20px;">
                            <button type="button" style="text-decoration:underline;" @click="openPost({{ $i }})">{{ __('clubs::page.read_update') }} <i class="bi bi-arrow-up-right cp-flip"></i></button>
                            <span style="color:rgba(255,255,255,.55);font-size:13px;display:flex;gap:12px;">
                                <span><i class="bi" :class="d.posts[{{ $i }}].liked ? 'bi-heart-fill' : 'bi-heart'"></i> <span x-text="d.posts[{{ $i }}].likes">{{ $p['likes'] }}</span></span>
                                <span><i class="bi bi-chat"></i> <span x-text="d.posts[{{ $i }}].comments.length">{{ count($p['comments']) }}</span></span>
                            </span>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ===================== GALLERY ===================== --}}
@if($cpGallery->isNotEmpty() || $cpYoutube)
<section id="media" class="cp-section" style="background:var(--cp-card);">
    <div class="cp-wrap">
        <p class="cp-tag cp-red">{{ __('clubs::page.media_eyebrow', ['club' => $cpName]) }}</p>
        <h2 class="cp-h-lg" style="margin-top:12px;">{{ __('clubs::page.media_title') }}</h2>
        <p class="cp-muted" style="margin:12px 0 28px;max-width:560px;">{{ __('clubs::page.media_sub') }}</p>
        <div class="cp-media">
            @foreach($cpGallery->take($cpYoutube ? 4 : 5) as $i => $g)
                <button type="button" @click="open({{ $i }})">
                    <img src="{{ $g['src'] }}" alt="{{ $g['caption'] ?: $cpName }}" loading="lazy">
                    @if($g['caption'])<span class="cp-media-label"><i class="bi bi-arrow-up-right cp-flip"></i> {{ $g['caption'] }}</span>@endif
                </button>
            @endforeach
            @if($cpYoutube)
                <button type="button" @click="openVideo()" style="background:linear-gradient(135deg, {{ $cpAccent }}, #171719);">
                    <span style="position:absolute;inset:0;z-index:1;display:grid;place-items:center;"><span class="cp-round" style="width:64px;height:64px;background:rgba(255,255,255,.92);color:{{ $cpAccent }};"><i class="bi bi-play-fill" style="font-size:30px;"></i></span></span>
                    <span class="cp-media-label">{{ __('clubs::page.watch_film') }} <i class="bi bi-arrow-up-right cp-flip"></i></span>
                </button>
            @endif
        </div>
        @if($cpGallery->count() > 4)
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;">
                <p class="cp-muted" style="font-size:12px;">{{ trans_choice('clubs::page.photos_n', $cpGallery->count(), ['n' => $cpGallery->count()]) }}</p>
                <button type="button" class="cp-pill" @click="open(0)">{{ __('clubs::page.open_gallery') }} <i class="bi bi-arrow-up-right cp-flip"></i></button>
            </div>
        @endif
    </div>
</section>
@endif

{{-- ===================== SCHEDULE ===================== --}}
@if($cpWeek->contains(fn ($d) => count($d['slots'])))
<section id="schedule" class="cp-section" style="background:var(--cp-sand);">
    <div class="cp-wrap cpd-split">
        <div>
            <p class="cp-tag cp-red">{{ __('clubs::page.schedule_eyebrow') }}</p>
            <h2 class="cp-h-xl" style="margin-top:20px;">{!! nl2br(e(__('clubs::page.schedule_title'))) !!}</h2>
            <p class="cp-muted" style="margin-top:24px;">{{ __('clubs::page.schedule_sub') }}</p>
        </div>
        <div>
            <div style="display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap;">
                @foreach($cpWeek as $d)
                    <button type="button" class="cp-pill" :class="day === '{{ $d['key'] }}' && 'is-on'" @click="day = '{{ $d['key'] }}'">{{ $d['label'] }}</button>
                @endforeach
            </div>
            @foreach($cpWeek as $d)
                <div x-show="day === '{{ $d['key'] }}'" @if($d['key'] !== $cpToday) x-cloak @endif class="cp-fade">
                    @forelse($d['slots'] as $s)
                        <div class="cp-card" style="padding:16px 20px;display:flex;align-items:center;gap:16px;margin-bottom:12px;">
                            <div style="min-width:96px;"><b>{{ $s['start'] }}</b>@if($s['end'])<p class="cp-muted" style="font-size:12px;">{{ $s['end'] }}</p>@endif</div>
                            <div style="flex:1;min-width:0;">
                                <b>{{ $s['title'] }}</b>
                                <p class="cp-muted" style="font-size:14px;">{{ collect([$s['package'] !== $s['title'] ? $s['package'] : null, $s['coach'], $s['place']])->filter()->implode(' · ') }}</p>
                            </div>
                            <button type="button" class="cp-pill" @click="go('packages')">{{ __('clubs::page.join') }}</button>
                        </div>
                    @empty
                        <div class="cp-card" style="padding:32px;text-align:center;">
                            <span class="cp-icon-tile" style="width:56px;height:56px;margin:0 auto 10px;"><i class="bi bi-moon-stars" style="font-size:24px;"></i></span>
                            <b>{{ __('clubs::page.rest_day') }}</b>
                            <p class="cp-muted">{{ __('clubs::page.rest_day_sub') }}</p>
                        </div>
                    @endforelse
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ===================== EVENTS ===================== --}}
@if($cpEvents->isNotEmpty())
<section id="events" class="cp-wrap cp-section">
    <p class="cp-tag cp-red">{{ __('clubs::page.events_eyebrow') }}</p>
    <h2 class="cp-h-lg" style="margin-top:16px;">{{ __('clubs::page.events_title') }}</h2>
    <div style="display:grid;gap:16px;margin-top:36px;">
        @foreach($cpEvents->take(4) as $e)
            <div class="cp-card" style="padding:28px 32px;display:flex;align-items:center;gap:40px;">
                <div style="text-align:center;padding-inline-end:40px;border-inline-end:1px solid var(--cp-line);min-width:120px;">
                    <b class="cp-display" style="font-size:56px;line-height:1;">{{ $e['day'] }}</b>
                    <p class="cp-tag cp-red" style="margin-top:6px;">{{ $e['month'] }}</p>
                </div>
                <div style="flex:1;min-width:0;">
                    <p class="cp-tag cp-red">
                        @if($e['live'])<span style="color:#16a34a;">● {{ __('clubs::page.live_now') }}</span> · @endif{{ $e['date'] }}@if($e['level']) · {{ $e['level'] }}@endif
                    </p>
                    <h3 style="font-size:30px;font-weight:700;margin-top:8px;">{{ $e['title'] }}</h3>
                    @if($e['location'])<p class="cp-muted" style="margin-top:6px;"><i class="bi bi-geo-alt"></i> {{ $e['location'] }}</p>@endif
                </div>
                @if($e['joined'])<span class="cp-badge" style="background:#e8f6ee;color:#15803d;"><i class="bi bi-check-circle-fill"></i>{{ __('clubs::page.joined') }}</span>@endif
                @if($e['url'])<a href="{{ $e['url'] }}" class="cp-cta">{{ $e['cta'] ?: __('clubs::page.open_event') }} <i class="bi bi-arrow-up-right cp-flip"></i></a>@endif
            </div>
        @endforeach
    </div>
</section>
@endif

{{-- ===================== PERKS ===================== --}}
@if($cpPerks->isNotEmpty())
<section id="offers" class="cp-section" style="background:var(--cp-blush);">
    <div class="cp-wrap">
        <p class="cp-tag cp-red">{{ __('clubs::page.perks_eyebrow') }}</p>
        <h2 class="cp-h-xl" style="margin-top:16px;">{{ __('clubs::page.perks_title') }}</h2>
        @php $cpLead = $cpPerks->first(); $cpRest = $cpPerks->slice(1)->values(); @endphp
        <div class="{{ $cpRest->isNotEmpty() ? 'cpd-perks' : '' }}" style="margin-top:40px;">
            <div style="background:var(--cp-accent);color:#fff;border-radius:30px;padding:48px;position:relative;overflow:hidden;min-height:380px;display:flex;flex-direction:column;">
                @if($cpLead['image'])<img src="{{ $cpLead['image'] }}" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.18;">@endif
                <i class="bi {{ $cpLead['icon'] }}" style="position:absolute;inset-inline-end:-10px;top:-30px;font-size:240px;opacity:.1;"></i>
                <div style="position:relative;display:flex;flex-direction:column;flex:1;">
                    <p class="cp-tag">{{ $cpLead['badge'] ?: __('clubs::page.members_only_perk') }}</p>
                    <h3 style="font-size:clamp(48px,5vw,80px);font-weight:700;line-height:1;margin-top:40px;">{{ $cpLead['title'] }}</h3>
                    @if($cpLead['description'])<p style="font-size:22px;margin-top:12px;">{{ $cpLead['description'] }}</p>@endif
                    <div style="margin-top:auto;padding-top:32px;">
                        <button type="button" class="cp-cta cp-cta-white" @click="collect(@js($cpLead['url']))"><i class="bi bi-ticket-perforated-fill"></i>{{ __('clubs::page.collect') }}</button>
                    </div>
                </div>
            </div>
            @if($cpRest->isNotEmpty())
                <div style="display:flex;flex-direction:column;gap:24px;">
                    @foreach($cpRest->take(3) as $pk)
                        <div class="cp-card" style="padding:32px;flex:1;display:flex;gap:20px;align-items:flex-start;">
                            <div style="flex:1;min-width:0;">
                                <p class="cp-tag cp-red">{{ $pk['badge'] ?: __('clubs::page.members_only_perk') }}</p>
                                <h3 style="font-size:34px;font-weight:700;margin-top:16px;line-height:1.05;">{{ $pk['title'] }}</h3>
                                @if($pk['description'])<p class="cp-muted" style="margin-top:8px;">{{ $pk['description'] }}</p>@endif
                                <button type="button" class="cp-pill" style="margin-top:24px;" @click="collect(@js($pk['url']))"><i class="bi bi-lock"></i>{{ __('clubs::page.collect') }}</button>
                            </div>
                            <span class="cp-icon-tile" style="width:64px;height:64px;overflow:hidden;">
                                @if($pk['image'])<img src="{{ $pk['image'] }}" alt="" class="cp-cover">@else<i class="bi {{ $pk['icon'] }}" style="font-size:26px;"></i>@endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
@endif

{{-- ===================== SHOP ===================== --}}
@if($cpProducts->isNotEmpty())
<section id="shop" class="cp-wrap cp-section">
    <p class="cp-tag cp-red">{{ __('clubs::page.shop_eyebrow') }}</p>
    <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;">
        <h2 class="cp-h-xl" style="margin-top:16px;">{{ __('clubs::page.shop_title') }}</h2>
        <a href="{{ route('me.market') }}" class="cp-pill">{{ __('clubs::page.visit_shop') }} <i class="bi bi-arrow-up-right cp-flip"></i></a>
    </div>
    @if($cpShopCats->count() > 1)
        <div style="display:flex;gap:8px;margin:32px 0;flex-wrap:wrap;">
            <button type="button" class="cp-pill" :class="shopCat === 'all' && 'is-on'" @click="shopCat = 'all'">{{ __('clubs::page.cat_all') }}</button>
            @foreach($cpShopCats as $sc)
                <button type="button" class="cp-pill" :class="shopCat === @js($sc['key']) && 'is-on'" @click="shopCat = @js($sc['key'])">{{ $sc['label'] }}</button>
            @endforeach
        </div>
    @else
        <div style="height:40px;"></div>
    @endif
    <div class="cpd-grid-4">
        @foreach($cpProducts as $i => $pr)
            <a href="{{ $pr['url'] }}" class="cp-card cp-lift" style="display:flex;flex-direction:column;" x-show="shopCat === 'all' || shopCat === @js($pr['cat'])" x-transition.opacity>
                <div style="height:200px;position:relative;display:grid;place-items:center;background:{{ $i % 2 ? '#e9e6e1' : '#e7e9e9' }};">
                    @if($pr['image'])<img src="{{ $pr['image'] }}" alt="{{ $pr['name'] }}" class="cp-cover" loading="lazy">@else<i class="bi {{ $pr['icon'] }}" style="font-size:56px;color:#171719;opacity:.7;"></i>@endif
                    @if($pr['badge'])
                        <span class="cp-badge" style="position:absolute;top:12px;inset-inline-start:12px;background:#fff;color:#111;">{{ $pr['badge'] }}</span>
                    @endif
                </div>
                <div style="padding:16px;">
                    <p class="cp-tag cp-muted">{{ $pr['brand'] ?: $pr['cat'] }}</p>
                    <h3 style="font-size:18px;font-weight:700;margin-top:8px;">{{ $pr['name'] }}</h3>
                    <p style="margin-top:6px;"><b>{{ $pr['price'] }} {{ $cpCurrency }}</b>@if($pr['old']) <s class="cp-muted" style="font-size:13px;">{{ $pr['old'] }}</s>@endif</p>
                </div>
            </a>
        @endforeach
    </div>
</section>
@endif

{{-- ===================== REVIEWS ===================== --}}
<section id="reviews" class="cp-wrap" style="padding-bottom:96px;">
    <div class="cp-card" style="padding:48px;">
        <p class="cp-tag cp-red">{{ __('clubs::page.reviews_eyebrow') }}</p>
        <h2 class="cp-h-md" style="margin-top:16px;">{{ __('clubs::page.reviews_title') }}</h2>
        <p class="cp-muted" style="margin-top:12px;">
            @if($reviews->count())
                <i class="bi bi-star-fill" style="color:#f59e0b;"></i> <b style="color:var(--cp-ink);">{{ number_format($averageRating, 1) }}</b> · {{ trans_choice('clubs::page.reviews_n', $reviews->count(), ['n' => $reviews->count()]) }}
            @else
                {{ __('clubs::page.reviews_none', ['club' => $cpName]) }}
            @endif
        </p>
        @if($cpReviews->isNotEmpty())
            <div class="cpd-grid-3" style="margin-top:32px;gap:16px;">
                @foreach($cpReviews as $r)
                    <figure style="margin:0;padding:22px;border-radius:18px;background:var(--cp-paper);">
                        <div style="color:#f59e0b;font-size:13px;">@for($s = 1; $s <= 5; $s++)<i class="bi {{ $s <= $r['rating'] ? 'bi-star-fill' : 'bi-star' }}"></i>@endfor</div>
                        <blockquote style="margin:10px 0 0;line-height:1.6;">{{ $r['comment'] }}</blockquote>
                        <figcaption class="cp-muted" style="font-size:13px;margin-top:12px;"><b style="color:var(--cp-ink);">{{ $r['name'] }}</b> · {{ $r['ago'] }}</figcaption>
                    </figure>
                @endforeach
            </div>
        @endif
    </div>
</section>

{{-- ===================== GOOD TO KNOW ===================== --}}
@if($cpFaq->isNotEmpty())
<section id="questions" class="cp-section" style="background:var(--cp-card);">
    <div class="cp-wrap">
        <p class="cp-tag cp-red">{{ __('clubs::page.faq_eyebrow') }}</p>
        <h2 class="cp-h-lg" style="margin:12px 0 32px;">{{ __('clubs::page.faq_title') }}</h2>
        <div class="cpd-grid-2" style="gap:12px;">
            @foreach($cpFaq as $q)
                <details class="cp-card cp-faq" style="padding:20px 22px;">
                    <summary>{{ $q['q'] }} <i class="bi bi-plus-lg"></i></summary>
                    <p class="cp-muted" style="margin-top:12px;line-height:1.7;">{{ $q['a'] }}</p>
                </details>
            @endforeach
        </div>
        @if($cpWhatsapp)<a href="{{ $cpWhatsapp }}" target="_blank" rel="noopener" class="cp-cta" style="margin-top:28px;"><i class="bi bi-whatsapp"></i>{{ __('clubs::page.ask_whatsapp') }}</a>@endif
    </div>
</section>
@endif
</main>

<footer style="background:#171719;color:#fff;">
    <div class="cp-wrap" style="padding-block:32px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
        <b class="cp-display" style="font-size:24px;text-transform:uppercase;">{{ $cpName }}</b>
        <span style="font-size:14px;color:#9ca3af;">{{ collect([$cpSlogan ?: null, $cpPlace])->filter()->implode(' · ') }}</span>
        <a href="{{ route('clubs.explore') }}" style="font-size:13px;color:#9ca3af;">{{ __('clubs::page.powered_by') }}</a>
    </div>
</footer>

@include('clubs::public.partials.page-overlays')

{{-- ===== Club in numbers — the member statistics the page has always published ===== --}}
<template x-teleport="body">
<div class="cp" :class="dark && 'cp-dark'" style="min-height:0;background:none;">
    <div x-show="stats" x-cloak class="cp-overlay" @click.self="closeAll()">
        <div class="cp-modal cp-modal-wide cp-fade" role="dialog" aria-modal="true">
            <div class="cp-band" style="background: linear-gradient(150deg, {{ $cpAccent }}, {{ $cpAccent }}b0);">
                <div style="display:flex;gap:12px;align-items:center;">
                    <span class="cp-round" style="width:48px;height:48px;border-radius:16px;background:rgba(255,255,255,.2);"><i class="bi bi-bar-chart-fill" style="font-size:20px;"></i></span>
                    <div style="flex:1;min-width:0;">
                        <h3 style="font-size:20px;font-weight:800;">{{ __('clubs::page.in_numbers') }}</h3>
                        <p style="font-size:12px;color:rgba(255,255,255,.85);margin-top:3px;">{{ __('clubs::club.platform_show_live_snapshot') }} · {{ now()->format('M Y') }}</p>
                    </div>
                    <button type="button" class="cp-x" @click="closeAll()" aria-label="{{ __('shared.close') }}"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div style="padding:24px;">
                @php
                    $cpStatBlocks = [
                        ['id' => 'donutNationalities', 'title' => __('clubs::club.platform_show_nationality'), 'rows' => $nationalityStats],
                        ['id' => 'donutAgeGroups', 'title' => __('clubs::club.platform_show_age_groups'), 'rows' => collect($ageGroups)],
                        ['id' => 'donutGender', 'title' => __('clubs::club.platform_show_gender_ratio'), 'rows' => $genderStats->mapWithKeys(fn ($v, $k) => [ucfirst($k ?: __('clubs::club.platform_show_unknown')) => $v])],
                        ['id' => 'donutHoroscope', 'title' => __('clubs::club.platform_show_horoscope'), 'rows' => collect($horoscopeGroups)],
                        ['id' => 'donutBloodType', 'title' => __('clubs::club.platform_show_blood_type'), 'rows' => $bloodTypeStats],
                        ['id' => 'donutGoals', 'title' => __('clubs::club.platform_show_goal_status'), 'rows' => collect($goalStats)],
                    ];
                    $cpPal = ['#6366f1','#0891b2','#059669','#d97706','#dc2626','#7c3aed','#0284c7','#16a34a','#b45309','#be185d','#1d4ed8','#0f766e'];
                @endphp
                <div class="cpd-grid-3" style="gap:14px;">
                    @foreach($cpStatBlocks as $b)
                        <div class="cp-card" style="padding:18px;">
                            <p class="cp-tag cp-muted">{{ $b['title'] }}</p>
                            <div style="max-width:170px;margin:12px auto;"><canvas id="{{ $b['id'] }}" height="160"></canvas></div>
                            <ul style="list-style:none;margin:0;padding:0;font-size:13px;display:grid;gap:4px;">
                                @foreach($b['rows'] as $label => $count)
                                    <li style="display:flex;justify-content:space-between;gap:8px;">
                                        <span><span style="display:inline-block;width:8px;height:8px;border-radius:9px;background:{{ $cpPal[$loop->index % 12] }};margin-inline-end:6px;"></span>{{ $label }}</span>
                                        <b>{{ $totalMembers > 0 ? round($count / $totalMembers * 100) : 0 }}%</b>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
                <div class="cp-card" style="padding:18px;margin-top:14px;">
                    <p class="cp-tag cp-muted">{{ __('clubs::club.platform_show_new_members_12mo') }}</p>
                    <div style="height:220px;margin-top:12px;"><canvas id="barMonthlyMembers"></canvas></div>
                </div>
            </div>
        </div>
    </div>
</div>
</template>
</div>

{{-- Package-select modal (package-first flow) — the enrol flow, unchanged --}}
<div id="selectPackageModalWrap" x-data="selectPackageApp()" x-cloak>
    @include('clubs::public.partials.join-club-modal')
    <x-toast-notification />
</div>

@if($canManage && auth()->user()?->isSuperAdmin())
    <x-club-modal mode="edit" :club="$club" />
@endif

@include('clubs::public.partials.page-runtime')
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Club in numbers — drawn on first open (a chart in a hidden box measures 0×0).
window.cpInitStats = function () {
    if (window.__cpStatsDrawn || typeof Chart === 'undefined') return;
    window.__cpStatsDrawn = true;
    const P = ['#6366f1','#0891b2','#059669','#d97706','#dc2626','#7c3aed','#0284c7','#16a34a','#b45309','#be185d','#1d4ed8','#0f766e'];
    const donut = { responsive: true, maintainAspectRatio: true, cutout: '65%', plugins: { legend: { display: false } } };
    const sets = {
        donutNationalities: [@json($nationalityStats->keys()->toArray()), @json($nationalityStats->values()->toArray())],
        donutAgeGroups:     [@json(array_keys($ageGroups)), @json(array_values($ageGroups))],
        donutGender:        [@json($genderStats->keys()->map(fn ($g) => ucfirst($g ?: __('clubs::club.platform_show_unknown')))->toArray()), @json($genderStats->values()->toArray())],
        donutHoroscope:     [@json(array_keys($horoscopeGroups)), @json(array_values($horoscopeGroups))],
        donutBloodType:     [@json($bloodTypeStats->keys()->toArray()), @json($bloodTypeStats->values()->toArray())],
        donutGoals:         [@json(array_keys($goalStats)), @json(array_values($goalStats))],
    };
    Object.keys(sets).forEach(function (id) {
        const el = document.getElementById(id);
        if (el) new Chart(el, { type: 'doughnut', data: { labels: sets[id][0], datasets: [{ data: sets[id][1], backgroundColor: P, borderWidth: 0 }] }, options: donut });
    });
    const bar = document.getElementById('barMonthlyMembers');
    if (bar) new Chart(bar, {
        type: 'bar',
        data: { labels: @json(array_keys($monthlyTrend)), datasets: [{ label: @json(__('clubs::club.platform_show_members_legend')), data: @json(array_values($monthlyTrend)), backgroundColor: @json($cpAccent), borderWidth: 0, borderRadius: 4 }] },
        options: { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { display: false } },
                   scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
};
</script>

@php
$packagesForJs = $club->packages->map(function ($p) {
    return [
        'id'              => $p->id,
        'name'            => $p->name,
        'price'           => $p->price,
        'duration_months' => $p->duration_months,
        'age_min'         => $p->age_min,
        'age_max'         => $p->age_max,
        'gender'          => $p->gender,
        'activity_type'   => $p->activity_type ?? null,
        'schedules'       => collect($p->packageActivities)->map(function ($pa) {
            $slots = is_string($pa->schedule) ? json_decode($pa->schedule, true) : $pa->schedule;
            if (!is_array($slots) || empty($slots)) return null;
            $days  = collect($slots)->pluck('day')->map(fn($d) => ucfirst($d))->unique()->join(', ');
            $times = collect($slots)->map(fn($s) => ($s['start_time'] ?? '') . ' – ' . ($s['end_time'] ?? ''))->first();
            return ['days' => $days, 'time' => $times];
        })->filter()->values(),
        'instructors'     => collect($p->packageActivities)->map(function ($pa) {
            if (!$pa->instructor?->user) return null;
            return [
                'name'      => $pa->instructor->user->full_name ?? $pa->instructor->user->name,
                'image_url' => $pa->instructor->user->profile_picture
                    ? file_url($pa->instructor->user->profile_picture)
                    : null,
            ];
        })->filter()->unique('name')->values(),
    ];
})->values();
@endphp
<script>
// ── Package-first join flow (club details page) ───────────────────────────────
const _clubPackagesData = @json($packagesForJs);

function selectPackageApp() {
    return {
        joinModal: {
            open: false,
            step: 'select-members',
            clubId: {{ $club->id }},
            clubSlug: '{{ $club->slug }}',
            clubName: @json($club->club_name),
            clubCountry: '{{ $club->country_code ?? "bh" }}',
            currency: @json($club->currency ?? 'BHD'),
            enrollmentFee: {{ $club->enrollment_fee ?? 0 }},
            vatPercentage: {{ $club->vat_percentage ?? 0 }},
            vatRegNumber: @json($club->vat_reg_number ?? null),
            familyMembers: @json($familyMembers),
            _allFamilyMembers: @json($familyMembers),
            selectedMemberIds: [],
            registrants: [],
            packages: _clubPackagesData,
            loadingPackages: false,
            payLater: false,
            paymentScreenshot: false,
            submitting: false,

            // The pre-selected package for the package-first flow
            _preselectPackageId: null,

            show(clubId, clubSlug, clubName, clubCountry) {
                // Standard flow (not used on this page but required by the modal template)
                this._preselectPackageId = null;
                this.open = true;
                this.step = 'select-members';
                this.selectedMemberIds = [];
                this.registrants = [];
                this.payLater = false;
                this.paymentScreenshot = false;
                this.submitting = false;
                window._joinModal = this;
                document.body.style.overflow = 'hidden';
            },

            showForPackage(packageId) {
                const pkg = this.packages.find(p => p.id == packageId);
                if (!pkg) return;

                // Filter family members to only those eligible for this package
                const eligible = this._allFamilyMembers.filter(m => {
                    const age = this.calculateAge(m.birthdate);
                    if (pkg.age_min !== null && pkg.age_min !== undefined && age !== null && age < pkg.age_min) return false;
                    if (pkg.age_max !== null && pkg.age_max !== undefined && age !== null && age > pkg.age_max) return false;
                    if (pkg.gender && pkg.gender !== 'mixed' && m.gender) {
                        if (pkg.gender === 'male'   && m.gender !== 'Male') return false;
                        if (pkg.gender === 'female' && m.gender !== 'Female') return false;
                    }
                    return true;
                });

                if (eligible.length === 0) {
                    Toast.warning('{{ __("clubs::club.platform_show_not_eligible_title") }}', '{{ __("clubs::club.platform_show_not_eligible_msg") }}');
                    return;
                }

                // Show only eligible members in the modal member list
                this.familyMembers = eligible;
                this._preselectPackageId = packageId;
                this.open = true;
                this.step = 'select-members';
                this.selectedMemberIds = [];
                this.registrants = [];
                this.payLater = false;
                this.paymentScreenshot = false;
                this.submitting = false;
                window._joinModal = this;
                document.body.style.overflow = 'hidden';
            },

            close() {
                this.open = false;
                this.familyMembers = this._allFamilyMembers;
                this._preselectPackageId = null;
                document.body.style.overflow = '';
            },

            toggleMember(member) {
                const idx = this.selectedMemberIds.indexOf(member.id);
                if (idx === -1) this.selectedMemberIds.push(member.id);
                else this.selectedMemberIds.splice(idx, 1);
            },

            isMemberSelected(memberId) {
                return this.selectedMemberIds.includes(memberId);
            },

            buildRegistrants() {
                this.registrants = this.familyMembers
                    .filter(m => this.selectedMemberIds.includes(m.id))
                    .map(m => ({
                        id: this._uid(),
                        userId: m.id,
                        type: m.type === 'guardian' ? 'self' : 'child',
                        packageId: this._preselectPackageId || '',
                        name: m.name,
                        gender: m.gender || '',
                        dateOfBirth: m.birthdate || '',
                        avatarUrl: m.profile_picture ? '/file/' + m.profile_picture : null,
                        relationship: m.relationship,
                        isMember: m.is_member || false,
                    }));
            },

            selectPackage(registrantId, packageId) {
                this.registrants = this.registrants.map(r => {
                    if (r.id === registrantId) return { ...r, packageId: r.packageId == packageId ? '' : packageId };
                    return r;
                });
            },

            getPackageForRegistrant(reg) {
                return this.packages.find(p => p.id == reg.packageId) || null;
            },

            getEligiblePackages(reg) {
                const age = this.calculateAge(reg.dateOfBirth);
                return this.packages.filter(pkg => {
                    if (pkg.age_min !== null && pkg.age_min !== undefined && age !== null && age < pkg.age_min) return false;
                    if (pkg.age_max !== null && pkg.age_max !== undefined && age !== null && age > pkg.age_max) return false;
                    if (pkg.gender && pkg.gender !== 'mixed' && reg.gender) {
                        if (pkg.gender === 'male'   && reg.gender !== 'Male') return false;
                        if (pkg.gender === 'female' && reg.gender !== 'Female') return false;
                    }
                    return true;
                });
            },

            goBack() {
                if (this.step === 'select-members') this.close();
                else if (this.step === 'package-selection') this.step = 'select-members';
                else if (this.step === 'payment-review') {
                    // Package-first: go back to member selection, not package selection
                    this.step = this._preselectPackageId ? 'select-members' : 'package-selection';
                }
            },

            goNext() {
                if (this.step === 'select-members') {
                    if (this.selectedMemberIds.length === 0) {
                        Toast.warning('{{ __("clubs::club.platform_show_no_members_title") }}', '{{ __("clubs::club.platform_show_no_members_msg") }}');
                        return;
                    }
                    this.buildRegistrants();
                    // Package-first: skip package selection step
                    this.step = this._preselectPackageId ? 'payment-review' : 'package-selection';
                } else if (this.step === 'package-selection') {
                    const missing = this.registrants.filter(r => !r.packageId);
                    if (missing.length > 0) {
                        Toast.warning('{{ __("clubs::club.platform_show_packages_required_title") }}', '{{ __("clubs::club.platform_show_packages_required_msg") }}');
                        return;
                    }
                    this.step = 'payment-review';
                } else if (this.step === 'payment-review') {
                    this.handleSubmit();
                }
            },

            async handleSubmit() {
                if (!this.payLater && !this.paymentScreenshot) {
                    Toast.warning('{{ __("clubs::club.platform_show_payment_required_title") }}', '{{ __("clubs::club.platform_show_payment_required_msg") }}');
                    return;
                }
                if (this.submitting) return;
                this.submitting = true;

                try {
                    const formData = new FormData();
                    formData.append('club_id', this.clubId);
                    formData.append('pay_later', this.payLater ? '1' : '0');

                    this.registrants.forEach((reg, i) => {
                        formData.append(`registrants[${i}][type]`, reg.type);
                        formData.append(`registrants[${i}][name]`, reg.name);
                        formData.append(`registrants[${i}][user_id]`, reg.userId || '');
                        formData.append(`registrants[${i}][package_id]`, reg.packageId);
                        formData.append(`registrants[${i}][gender]`, reg.gender || '');
                        if (reg.dateOfBirth) formData.append(`registrants[${i}][date_of_birth]`, reg.dateOfBirth);
                    });

                    if (!this.payLater) {
                        const proofBase64 = document.getElementById('hiddenInput_joinPaymentProofCropper')?.value;
                        if (proofBase64) formData.append('payment_proof_base64', proofBase64);
                    }

                    const response = await fetch(`/${this.clubCountry}/clubs/join`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                            'Accept': 'application/json'
                        },
                        body: formData
                    });

                    const data = await response.json();
                    if (data.success) {
                        this.close();
                        Toast.success('{{ __("clubs::club.platform_show_registration_submitted_title") }}', '{{ __("clubs::club.platform_show_registration_submitted_msg") }}');
                    } else {
                        Toast.error('{{ __("clubs::club.platform_show_registration_failed_title") }}', data.message || '{{ __("clubs::club.platform_show_please_try_again") }}');
                    }
                } catch (error) {
                    console.error('Registration error:', error);
                    Toast.error('{{ __("shared.error") }}', '{{ __("clubs::club.platform_show_registration_error_msg") }}');
                } finally {
                    this.submitting = false;
                }
            },

            formatCurrency(amount) {
                try {
                    const hasDecimals = amount % 1 !== 0;
                    return new Intl.NumberFormat('en-US', {
                        style: 'currency', currency: this.currency,
                        minimumFractionDigits: hasDecimals ? 2 : 0,
                        maximumFractionDigits: hasDecimals ? 2 : 0
                    }).format(amount);
                } catch { return amount + ' ' + this.currency; }
            },

            calculateSubtotal() {
                const packageTotal = this.registrants.reduce((sum, reg) => {
                    const pkg = this.packages.find(p => p.id == reg.packageId);
                    return sum + Number(pkg?.price || 0);
                }, 0);
                const enrollmentTotal = this.enrollmentFee * this.registrants.length;
                return (packageTotal + enrollmentTotal).toFixed(2);
            },

            calculateVat() {
                if (!this.vatPercentage || this.vatPercentage <= 0) return '0.00';
                return (parseFloat(this.calculateSubtotal()) * this.vatPercentage / 100).toFixed(2);
            },

            calculateTotal() {
                return (parseFloat(this.calculateSubtotal()) + parseFloat(this.calculateVat())).toFixed(2);
            },

            firstTimerCount() { return this.registrants.filter(r => !r.isMember).length; },
            firstTimerNames() { return this.registrants.map(r => r.name).join(', '); },

            genderLabel(g) {
                
                
                return g ? g.charAt(0).toUpperCase() + g.slice(1) : '';
            },

            calculateAge(dateOfBirth) {
                if (!dateOfBirth) return 0;
                const today = new Date();
                const birth = new Date(dateOfBirth);
                let age = today.getFullYear() - birth.getFullYear();
                const m = today.getMonth() - birth.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
                return age;
            },

            _uid() { return Math.random().toString(36).substr(2, 9); }
        },

        init() {
            window._joinModal = this.joinModal;
        }
    };
}

// Switch to the Packages tab and scroll to it
function switchToPackagesTab() {
    const tab = document.querySelector('[data-bs-target="#tab-packages"]');
    if (tab) {
        tab.click();
        setTimeout(() => {
            document.getElementById('tab-packages')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 150);
    }
}

// Global function called by the "Select Package" buttons
function openSelectPackageModal(packageId) {
    const wrap = document.getElementById('selectPackageModalWrap');
    if (wrap && wrap._x_dataStack) {
        const app = wrap._x_dataStack[0];
        if (app && app.joinModal) app.joinModal.showForPackage(packageId);
    }
}
</script>
@endpush
