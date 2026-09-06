{{-- `$shell` is shared ONLY on the sealed event routes (/e/{uuid}/admin/…), so with
     nothing shared this is the member shell exactly as before. See entry/shell. --}}
@extends($shell ?? 'layouts.app')

@section('hide-navbar', true)
@section('title', $person->full_name)

{{--
    Public athlete profile — mobile.

    The whole page is one Alpine scope with four exchangeable panels. The stat
    row IS the tab bar: every headline number on the profile opens the list that
    produced it, so a reader never sees a figure they cannot go behind. The hero
    tally chips are the same idea one level deeper — tapping "gold" opens Honours
    already filtered to gold.

    Styling is deliberately inline and self-contained rather than drawn from the
    shared mobile card tokens: this screen is its own art direction, and pinning
    the exact values here means a later change to the shared tokens cannot
    silently redraw it.
--}}

@php
    // Resolved by the controller: their own profile picture when they have
    // one, otherwise the competition photograph their public entry already
    // shows (App\Events\Support\EntryPhoto::publicFaceFor).
    $avatar = $avatarUrl ?? ($person->profile_picture
        ? file_url($person->profile_picture).'?v='.optional($person->updated_at)->timestamp
        : null);

    $age = $person->birthdate ? \Illuminate\Support\Carbon::parse($person->birthdate)->age : null;

    // "Verified" under the portrait means a club has attested something on this
    // profile — never merely that the account exists.
    $isAttested = $verifiedMedals->isNotEmpty()
        || $activeAffil->contains(fn ($a) => ($a->verification_status ?? null) === 'verified');

    $flag = $countryCode ? mb_strtolower($countryCode) : null;

    $tiers = [
        ['key' => 'gold',   'label' => __('personal.honour_gold'),      'count' => $honourTally['gold'],   'ring' => '#e0a300', 'face' => '#ffcb3d', 'ink' => '#7a5200', 'ribbon' => '#5b9bd5', 'ribbonDark' => '#3f7fb8', 'num' => 1],
        ['key' => 'silver', 'label' => __('personal.honour_silver'),    'count' => $honourTally['silver'], 'ring' => '#9aa4b4', 'face' => '#dfe5ec', 'ink' => '#4d5666', 'ribbon' => '#7f8b9c', 'ribbonDark' => '#67717f', 'num' => 2],
        ['key' => 'bronze', 'label' => __('personal.honour_bronze'),    'count' => $honourTally['bronze'], 'ring' => '#a9662f', 'face' => '#e08d4d', 'ink' => '#5f3413', 'ribbon' => '#c07a3c', 'ribbonDark' => '#9c5e2a', 'num' => 3],
    ];

    $tones = [
        'gold'   => ['#fff5da', '#b58500'],
        'silver' => ['#f2f4f7', '#7d8794'],
        'bronze' => ['#fdefe4', '#a4693a'],
        'trophy' => ['#f2effe', '#6d4bd8'],
    ];

    $glassBtn = 'width:38px;height:38px;border:1px solid rgba(255,255,255,.28);background:rgba(255,255,255,.16);backdrop-filter:blur(8px);border-radius:50%;color:#fff;display:grid;place-items:center;cursor:pointer;transition:transform .16s cubic-bezier(.22,.61,.36,1),background .16s ease';
    $card = 'background:#fff;border-radius:18px;box-shadow:0 6px 20px rgba(28,16,72,.07)';
    $chipMeta = 'display:flex;align-items:center;gap:5px;font-size:11.5px;font-weight:700;border-radius:8px;padding:5px 9px';
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
    /* Panels toggled by x-show take their display from a class, never from an inline
       style: Alpine shows an element by writing style.display = '', which would erase
       an inline display:flex and collapse the layout. */
    .prof-stack { display: flex; flex-direction: column; gap: 10px; }
    .prof-stack-lg { display: flex; flex-direction: column; gap: 12px; }
    .prof-line { display: flex; align-items: center; gap: 12px; }
    .prof-chip { display: flex; align-items: center; gap: 6px; }
    .prof-col { display: flex; flex-direction: column; }
    @media (prefers-reduced-motion: reduce) {
        .prof-page *, .prof-page *::before, .prof-page *::after { animation: none !important; transition: none !important; }
    }
</style>

{{-- Full width, no phone-canvas cap. It used to be max-width:430px centred,
     which is invisible on a 412px phone but shows as grey margins the moment the
     viewport is wider — a large phone, landscape, a foldable, or a tablet that
     lands on the mobile view. The layout inside is fluid, so it simply fills. --}}
{{-- ⚠️ Full-bleed on BOTH paths, and the width is part of the cancellation.

     On the member path this page fills `content` on layouts.app, whose <main>
     has no padding, so `width:100%` is already edge to edge. Inside the sealed
     event shell (/e/{uuid}/admin/person/{uuid}) it lands in a `px-4 py-4`
     wrapper and the whole grey canvas sat inset 16px on three sides, with the
     purple cover band stopping short of the edges.

     `-mx-4` alone does NOT fix it: the inline `width:100%` resolves against the
     wrapper's CONTENT box, so the box would shift left and leave a 32px strip
     on the right — and an inline width beats a utility class. So the width goes
     to `auto` when the margins are cancelled, and the box fills what it is
     given. --}}
<div class="prof-page {{ isset($shell) ? '-mx-4 -mt-4' : '' }}"
     style="width:{{ isset($shell) ? 'auto' : '100%' }};background:#f4f5f9;min-height:100vh;padding-bottom:28px;color:#1c1c28"
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

    {{-- ===== Portrait lightbox — teleported so the mobile shell's transformed
         wrapper cannot become its containing block and clip it. ===== --}}
    @if($avatar)
        <template x-teleport="body">
            <div x-show="photos" x-cloak x-transition.opacity.duration.180ms
                 @keydown.escape.window="photos = false"
                 class="prof-col" style="position:fixed;inset:0;z-index:70;background:rgba(9,5,22,.94)">
                <div style="display:flex;align-items:center;justify-content:flex-end;padding:calc(env(safe-area-inset-top) + 12px) 14px 10px">
                    <button type="button" @click="photos = false" class="prof-press"
                            style="width:40px;height:40px;border:0;background:rgba(255,255,255,.14);border-radius:50%;color:#fff;font-size:16px;display:grid;place-items:center;cursor:pointer;transition:transform .16s cubic-bezier(.22,.61,.36,1)"
                            aria-label="{{ __('shared.close') }}"><i class="bi bi-x-lg"></i></button>
                </div>
                <div @click="photos = false" style="flex:1;min-height:0;overflow:hidden;padding:0 12px 30px">
                    <div style="width:100%;height:100%;border-radius:14px;background:url('{{ $avatar }}') center/contain no-repeat"></div>
                </div>
            </div>
        </template>
    @endif

    {{-- ===== Hero ===== --}}
    <div style="position:relative;background:linear-gradient(165deg,#8f70f6 0%,#6d4bd8 58%,#5834bd 100%);padding:calc(env(safe-area-inset-top) + 14px) 20px 68px;color:#fff;overflow:hidden">
        <div style="position:absolute;top:-90px;right:-70px;width:240px;height:240px;border-radius:50%;background:rgba(255,255,255,.10);animation:profDrift 14s ease-in-out infinite"></div>
        <div style="position:absolute;bottom:-120px;left:-60px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.07);animation:profDrift 18s ease-in-out infinite reverse"></div>

        <div style="position:relative;display:flex;align-items:center;justify-content:space-between">
            {{-- ⚠️ An ADDRESS, not `history.back()`.

                 This page renders in two places, and the gesture was wrong in
                 both: on the platform an empty history sent it to /me/people,
                 and inside the SEALED event app that same fallback walked the
                 organiser straight out of the white-label surface onto the
                 platform's Find People screen — a product they were never told
                 they were in, and a hole in the seal (the rewriter only
                 touches href attributes, never an onclick). --}}
            @php
                /* Inside a sealed event the controller decides, because only
                   it knows where the reader came from (`?from=`) and whether
                   they may open the organiser's list at all. This used to
                   hardcode /admin/people and drop everybody there. */
                $backHref = $sealedBack
                    ?? (isset($shell)
                        ? url('/e/'.request()->route('event')?->uuid.'/admin/people')
                        /* The page they actually came from, validated same-origin
                           by the controller, falling back to Find People when the
                           browser sent nothing. */
                        : ($platformBack ?? route('me.people')));
            @endphp
            <a href="{{ $backHref }}" class="prof-press"
               style="{{ $glassBtn }};font-size:17px;text-decoration:none" aria-label="{{ __('shared.back') }}"><i class="bi bi-chevron-left"></i></a>
            <button type="button" class="prof-press" @click="share()"
                    style="{{ $glassBtn }};font-size:15px" aria-label="{{ __('shared.share') }}"><i class="bi bi-share"></i></button>
        </div>

        <div class="prof-rise" style="position:relative;display:flex;gap:16px;align-items:stretch;margin-top:18px">
            <div style="position:relative;flex-shrink:0">
                {{-- The silhouette is the BASE layer and the photograph sits on
                     top of it, so a picture that does not load leaves a face
                     rather than the browser's broken-image glyph. `/file/…`
                     answers 404 both when the bytes are missing and when this
                     viewer may not read them (their `profile_picture_is_public`
                     is theirs to decide — App\Support\FileAccess), and neither
                     is a torn icon to show a reader. --}}
                <span style="position:relative;display:block;width:104px;height:128px;border-radius:18px;overflow:hidden;box-shadow:0 12px 28px rgba(20,10,60,.32);outline:3px solid rgba(255,255,255,.30);animation:profPop .55s cubic-bezier(.22,.61,.36,1) both">
                    <x-gender-avatar :gender="$person->gender" class="w-full h-full" />
                    @if($avatar)
                        <img @click="photos = true" src="{{ $avatar }}" alt="{{ $person->full_name }}"
                             onerror="this.remove()"
                             style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;cursor:zoom-in;display:block">
                    @endif
                </span>

                @if($isAttested)
                    <span style="position:absolute;bottom:-10px;left:50%;transform:translateX(-50%);display:flex;align-items:center;gap:4px;background:#fff;color:#5834bd;font-size:10px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;padding:4px 9px;border-radius:999px;box-shadow:0 4px 12px rgba(20,10,60,.22);white-space:nowrap">
                        <i class="bi bi-patch-check-fill" style="font-size:11px"></i>{{ __('personal.verified_badge') }}
                    </span>
                @endif
            </div>

            <div style="min-width:0;flex:1;display:flex;flex-direction:column;justify-content:space-between;gap:6px;padding:2px 0 4px">
                <p style="margin:0;display:flex;align-items:center;gap:8px;font-size:13px;line-height:1.2;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.78);white-space:nowrap">
                    @if($flag)
                        <span class="fi fi-{{ $flag }}" style="flex-shrink:0;width:22px;height:16px;border-radius:3px;background-size:cover;box-shadow:0 0 0 1px rgba(255,255,255,.35)"></span>
                    @endif
                    {{ $person->is_personal_trainer ? __('personal.people_trainer') : __('personal.profile_eyebrow') }}
                </p>

                <div style="display:flex;align-items:center;gap:8px;min-width:0">
                    @if($person->gender === 'Female' || $person->gender === 'Male')
                        @php $isF = $person->gender === 'Female'; @endphp
                        <svg viewBox="4 -3 26 52" width="12.5" height="25" fill="none" style="flex-shrink:0;display:block;overflow:visible" aria-label="{{ $person->gender }}">
                            <defs><linearGradient id="gGender{{ $person->id }}" x1="4" y1="2" x2="30" y2="44" gradientUnits="userSpaceOnUse">
                                <stop offset="0" stop-color="{{ $isF ? '#ff2f9b' : '#2f8bff' }}"></stop>
                                <stop offset="1" stop-color="{{ $isF ? '#ff9ecd' : '#9ecdff' }}"></stop>
                            </linearGradient></defs>
                            <g stroke="url(#gGender{{ $person->id }})" stroke-width="7" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="17" cy="{{ $isF ? '14.5' : '30' }}" r="9.5"></circle>
                                @if($isF)
                                    <path d="M17 24v19"></path><path d="M8.5 34.5h17"></path>
                                @else
                                    <path d="M24 23L31 16"></path><path d="M23 14h9v9"></path>
                                @endif
                            </g>
                        </svg>
                    @endif
                    <h1 style="margin:0;font-size:21px;line-height:1.15;font-weight:800;letter-spacing:-.01em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $person->full_name }}</h1>
                </div>

                {{-- Tally chips: a shortcut into Honours, already filtered. --}}
                <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:5px">
                    @foreach($tiers as $t)
                        <button type="button" @click="open('honours', '{{ $t['key'] }}')"
                                :style="'box-sizing:border-box;display:flex;align-items:center;justify-content:center;gap:4px;height:28px;font-family:inherit;font-size:13px;font-weight:800;border-radius:9px;cursor:pointer;transition:background .16s,border-color .16s,transform .16s cubic-bezier(.22,.61,.36,1);color:#fff;' + (tab === 'honours' && honour === '{{ $t['key'] }}' ? 'transform:translateY(-1px);background:rgba(255,255,255,.34);border:1px solid rgba(255,255,255,.55);' : 'background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.18);')"
                                aria-label="{{ $t['label'] }}">
                            <span style="position:relative;flex-shrink:0;width:16px;height:16px;display:block">
                                <svg viewBox="0 0 24 24" width="16" height="16" style="display:block">
                                    <path d="M6 1.5h4l4 8H10z" fill="{{ $t['ribbon'] }}"></path>
                                    <path d="M18 1.5h-4l-4 8h4z" fill="{{ $t['ribbonDark'] }}"></path>
                                    <circle cx="12" cy="15.6" r="6.9" fill="{{ $t['ring'] }}"></circle>
                                    <circle cx="12" cy="15.6" r="5.1" fill="{{ $t['face'] }}"></circle>
                                </svg>
                                <span style="position:absolute;left:0;right:0;top:6.2px;text-align:center;font-size:8px;font-weight:800;line-height:1;color:{{ $t['ink'] }}">{{ $t['num'] }}</span>
                            </span>
                            {{ $t['count'] }}
                        </button>
                    @endforeach
                    <button type="button" @click="open('honours', 'trophy')"
                            :style="'box-sizing:border-box;display:flex;align-items:center;justify-content:center;gap:4px;height:28px;font-family:inherit;font-size:13px;font-weight:800;border-radius:9px;cursor:pointer;transition:background .16s,border-color .16s,transform .16s cubic-bezier(.22,.61,.36,1);color:#ffd76a;' + (tab === 'honours' && honour === 'trophy' ? 'transform:translateY(-1px);background:rgba(255,255,255,.34);border:1px solid rgba(255,255,255,.55);' : 'background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.18);')"
                            aria-label="{{ __('personal.trophies') }}">
                        <i class="bi bi-trophy-fill" style="font-size:14px"></i>{{ $honourTally['trophy'] }}
                    </button>
                    <button type="button" @click="open('honours', 'cert')"
                            :style="'box-sizing:border-box;display:flex;align-items:center;justify-content:center;gap:4px;height:28px;font-family:inherit;font-size:13px;font-weight:800;border-radius:9px;cursor:pointer;transition:background .16s,border-color .16s,transform .16s cubic-bezier(.22,.61,.36,1);color:#9be7c4;' + (tab === 'honours' && honour === 'cert' ? 'transform:translateY(-1px);background:rgba(255,255,255,.34);border:1px solid rgba(255,255,255,.55);' : 'background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.18);')"
                            aria-label="{{ __('personal.certifications') }}">
                        <i class="bi bi-patch-check-fill" style="font-size:14px"></i>{{ $certifications->count() }}
                    </button>
                </div>

                <p style="margin:0;display:flex;align-items:center;gap:6px;font-size:13.5px;font-weight:600;white-space:nowrap;color:rgba(255,255,255,.85)">
                    @if($age)
                        <span>{{ __('personal.years_old', ['count' => $age]) }}</span><span style="opacity:.55">·</span>
                    @endif
                    <span style="display:flex;align-items:center;gap:5px;font-weight:500;color:rgba(255,255,255,.75)">
                        <i class="bi bi-calendar3" style="font-size:12px"></i>{{ __('personal.member_since') }} {{ optional($person->created_at)->format('M Y') }}
                    </span>
                </p>
            </div>
        </div>

        {{-- ===== Actions ===== --}}
        <div class="prof-rise" style="position:relative;display:flex;gap:8px;margin-top:20px;animation-delay:.12s">
            @if($isGuest ?? false)
                {{-- A guest is offered sign-in, never a Follow button that would fail the moment they pressed it. --}}
                <a href="{{ route('login') }}" class="prof-press"
                   style="flex:1;height:46px;border-radius:14px;background:#fff;border:1px solid #fff;color:#5834bd;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:7px;box-shadow:0 6px 16px rgba(20,10,60,.18);transition:transform .16s cubic-bezier(.22,.61,.36,1)">
                    <i class="bi bi-box-arrow-in-right"></i>{{ __('personal.sign_in_to_connect') }}
                </a>
            @else
                <button type="button" class="prof-press" @click="toggleFollow()"
                        :style="'flex:1.15;height:46px;border-radius:14px;font-family:inherit;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:7px;cursor:pointer;transition:transform .16s cubic-bezier(.22,.61,.36,1),background .18s ease;' + (following ? 'background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.30);color:#fff;' : 'background:#fff;border:1px solid #fff;color:#5834bd;box-shadow:0 6px 16px rgba(20,10,60,.18);')">
                    <i :class="following ? 'bi bi-check2' : 'bi bi-plus-lg'"></i>
                    <span x-text="following ? @js(__('personal.following')) : @js(__('personal.follow'))"></span>
                </button>

                @if($canMessage)
                    <form method="POST" action="{{ route('messages.start', $person) }}" style="flex:1;display:flex">
                        @csrf
                        <button type="submit" class="prof-press"
                                style="flex:1;height:46px;border:1px solid rgba(255,255,255,.30);background:rgba(255,255,255,.16);backdrop-filter:blur(8px);border-radius:14px;color:#fff;font-family:inherit;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:7px;cursor:pointer;transition:transform .16s cubic-bezier(.22,.61,.36,1),background .16s ease">
                            <i class="bi bi-chat-dots"></i>{{ __('personal.message') }}
                        </button>
                    </form>
                @endif

                <a href="{{ route('me.challenge.create') }}" class="prof-press"
                   style="width:46px;height:46px;border:1px solid rgba(255,255,255,.30);background:rgba(255,255,255,.16);backdrop-filter:blur(8px);border-radius:14px;color:#fff;font-size:17px;display:grid;place-items:center;transition:transform .16s cubic-bezier(.22,.61,.36,1),background .16s ease"
                   aria-label="{{ __('personal.challenge') }}"><i class="bi bi-lightning-charge-fill"></i></a>
            @endif
        </div>
    </div>

    {{-- ===== Body — rides up over the hero's tail ===== --}}
    <div style="padding:0 16px;margin-top:-46px;position:relative;z-index:2;display:flex;flex-direction:column;gap:20px">

        {{-- Stat row = the tab bar. Every number opens the list behind it. --}}
        @php
            // Reading order: what they did, then what they do it in, then where,
            // then what it won them.
            $statTabs = [
                ['tab' => 'record',  'value' => $competition['fought'],                     'label' => __('personal.record')],
                ['tab' => 'sports',  'value' => $sports->count(),                          'label' => __('personal.sports')],
                ['tab' => 'clubs',   'value' => $activeAffil->count(),                      'label' => __('personal.clubs')],
                ['tab' => 'honours', 'value' => $honours->count() + $certifications->count(), 'label' => __('personal.honours')],
            ];
        @endphp
        <div class="prof-rise" style="background:#fff;border-radius:20px;box-shadow:0 10px 30px rgba(28,16,72,.10);padding:16px 8px;display:grid;grid-template-columns:repeat(4,1fr);animation-delay:.18s">
            @foreach($statTabs as $i => $st)
                <button type="button" @click="open('{{ $st['tab'] }}')"
                        :style="'text-align:center;padding:2px 4px;border:0;background:none;font-family:inherit;cursor:pointer;position:relative;transition:opacity .16s ease;{{ $i ? 'border-inline-start:1px solid #ecedf3;' : '' }}' + (tab === '{{ $st['tab'] }}' ? '' : 'opacity:.55;')">
                    <p style="margin:0;font-size:22px;font-weight:800;line-height:1;letter-spacing:-.02em" :style="{ color: tab === '{{ $st['tab'] }}' ? '#6d4bd8' : '#1c1c28' }">{{ $st['value'] }}</p>
                    <p style="margin:6px 0 0;font-size:10.5px;font-weight:700" :style="{ color: tab === '{{ $st['tab'] }}' ? '#6d4bd8' : '#1c1c28' }">{{ $st['label'] }}</p>
                </button>
            @endforeach
        </div>

        {{-- ===== Sports ===== --}}
        <div x-show="tab === 'sports'" x-cloak class="prof-fade prof-stack">
            @forelse($sports as $s)
                @php
                    // Competing outranks enrolled: being entered into a championship
                    // is the stronger statement, so it wins the tag.
                    $competing = $s['competing'];
                @endphp
                <div class="prof-row" style="display:flex;align-items:stretch;gap:14px;{{ $card }};padding:15px 16px">
                    <span style="flex-shrink:0;display:grid;place-items:center;width:46px;height:46px;border-radius:14px;background:linear-gradient(150deg,#8f70f6,#6d4bd8);color:#fff;font-size:19px"><i class="bi {{ $s['icon'] }}"></i></span>
                    <div style="min-width:0;flex:1">
                        <div style="display:flex;align-items:center;gap:8px">
                            <p style="margin:0;flex:1;min-width:0;font-size:16px;font-weight:800;letter-spacing:-.01em;line-height:1.15;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $s['name'] }}</p>
                            <span style="flex-shrink:0;font-size:10px;font-weight:700;padding:4px 9px;border-radius:999px;{{ $competing ? 'background:#e7f7ee;color:#15803d' : 'background:#f1f2f7;color:#7a7f94' }}">{{ $competing ? __('personal.sport_competing') : ($s['active'] ? __('personal.sport_registered') : __('personal.sport_past')) }}</span>
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px">
                            {{-- Grade: only a club-attested level is stated as one; a
                                 self-typed belt is a claim, so it reads "No grading". --}}
                            <span style="{{ $chipMeta }};color:#5834bd;background:#f2effe"><i class="bi bi-patch-check-fill" style="font-size:11px"></i>{{ $s['grade'] ?: __('personal.no_grading') }}</span>

                            {{-- Time in the sport: the union of every enrolment spell and
                                 the first event entered, so overlaps count once. --}}
                            @if($s['experience'])
                                <span style="{{ $chipMeta }};color:#8a8fa3;background:#f8f8fb"><i class="bi bi-hourglass-split" style="font-size:11px"></i>{{ $s['experience'] }}</span>
                            @endif

                            {{-- The slot the draft used for a national rank. There is no
                                 ranking system to read, so it carries the real figure off
                                 the draw instead of an invented position. --}}
                            @if($s['bouts'] || $s['events'])
                                <span style="{{ $chipMeta }};color:#1c1c28;background:#f1f2f7"><i class="bi bi-bar-chart-fill" style="font-size:11px"></i>{{ $s['bouts']
                                    ? trans_choice('personal.sport_bouts', $s['bouts'], ['count' => $s['bouts']])
                                    : trans_choice('personal.sport_events', $s['events'], ['count' => $s['events']]) }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <x-people.empty-state icon="bi bi-person-arms-up" :title="__('personal.nothing_here')" :message="__('personal.no_sports')" />
            @endforelse
        </div>

        {{-- ===== Competition record ===== --}}
        <div x-show="tab === 'record'" class="prof-fade prof-stack-lg">
            @forelse($competitionBouts as $b)
                @php
                    $initials = collect(preg_split('/\s+/', (string) $b['opponent']))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
                    // The opponent's own face when they have opted into showing it,
                    // and their initials when they have not.
                    $tile = $b['opponent_photo'] ?: 'data:image/svg+xml,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="88" height="88"><rect width="88" height="88" fill="#eceaf7"/><text x="44" y="56" text-anchor="middle" font-family="Inter,sans-serif" font-size="32" font-weight="700" fill="#8b8ba7">'.mb_strtoupper($initials).'</text></svg>');
                    $meta = collect([$b['event'], $b['division'], $b['round']])->filter()->implode(' · ');
                @endphp
                <div class="prof-row" style="display:grid;grid-template-columns:88px 1fr;min-height:117px;{{ $card }};overflow:hidden">
                    <span style="position:relative">
                        <span style="position:absolute;inset:0;display:block;background:#f1f2f7 url('{{ $tile }}') center/cover no-repeat"></span>
                        @if($b['decided'])
                            <span style="position:absolute;bottom:8px;inset-inline-end:-9px;z-index:1;display:grid;place-items:center;width:20px;height:20px;border-radius:50%;border:2px solid #fff;font-size:10px;color:#fff;background:{{ $b['won'] ? '#22c55e' : '#dc2626' }}">
                                <i class="bi {{ $b['won'] ? 'bi-check-lg' : 'bi-x-lg' }}"></i>
                            </span>
                        @endif
                    </span>
                    <div style="flex:1;min-width:0;display:flex;flex-direction:column;justify-content:space-between;gap:11px;padding:12px 12px 11px 13px">
                        <div style="display:flex;align-items:flex-start;gap:10px">
                            <div style="min-width:0;flex:1">
                                <p style="margin:0;font-size:14px;font-weight:700;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ __('personal.vs_opponent', ['name' => $b['opponent']]) }}</p>
                                <p style="margin:3px 0 0;font-size:11px;color:#8a8fa3;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">{{ $meta }}</p>
                            </div>
                            <div style="text-align:end;flex-shrink:0">
                                @if($b['my_score'] !== null || $b['their_score'] !== null)
                                    <p style="margin:0;font-size:15px;font-weight:800;font-variant-numeric:tabular-nums;line-height:1">{{ $b['my_score'] ?? '–' }}–{{ $b['their_score'] ?? '–' }}</p>
                                @endif
                                <span style="display:inline-flex;align-items:center;justify-content:center;margin-top:5px;font-size:10px;font-weight:700;padding:3px 8px;border-radius:999px;{{ ! $b['decided'] ? 'background:#fdf4e3;color:#92700f' : ($b['won'] ? 'background:#e7f7ee;color:#15803d' : 'background:#fdecec;color:#b91c1c') }}">
                                    {{ ! $b['decided'] ? __('personal.awaiting_result') : ($b['won'] ? __('personal.challenge_win') : __('personal.challenge_loss')) }}
                                </span>
                            </div>
                        </div>

                        {{-- Only real destinations are rendered: an unfilmed bout has no
                             Watch button rather than a dead one. --}}
                        @if($b['bout_url'] || $b['video_url'])
                            <div style="display:flex;gap:6px">
                                @if($b['bout_url'])
                                    <a href="{{ $b['bout_url'] }}" class="prof-press" style="box-sizing:border-box;flex:1;height:32px;padding:0 10px;border:1px solid #e4e5ee;background:#fff;color:#5834bd;border-radius:10px;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:6px;transition:transform .16s ease"><i class="bi bi-list-ul"></i>{{ __('personal.match_details') }}</a>
                                @endif
                                @if($b['video_url'])
                                    <a href="{{ $b['video_url'] }}" target="_blank" rel="noopener" class="prof-press" style="box-sizing:border-box;flex:1;height:32px;padding:0 10px;border-radius:10px;background:#6d4bd8;color:#fff;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:6px;transition:transform .16s ease"><i class="bi bi-play-fill" style="font-size:15px"></i>{{ __('personal.watch') }}</a>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <x-people.empty-state icon="bi bi-lightning-charge" :title="__('personal.nothing_here')" :message="__('personal.no_bouts')" />
            @endforelse
        </div>

        {{-- ===== Clubs ===== --}}
        <div x-show="tab === 'clubs'" x-cloak class="prof-fade prof-stack">
            @forelse($activeAffil as $i => $a)
                @include('members::people.partials.club-card', ['a' => $a, 'active' => true, 'primary' => $i === 0])
            @empty
                <x-people.empty-state icon="bi bi-buildings" :title="__('personal.nothing_here')" :message="__('personal.no_public_clubs')" />
            @endforelse

            @if($pastAffil->count())
                <p style="margin:6px 2px 0;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#8a8fa3">{{ __('personal.previous_clubs') }}</p>
                @foreach($pastAffil as $a)
                    @include('members::people.partials.club-card', ['a' => $a, 'active' => false, 'primary' => false])
                @endforeach
            @endif
        </div>

        {{-- ===== Honours — medals, trophies and certifications ===== --}}
        <div x-show="tab === 'honours'" x-cloak class="prof-fade prof-stack-lg">
            {{-- The filter chip only appears once a hero tally narrowed the list,
                 and clearing it is the way back to everything. --}}
            <button type="button" x-show="honour !== 'all'" x-cloak @click="honour = 'all'" class="prof-chip"
                    style="align-self:flex-start;height:30px;padding:0 12px;border:1px solid #d8d0f6;background:#f2effe;color:#5834bd;border-radius:999px;font-family:inherit;font-size:12px;font-weight:700;cursor:pointer">
                <i class="bi bi-funnel-fill" style="font-size:11px"></i>
                <span x-text="{ gold: @js(__('personal.honours_gold')), silver: @js(__('personal.honours_silver')), bronze: @js(__('personal.honours_bronze')), trophy: @js(__('personal.trophies')), cert: @js(__('personal.certifications')) }[honour]"></span>
                <i class="bi bi-x-lg" style="font-size:11px;opacity:.7"></i>
            </button>

            <div x-show="honour !== 'cert'" class="prof-stack">
                @forelse($honours as $h)
                    @php $tone = $tones[$h['tier']] ?? $tones['trophy']; @endphp
                    <div class="prof-row prof-line" x-show="honour === 'all' || honour === '{{ $h['tier'] }}'"
                         style="{{ $card }};padding:14px;color:#1c1c28">
                        <span style="flex-shrink:0;display:grid;place-items:center;width:34px;height:34px;border-radius:11px;font-size:16px;background:{{ $tone[0] }};color:{{ $tone[1] }}"><i class="bi {{ $h['tier'] === 'trophy' ? 'bi-trophy-fill' : 'bi-award-fill' }}"></i></span>
                        <div style="min-width:0;flex:1">
                            <p style="margin:0;font-size:13.5px;font-weight:700;line-height:1.25">{{ $h['place'] }}</p>
                            @if($h['event'])
                                <p style="margin:3px 0 0;font-size:11px;color:#8a8fa3;line-height:1.35">{{ $h['event'] }}</p>
                            @endif
                        </div>
                        @if($h['date'])
                            <span style="flex-shrink:0;font-size:11px;font-weight:600;color:#8a8fa3">{{ \Illuminate\Support\Carbon::parse($h['date'])->format('M Y') }}</span>
                        @endif
                    </div>
                @empty
                    <x-people.empty-state icon="bi bi-award" :title="__('personal.nothing_here')" :message="__('personal.no_honours')" />
                @endforelse
            </div>

            <div x-show="honour === 'all' || honour === 'cert'" x-cloak class="prof-stack">
                @foreach($certifications as $c)
                    @php
                        $expired = $c->expiry_date !== null && $c->expiry_date->isPast();
                        // A certificate URL is member-supplied, so only http(s) is ever linked.
                        $link = filter_var((string) $c->credential_url, FILTER_VALIDATE_URL) && \Illuminate\Support\Str::startsWith($c->credential_url, ['http://', 'https://'])
                            ? $c->credential_url : null;
                    @endphp
                    <{{ $link ? 'a' : 'div' }} @if($link) href="{{ $link }}" target="_blank" rel="noopener nofollow" @endif
                        class="prof-row" style="display:flex;align-items:center;gap:12px;{{ $card }};padding:14px;color:#1c1c28">
                        <span style="flex-shrink:0;display:grid;place-items:center;width:34px;height:34px;border-radius:11px;background:#f2effe;color:#6d4bd8;font-size:15px"><i class="bi bi-patch-check"></i></span>
                        <div style="min-width:0;flex:1">
                            <p style="margin:0;font-size:13.5px;font-weight:700;line-height:1.25">{{ $c->title }}</p>
                            <p style="margin:3px 0 0;font-size:11px;color:#8a8fa3;line-height:1.35">{{ collect([$c->issuer, $c->credential_id])->filter()->implode(' · ') }}</p>
                        </div>
                        <span style="flex-shrink:0;display:flex;align-items:center;gap:7px">
                            @if($c->expiry_date)
                                <span style="flex-shrink:0;font-size:10px;font-weight:700;padding:4px 9px;border-radius:999px;{{ $expired ? 'background:#fdecec;color:#b91c1c' : 'background:#e7f7ee;color:#15803d' }}">{{ $expired ? __('personal.certificate_expired') : __('personal.certificate_valid') }}</span>
                            @endif
                            @if($link)<i class="bi bi-box-arrow-up-right" style="font-size:11px;color:#a2a6b8"></i>@endif
                        </span>
                    </{{ $link ? 'a' : 'div' }}>
                @endforeach

                @if($certifications->isEmpty())
                    <div x-show="honour === 'cert'" x-cloak>
                        <x-people.empty-state icon="bi bi-patch-check" :title="__('personal.nothing_here')" :message="__('personal.no_certifications')" />
                    </div>
                @endif
            </div>

            {{-- Claims still awaiting a peer/coach vouch — shown plainly as unverified
                 and never counted in the tallies above. --}}
            @if($vouchable->count())
                @include('members::people.partials.vouch-list', ['vouchable' => $vouchable, 'canVouch' => $canVouch])
            @endif
        </div>
    </div>
</div>
@endsection
