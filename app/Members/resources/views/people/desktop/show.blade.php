{{-- `$shell` is shared ONLY on the sealed event routes (/e/{uuid}/admin/…), so with
     nothing shared this is the member shell exactly as before. See entry/shell. --}}
@extends($shell ?? 'layouts.app')

@section('title', $person->full_name)

{{--
    Public athlete profile — desktop.

    The phone's profile, laid out for a wide screen (owner, 2026-10-17: "make
    sure the functionality is exactly like the public profile on the mobile
    view but just in desktop view"). Same controller data, same Alpine scope:
    the stat row IS the tab bar (Record · Sports · Clubs · Honours), the hero's
    tally chips open Honours already filtered, the portrait opens a lightbox,
    Follow / Message / Challenge / Share behave as on the phone, and Back is an
    address, not history.back(). Only the arrangement differs: the hero is a
    full-width band, and each panel lays its cards out in a grid.

    Parity: a change to people/mobile/show lands here too.
--}}

@php
    $avatar = $avatarUrl ?? ($person->profile_picture
        ? file_url($person->profile_picture).'?v='.optional($person->updated_at)->timestamp
        : null);

    $age = $person->birthdate ? \Illuminate\Support\Carbon::parse($person->birthdate)->age : null;

    // "Verified" means a club has attested something on this profile.
    $isAttested = $verifiedMedals->isNotEmpty()
        || $activeAffil->contains(fn ($a) => ($a->verification_status ?? null) === 'verified');

    $flag = $countryCode ? mb_strtolower($countryCode) : null;
    $flagLabel = $countryCode ? (\App\Support\Countries::name($countryCode) ?: mb_strtoupper($countryCode)) : null;

    $tiers = [
        ['key' => 'gold',   'label' => __('personal.honour_gold'),   'count' => $honourTally['gold'],   'ring' => '#e0a300', 'face' => '#ffcb3d', 'ink' => '#7a5200', 'ribbon' => '#5b9bd5', 'ribbonDark' => '#3f7fb8', 'num' => 1],
        ['key' => 'silver', 'label' => __('personal.honour_silver'), 'count' => $honourTally['silver'], 'ring' => '#9aa4b4', 'face' => '#dfe5ec', 'ink' => '#4d5666', 'ribbon' => '#7f8b9c', 'ribbonDark' => '#67717f', 'num' => 2],
        ['key' => 'bronze', 'label' => __('personal.honour_bronze'), 'count' => $honourTally['bronze'], 'ring' => '#a9662f', 'face' => '#e08d4d', 'ink' => '#5f3413', 'ribbon' => '#c07a3c', 'ribbonDark' => '#9c5e2a', 'num' => 3],
    ];

    $tones = [
        'gold'   => ['#fff5da', '#b58500'],
        'silver' => ['#f2f4f7', '#7d8794'],
        'bronze' => ['#fdefe4', '#a4693a'],
        'trophy' => ['#f2effe', '#6d4bd8'],
    ];

    $glassBtn = 'width:40px;height:40px;border:1px solid rgba(255,255,255,.28);background:rgba(255,255,255,.16);backdrop-filter:blur(8px);border-radius:50%;color:#fff;display:grid;place-items:center;cursor:pointer;transition:transform .16s cubic-bezier(.22,.61,.36,1),background .16s ease';
    $card = 'background:#fff;border-radius:18px;box-shadow:0 6px 20px rgba(28,16,72,.07)';
    $chipMeta = 'display:flex;align-items:center;gap:5px;font-size:11.5px;font-weight:700;border-radius:8px;padding:5px 9px';
    $chipBase = 'box-sizing:border-box;display:flex;align-items:center;justify-content:center;gap:6px;height:34px;padding:0 12px;font-family:inherit;font-size:14px;font-weight:800;border-radius:10px;cursor:pointer;transition:background .16s,border-color .16s,transform .16s cubic-bezier(.22,.61,.36,1);';
    $chipOn = 'transform:translateY(-1px);background:rgba(255,255,255,.34);border:1px solid rgba(255,255,255,.55);';
    $chipOff = 'background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.18);';

    // Same back address as the phone.
    $backHref = $sealedBack
        ?? (($sealed ?? false)
            ? route('me.events.people', ['event' => request()->route('event')?->uuid])
            : ($platformBack ?? route('me.people')));

    $statTabs = [
        ['tab' => 'record',  'value' => $competition['fought'],                       'label' => __('personal.record'),  'icon' => 'bi-lightning-charge-fill'],
        ['tab' => 'sports',  'value' => $sports->count(),                            'label' => __('personal.sports'),  'icon' => 'bi-person-arms-up'],
        ['tab' => 'clubs',   'value' => $activeAffil->count(),                        'label' => __('personal.clubs'),   'icon' => 'bi-buildings'],
        ['tab' => 'honours', 'value' => $honours->count() + $certifications->count(), 'label' => __('personal.honours'), 'icon' => 'bi-award-fill'],
    ];
@endphp

@section($contentSection ?? 'content')
<style>
    .prof-page a { color: #6d4bd8; text-decoration: none; }
    @keyframes profFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
    @keyframes profRise { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
    @keyframes profPop  { 0% { opacity: 0; transform: scale(.86); } 60% { transform: scale(1.04); } 100% { opacity: 1; transform: scale(1); } }
    @keyframes profDrift { 0%, 100% { transform: translate3d(0,0,0) scale(1); } 50% { transform: translate3d(-14px,12px,0) scale(1.08); } }
    .prof-rise { animation: profRise .5s cubic-bezier(.22,.61,.36,1) both; }
    .prof-fade { animation: profFade .28s ease-out both; }
    .prof-row  { animation: profRise .42s cubic-bezier(.22,.61,.36,1) both; transition: transform .2s ease, box-shadow .2s ease; }
    .prof-row:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(28,16,72,.13); }
    .prof-press:active { transform: scale(.94); }
    .prof-press:hover { filter: brightness(1.04); }
    /* Panels toggled by x-show take their display from a class, never an inline
       style: Alpine shows an element by writing style.display = ''. */
    .prof-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 14px; }
    .prof-stack-lg { display: flex; flex-direction: column; gap: 16px; }
    .prof-line { display: flex; align-items: center; gap: 12px; }
    .prof-chip { display: flex; align-items: center; gap: 6px; }
    .prof-col { display: flex; flex-direction: column; }
    .prof-full { grid-column: 1 / -1; }
    @media (prefers-reduced-motion: reduce) {
        .prof-page *, .prof-page *::before, .prof-page *::after { animation: none !important; transition: none !important; }
    }
</style>

<div class="prof-page {{ isset($shell) ? '-mx-4 sm:-mx-6 lg:-mx-8 -mt-6' : '' }}"
     style="width:{{ isset($shell) ? 'auto' : '100%' }};background:#f4f5f9;min-height:100vh;padding-bottom:40px;color:#1c1c28"
     x-data="{
        tab: 'record',
        honour: 'all',
        following: {{ $isFollowing ? 'true' : 'false' }},
        photos: false,
        open(tab, honour = 'all') { this.tab = tab; this.honour = honour; },
        toggleFollow() {
            const was = this.following;
            this.following = !was;
            fetch('{{ url('u') }}/{{ $person->slug }}/follow', {
                method: was ? 'DELETE' : 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            }).then(r => { if (! r.ok) throw r; })
              .catch(() => { this.following = was; window.showToast && window.showToast('error', @js(__('shared.something_went_wrong'))); });
        },
        share() {
            const url = window.location.href;
            if (navigator.share) { navigator.share({ title: @js($person->full_name), url }).catch(() => {}); return; }
            navigator.clipboard?.writeText(url)
                .then(() => window.showToast && window.showToast('success', @js(__('shared.link_copied'))))
                .catch(() => {});
        },
     }">

    {{-- ===== Portrait lightbox ===== --}}
    @if($avatar)
        <template x-teleport="body">
            <div x-show="photos" x-cloak x-transition.opacity.duration.180ms
                 @keydown.escape.window="photos = false"
                 class="prof-col" style="position:fixed;inset:0;z-index:70;background:rgba(9,5,22,.94)">
                <div style="display:flex;align-items:center;justify-content:flex-end;padding:16px 20px 10px">
                    <button type="button" @click="photos = false" class="prof-press"
                            style="width:44px;height:44px;border:0;background:rgba(255,255,255,.14);border-radius:50%;color:#fff;font-size:17px;display:grid;place-items:center;cursor:pointer"
                            aria-label="{{ __('shared.close') }}"><i class="bi bi-x-lg"></i></button>
                </div>
                <div @click="photos = false" style="flex:1;min-height:0;overflow:hidden;padding:0 24px 40px">
                    <div style="width:100%;height:100%;border-radius:14px;background:url('{{ $avatar }}') center/contain no-repeat"></div>
                </div>
            </div>
        </template>
    @endif

    {{-- ===== Hero — full-width band ===== --}}
    <div style="position:relative;background:linear-gradient(165deg,#8f70f6 0%,#6d4bd8 58%,#5834bd 100%);color:#fff;overflow:hidden">
        <div style="position:absolute;top:-110px;right:-60px;width:320px;height:320px;border-radius:50%;background:rgba(255,255,255,.10);animation:profDrift 14s ease-in-out infinite"></div>
        <div style="position:absolute;bottom:-140px;left:30%;width:280px;height:280px;border-radius:50%;background:rgba(255,255,255,.07);animation:profDrift 18s ease-in-out infinite reverse"></div>

        <div class="px-4 sm:px-6 lg:px-8" style="position:relative;padding-top:24px;padding-bottom:92px">
            {{-- Control row: back on the leading edge, share on the trailing one. --}}
            <div style="display:flex;align-items:center;justify-content:space-between">
                <a href="{{ $backHref }}" class="prof-press" data-prof-back
                   style="{{ $glassBtn }};font-size:17px;text-decoration:none;color:#fff" aria-label="{{ __('shared.back') }}" title="{{ __('shared.back') }}"><i class="bi bi-chevron-left rtl:rotate-180"></i></a>
                <script>
                    (function () {
                        var link = document.currentScript.previousElementSibling;
                        if (! link || ! link.hasAttribute('data-prof-back')) return;
                        link.addEventListener('click', function (ev) {
                            if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.button) return;
                            var ref = document.referrer;
                            if (! ref || history.length <= 1) return;
                            var strip = function (u) { try { var x = new URL(u, location.href); return x.origin + x.pathname; } catch (e) { return null; } };
                            if (strip(ref) === null || strip(ref) !== strip(link.getAttribute('href'))) return;
                            ev.preventDefault();
                            history.back();
                        });
                    })();
                </script>
                <button type="button" class="prof-press" @click="share()"
                        style="{{ $glassBtn }};font-size:15px" aria-label="{{ __('shared.share') }}" title="{{ __('shared.share') }}"><i class="bi bi-share"></i></button>
            </div>

            <div class="prof-rise" style="display:flex;flex-wrap:wrap;gap:28px;align-items:flex-end;margin-top:22px">
                {{-- Portrait 3:4 — silhouette underneath, photograph on top. --}}
                <div style="position:relative;flex-shrink:0">
                    <span style="position:relative;display:block;width:150px;height:200px;border-radius:22px;overflow:hidden;box-shadow:0 16px 36px rgba(20,10,60,.34);outline:3px solid rgba(255,255,255,.30);animation:profPop .55s cubic-bezier(.22,.61,.36,1) both">
                        <x-gender-avatar :gender="$person->gender" class="w-full h-full" />
                        @if($avatar)
                            <img @click="photos = true" src="{{ $avatar }}" alt="{{ $person->full_name }}"
                                 onerror="this.remove()"
                                 style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;cursor:zoom-in;display:block">
                        @endif
                    </span>
                    @if($isAttested)
                        <span style="position:absolute;bottom:-11px;left:50%;transform:translateX(-50%);display:flex;align-items:center;gap:4px;background:#fff;color:#5834bd;font-size:10.5px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;padding:5px 10px;border-radius:999px;box-shadow:0 4px 12px rgba(20,10,60,.22);white-space:nowrap">
                            <i class="bi bi-patch-check-fill" style="font-size:11px"></i>{{ __('personal.verified_badge') }}
                        </span>
                    @endif
                </div>

                {{-- Identity --}}
                <div style="min-width:280px;flex:1 1 380px;display:flex;flex-direction:column;gap:10px;padding-bottom:4px">
                    <p style="margin:0;display:flex;align-items:center;gap:8px;font-size:13px;line-height:1.2;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.80)">
                        @if($flag)
                            <span class="fi fi-{{ $flag }}" title="{{ $flagLabel }}" style="flex-shrink:0;width:24px;height:18px;border-radius:3px;background-size:cover;box-shadow:0 0 0 1px rgba(255,255,255,.35)"></span>
                        @endif
                        {{ $person->is_personal_trainer ? __('personal.people_trainer') : __('personal.profile_eyebrow') }}
                        @if($flagLabel)<span style="opacity:.6">·</span><span style="letter-spacing:.04em;text-transform:none;font-weight:600">{{ $flagLabel }}</span>@endif
                    </p>

                    <div style="display:flex;align-items:center;gap:10px;min-width:0">
                        @if($person->gender === 'Female' || $person->gender === 'Male')
                            @php $isF = $person->gender === 'Female'; @endphp
                            <svg viewBox="4 -3 26 52" width="15" height="30" fill="none" style="flex-shrink:0;display:block;overflow:visible" aria-label="{{ $person->gender }}">
                                <defs><linearGradient id="gGenderD{{ $person->id }}" x1="4" y1="2" x2="30" y2="44" gradientUnits="userSpaceOnUse">
                                    <stop offset="0" stop-color="{{ $isF ? '#ff2f9b' : '#2f8bff' }}"></stop>
                                    <stop offset="1" stop-color="{{ $isF ? '#ff9ecd' : '#9ecdff' }}"></stop>
                                </linearGradient></defs>
                                <g stroke="url(#gGenderD{{ $person->id }})" stroke-width="7" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="17" cy="{{ $isF ? '14.5' : '30' }}" r="9.5"></circle>
                                    @if($isF)
                                        <path d="M17 24v19"></path><path d="M8.5 34.5h17"></path>
                                    @else
                                        <path d="M24 23L31 16"></path><path d="M23 14h9v9"></path>
                                    @endif
                                </g>
                            </svg>
                        @endif
                        {{-- Names are never truncated: the band wraps instead. --}}
                        <h1 style="margin:0;font-size:30px;line-height:1.15;font-weight:900;letter-spacing:-.01em">{{ $person->full_name }}</h1>
                    </div>

                    <p style="margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:600;color:rgba(255,255,255,.88)">
                        @if($age)
                            <span>{{ __('personal.years_old', ['count' => $age]) }}</span><span style="opacity:.55">·</span>
                        @endif
                        <span style="display:flex;align-items:center;gap:6px;font-weight:500;color:rgba(255,255,255,.78)">
                            <i class="bi bi-calendar3" style="font-size:12px"></i>{{ __('personal.member_since') }} {{ optional($person->created_at)->format('M Y') }}
                        </span>
                    </p>

                    {{-- Tally chips: a shortcut into Honours, already filtered. --}}
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:2px">
                        @foreach($tiers as $t)
                            <button type="button" @click="open('honours', '{{ $t['key'] }}')"
                                    :style="'{{ $chipBase }}color:#fff;' + (tab === 'honours' && honour === '{{ $t['key'] }}' ? '{{ $chipOn }}' : '{{ $chipOff }}')"
                                    aria-label="{{ $t['label'] }}" title="{{ $t['label'] }}">
                                <span style="position:relative;flex-shrink:0;width:18px;height:18px;display:block">
                                    <svg viewBox="0 0 24 24" width="18" height="18" style="display:block">
                                        <path d="M6 1.5h4l4 8H10z" fill="{{ $t['ribbon'] }}"></path>
                                        <path d="M18 1.5h-4l-4 8h4z" fill="{{ $t['ribbonDark'] }}"></path>
                                        <circle cx="12" cy="15.6" r="6.9" fill="{{ $t['ring'] }}"></circle>
                                        <circle cx="12" cy="15.6" r="5.1" fill="{{ $t['face'] }}"></circle>
                                    </svg>
                                    <span style="position:absolute;left:0;right:0;top:7px;text-align:center;font-size:9px;font-weight:800;line-height:1;color:{{ $t['ink'] }}">{{ $t['num'] }}</span>
                                </span>
                                {{ $t['count'] }}
                            </button>
                        @endforeach
                        <button type="button" @click="open('honours', 'trophy')"
                                :style="'{{ $chipBase }}color:#ffd76a;' + (tab === 'honours' && honour === 'trophy' ? '{{ $chipOn }}' : '{{ $chipOff }}')"
                                aria-label="{{ __('personal.trophies') }}" title="{{ __('personal.trophies') }}">
                            <i class="bi bi-trophy-fill" style="font-size:15px"></i>{{ $honourTally['trophy'] }}
                        </button>
                        <button type="button" @click="open('honours', 'cert')"
                                :style="'{{ $chipBase }}color:#9be7c4;' + (tab === 'honours' && honour === 'cert' ? '{{ $chipOn }}' : '{{ $chipOff }}')"
                                aria-label="{{ __('personal.certifications') }}" title="{{ __('personal.certifications') }}">
                            <i class="bi bi-patch-check-fill" style="font-size:15px"></i>{{ $certifications->count() }}
                        </button>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="prof-rise" style="display:flex;gap:10px;flex:0 0 auto;align-items:center;animation-delay:.12s;padding-bottom:4px">
                    @if($isGuest ?? false)
                        <a href="{{ route('login') }}" class="prof-press"
                           style="height:46px;padding:0 22px;border-radius:14px;background:#fff;border:1px solid #fff;color:#5834bd;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:7px;box-shadow:0 6px 16px rgba(20,10,60,.18)">
                            <i class="bi bi-box-arrow-in-right"></i>{{ __('personal.sign_in_to_connect') }}
                        </a>
                    @else
                        <button type="button" class="prof-press" @click="toggleFollow()"
                                :style="'min-width:140px;height:46px;padding:0 20px;border-radius:14px;font-family:inherit;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:7px;cursor:pointer;transition:transform .16s cubic-bezier(.22,.61,.36,1),background .18s ease;' + (following ? 'background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.30);color:#fff;' : 'background:#fff;border:1px solid #fff;color:#5834bd;box-shadow:0 6px 16px rgba(20,10,60,.18);')">
                            <i :class="following ? 'bi bi-check2' : 'bi bi-plus-lg'"></i>
                            <span x-text="following ? @js(__('personal.following')) : @js(__('personal.follow'))"></span>
                        </button>

                        @if($canMessage)
                            <form method="POST" action="{{ route('messages.start', $person) }}" style="display:flex">
                                @csrf
                                <button type="submit" class="prof-press"
                                        style="height:46px;padding:0 20px;border:1px solid rgba(255,255,255,.30);background:rgba(255,255,255,.16);backdrop-filter:blur(8px);border-radius:14px;color:#fff;font-family:inherit;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:7px;cursor:pointer">
                                    <i class="bi bi-chat-dots"></i>{{ __('personal.message') }}
                                </button>
                            </form>
                        @endif

                        <a href="{{ route('me.challenge.create') }}" class="prof-press"
                           style="height:46px;padding:0 18px;border:1px solid rgba(255,255,255,.30);background:rgba(255,255,255,.16);backdrop-filter:blur(8px);border-radius:14px;color:#fff;font-size:14px;font-weight:700;display:flex;align-items:center;gap:7px"
                           aria-label="{{ __('personal.challenge') }}"><i class="bi bi-lightning-charge-fill"></i>{{ __('personal.challenge') }}</a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Body — rides up over the hero's tail ===== --}}
    <div class="px-4 sm:px-6 lg:px-8" style="margin-top:-52px;position:relative;z-index:2;display:flex;flex-direction:column;gap:22px">

        {{-- Stat row = the tab bar. Every number opens the list behind it. --}}
        <div class="prof-rise" role="tablist" style="background:#fff;border-radius:20px;box-shadow:0 10px 30px rgba(28,16,72,.10);padding:8px;display:grid;grid-template-columns:repeat(4,1fr);gap:6px;animation-delay:.18s">
            @foreach($statTabs as $st)
                <button type="button" role="tab" @click="open('{{ $st['tab'] }}')"
                        :aria-selected="tab === '{{ $st['tab'] }}' ? 'true' : 'false'"
                        :style="'display:flex;align-items:center;justify-content:center;gap:14px;padding:14px 10px;border:0;border-radius:14px;font-family:inherit;cursor:pointer;transition:background .16s ease,opacity .16s ease;' + (tab === '{{ $st['tab'] }}' ? 'background:#f2effe;' : 'background:transparent;opacity:.6;')">
                    <span :style="'width:40px;height:40px;border-radius:12px;display:grid;place-items:center;font-size:17px;flex-shrink:0;' + (tab === '{{ $st['tab'] }}' ? 'background:linear-gradient(150deg,#8f70f6,#6d4bd8);color:#fff' : 'background:#f1f2f7;color:#8a8fa3')"><i class="bi {{ $st['icon'] }}"></i></span>
                    <span style="text-align:start">
                        <span style="display:block;font-size:24px;font-weight:800;line-height:1;letter-spacing:-.02em" :style="{ color: tab === '{{ $st['tab'] }}' ? '#6d4bd8' : '#1c1c28' }">{{ $st['value'] }}</span>
                        <span style="display:block;margin-top:5px;font-size:12px;font-weight:700" :style="{ color: tab === '{{ $st['tab'] }}' ? '#6d4bd8' : '#1c1c28' }">{{ $st['label'] }}</span>
                    </span>
                </button>
            @endforeach
        </div>

        {{-- ===== Competition record ===== --}}
        <div x-show="tab === 'record'" class="prof-fade prof-grid">
            @forelse($competitionBouts as $b)
                @php
                    $initials = collect(preg_split('/\s+/', (string) $b['opponent']))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
                    $tile = $b['opponent_photo'] ?: 'data:image/svg+xml,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="96" height="128"><rect width="96" height="128" fill="#eceaf7"/><text x="48" y="74" text-anchor="middle" font-family="Inter,sans-serif" font-size="32" font-weight="700" fill="#8b8ba7">'.mb_strtoupper($initials).'</text></svg>');
                    $meta = collect([$b['event'], $b['division'], $b['round']])->filter()->implode(' · ');
                @endphp
                <div class="prof-row" style="display:grid;grid-template-columns:96px 1fr;min-height:128px;{{ $card }};overflow:hidden">
                    <span style="position:relative">
                        <span style="position:absolute;inset:0;display:block;background:#f1f2f7 url('{{ $tile }}') center top/cover no-repeat"></span>
                        @if($b['decided'])
                            <span style="position:absolute;bottom:8px;inset-inline-end:-10px;z-index:1;display:grid;place-items:center;width:22px;height:22px;border-radius:50%;border:2px solid #fff;font-size:10px;color:#fff;background:{{ $b['won'] ? '#22c55e' : '#dc2626' }}">
                                <i class="bi {{ $b['won'] ? 'bi-check-lg' : 'bi-x-lg' }}"></i>
                            </span>
                        @endif
                    </span>
                    <div style="flex:1;min-width:0;display:flex;flex-direction:column;justify-content:space-between;gap:12px;padding:14px 14px 13px 16px">
                        <div style="display:flex;align-items:flex-start;gap:12px">
                            <div style="min-width:0;flex:1">
                                <p style="margin:0;font-size:15px;font-weight:700;line-height:1.3">{{ __('personal.vs_opponent', ['name' => $b['opponent']]) }}</p>
                                <p style="margin:4px 0 0;font-size:12px;color:#8a8fa3;line-height:1.4">{{ $meta }}</p>
                            </div>
                            <div style="text-align:end;flex-shrink:0">
                                @if($b['my_score'] !== null || $b['their_score'] !== null)
                                    <p style="margin:0;font-size:17px;font-weight:800;font-variant-numeric:tabular-nums;line-height:1">{{ $b['my_score'] ?? '–' }}–{{ $b['their_score'] ?? '–' }}</p>
                                @endif
                                <span style="display:inline-flex;align-items:center;justify-content:center;margin-top:6px;font-size:10.5px;font-weight:700;padding:3px 9px;border-radius:999px;{{ ! $b['decided'] ? 'background:#fdf4e3;color:#92700f' : ($b['won'] ? 'background:#e7f7ee;color:#15803d' : 'background:#fdecec;color:#b91c1c') }}">
                                    {{ ! $b['decided'] ? __('personal.awaiting_result') : ($b['won'] ? __('personal.challenge_win') : __('personal.challenge_loss')) }}
                                </span>
                            </div>
                        </div>
                        {{-- Only real destinations: an unfilmed bout has no Watch button. --}}
                        @if($b['bout_url'] || $b['video_url'])
                            <div style="display:flex;gap:8px">
                                @if($b['bout_url'])
                                    <a href="{{ $b['bout_url'] }}" class="prof-press" style="box-sizing:border-box;flex:1;height:34px;padding:0 12px;border:1px solid #e4e5ee;background:#fff;color:#5834bd;border-radius:10px;font-size:12.5px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:6px"><i class="bi bi-list-ul"></i>{{ __('personal.match_details') }}</a>
                                @endif
                                @if($b['video_url'])
                                    <a href="{{ $b['video_url'] }}" target="_blank" rel="noopener" class="prof-press" style="box-sizing:border-box;flex:1;height:34px;padding:0 12px;border-radius:10px;background:#6d4bd8;color:#fff;font-size:12.5px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:6px"><i class="bi bi-play-fill" style="font-size:15px"></i>{{ __('personal.watch') }}</a>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="prof-full"><x-people.empty-state icon="bi bi-lightning-charge" :title="__('personal.nothing_here')" :message="__('personal.no_bouts')" /></div>
            @endforelse
        </div>

        {{-- ===== Sports ===== --}}
        <div x-show="tab === 'sports'" x-cloak class="prof-fade prof-grid">
            @forelse($sports as $s)
                @php $competing = $s['competing']; @endphp
                <div class="prof-row" style="display:flex;align-items:stretch;gap:14px;{{ $card }};padding:16px 18px">
                    <span style="flex-shrink:0;display:grid;place-items:center;width:50px;height:50px;border-radius:14px;background:linear-gradient(150deg,#8f70f6,#6d4bd8);color:#fff;font-size:20px"><i class="bi {{ $s['icon'] }}"></i></span>
                    <div style="min-width:0;flex:1">
                        <div style="display:flex;align-items:center;gap:8px">
                            <p style="margin:0;flex:1;min-width:0;font-size:16px;font-weight:800;letter-spacing:-.01em;line-height:1.2">{{ $s['name'] }}</p>
                            <span style="flex-shrink:0;font-size:10.5px;font-weight:700;padding:4px 10px;border-radius:999px;{{ $competing ? 'background:#e7f7ee;color:#15803d' : 'background:#f1f2f7;color:#7a7f94' }}">{{ $competing ? __('personal.sport_competing') : ($s['active'] ? __('personal.sport_registered') : __('personal.sport_past')) }}</span>
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px">
                            <span style="{{ $chipMeta }};color:#5834bd;background:#f2effe"><i class="bi bi-patch-check-fill" style="font-size:11px"></i>{{ $s['grade'] ?: __('personal.no_grading') }}</span>
                            @if($s['experience'])
                                <span style="{{ $chipMeta }};color:#8a8fa3;background:#f8f8fb"><i class="bi bi-hourglass-split" style="font-size:11px"></i>{{ $s['experience'] }}</span>
                            @endif
                            @if($s['bouts'] || $s['events'])
                                <span style="{{ $chipMeta }};color:#1c1c28;background:#f1f2f7"><i class="bi bi-bar-chart-fill" style="font-size:11px"></i>{{ $s['bouts']
                                    ? trans_choice('personal.sport_bouts', $s['bouts'], ['count' => $s['bouts']])
                                    : trans_choice('personal.sport_events', $s['events'], ['count' => $s['events']]) }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="prof-full"><x-people.empty-state icon="bi bi-person-arms-up" :title="__('personal.nothing_here')" :message="__('personal.no_sports')" /></div>
            @endforelse
        </div>

        {{-- ===== Clubs ===== --}}
        <div x-show="tab === 'clubs'" x-cloak class="prof-fade prof-grid">
            @forelse($activeAffil as $i => $a)
                @include('members::people.partials.club-card', ['a' => $a, 'active' => true, 'primary' => $i === 0])
            @empty
                <div class="prof-full"><x-people.empty-state icon="bi bi-buildings" :title="__('personal.nothing_here')" :message="__('personal.no_public_clubs')" /></div>
            @endforelse

            @if($pastAffil->count())
                <p class="prof-full" style="margin:6px 2px 0;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#8a8fa3">{{ __('personal.previous_clubs') }}</p>
                @foreach($pastAffil as $a)
                    @include('members::people.partials.club-card', ['a' => $a, 'active' => false, 'primary' => false])
                @endforeach
            @endif
        </div>

        {{-- ===== Honours — medals, trophies and certifications ===== --}}
        <div x-show="tab === 'honours'" x-cloak class="prof-fade prof-stack-lg">
            <button type="button" x-show="honour !== 'all'" x-cloak @click="honour = 'all'" class="prof-chip"
                    style="align-self:flex-start;height:32px;padding:0 14px;border:1px solid #d8d0f6;background:#f2effe;color:#5834bd;border-radius:999px;font-family:inherit;font-size:12.5px;font-weight:700;cursor:pointer">
                <i class="bi bi-funnel-fill" style="font-size:11px"></i>
                <span x-text="{ gold: @js(__('personal.honours_gold')), silver: @js(__('personal.honours_silver')), bronze: @js(__('personal.honours_bronze')), trophy: @js(__('personal.trophies')), cert: @js(__('personal.certifications')) }[honour]"></span>
                <i class="bi bi-x-lg" style="font-size:11px;opacity:.7"></i>
            </button>

            <div x-show="honour !== 'cert'" class="prof-grid">
                @forelse($honours as $h)
                    @php $tone = $tones[$h['tier']] ?? $tones['trophy']; @endphp
                    <div class="prof-row prof-line" x-show="honour === 'all' || honour === '{{ $h['tier'] }}'"
                         style="{{ $card }};padding:16px;color:#1c1c28">
                        <span style="flex-shrink:0;display:grid;place-items:center;width:38px;height:38px;border-radius:12px;font-size:17px;background:{{ $tone[0] }};color:{{ $tone[1] }}"><i class="bi {{ $h['tier'] === 'trophy' ? 'bi-trophy-fill' : 'bi-award-fill' }}"></i></span>
                        <div style="min-width:0;flex:1">
                            <p style="margin:0;font-size:14px;font-weight:700;line-height:1.3">{{ $h['place'] }}</p>
                            @if($h['event'])
                                <p style="margin:3px 0 0;font-size:12px;color:#8a8fa3;line-height:1.4">{{ $h['event'] }}</p>
                            @endif
                        </div>
                        @if($h['date'])
                            <span style="flex-shrink:0;font-size:12px;font-weight:600;color:#8a8fa3">{{ \Illuminate\Support\Carbon::parse($h['date'])->format('M Y') }}</span>
                        @endif
                    </div>
                @empty
                    <div class="prof-full"><x-people.empty-state icon="bi bi-award" :title="__('personal.nothing_here')" :message="__('personal.no_honours')" /></div>
                @endforelse
            </div>

            <div x-show="honour === 'all' || honour === 'cert'" x-cloak class="prof-grid">
                @foreach($certifications as $c)
                    @php
                        $expired = $c->expiry_date !== null && $c->expiry_date->isPast();
                        // A certificate URL is member-supplied, so only http(s) is ever linked.
                        $link = filter_var((string) $c->credential_url, FILTER_VALIDATE_URL) && \Illuminate\Support\Str::startsWith($c->credential_url, ['http://', 'https://'])
                            ? $c->credential_url : null;
                    @endphp
                    <{{ $link ? 'a' : 'div' }} @if($link) href="{{ $link }}" target="_blank" rel="noopener nofollow" @endif
                        class="prof-row" style="display:flex;align-items:center;gap:12px;{{ $card }};padding:16px;color:#1c1c28">
                        <span style="flex-shrink:0;display:grid;place-items:center;width:38px;height:38px;border-radius:12px;background:#f2effe;color:#6d4bd8;font-size:16px"><i class="bi bi-patch-check"></i></span>
                        <div style="min-width:0;flex:1">
                            <p style="margin:0;font-size:14px;font-weight:700;line-height:1.3">{{ $c->title }}</p>
                            <p style="margin:3px 0 0;font-size:12px;color:#8a8fa3;line-height:1.4">{{ collect([$c->issuer, $c->credential_id])->filter()->implode(' · ') }}</p>
                        </div>
                        <span style="flex-shrink:0;display:flex;align-items:center;gap:7px">
                            @if($c->expiry_date)
                                <span style="flex-shrink:0;font-size:10.5px;font-weight:700;padding:4px 10px;border-radius:999px;{{ $expired ? 'background:#fdecec;color:#b91c1c' : 'background:#e7f7ee;color:#15803d' }}">{{ $expired ? __('personal.certificate_expired') : __('personal.certificate_valid') }}</span>
                            @endif
                            @if($link)<i class="bi bi-box-arrow-up-right" style="font-size:11px;color:#a2a6b8"></i>@endif
                        </span>
                    </{{ $link ? 'a' : 'div' }}>
                @endforeach

                @if($certifications->isEmpty())
                    <div class="prof-full" x-show="honour === 'cert'" x-cloak>
                        <x-people.empty-state icon="bi bi-patch-check" :title="__('personal.nothing_here')" :message="__('personal.no_certifications')" />
                    </div>
                @endif
            </div>

            {{-- Claims still awaiting a peer/coach vouch — shown plainly as
                 unverified and never counted in the tallies above. --}}
            @if($vouchable->count())
                @include('members::people.partials.vouch-list', ['vouchable' => $vouchable, 'canVouch' => $canVouch])
            @endif
        </div>
    </div>
</div>
@endsection
