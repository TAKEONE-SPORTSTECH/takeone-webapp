{{--
    The athlete's claim page — completing an entry somebody else committed.
    Spec: Documentation/EVENTS-PUBLIC-ENTRY.md (Door B, "What the athlete sees").

    Standalone document on purpose: the real page is opened by someone with no
    account, from a link in a WhatsApp message. It has no app shell, no bottom
    nav and no back button into a site they have never seen — the event IS the
    page. (Same reasoning as the bout-review screens.)

    Choreography, in order:
      1. the event's picture lands first and settles out of a slow push-in;
      2. its identity rises out of the bottom — chips, title, host;
      3. the "you have been entered" card and the CTA follow;
      4. every step after that keeps the picture alive behind glass — it pushes
         back, blurs and dims as the sheet rises, and returns on the last screen.
    Every step's fields stagger in through the shared `mobile-stagger` vocabulary,
    and travel with the direction of the move (forward from the end, back from
    the start), so the flow never feels like a page swap.

    UNAUTHENTICATED. The only thing standing between this page and a stranger is
    the claim token in the URL — bound to ONE entry, single-use, expiring — so it
    shows the event and the one name it is for, and nothing else about anybody.
    Served by EntryClaimController; every rule lives in App\Events\Support\EntryClaim.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ config('locales.' . app()->getLocale() . '.dir', 'ltr') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- The page's own ground, not the event's accent: this page is dark
         (html, body { background: #0b0b12 }), so tinting the phone's status bar
         in the accent put a band of colour above a black screen. --}}
    <meta name="theme-color" content="#0b0b12">
    <title>{{ $ev['title'] }} — {{ __('events.claim_page_title') }}</title>
    {{-- The tab wears the club's mark. Nothing about a link sent in a WhatsApp
         message should announce a platform the sender never mentioned. --}}
    @if($ev['club_logo'])<link rel="icon" href="{{ $ev['club_logo'] }}">@endif
    <meta name="robots" content="noindex, nofollow">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important}</style>
    @vite(['resources/css/app.css'])

    <style>
        :root { --ev: {{ $ev['color'] }}; }
        html, body { background: #0b0b12; }
        body { font-family: 'Inter', sans-serif; overscroll-behavior-y: none; }

        /* ---- The picture layer -------------------------------------------------
           One element, animated once on load and then only transformed as the
           step changes, so the photo never re-decodes mid-flow.               */
        .e-photo {
            position: fixed; inset: 0;
            background-size: cover; background-position: center;
            transform-origin: 50% 42%;
            animation: e-land 1500ms cubic-bezier(.16,.84,.34,1) both;
            transition: transform 900ms cubic-bezier(.22,.61,.36,1),
                        filter    900ms cubic-bezier(.22,.61,.36,1);
            will-change: transform, filter;
        }
        @keyframes e-land {
            0%   { opacity: 0; transform: scale(1.28); filter: blur(14px) saturate(.6); }
            55%  { opacity: 1; }
            100% { opacity: 1; transform: scale(1.06); filter: blur(0) saturate(1); }
        }
        /* Pushed back once the form is up — still alive, no longer the subject. */
        .e-photo.is-back { transform: scale(1.16) translateY(-2%); filter: blur(11px) saturate(.85) brightness(.55); }

        /* Event-coloured wash + the floor the content stands on. */
        .e-wash {
            position: fixed; inset: 0; pointer-events: none;
            background:
                radial-gradient(120% 80% at 50% 0%,  {{ $ev['color'] }}8c 0%, transparent 62%),
                linear-gradient(180deg, rgba(6,6,14,.10) 0%, rgba(6,6,14,.55) 46%, rgba(6,6,14,.94) 82%, #06060e 100%);
            transition: opacity 700ms ease;
        }

        /* ---- Step travel ------------------------------------------------------- */
        @keyframes e-inR { from { opacity:0; transform: translate3d(26px,0,0) } to { opacity:1; transform:none } }
        @keyframes e-inL { from { opacity:0; transform: translate3d(-26px,0,0) } to { opacity:1; transform:none } }
        .e-inR { animation: e-inR .42s cubic-bezier(.22,.61,.36,1) both }
        .e-inL { animation: e-inL .42s cubic-bezier(.22,.61,.36,1) both }

        /* The sheet itself rises once, then holds; only its contents travel. */
        @keyframes e-sheet { from { opacity:0; transform: translate3d(0,42px,0) } to { opacity:1; transform:none } }
        .e-sheet { animation: e-sheet .5s cubic-bezier(.16,.84,.34,1) both; }

        /* Progress segments fill in the event's colour. */
        .e-seg { background: rgba(255,255,255,.22); }
        .e-seg > i {
            display:block; height:100%; width:0; border-radius:inherit; background: var(--ev);
            transition: width .55s cubic-bezier(.22,.61,.36,1);
        }
        .e-seg.is-done > i, .e-seg.is-now > i { width:100%; }

        /* Frosted field surface — reads on a photo, unlike a flat white card. */
        .e-glass {
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.14);
            backdrop-filter: blur(14px) saturate(1.2);
            -webkit-backdrop-filter: blur(14px) saturate(1.2);
        }
        .e-field {
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.16);
            color: #fff;
        }
        .e-field::placeholder { color: rgba(255,255,255,.42); }
        .e-field:focus {
            outline: none;
            border-color: {{ $ev['color'] }};
            box-shadow: 0 0 0 4px {{ $ev['color'] }}47;
        }
        .e-pick { border: 1.5px solid rgba(255,255,255,.16); background: rgba(255,255,255,.05); }
        .e-pick.is-on {
            border-color: {{ $ev['color'] }};
            background: {{ $ev['color'] }}42;
            box-shadow: 0 0 0 4px {{ $ev['color'] }}29;
        }

        input[type=range].e-range { accent-color: var(--ev); }

        /* The final tick draws itself. */
        @keyframes e-ring { from { transform: scale(.6); opacity:0 } 60% { transform: scale(1.06) } to { transform:scale(1); opacity:1 } }
        @keyframes e-tick { to { stroke-dashoffset: 0 } }
        .e-ring { animation: e-ring .55s cubic-bezier(.22,.61,.36,1) both }
        .e-tick { stroke-dasharray: 48; stroke-dashoffset: 48; animation: e-tick .5s ease .35s forwards }

        /* A slow drift so a still photo never looks frozen behind the glass. */
        @keyframes e-drift { 0%,100% { background-position: 50% 48% } 50% { background-position: 50% 55% } }
        .e-drift { animation: e-drift 26s ease-in-out 1.6s infinite; }

        @media (prefers-reduced-motion: reduce) {
            .e-photo, .e-sheet, .e-inR, .e-inL, .e-ring, .e-tick, .e-drift { animation: none !important; }
            .e-photo { opacity: 1; transform: scale(1.02); }
            .e-photo.is-back { transform: scale(1.02); filter: brightness(.55); }
        }
    </style>
</head>
<body class="text-white antialiased">

<div x-data="claimFlow()" x-cloak class="relative min-h-[100dvh] overflow-hidden">

    {{-- ========== 1. The picture, first ========== --}}
    <div class="e-photo e-drift" :class="step > 0 && 'is-back'"
         style="background-image:url('{{ $ev['photo'] }}')" aria-hidden="true"></div>
    <div class="e-wash" aria-hidden="true"></div>

    {{-- ========== The event's identity — always on screen, compresses as you go ========== --}}
    <div class="relative z-20 px-5"
         :class="step === 0 ? 'pt-[max(1.25rem,env(safe-area-inset-top))]' : 'pt-[max(.9rem,env(safe-area-inset-top))]'"
         style="transition: padding .5s cubic-bezier(.22,.61,.36,1)">

        {{-- Top row: the CLUB's mark, never the platform's. The athlete was
             sent this link by their coach, about their club's competition —
             a logo they do not recognise reads as the wrong website. --}}
        <div class="m-in-fade flex items-center justify-between" style="animation-delay:.75s">
            <span class="flex items-center gap-2 min-w-0">
                @if($ev['club_logo'])
                    <span class="w-8 h-8 flex-shrink-0"><img src="{{ $ev['club_logo'] }}" alt="" class="w-full h-full object-contain drop-shadow"></span>
                @endif
                <span class="text-[12px] font-bold text-white/80 truncate">{{ $ev['club'] }}</span>
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/15 border border-white/20 backdrop-blur">
                <i class="bi bi-hourglass-split"></i> {{ $claim['expires'] }} {{ __('events.claim_left') }}
            </span>
        </div>

        {{-- chips → title → host. Rises out of the picture. --}}
        <div class="mt-4" x-show="step === 0" x-transition.opacity.duration.400ms>
            <div class="m-in flex items-center gap-1.5 flex-wrap" style="animation-delay:.85s">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide backdrop-blur border border-white/25"
                      style="background: {{ $ev['color'] }}8c">
                    <i class="bi bi-trophy-fill"></i> {{ $ev['type'] }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/15 border border-white/25 backdrop-blur">
                    {{ $ev['sport'] }}
                </span>
            </div>
            <h1 class="m-in text-[26px] leading-[1.12] font-black mt-3 drop-shadow-lg" style="animation-delay:.95s">{{ $ev['title'] }}</h1>
            <p class="m-in text-sm text-white/85 mt-1.5 flex items-center gap-1.5" style="animation-delay:1.02s">
                <i class="bi bi-building"></i>{{ $ev['club'] }}
            </p>
        </div>

        {{-- the compact version, once the form is up --}}
        <div class="mt-3" x-show="step > 0" x-transition.opacity.duration.400ms>
            <div class="flex items-center gap-3">
                <button type="button" @click="back()" aria-label="{{ __('shared.back') }}"
                        class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] uppercase tracking-wide text-white/60 font-bold" x-text="stepLabel"></p>
                    <p class="text-[15px] font-black leading-tight truncate">{{ $ev['title'] }}</p>
                </div>
                <span class="text-[11px] font-black text-white/70 flex-shrink-0"
                      x-text="`${Math.min(step,4)} / 4`"></span>
            </div>

            {{-- progress: four segments, filling in the event's colour --}}
            <div class="grid grid-cols-4 gap-1.5 mt-3">
                <template x-for="i in 4" :key="i">
                    <span class="e-seg h-1 rounded-full overflow-hidden"
                          :class="{ 'is-done': step > i, 'is-now': step === i }"><i></i></span>
                </template>
            </div>
        </div>
    </div>

    {{-- ========== 2. STEP 0 — the reveal ========== --}}
    <div x-show="step === 0" x-transition.opacity.duration.300ms
         class="relative z-20 px-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] min-h-[calc(100dvh-9rem)] flex flex-col justify-end gap-3">

        {{-- the sentence that explains why they are here --}}
        <div class="m-in e-glass rounded-2xl p-4 flex items-start gap-3" style="animation-delay:1.15s">
            <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 border border-white/20"
                  style="background: {{ $ev['color'] }}66">
                <i class="bi bi-person-check-fill text-xl"></i>
            </span>
            <div class="min-w-0">
<p class="text-[13px] text-white/75 leading-snug">
                    {!! __('events.claim_entered_you', [
                        'coach' => '<span class="font-black text-white">'.e($claim['coach']).'</span>',
                        'club'  => '<span class="font-bold text-white">'.e($claim['club']).'</span>',
                    ]) !!}
                </p>
                <p class="text-[12px] text-white/55 mt-1">{{ __('events.claim_entered_hint') }}</p>
            </div>
        </div>

        {{-- the three facts a competitor needs before agreeing to anything --}}
        <div class="m-in grid grid-cols-3 gap-2" style="animation-delay:1.25s">
            @foreach ([
                ['bi-calendar-event', $ev['date']],
                ['bi-clock', $ev['time']],
                ['bi-cash-coin', $ev['fee']],
            ] as [$icon, $val])
                <div class="e-glass rounded-2xl px-3 py-2.5">
                    <i class="bi {{ $icon }} text-[13px] text-white/60"></i>
                    <p class="text-[11.5px] font-bold mt-1 leading-tight">{{ $val }}</p>
                </div>
            @endforeach
        </div>

        <div class="m-in e-glass rounded-2xl px-4 py-3 flex items-center gap-2.5" style="animation-delay:1.32s">
            <i class="bi bi-geo-alt-fill text-white/60"></i>
            <p class="text-[12.5px] text-white/85 leading-snug">{{ $ev['location'] }}</p>
        </div>

        {{-- the CTA, last in --}}
        <div class="m-in-pop mt-1" style="animation-delay:1.45s">
            <button type="button" @click="next()"
                    class="m-press w-full h-14 rounded-2xl font-black text-[15px] flex items-center justify-center gap-2 shadow-2xl"
                    style="background: {{ $ev['color'] }}; box-shadow: 0 18px 40px -18px {{ $ev['color'] }}">
                {{ __('events.claim_cta') }} <i class="bi bi-arrow-right"></i>
            </button>
            <p class="text-center text-[11px] text-white/50 mt-2.5">
                {{ __('events.claim_takes_a_minute') }}@if($ev['deadline']) · {{ __('events.claim_closes', ['date' => $ev['deadline']]) }}@endif
            </p>
        </div>
    </div>

    {{-- ========== 3. STEPS 1–4 — the sheet ========== --}}
    <div x-show="step > 0" x-cloak
         class="e-sheet relative z-20 mt-4 px-5 pb-[max(6.5rem,calc(5.5rem+env(safe-area-inset-bottom)))]">

        {{-- Each step is re-created on entry, so the shared stagger replays and
             the fields travel with the direction of the move. --}}
        <template x-if="step === 1">
            <div :class="dir === 1 ? 'e-inR' : 'e-inL'">
                <h2 class="text-[22px] font-black leading-tight">{{ __('events.claim_who') }}</h2>
                <p class="text-[13px] text-white/60 mt-1">{{ __('events.claim_who_hint') }}</p>

                <div class="mobile-stagger space-y-3.5 mt-5">
                    {{-- the name the coach committed --}}
                    <div class="e-glass rounded-2xl p-3.5 flex items-center gap-3">
                        <span class="w-11 h-11 rounded-xl bg-white/10 grid place-items-center flex-shrink-0 text-lg font-black"
                              style="color: {{ $ev['color'] }}">{{ mb_substr($claim['athlete'], 0, 1) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[15px] font-black truncate">{{ $claim['athlete'] }}</p>
                            <p class="text-[11.5px] text-white/55">{{ __('events.claim_entered_by', ['coach' => $claim['coach']]) }}</p>
                        </div>
                        <button type="button" @click="notMe()"
                                class="m-press text-[11px] font-bold px-2.5 py-1.5 rounded-full bg-white/10 border border-white/20 flex-shrink-0">
                            {{ __('events.claim_not_me') }}
                        </button>
                    </div>

                    <div>
                        <label class="block text-[12px] font-bold text-white/70 mb-1.5">{{ __('events.claim_birthdate') }}</label>
                        <div class="e-glass rounded-2xl p-1.5">
                            <x-date-picker variant="dropdown" model="form.birthdate" max="{{ now()->toDateString() }}" />
                        </div>
                        {{-- Never required of anyone (CLAUDE.md). Say what a blank costs instead of demanding one. --}}
                        <p x-show="!form.birthdate" x-transition.opacity
                           class="text-[11.5px] text-amber-300/90 mt-2 flex items-start gap-1.5">
                            <i class="bi bi-info-circle-fill mt-0.5"></i>
                            <span>{{ __('events.claim_birthdate_blank') }}</span>
                        </p>
                    </div>

                    <div>
                        <label class="block text-[12px] font-bold text-white/70 mb-1.5">{{ __('events.claim_gender') }}</label>
                        <x-gender-toggle model="form.gender" />
                    </div>
                </div>
            </div>
        </template>

        <template x-if="step === 2">
            <div :class="dir === 1 ? 'e-inR' : 'e-inL'">
                <h2 class="text-[22px] font-black leading-tight">{{ __('events.claim_what') }}</h2>
                <p class="text-[13px] text-white/60 mt-1">{{ __('events.claim_what_hint') }}</p>

                <div class="mobile-stagger space-y-4 mt-5">
                    {{-- weight: stepper + slider, no keyboard needed --}}
                    <div class="e-glass rounded-2xl p-4">
                        <div class="flex items-center justify-between">
                            <label class="text-[12px] font-bold text-white/70">{{ __('events.claim_weight') }}</label>
                            <button type="button" @click="form.weight = null"
                                    x-show="form.weight" class="m-press text-[11px] font-bold text-white/50">clear</button>
                        </div>
                        <div class="flex items-center justify-center gap-5 mt-2">
                            <button type="button" @click="bump(-0.5)"
                                    class="m-press w-11 h-11 rounded-full bg-white/10 border border-white/20 grid place-items-center text-lg">
                                <i class="bi bi-dash-lg"></i>
                            </button>
                            <div class="text-center min-w-[7rem]">
                                <span class="text-[40px] font-black leading-none tabular-nums"
                                      x-text="form.weight ? form.weight.toFixed(1) : '—'"></span>
                                <span class="text-[13px] font-bold text-white/50 ms-1">kg</span>
                            </div>
                            <button type="button" @click="bump(0.5)"
                                    class="m-press w-11 h-11 rounded-full bg-white/10 border border-white/20 grid place-items-center text-lg">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                        <input type="range" min="20" max="140" step="0.5" class="e-range w-full mt-3"
                               :value="form.weight ?? 60" @input="form.weight = Number($event.target.value)">
                        <p class="text-[11.5px] mt-2 text-center" x-show="divisionPreview" x-transition.opacity
                           :style="`color:{{ $ev['color'] }}`">
                            <i class="bi bi-diagram-3 bracket-icon"></i>
                            <span x-text="@js(__('events.claim_places_you', ['division' => ':d'])).replace(':d', divisionPreview)" class="font-bold"></span>
                        </p>
                        <p x-show="!form.weight" class="text-[11.5px] text-white/50 mt-2 text-center">
                            {{ __('events.claim_weight_blank') }}
                        </p>
                    </div>

                    {{-- belt: selection cards, never a dropdown (Mobile Pattern Language) --}}
                    <div>
                        <label class="block text-[12px] font-bold text-white/70 mb-2">{{ __('events.claim_belt') }}</label>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach ($belts as $b)
                                <button type="button" @click="form.belt = '{{ $b['v'] }}'"
                                        class="m-press e-pick rounded-2xl py-3 flex flex-col items-center gap-1.5"
                                        :class="form.belt === '{{ $b['v'] }}' && 'is-on'">
                                    <span class="w-8 h-2.5 rounded-full border border-white/25" style="background: {{ $b['hex'] }}"></span>
                                    <span class="text-[11.5px] font-bold">{{ $b['label'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="step === 3">
            <div :class="dir === 1 ? 'e-inR' : 'e-inL'">
                <h2 class="text-[22px] font-black leading-tight">{{ __('events.claim_keep_place') }}</h2>
                <p class="text-[13px] text-white/60 mt-1">{{ __('events.claim_keep_place_hint') }}</p>

                <div class="mobile-stagger space-y-3.5 mt-5">
                    <div>
                        <label class="block text-[12px] font-bold text-white/70 mb-1.5">{{ __('events.claim_email') }}</label>
                        <input type="email" inputmode="email" autocomplete="email" x-model="form.email"
                               placeholder="you@example.com"
                               class="e-field w-full h-12 px-4 rounded-2xl text-[15px]">
                    </div>
                    <div>
                        <label class="block text-[12px] font-bold text-white/70 mb-1.5">{{ __('events.claim_password') }}</label>
                        <div class="relative">
                            <input :type="reveal ? 'text' : 'password'" autocomplete="new-password" x-model="form.password"
                                   placeholder="{{ __('events.claim_password_hint') }}"
                                   class="e-field w-full h-12 ps-4 pe-12 rounded-2xl text-[15px]">
                            <button type="button" @click="reveal = !reveal"
                                    class="absolute end-3 top-1/2 -translate-y-1/2 text-white/50">
                                <i class="bi" :class="reveal ? 'bi-eye-slash' : 'bi-eye'"></i>
                            </button>
                        </div>
                    </div>

                    <button type="button" @click="already()"
                            class="m-press w-full e-glass rounded-2xl px-4 py-3.5 flex items-center gap-3 text-start">
                        <i class="bi bi-box-arrow-in-right text-lg" style="color: {{ $ev['color'] }}"></i>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[13px] font-black">{{ __('events.claim_have_account') }}</span>
                            <span class="block text-[11.5px] text-white/55">{{ __('events.claim_have_account_hint') }}</span>
                        </span>
                        <i class="bi bi-chevron-right text-white/40"></i>
                    </button>

                    {{-- A minor never gets a bare account of their own (spec,
                         scenario 4): the credentials above create the GUARDIAN,
                         who is linked to the athlete as the family flows do it. --}}
                    <div x-show="isMinor" x-cloak x-transition.opacity>
                        <label class="block text-[12px] font-bold text-white/70 mb-1.5">{{ __('events.claim_guardian_name') }}</label>
                        <input type="text" x-model="form.guardian_name" autocomplete="name"
                               class="e-field w-full h-12 px-4 rounded-2xl text-[15px]">
                    </div>

                    <div x-show="isMinor" x-transition.opacity
                         class="rounded-2xl px-4 py-3 flex items-start gap-2.5 border"
                         style="background: rgba(251,191,36,.12); border-color: rgba(251,191,36,.35)">
                        <i class="bi bi-shield-fill-check text-amber-300 mt-0.5"></i>
                        <p class="text-[12px] text-amber-100/90 leading-snug">
                            This competitor is under 18 — the account will belong to their guardian, who signs off the entry.
                        </p>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="step === 4">
            <div class="text-center pt-6" :class="dir === 1 ? 'e-inR' : 'e-inL'">
                <div class="e-ring mx-auto w-24 h-24 rounded-full grid place-items-center border-2"
                     style="border-color: {{ $ev['color'] }}; background: {{ $ev['color'] }}3d">
                    <svg width="46" height="46" viewBox="0 0 24 24" fill="none">
                        <path class="e-tick" d="M4.5 12.5 L9.5 17.5 L19.5 6.5" stroke="#fff" stroke-width="2.6"
                              stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>

                <h2 class="text-[24px] font-black mt-5">{{ __('events.claim_done_title') }}</h2>
                <p class="text-[13.5px] text-white/65 mt-1.5 px-4">
                    {{ __('events.claim_done_body', ['coach' => $claim['coach']]) }}
                </p>

                <div class="mobile-stagger mt-6 space-y-2.5 text-start">
                    <div class="e-glass rounded-2xl px-4 py-3.5 flex items-center gap-3">
                        <i class="bi bi-diagram-3 bracket-icon text-lg" style="color: {{ $ev['color'] }}"></i>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] uppercase tracking-wide text-white/50 font-bold">{{ __('events.claim_your_division') }}</p>
                            <p class="text-[14px] font-black" x-text="division || @js(__('events.claim_set_at_weigh_in'))"></p>
                        </div>
                    </div>
                    <div class="e-glass rounded-2xl px-4 py-3.5 flex items-center gap-3">
                        <i class="bi bi-flag-fill text-lg" style="color: {{ $ev['color'] }}"></i>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] uppercase tracking-wide text-white/50 font-bold">{{ __('events.claim_competing_for') }}</p>
                            <p class="text-[14px] font-black">{{ $claim['club'] }}</p>
                        </div>
                    </div>
                    <div class="e-glass rounded-2xl px-4 py-3.5 flex items-center gap-3">
                        <i class="bi bi-envelope-check-fill text-lg" style="color: {{ $ev['color'] }}"></i>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] uppercase tracking-wide text-white/50 font-bold">{{ __('events.claim_next') }}</p>
                            <p class="text-[14px] font-black">{{ __('events.claim_next_body') }}</p>
                        </div>
                    </div>
                </div>

            </div>
        </template>
    </div>

    {{-- ========== The action bar — reachable, safe-area padded, never scrolls away ========== --}}
    <div x-show="step > 0 && step < 4" x-cloak
         class="ev-app-fixed fixed inset-x-0 bottom-0 z-30 px-5 pt-3"
         style="padding-bottom: calc(0.9rem + env(safe-area-inset-bottom));
                background: linear-gradient(180deg, transparent, rgba(6,6,14,.86) 38%, #06060e);">
        <button type="button" @click="next()"
                class="m-press w-full h-14 rounded-2xl font-black text-[15px] flex items-center justify-center gap-2"
                :class="(canContinue && !saving) ? '' : 'opacity-40'"
                :style="canContinue ? `background: {{ $ev['color'] }}; box-shadow: 0 18px 40px -18px {{ $ev['color'] }}` : 'background: rgba(255,255,255,.14)'">
            <span x-text="saving ? '…' : (step === 3 ? @js(__('events.claim_confirm')) : @js(__('events.claim_continue')))"></span>
            <i class="bi bi-arrow-right"></i>
        </button>
    </div>
</div>

<script>
function claimFlow() {
    return {
        step: 0,
        dir: 1,
        reveal: false,
        saving: false,
        error: '',
        // The server's own answer, so the last screen never invents a division.
        division: '',
        form: { birthdate: '', gender: '', weight: null, belt: '', email: '', password: '', guardian_name: '' },

        get stepLabel() {
            return ['', @js(__('events.claim_step_you')), @js(__('events.claim_step_division')),
                    @js(__('events.claim_step_account')), @js(__('events.claim_step_done'))][this.step] || '';
        },

        /* Age from the birthdate the ATHLETE gave — never one anybody guessed. */
        get age() {
            if (!this.form.birthdate) return null;
            const d = new Date(this.form.birthdate);
            if (isNaN(d)) return null;
            const now = new Date();
            let a = now.getFullYear() - d.getFullYear();
            const m = now.getMonth() - d.getMonth();
            if (m < 0 || (m === 0 && now.getDate() < d.getDate())) a--;
            return a;
        },
        get isMinor() { return this.age !== null && this.age < 18; },

        /* A PREVIEW while they choose a weight — never the authority. The real
           division comes back from EventType::classifyEntry() on submit, and
           the scale on the day outranks both. */
        get divisionPreview() {
            const w = this.form.weight;
            if (!w) return '';
            const band = w <= 60 ? '−60 kg' : w <= 67 ? '−67 kg' : w <= 75 ? '−75 kg' : w <= 84 ? '−84 kg' : '+84 kg';
            const group = this.age === null ? '' : this.age < 12 ? 'Kids ' : this.age < 15 ? 'Cadet ' : this.age < 18 ? 'Junior ' : '';
            return `${group}${band}`;
        },

        /* Only the account step can actually block: a birthdate and a weight are
           never demanded of anyone (CLAUDE.md / EnrolmentDecision::defer). */
        get canContinue() {
            if (this.step === 3) return /^\S+@\S+\.\S+$/.test(this.form.email) && this.form.password.length >= 8;
            return true;
        },

        bump(by) {
            const v = (this.form.weight ?? 60) + by;
            this.form.weight = Math.min(140, Math.max(20, Math.round(v * 2) / 2));
        },

        async next() {
            if (!this.canContinue || this.saving) return;

            // Step 3 is the only one that writes. Everything before it is the
            // athlete deciding what to say.
            if (this.step === 3) { await this.submit(); return; }

            this.dir = 1;
            this.step = Math.min(4, this.step + 1);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        async submit() {
            this.saving = true;
            this.error = '';
            try {
                const res = await fetch(@js(route('entry.claim.store', ['claim' => $claimUuid])), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        t: @js($secret),
                        birthdate: this.form.birthdate || null,
                        gender: this.form.gender || null,
                        weight: this.form.weight,
                        belt: this.form.belt || null,
                        guardian_name: this.form.guardian_name || null,
                        email: this.form.email,
                        password: this.form.password,
                    }),
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || !d.success) throw new Error(d.message || @js(__('personal.event_show_action_failed')));

                this.division = d.division || '';
                this.dir = 1;
                this.step = 4;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch (e) {
                this.error = e.message;
                notice(e.message);
            } finally {
                this.saving = false;
            }
        },
        back() {
            if (this.step >= 4) return;   // it is done; there is nothing behind it
            this.dir = -1;
            this.step = Math.max(0, this.step - 1);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        notMe()   { notice(@js(__('events.claim_not_me_msg'))); },
        already() { window.location.href = @js(route('login')); },
    };
}

/* This page is outside the app shell, so window.showToast is not loaded here.
   One small on-palette notice, safe-area aware, instead of a native dialog. */
function notice(msg) {
    const n = document.createElement('div');
    n.textContent = msg;
    n.style.cssText = 'position:fixed;left:1rem;right:1rem;bottom:calc(6rem + env(safe-area-inset-bottom));z-index:60;'
        + 'background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.22);backdrop-filter:blur(14px);'
        + 'color:#fff;font-size:12.5px;line-height:1.4;padding:.85rem 1rem;border-radius:1rem;'
        + 'box-shadow:0 20px 40px -20px rgba(0,0,0,.7);transition:opacity .3s,transform .3s;transform:translateY(8px);opacity:0';
    document.body.appendChild(n);
    requestAnimationFrame(() => { n.style.opacity = '1'; n.style.transform = 'none'; });
    setTimeout(() => { n.style.opacity = '0'; n.style.transform = 'translateY(8px)'; setTimeout(() => n.remove(), 320); }, 3400);
}
</script>
</body>
</html>
