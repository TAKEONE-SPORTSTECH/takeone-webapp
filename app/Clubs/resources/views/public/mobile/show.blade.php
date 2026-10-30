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
    The club's front page — MOBILE (and the Android app, which is this page in a
    WebView). Design source: drafts/Club Pages & Design/Club/emperor-academy-mobile-
    complete.html. Desktop twin: clubs::public.show. Both read App\Clubs\Support\ClubFrontPage,
    page-style, page-runtime and page-overlays.

    Real data only: a section whose data is empty is not drawn.
--}}
@section('content')
@php extract(\App\Clubs\Support\ClubFrontPage::data(compact('club', 'achievementsAll', 'reviews', 'likedPostIds', 'joinedEventIds'))); @endphp
@include('clubs::public.partials.page-style')
@php
    $cpMobile = true;
    $cpHasSchedule = $cpWeek->contains(fn ($d) => count($d['slots']));
    $cpTabs = collect([
        'packages' => [__('clubs::page.tab_packages'), true],
        'overview' => [__('clubs::page.tab_overview'), true],
        'schedule' => [__('clubs::page.nav_schedule'), $cpHasSchedule],
        'events' => [__('clubs::page.nav_events'), $cpEvents->isNotEmpty()],
        'offers' => [__('clubs::page.tab_offers'), $cpPerks->isNotEmpty()],
        'shop' => [__('clubs::page.nav_shop'), $cpProducts->isNotEmpty()],
        'reviews' => [__('clubs::page.nav_reviews'), true],
    ])->filter(fn ($t) => $t[1]);
@endphp
<style>
    .cpm { max-width: 520px; margin: 0 auto; padding-bottom: calc(96px + env(safe-area-inset-bottom)); }
    .cpm section[id] { scroll-margin-top: 56px; }
    .cpm-top { background: #171719; color: #fff; padding: 16px 20px 12px; display: flex; align-items: center; gap: 10px; }
    .cpm-top .cp-round { width: 40px; height: 40px; border: 1px solid rgba(255,255,255,.3); color: #fff; }
    .cpm-hero { height: 385px; position: relative; color: #fff; background-color: #121214; background-size: cover; background-position: center; }
    .cpm-hero-shade { position: absolute; inset: 0; background: linear-gradient(0deg, #121214 0%, rgba(18,18,20,.31) 58%, rgba(18,18,20,.06) 100%); }
    .cpm-title { font-size: 52px; line-height: .9; font-weight: 700; letter-spacing: -.06em; text-transform: uppercase; overflow-wrap: anywhere; }
    .cpm-h { font-size: 40px; line-height: 1; font-weight: 700; text-transform: uppercase; letter-spacing: -.05em; }
    .cpm-tabs { position: sticky; top: 0; z-index: 30; background: var(--cp-card); border-bottom: 1px solid var(--cp-line); display: flex; }
    .cpm-tabs button { font-size: 13px; font-weight: 800; padding: 15px 14px; white-space: nowrap; border-bottom: 3px solid transparent !important; color: var(--cp-ink); }
    .cpm-tabs button.is-on { border-color: var(--cp-accent) !important; color: var(--cp-accent); }
    .cpm-pad { padding-inline: 20px; }
    .cpm-dark { background: #1b1b1f; color: #fff; }
    .cpm-bottom { position: fixed; bottom: 0; left: 50%; transform: translateX(-50%); width: min(520px, 100%); z-index: 50; background: var(--cp-card);
                  border-top: 1px solid var(--cp-line); padding: 8px 10px calc(8px + env(safe-area-inset-bottom)); display: flex; justify-content: space-between; }
    .cpm-bottom button { font-size: 10px; font-weight: 800; display: flex; flex-direction: column; align-items: center; gap: 3px; min-width: 56px; min-height: 44px; justify-content: center; color: var(--cp-muted); }
    .cpm-bottom button i { font-size: 20px; }
    .cpm-bottom button.is-join { color: var(--cp-accent); }
    .cpm .cp-media { grid-template-columns: 1fr 1fr; gap: 8px; }
    .cpm .cp-media > button { min-height: 130px; }
    .cpm .cp-media > button:first-child { grid-column: span 2; grid-row: auto; min-height: 210px; }
    .cpm-tile { width: 220px; }
</style>

<div class="cp" x-data="clubPage" :class="dark && 'cp-dark'" id="clubPage">
<script type="application/json" id="cp-json">@json($cpJson)</script>
<div class="cpm">

{{-- ===================== TOP BAR ===================== --}}
<header class="cpm-top">
    <button type="button" class="cp-round" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('clubs.explore') }}')" aria-label="{{ __('clubs::page.back_explore') }}">
        <i class="bi bi-chevron-left rtl:rotate-180"></i>
    </button>
    <div style="flex:1;min-width:0;">
        @if($cpPlace)<p style="font-size:10px;letter-spacing:.2em;color:rgba(255,255,255,.5);text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $cpPlace }}</p>@endif
        <b class="cp-display" style="font-size:20px;text-transform:uppercase;display:block;line-height:1.1;">{{ $cpNameHead }} @if($cpNameTail)<span class="cp-red" style="filter:brightness(1.25);">{{ $cpNameTail }}</span>@endif</b>
    </div>
    <button type="button" class="cp-round" @click="toggleDark()" :aria-pressed="dark" aria-label="{{ __('clubs::page.theme') }}"><i class="bi" :class="dark ? 'bi-sun' : 'bi-moon-stars'"></i></button>
    <form method="POST" action="{{ route('locale.set') }}" style="margin:0;">
        @csrf @method('PUT')
        <input type="hidden" name="locale" value="{{ $cpNextLocale }}">
        <button type="submit" class="cp-round" style="font-size:12px;font-weight:800;" aria-label="{{ $cpNextLocale === 'ar' ? 'العربية' : 'English' }}">{{ $cpNextLocale === 'ar' ? 'ع' : 'EN' }}</button>
    </form>
    <button type="button" class="cp-round" @click="share()" aria-label="{{ __('clubs::page.share') }}"><i class="bi bi-arrow-up-right cp-flip"></i></button>
</header>

{{-- ===================== HERO ===================== --}}
<div class="cpm-hero" style="{{ $cpCover ? "background-image:url('".e($cpCover)."')" : 'background:linear-gradient(160deg, '.$cpAccent.', #121214 70%)' }}">
    <div class="cpm-hero-shade"></div>
    @if($cpSports->isNotEmpty())
        <span class="cp-tag" style="position:absolute;top:20px;inset-inline-start:20px;background:var(--cp-accent);padding:8px 12px;border-radius:99px;font-size:10px;"><i class="bi bi-check-lg"></i> {{ $cpSports->first() }}</span>
    @endif
    @if($canManage)
        <a href="{{ route('admin.club.details', $club->slug) }}" class="cp-badge" style="position:absolute;top:20px;inset-inline-end:20px;background:rgba(255,255,255,.92);color:#171719;padding:8px 12px;"><i class="bi bi-pencil-square"></i>{{ __('clubs::page.edit_club') }}</a>
    @endif
    <div style="position:absolute;bottom:20px;inset-inline:20px;">
        <p class="cp-tag" style="color:rgba(255,255,255,.8);margin-bottom:12px;">{{ collect([$cpSports->take(2)->implode(' · ') ?: null, $cpPlace])->filter()->implode(' · ') }}</p>
        <h1 class="cpm-title cp-display">
            @if(count($cpHeadline))
                @foreach($cpHeadline as $i => $line)
                    <span @if($loop->last && $loop->count > 1) class="cp-red" style="filter:brightness(1.25);" @endif>{{ $line }}</span>@if(! $loop->last)<br>@endif
                @endforeach
            @else
                {{ $cpName }}<span class="cp-red">.</span>
            @endif
        </h1>
        <div style="margin-top:20px;display:flex;align-items:flex-end;justify-content:space-between;gap:12px;">
            <div style="min-width:0;">
                @if(count($cpHeadline))<p style="font-weight:700;font-size:19px;">{{ $cpName }}</p>@endif
                <p style="color:rgba(255,255,255,.7);font-size:12px;margin-top:4px;">{{ collect([$cpSports->take(2)->implode(' & ') ?: null, $cpFrom ? __('clubs::page.from_price', ['price' => $cpFrom, 'currency' => $cpCurrency]).' '.__('clubs::page.per_month') : null])->filter()->implode(' · ') }}</p>
            </div>
            @if($cpLogo)<span style="width:56px;height:56px;flex-shrink:0;"><img src="{{ $cpLogo }}" alt="" style="width:100%;height:100%;object-fit:contain;"></span>@endif
        </div>
    </div>
</div>

{{-- ===================== STATS + JOIN ===================== --}}
<div style="background:#171719;color:#fff;padding:8px 20px 24px;">
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;padding:16px 0;border-bottom:1px solid rgba(255,255,255,.15);">
        <div><b class="cp-display" style="font-size:30px;">{{ number_format($activeMembersCount) }}</b><p style="font-size:10px;color:rgba(255,255,255,.5);letter-spacing:.08em;text-transform:uppercase;">{{ __('clubs::page.stat_members') }}</p></div>
        <div><b class="cp-display" style="font-size:30px;">{{ str_pad($cpCoachesCount, 2, '0', STR_PAD_LEFT) }}</b><p style="font-size:10px;color:rgba(255,255,255,.5);letter-spacing:.08em;text-transform:uppercase;">{{ __('clubs::page.stat_coaches') }}</p></div>
        <div><b class="cp-display" style="font-size:30px;">{{ str_pad($cpMedals, 2, '0', STR_PAD_LEFT) }}</b><p style="font-size:10px;color:rgba(255,255,255,.5);letter-spacing:.08em;text-transform:uppercase;">{{ __('clubs::page.stat_medals') }}</p></div>
    </div>
    <div style="display:flex;gap:8px;margin-top:16px;">
        <button type="button" class="cp-cta" style="flex:1;" @click="join()">{{ __('clubs::page.join_club') }} <i class="bi bi-arrow-up-right cp-flip"></i></button>
        @if($cpWhatsapp)<a href="{{ $cpWhatsapp }}" target="_blank" rel="noopener" class="cp-round" style="width:48px;height:48px;border:1px solid rgba(255,255,255,.3);" aria-label="{{ __('clubs::page.whatsapp') }}"><i class="bi bi-whatsapp"></i></a>@endif
        @if($cpTel)<a href="{{ $cpTel }}" class="cp-round" style="width:48px;height:48px;border:1px solid rgba(255,255,255,.3);" aria-label="{{ __('clubs::page.call') }}"><i class="bi bi-telephone"></i></a>@endif
        <button type="button" class="cp-round" style="width:48px;height:48px;border:1px solid rgba(255,255,255,.3);" @click="share()" aria-label="{{ __('clubs::page.share') }}"><i class="bi bi-share"></i></button>
    </div>
</div>

{{-- ===================== SECTION TABS ===================== --}}
<nav class="cpm-tabs cp-scroll-x" aria-label="{{ $cpName }}">
    @foreach($cpTabs as $key => $t)
        <button type="button" :class="tab === '{{ $key }}' && 'is-on'" @click="go('{{ $key }}')">{{ $t[0] }}</button>
    @endforeach
</nav>

<main>
{{-- ===================== YOUR MOVE ===================== --}}
<section class="cp-fade" style="margin:40px 20px 0;background:var(--cp-accent);color:#fff;border-radius:28px;padding:28px;">
    <p class="cp-tag">{{ __('clubs::page.cta_eyebrow_m') }}</p>
    <h2 style="font-size:48px;font-weight:700;margin-top:16px;line-height:1;text-transform:uppercase;">{!! nl2br(e(__('clubs::page.cta_title_m'))) !!}</h2>
    @if($cpFrom)<p style="margin-top:20px;">{{ __('clubs::page.cta_join_m', ['club' => $cpName, 'price' => $cpFrom, 'currency' => $cpCurrency]) }}</p>@endif
    <button type="button" class="cp-cta cp-cta-white" style="width:100%;margin-top:20px;" @click="join()">{{ __('clubs::page.become_member') }} <i class="bi bi-arrow-up-right cp-flip"></i></button>
    <div style="display:flex;justify-content:center;margin-top:14px;">
        <x-qr-code label="{{ __('clubs::page.registration_qr') }}" icon="bi-qr-code" button-class="text-xs text-white/70 inline-flex items-center gap-1.5" :targets="$cpQrTargets" />
    </div>
</section>

{{-- ===================== WHO IS TRAINING ===================== --}}
@if($cpWho->count() > 1)
<section class="cpm-pad" style="padding-top:40px;">
    <p class="cp-tag cp-red">{{ __('clubs::page.who_eyebrow') }}</p>
    <h2 style="font-size:32px;font-weight:700;margin-top:10px;line-height:1.05;">{{ __('clubs::page.who_title') }}</h2>
    <p class="cp-muted" style="margin-top:12px;font-size:15px;line-height:1.55;">{{ __('clubs::page.who_sub') }}</p>
    <div style="display:grid;grid-template-columns:repeat({{ $cpWho->count() }},1fr);gap:8px;margin-top:24px;">
        @foreach($cpWho as $w)
            <button type="button" class="cp-card" style="padding:14px 8px;text-align:center;" @click="pick('{{ $w['cat'] }}')">
                <span class="cp-icon-tile" style="width:48px;height:48px;margin:0 auto 8px;border-radius:16px;"><i class="bi {{ $w['icon'] }}" style="font-size:22px;"></i></span>
                <b style="font-size:14px;display:block;">{{ $w['label'] }}</b>
                <p class="cp-muted" style="font-size:11px;margin-top:2px;">{{ $w['sub'] }}</p>
            </button>
        @endforeach
    </div>
</section>
@endif

{{-- ===================== PROGRAMMES ===================== --}}
<section class="cpm-pad" id="packages" style="padding-top:40px;">
    <p class="cp-tag cp-red">{{ __('clubs::page.packages_eyebrow_m') }}</p>
    <h2 class="cpm-h cp-display" style="margin-top:8px;">{!! nl2br(e(__('clubs::page.packages_title_m'))) !!}</h2>
    <p class="cp-muted" style="font-size:14px;margin-top:12px;">{{ __('clubs::page.packages_sub_m') }}</p>
    @if($cpPackages->isNotEmpty())
        @if($cpCats->count() > 1)
            <div class="cp-scroll-x" style="display:flex;gap:8px;margin-top:20px;margin-inline-end:-20px;padding-bottom:4px;">
                <button type="button" class="cp-pill" :class="cat === 'all' && 'is-on'" @click="cat = 'all'">{{ __('clubs::page.cat_all') }}</button>
                @foreach($cpCats as $c)
                    <button type="button" class="cp-pill" :class="cat === '{{ $c }}' && 'is-on'" @click="cat = '{{ $c }}'">{{ __('clubs::page.cat_'.$c) }}</button>
                @endforeach
            </div>
        @endif
        @if(auth()->check() && $cpPackages->contains(fn ($p) => ! $p['applicable']))
            <button type="button" style="margin-top:18px;display:flex;align-items:center;gap:10px;font-size:14px;font-weight:700;" @click="eligibleOnly = !eligibleOnly" :aria-pressed="eligibleOnly">
                <i class="bi" :class="eligibleOnly ? 'bi-check-square-fill cp-red' : 'bi-square'" style="font-size:18px;"></i>{{ __('clubs::page.eligible_family_m') }}
            </button>
        @endif
        <div style="display:grid;gap:12px;margin-top:20px;">
            @foreach($cpPackages as $p)
                @include('clubs::public.partials.page-package-card')
            @endforeach
        </div>
    @else
        <div class="cp-card" style="padding:40px 20px;text-align:center;margin-top:20px;">
            <span class="cp-icon-tile" style="width:56px;height:56px;margin:0 auto;"><i class="bi bi-box" style="font-size:24px;"></i></span>
            <p style="font-weight:700;margin-top:12px;">{{ __('clubs::club.no_packages') }}</p>
            <p class="cp-muted" style="font-size:13px;margin-top:4px;">{{ __('clubs::club.check_back_later') }}</p>
        </div>
    @endif
</section>

{{-- ===================== THE CLUB ===================== --}}
<section id="overview" style="padding-top:40px;">
    <div class="cpm-pad">
        <p class="cp-tag cp-red">{{ __('clubs::page.about_eyebrow_m', ['club' => mb_strtoupper($cpName)]) }}</p>
        <h2 class="cpm-h cp-display" style="margin-top:10px;">{!! nl2br(e(__('clubs::page.about_title_m'))) !!}</h2>
        @if($club->tr('description'))
            <div class="cp-muted" style="font-size:14px;line-height:1.65;margin-top:12px;"><x-prose-text :text="$club->tr('description')" /></div>
        @endif
        @if($cpSocial->isNotEmpty())
            <div class="cp-scroll-x" style="display:flex;gap:8px;margin-top:16px;">
                @foreach($cpSocial as $s)
                    <a href="{{ $s['url'] }}" target="_blank" rel="noopener" class="cp-pill" aria-label="{{ $s['label'] }}"><i class="bi {{ $s['icon'] }}"></i>{{ $s['label'] }}</a>
                @endforeach
            </div>
        @endif
    </div>

    @if($cpHome || $cpAddress)
        <div class="cpm-pad" style="margin-top:24px;">
            <div class="cp-card">
                <div style="height:160px;position:relative;background:linear-gradient(135deg, {{ $cpAccent }}, #171719);">
                    @if($cpHomeImage ?: $cpCover)<img src="{{ $cpHomeImage ?: $cpCover }}" alt="" class="cp-cover">@endif
                    @if($cpHome?->is_available)<span class="cp-badge" style="position:absolute;top:12px;inset-inline-start:12px;background:#ecfdf3;color:#166534;">● {{ __('clubs::page.hall_available') }}</span>@endif
                </div>
                <div style="padding:16px;display:flex;justify-content:space-between;align-items:center;gap:12px;">
                    <div style="min-width:0;">
                        <p class="cp-tag cp-red">{{ __('clubs::page.train_here') }}</p>
                        <h3 style="font-weight:700;font-size:18px;">{{ $cpHome?->tr('name') ?: $cpName }}</h3>
                        @if($cpHome?->tr('address') ?: $cpAddress)<p class="cp-muted" style="font-size:12px;">{{ $cpHome?->tr('address') ?: $cpAddress }}</p>@endif
                    </div>
                    @if($cpMapsUrl)<a href="{{ $cpMapsUrl }}" target="_blank" rel="noopener" class="cp-pill"><i class="bi bi-geo-alt"></i>{{ __('clubs::page.map') }}</a>@endif
                </div>
            </div>
        </div>
    @endif

    @if($cpCoaches->isNotEmpty())
        <div style="margin-top:36px;">
            <div class="cpm-pad" style="display:flex;justify-content:space-between;align-items:flex-end;gap:10px;">
                <h2 style="font-size:30px;font-weight:700;">{{ __('clubs::page.coaches_title_m') }}</h2>
                <span class="cp-tag cp-red" style="text-align:end;">{{ __('clubs::page.coaches_aside_m') }}</span>
            </div>
            <div class="cp-scroll-x" style="display:flex;gap:12px;padding:16px 20px 4px;">
                @foreach($cpCoaches as $c)
                    <a href="{{ $c['url'] }}" class="cp-card cpm-tile" style="padding:16px;display:block;">
                        <span style="display:flex;justify-content:center;background:var(--cp-sand);border-radius:14px;height:132px;overflow:hidden;">
                            <span class="cp-portrait" style="height:132px;background:none;">
                                @if($c['photo'])<img src="{{ $c['photo'] }}" alt="{{ $c['name'] }}">@else<x-gender-avatar :gender="$c['gender']" class="w-full h-full" />@endif
                            </span>
                        </span>
                        <b style="display:block;font-size:16px;line-height:1.2;margin-top:12px;">{{ $c['name'] }}</b>
                        <span class="cp-muted" style="font-size:12px;display:block;">{{ $c['role'] }}</span>
                        <span class="cp-red" style="font-size:14px;font-weight:700;display:inline-block;margin-top:10px;">{{ __('clubs::page.meet', ['name' => \Illuminate\Support\Str::of($c['name'])->explode(' ')->first()]) }} <i class="bi bi-arrow-up-right cp-flip"></i></span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if($cpAchievements->isNotEmpty())
        <div class="cpm-dark" style="margin-top:36px;padding:32px 0;" id="medals">
            <div class="cpm-pad" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                <div>
                    <p class="cp-tag" style="color:#ff8197;">{{ __('clubs::page.medals_eyebrow_m') }}</p>
                    <h2 style="font-size:30px;font-weight:700;margin-top:8px;">{{ __('clubs::page.medals_title_m') }}</h2>
                </div>
                @if($cpAchievements->count() > 1)
                    <button type="button" class="cp-round" style="width:44px;height:44px;border:1px solid rgba(255,255,255,.3);" onclick="document.getElementById('cpmAchTrack').scrollBy({left: (document.dir === 'rtl' ? -240 : 240), behavior: 'smooth'})" aria-label="{{ __('clubs::page.next') }}"><i class="bi bi-arrow-right rtl:rotate-180"></i></button>
                @endif
            </div>
            <div class="cp-scroll-x" id="cpmAchTrack" style="display:flex;gap:12px;padding:20px 20px 0;">
                @foreach($cpAchievements as $i => $a)
                    <button type="button" style="width:260px;border-radius:18px;overflow:hidden;background:#29292e;text-align:start;" @click="openAch({{ $i }})">
                        @if($a['image'])
                            <img src="{{ $a['image'] }}" alt="" style="height:112px;width:100%;object-fit:cover;display:block;">
                        @else
                            <div style="height:112px;display:grid;place-items:center;background:linear-gradient(135deg, {{ $cpAccent }}, #29292e);"><i class="bi bi-trophy-fill" style="font-size:36px;opacity:.5;"></i></div>
                        @endif
                        <div style="padding:16px;">
                            @if($a['location'])<p class="cp-tag" style="color:#ff8197;">{{ $a['location'] }}</p>@endif
                            <h3 style="font-weight:700;font-size:18px;margin-top:6px;line-height:1.15;">{{ $a['short'] }}</h3>
                            @if($a['date'])<p style="font-size:12px;color:rgba(255,255,255,.5);margin-top:4px;">{{ $a['date'] }}</p>@endif
                            @if($a['gold'] + $a['silver'] + $a['bronze'])
                                <p style="margin-top:10px;font-weight:700;font-size:13px;display:flex;gap:10px;flex-wrap:wrap;">
                                    @foreach([1 => $a['gold'], 2 => $a['silver'], 3 => $a['bronze']] as $place => $count)
                                        @if($count)<span style="display:inline-flex;align-items:center;gap:4px;"><x-medal-icon :place="$place" :size="13" :numeral="false" /> {{ $count }}</span>@endif
                                    @endforeach
                                </p>
                            @endif
                            <span style="font-size:13px;text-decoration:underline;margin-top:10px;display:inline-block;">{{ __('clubs::page.view_story') }}</span>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    @endif
</section>

{{-- ===================== NEWS ===================== --}}
@if($cpPosts->isNotEmpty())
<section id="latest" style="background:#19191d;color:#fff;padding:40px 20px;">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:12px;">
        <div>
            <p class="cp-tag" style="color:#fa8197;">{{ __('clubs::page.news_eyebrow') }}</p>
            <h2 style="font-size:32px;font-weight:700;margin-top:8px;">{{ __('clubs::page.news_title') }}</h2>
            <p style="color:rgba(255,255,255,.6);margin-top:10px;font-size:14px;">{{ __('clubs::page.news_sub') }}</p>
        </div>
        @if($cpPosts->count() > 3)<button type="button" class="cp-pill cp-pill-light" @click="openNews()">{{ __('clubs::page.all_updates') }}</button>@endif
    </div>
    <div style="display:grid;gap:12px;margin-top:20px;">
        @foreach($cpPosts->take(3) as $i => $p)
            <button type="button" style="border-radius:22px;overflow:hidden;background:#29292d;text-align:start;" @click="openPost({{ $i }})" id="post-{{ $p['id'] }}">
                @if($p['image'])<img src="{{ $p['image'] }}" alt="" style="width:100%;height:160px;object-fit:cover;display:block;">@endif
                <div style="padding:16px;">
                    <span class="cp-tag" style="color:#ff8197;">{{ collect([$p['category'], $p['date']])->filter()->implode(' · ') }}</span>
                    <h3 style="font-size:20px;font-weight:700;margin-top:8px;line-height:1.2;">{{ $p['title'] }}</h3>
                    <span style="display:flex;justify-content:space-between;margin-top:12px;font-size:13px;">
                        <span style="text-decoration:underline;">{{ __('clubs::page.read_update') }}</span>
                        <span style="color:rgba(255,255,255,.55);display:flex;gap:12px;">
                            <span><i class="bi" :class="d.posts[{{ $i }}].liked ? 'bi-heart-fill' : 'bi-heart'"></i> <span x-text="d.posts[{{ $i }}].likes">{{ $p['likes'] }}</span></span>
                            <span><i class="bi bi-chat"></i> <span x-text="d.posts[{{ $i }}].comments.length">{{ count($p['comments']) }}</span></span>
                        </span>
                    </span>
                </div>
            </button>
        @endforeach
    </div>
</section>
@endif

{{-- ===================== GALLERY ===================== --}}
@if($cpGallery->isNotEmpty() || $cpYoutube)
<section id="media" style="background:var(--cp-card);padding:40px 20px;">
    <p class="cp-tag cp-red">{{ __('clubs::page.media_eyebrow', ['club' => $cpName]) }}</p>
    <h2 style="font-size:32px;font-weight:700;margin-top:8px;">{{ __('clubs::page.media_title') }}</h2>
    <p class="cp-muted" style="margin:12px 0 24px;font-size:15px;line-height:1.55;">{{ __('clubs::page.media_sub') }}</p>
    <div class="cp-media">
        @foreach($cpGallery->take($cpYoutube ? 4 : 5) as $i => $g)
            <button type="button" @click="open({{ $i }})">
                <img src="{{ $g['src'] }}" alt="{{ $g['caption'] ?: $cpName }}" loading="lazy">
                @if($g['caption'])<span class="cp-media-label">{{ $g['caption'] }}</span>@endif
            </button>
        @endforeach
        @if($cpYoutube)
            <button type="button" @click="openVideo()" style="background:linear-gradient(135deg, {{ $cpAccent }}, #171719);">
                <span style="position:absolute;inset:0;z-index:1;display:grid;place-items:center;"><span class="cp-round" style="width:52px;height:52px;background:rgba(255,255,255,.92);color:{{ $cpAccent }};"><i class="bi bi-play-fill" style="font-size:24px;"></i></span></span>
                <span class="cp-media-label">{{ __('clubs::page.watch_film') }}</span>
            </button>
        @endif
    </div>
    @if($cpGallery->isNotEmpty())
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:16px;">
            <p class="cp-muted" style="font-size:12px;">{{ __('clubs::page.media_footer') }}</p>
            <button type="button" class="cp-pill" @click="open(0)">{{ __('clubs::page.open_gallery') }} <i class="bi bi-arrow-up-right cp-flip"></i></button>
        </div>
    @endif
</section>
@endif

{{-- ===================== SCHEDULE ===================== --}}
@if($cpHasSchedule)
<section id="schedule" class="cpm-pad" style="padding-top:48px;">
    <p class="cp-tag cp-red">{{ __('clubs::page.schedule_eyebrow_m') }}</p>
    <h2 class="cpm-h cp-display" style="margin-top:8px;">{!! nl2br(e(__('clubs::page.schedule_title_m'))) !!}</h2>
    <div class="cp-scroll-x" style="display:flex;gap:8px;margin-top:20px;margin-bottom:16px;margin-inline-end:-20px;">
        @foreach($cpWeek as $d)
            <button type="button" class="cp-pill" :class="day === '{{ $d['key'] }}' && 'is-on'" @click="day = '{{ $d['key'] }}'">{{ $d['label'] }}</button>
        @endforeach
    </div>
    @foreach($cpWeek as $d)
        <div x-show="day === '{{ $d['key'] }}'" @if($d['key'] !== $cpToday) x-cloak @endif class="cp-fade">
            @forelse($d['slots'] as $s)
                <div class="cp-card" style="padding:16px;display:flex;align-items:center;gap:14px;margin-bottom:12px;">
                    <div style="min-width:80px;text-align:center;"><b style="font-size:15px;">{{ $s['start'] }}</b></div>
                    <div style="flex:1;min-width:0;">
                        <b style="font-size:15px;">{{ $s['title'] }}</b>
                        <p class="cp-muted" style="font-size:13px;">{{ collect([$s['coach'] ? __('clubs::page.coach_named', ['name' => $s['coach']]) : null, $s['place'] ?: ($s['package'] !== $s['title'] ? $s['package'] : null)])->filter()->implode(' · ') }}</p>
                    </div>
                    <span style="color:#16a34a;font-size:12px;font-weight:700;white-space:nowrap;">● {{ __('clubs::page.available') }}</span>
                </div>
            @empty
                <div class="cp-card" style="padding:28px;text-align:center;">
                    <span class="cp-icon-tile" style="width:52px;height:52px;margin:0 auto 8px;"><i class="bi bi-moon-stars" style="font-size:22px;"></i></span>
                    <b>{{ __('clubs::page.rest_day') }}</b>
                    <p class="cp-muted" style="font-size:13px;">{{ __('clubs::page.rest_day_sub') }}</p>
                </div>
            @endforelse
        </div>
    @endforeach
</section>
@endif

{{-- ===================== EVENTS ===================== --}}
@if($cpEvents->isNotEmpty())
<section id="events" class="cpm-pad" style="padding-top:48px;">
    <p class="cp-tag cp-red">{{ __('clubs::page.events_eyebrow_m') }}</p>
    <h2 class="cpm-h cp-display" style="margin-top:8px;">{{ __('clubs::page.events_title_m') }}</h2>
    <div style="display:grid;gap:12px;margin-top:20px;">
        @foreach($cpEvents->take(4) as $e)
            <div class="cp-card">
                @if($e['image'])<div style="height:160px;"><img src="{{ $e['image'] }}" alt="" class="cp-cover"></div>@endif
                <div style="padding:18px;">
                    <p class="cp-tag cp-red">@if($e['live'])<span style="color:#16a34a;">● {{ __('clubs::page.live_now') }}</span> · @endif{{ $e['date'] }}@if($e['level']) · {{ $e['level'] }}@endif</p>
                    <h3 style="font-size:22px;font-weight:700;margin-top:6px;">{{ $e['title'] }}</h3>
                    @if($e['location'])<p class="cp-muted" style="font-size:13px;margin-top:6px;"><i class="bi bi-geo-alt"></i> {{ $e['location'] }}</p>@endif
                    @if($e['joined'])<span class="cp-badge" style="background:#e8f6ee;color:#15803d;margin-top:10px;"><i class="bi bi-check-circle-fill"></i>{{ __('clubs::page.joined') }}</span>@endif
                    @if($e['url'])<a href="{{ $e['url'] }}" class="cp-cta" style="width:100%;margin-top:16px;">{{ $e['cta'] ?: __('clubs::page.register_event') }} <i class="bi bi-arrow-up-right cp-flip"></i></a>@endif
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif

{{-- ===================== PERKS ===================== --}}
@if($cpPerks->isNotEmpty())
@php $cpLead = $cpPerks->first(); $cpRest = $cpPerks->slice(1)->values(); @endphp
<section id="offers" style="padding-top:48px;">
    <div class="cpm-pad">
        <p class="cp-tag cp-red">{{ __('clubs::page.perks_eyebrow_m') }}</p>
        <h2 class="cpm-h cp-display" style="margin-top:8px;">{!! nl2br(e(__('clubs::page.perks_title_m'))) !!}</h2>
    </div>
    <div style="margin:24px 20px 0;border-radius:26px;background:var(--cp-accent);color:#fff;padding:24px;position:relative;overflow:hidden;">
        @if($cpLead['image'])<img src="{{ $cpLead['image'] }}" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.18;">@endif
        <i class="bi {{ $cpLead['icon'] }}" style="position:absolute;inset-inline-end:-20px;top:-30px;font-size:170px;opacity:.1;"></i>
        <div style="position:relative;">
            <p class="cp-tag">{{ $cpLead['badge'] ?: __('clubs::page.members_only_perk') }}</p>
            <h3 style="font-size:44px;font-weight:700;line-height:1;margin-top:24px;">{{ $cpLead['title'] }}</h3>
            @if($cpLead['description'])<p style="font-weight:700;font-size:17px;margin-top:10px;">{{ $cpLead['description'] }}</p>@endif
            <button type="button" class="cp-cta cp-cta-white" style="width:100%;margin-top:20px;" @click="collect(@js($cpLead['url']))"><i class="bi bi-ticket-perforated-fill"></i>{{ __('clubs::page.collect') }}</button>
        </div>
    </div>
    @if($cpRest->isNotEmpty())
        <div class="cpm-pad" style="display:flex;justify-content:space-between;align-items:center;margin-top:28px;">
            <h3 style="font-size:24px;font-weight:700;">{{ __('clubs::page.member_perks') }}</h3>
            <span class="cp-tag cp-red"><i class="bi bi-lock-fill"></i> {{ __('clubs::page.exclusive') }}</span>
        </div>
        <div class="cpm-pad" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px;">
            @foreach($cpRest as $pk)
                <div class="cp-card" style="padding:16px;display:flex;flex-direction:column;">
                    <span class="cp-icon-tile" style="width:44px;height:44px;border-radius:14px;overflow:hidden;">
                        @if($pk['image'])<img src="{{ $pk['image'] }}" alt="" class="cp-cover">@else<i class="bi {{ $pk['icon'] }}" style="font-size:20px;"></i>@endif
                    </span>
                    <h3 style="font-weight:700;font-size:16px;margin-top:12px;line-height:1.15;">{{ $pk['title'] }}</h3>
                    @if($pk['description'])<p class="cp-muted" style="font-size:12px;margin-top:4px;">{{ $pk['description'] }}</p>@endif
                    @if($pk['badge'])<p class="cp-red cp-display" style="font-size:20px;font-weight:700;margin-top:10px;">{{ $pk['badge'] }}</p>@endif
                    <div style="flex:1;"></div>
                    <button type="button" class="cp-pill" style="margin-top:12px;width:100%;" @click="collect(@js($pk['url']))"><i class="bi bi-lock"></i>{{ __('clubs::page.collect') }}</button>
                </div>
            @endforeach
        </div>
    @endif
</section>
@endif

{{-- ===================== SHOP ===================== --}}
@if($cpProducts->isNotEmpty())
<section id="shop" class="cpm-pad" style="padding-top:48px;">
    <p class="cp-tag cp-red">{{ __('clubs::page.shop_eyebrow_m') }}</p>
    <h2 class="cpm-h cp-display" style="margin-top:8px;">{!! nl2br(e(__('clubs::page.shop_title_m'))) !!}</h2>
    @if($cpShopCats->count() > 1)
        <div class="cp-scroll-x" style="display:flex;gap:8px;margin-top:20px;margin-inline-end:-20px;">
            <button type="button" class="cp-pill" :class="shopCat === 'all' && 'is-on'" @click="shopCat = 'all'">{{ __('clubs::page.cat_all') }}</button>
            @foreach($cpShopCats as $sc)
                <button type="button" class="cp-pill" :class="shopCat === @js($sc['key']) && 'is-on'" @click="shopCat = @js($sc['key'])">{{ $sc['label'] }}</button>
            @endforeach
        </div>
    @endif
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:20px;">
        @foreach($cpProducts as $i => $pr)
            <a href="{{ $pr['url'] }}" class="cp-card" style="display:flex;flex-direction:column;" x-show="shopCat === 'all' || shopCat === @js($pr['cat'])">
                <div style="height:144px;position:relative;display:grid;place-items:center;background:{{ $i % 2 ? '#e9e6e1' : '#e7e9e9' }};">
                    @if($pr['image'])<img src="{{ $pr['image'] }}" alt="{{ $pr['name'] }}" class="cp-cover" loading="lazy">@else<i class="bi {{ $pr['icon'] }}" style="font-size:44px;color:#171719;opacity:.7;"></i>@endif
                    @if($pr['badge'])<span class="cp-badge" style="position:absolute;top:10px;inset-inline-start:10px;background:#fff;color:#111;font-size:10px;">{{ $pr['badge'] }}</span>@endif
                </div>
                <div style="padding:12px;">
                    <h3 style="font-size:15px;font-weight:700;line-height:1.2;">{{ $pr['name'] }}</h3>
                    <p style="margin-top:4px;font-size:14px;"><b>{{ $pr['price'] }} {{ $cpCurrency }}</b>@if($pr['old']) <s class="cp-muted" style="font-size:12px;">{{ $pr['old'] }}</s>@endif</p>
                </div>
            </a>
        @endforeach
    </div>
    <a href="{{ route('me.market') }}" class="cp-pill" style="width:100%;margin-top:14px;">{{ __('clubs::page.visit_shop') }} <i class="bi bi-arrow-up-right cp-flip"></i></a>
</section>
@endif

{{-- ===================== REVIEWS ===================== --}}
<section id="reviews" class="cpm-pad" style="padding-top:48px;">
    <p class="cp-tag cp-red">{{ __('clubs::page.reviews_eyebrow_m') }}</p>
    <h2 class="cpm-h cp-display" style="margin-top:8px;">{!! nl2br(e(__('clubs::page.reviews_title_m'))) !!}</h2>
    <div class="cp-card" style="padding:24px;margin-top:20px;text-align:center;">
        @if($reviews->count())
            <p class="cp-display" style="font-size:44px;font-weight:700;">{{ number_format($averageRating, 1) }}</p>
            <div style="color:#f59e0b;">@for($s = 1; $s <= 5; $s++)<i class="bi {{ $s <= round($averageRating) ? 'bi-star-fill' : 'bi-star' }}"></i>@endfor</div>
            <p class="cp-muted" style="font-size:13px;margin-top:4px;">{{ trans_choice('clubs::page.reviews_n', $reviews->count(), ['n' => $reviews->count()]) }}</p>
        @else
            <span class="cp-icon-tile" style="width:56px;height:56px;margin:0 auto;"><i class="bi bi-stars" style="font-size:24px;"></i></span>
            <h3 style="font-size:20px;font-weight:700;margin-top:12px;">{{ __('clubs::page.reviews_none_m') }}</h3>
            <p class="cp-muted" style="font-size:14px;margin-top:6px;">{{ __('clubs::page.reviews_none_sub_m') }}</p>
        @endif
    </div>
    @foreach($cpReviews as $r)
        <figure class="cp-card" style="margin:10px 0 0;padding:18px;">
            <div style="color:#f59e0b;font-size:12px;">@for($s = 1; $s <= 5; $s++)<i class="bi {{ $s <= $r['rating'] ? 'bi-star-fill' : 'bi-star' }}"></i>@endfor</div>
            <blockquote style="margin:8px 0 0;font-size:14px;line-height:1.6;">{{ $r['comment'] }}</blockquote>
            <figcaption class="cp-muted" style="font-size:12px;margin-top:8px;"><b style="color:var(--cp-ink);">{{ $r['name'] }}</b> · {{ $r['ago'] }}</figcaption>
        </figure>
    @endforeach
</section>

{{-- ===================== GOOD TO KNOW ===================== --}}
@if($cpFaq->isNotEmpty())
<section id="questions" style="background:var(--cp-card);padding:40px 20px;margin-top:48px;">
    <p class="cp-tag cp-red">{{ __('clubs::page.faq_eyebrow') }}</p>
    <h2 style="font-size:32px;font-weight:700;margin:8px 0 20px;">{{ __('clubs::page.faq_title') }}</h2>
    <div style="display:grid;gap:10px;">
        @foreach($cpFaq as $q)
            <details class="cp-card cp-faq" style="padding:18px;">
                <summary>{{ $q['q'] }} <i class="bi bi-plus-lg"></i></summary>
                <p class="cp-muted" style="margin-top:10px;font-size:14px;line-height:1.65;">{{ $q['a'] }}</p>
            </details>
        @endforeach
    </div>
    @if($cpWhatsapp)<a href="{{ $cpWhatsapp }}" target="_blank" rel="noopener" class="cp-cta" style="width:100%;margin-top:20px;"><i class="bi bi-whatsapp"></i>{{ __('clubs::page.ask_whatsapp') }}</a>@endif
</section>
@endif
</main>

{{-- ===================== BOTTOM BAR ===================== --}}
<nav class="cpm-bottom" aria-label="{{ $cpName }}">
    <button type="button" @click="go('overview')"><i class="bi bi-compass"></i>{{ __('clubs::page.tab_discover') }}</button>
    <button type="button" @click="go('packages')"><i class="bi bi-grid-1x2"></i>{{ __('clubs::page.tab_programs') }}</button>
    @if($cpPerks->isNotEmpty())<button type="button" @click="go('offers')"><i class="bi bi-stars"></i>{{ __('clubs::page.tab_perks') }}</button>@endif
    @if($cpProducts->isNotEmpty())<button type="button" @click="go('shop')"><i class="bi bi-bag"></i>{{ __('clubs::page.tab_shop') }}</button>@endif
    <button type="button" class="is-join" @click="join()"><i class="bi bi-plus-circle-fill"></i>{{ __('clubs::page.tab_join') }}</button>
</nav>

</div>

@include('clubs::public.partials.page-overlays')
</div>

{{-- ===== Enrol flow (shared, unchanged) ===== --}}
@include('clubs::public.partials._join-flow')
@include('clubs::public.partials.page-runtime')
@endsection
