@extends('layouts.personal-mobile')

@section('title', __('event-open_mat::messages.label').' · '.$e['title'])

{{--
    Open Mat console — mobile. The whole product on one screen.

    ── Why it looks nothing like the other consoles ────────────────────────────
    A championship console is a hub of jobs: entries, weigh-in, the draw, a P&L.
    A sparring console is a floor and a queue. This one has two boxes and a
    button, because that is genuinely all an open mat is — put somebody in red,
    put somebody in blue, go. Everything else on the screen is beneath the fold.

    The corner cards are the interface. Tapping one opens the sheet that fills
    it, and the sheet is where the three ways of filling a corner live: find a
    member, type a stranger's name, or show the code and let them join. Nothing
    scores here — the sport's own table does that, and Start hands the mat
    straight to it with the bout already loaded.
--}}

@section('personal-content')
@php
    $om = $openMat ?? ['mats' => [], 'corners' => [], 'codes' => [], 'bouts' => [], 'control_urls' => [], 'live' => [], 'closed' => true, 'max_mats' => 4];
    $omColor = '#F97316';
    $boot = $om + [
        'action_base' => \Illuminate\Support\Str::beforeLast(route('me.events.action', [$e['key'], 'x'], false), 'x'),
        'state_url' => route('me.events.openmat', $e['key'], false),
        'search_url' => route('me.events.openmat.search', $e['key'], false),
        'qr_url' => route('me.events.openmat.qr', $e['key'], false),
    ];
@endphp

<div class="-mx-4 -mt-4" x-data="openMatConsole(@js($boot))" x-init="init()">

    {{-- ══════════════ Hero ══════════════ --}}
    <header class="m-hero -mt-0 px-5 pt-5 pb-16 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $omColor }}, {{ $omColor }}b0);">
        <div class="absolute -end-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute end-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between relative z-50">
            <a href="{{ route('me.events') }}"
               class="inline-flex items-center gap-2 h-10 ps-3 pe-4 rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold">
                <i class="bi bi-arrow-left rtl:rotate-180"></i>{{ __('nav.events') }}
            </a>
            <button type="button" x-show="! closed" @click="endMat()"
                    class="h-10 px-4 rounded-full bg-white/15 border border-white/25 backdrop-blur text-xs font-bold inline-flex items-center gap-1.5">
                <i class="bi bi-stop-circle"></i>{{ __('event-open_mat::messages.console_end') }}
            </button>
        </div>

        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span x-show="! closed" class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider bg-white/20 border border-white/30 rounded-full px-2.5 py-1">
                    <span class="w-1.5 h-1.5 m-live-dot text-white"></span>{{ __('event-open_mat::messages.launch_running') }}
                </span>
                <span x-show="closed" class="text-[10px] font-black uppercase tracking-wider bg-black/25 border border-white/20 rounded-full px-2.5 py-1">{{ __('event-open_mat::messages.console_closed') }}</span>
                <span class="text-[10px] font-bold uppercase tracking-wider bg-white/15 border border-white/25 rounded-full px-2.5 py-1"
                      x-text="'{{ __('event-open_mat::messages.console_bouts_n', ['n' => ':n']) }}'.replace(':n', bouts.length)"></span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-building"></i>{{ $e['club'] ?? '' }}
            </p>
        </div>
    </header>

    <div class="px-4 -mt-10 relative z-10 space-y-4 pb-40">

        {{-- ══════════════ Mats ══════════════ --}}
        <section class="m-card rounded-3xl p-4" x-show="mats.length > 1 || ! closed">
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('event-open_mat::messages.console_mats') }}</p>
                <button type="button" x-show="! closed && mats.length < maxMats" @click="addMat()"
                        class="text-xs font-bold text-primary inline-flex items-center gap-1">
                    <i class="bi bi-plus-lg"></i>{{ __('event-open_mat::messages.console_add_mat') }}
                </button>
            </div>
            <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                <template x-for="m in mats" :key="m">
                    <button type="button" @click="mat = m"
                            class="flex-shrink-0 px-4 h-11 rounded-2xl font-bold text-sm border transition-colors"
                            :class="mat === m ? 'border-primary bg-primary text-white' : 'border-border bg-white text-foreground'"
                            x-text="m"></button>
                </template>
            </div>
        </section>

        {{-- ══════════════ Rules ══════════════
             The one question the front door does not ask, answered here in a
             tap by somebody already standing at the mat. Gone the moment the
             mat has fought something — see OpenMat::setSport(). --}}
        <section class="m-card rounded-3xl p-4" x-show="! closed && ! sportLocked && sports.length > 1">
            <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('event-open_mat::messages.console_sport') }}</p>
            <div class="mt-3 flex gap-2">
                <template x-for="s in sports" :key="s.key">
                    <button type="button" @click="setSport(s.key)"
                            class="flex-1 h-12 rounded-2xl font-bold text-sm border transition-colors inline-flex items-center justify-center gap-2"
                            :class="sport === s.key ? 'border-primary bg-primary text-white' : 'border-border bg-white text-foreground'">
                        <i :class="s.icon"></i><span x-text="s.label"></span>
                    </button>
                </template>
            </div>
        </section>

        {{-- ══════════════ The two corners ══════════════ --}}
        <section class="space-y-2.5">
            {{-- Red --}}
            <button type="button" @click="openSheet('aka')" :disabled="closed"
                    class="m-press w-full rounded-3xl p-4 text-start relative overflow-hidden border-2 transition-colors"
                    :class="aka ? 'border-rose-500 bg-rose-50' : 'border-dashed border-rose-300 bg-white'">
                <span class="absolute -end-6 -top-6 w-24 h-24 rounded-full bg-rose-500/10"></span>
                <span class="relative flex items-center gap-3">
                    <span class="w-14 h-[74px] rounded-2xl overflow-hidden bg-rose-100 flex-shrink-0 grid place-items-center">
                        <template x-if="aka && aka.photo"><img :src="aka.photo" :alt="aka.name" class="w-full h-full object-cover"></template>
                        <template x-if="aka && ! aka.photo"><img :src="aka.fallback" :alt="aka.name" class="w-full h-full object-cover"></template>
                        <template x-if="! aka"><i class="bi bi-plus-lg text-rose-400 text-2xl"></i></template>
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-[10px] font-black uppercase tracking-wider text-rose-500">{{ __('event-open_mat::messages.console_red') }}</span>
                        <span class="block text-lg font-black text-rose-700 truncate mt-0.5"
                              x-text="aka ? aka.name : '{{ __('event-open_mat::messages.console_empty_corner') }}'"></span>
                        <span class="block text-[11px] text-rose-600/70 mt-0.5"
                              x-text="aka ? (aka.club || (aka.member ? '{{ __('event-open_mat::messages.fill_search_tab') }}' : '{{ __('event-open_mat::messages.fill_guest_tab') }}')) : '{{ __('event-open_mat::messages.console_tap_to_fill') }}'"></span>
                        {{-- Their open-mat record, and only that. Never a
                             competitive one — see OpenMatCorner::present(). --}}
                        <template x-if="aka && aka.record && aka.record.fought">
                            <span class="inline-flex items-center gap-1.5 mt-1.5 px-2 py-0.5 rounded-full bg-rose-500/10 text-[10px] font-black text-rose-700">
                                <i class="bi bi-fire"></i>
                                <span x-text="aka.record.won + '\u2013' + aka.record.lost"></span>
                            </span>
                        </template>
                    </span>
                    <template x-if="aka && aka.country">
                        <img :src="'https://flagcdn.com/w40/' + aka.country + '.png'" alt="" class="w-8 rounded shadow-sm flex-shrink-0">
                    </template>
                </span>
            </button>

            {{-- Swap --}}
            <div class="flex items-center gap-3">
                <span class="flex-1 h-px bg-border"></span>
                <button type="button" @click="swap()" :disabled="closed || (! aka && ! ao)"
                        class="m-press w-11 h-11 rounded-full bg-white border border-border grid place-items-center text-muted-foreground disabled:opacity-40">
                    <i class="bi bi-arrow-down-up"></i>
                </button>
                <span class="flex-1 h-px bg-border"></span>
            </div>

            {{-- Blue --}}
            <button type="button" @click="openSheet('ao')" :disabled="closed"
                    class="m-press w-full rounded-3xl p-4 text-start relative overflow-hidden border-2 transition-colors"
                    :class="ao ? 'border-blue-500 bg-blue-50' : 'border-dashed border-blue-300 bg-white'">
                <span class="absolute -end-6 -bottom-6 w-24 h-24 rounded-full bg-blue-500/10"></span>
                <span class="relative flex items-center gap-3">
                    <span class="w-14 h-[74px] rounded-2xl overflow-hidden bg-blue-100 flex-shrink-0 grid place-items-center">
                        <template x-if="ao && ao.photo"><img :src="ao.photo" :alt="ao.name" class="w-full h-full object-cover"></template>
                        <template x-if="ao && ! ao.photo"><img :src="ao.fallback" :alt="ao.name" class="w-full h-full object-cover"></template>
                        <template x-if="! ao"><i class="bi bi-plus-lg text-blue-400 text-2xl"></i></template>
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-[10px] font-black uppercase tracking-wider text-blue-500">{{ __('event-open_mat::messages.console_blue') }}</span>
                        <span class="block text-lg font-black text-blue-700 truncate mt-0.5"
                              x-text="ao ? ao.name : '{{ __('event-open_mat::messages.console_empty_corner') }}'"></span>
                        <span class="block text-[11px] text-blue-600/70 mt-0.5"
                              x-text="ao ? (ao.club || (ao.member ? '{{ __('event-open_mat::messages.fill_search_tab') }}' : '{{ __('event-open_mat::messages.fill_guest_tab') }}')) : '{{ __('event-open_mat::messages.console_tap_to_fill') }}'"></span>
                        {{-- Their open-mat record, and only that. Never a
                             competitive one — see OpenMatCorner::present(). --}}
                        <template x-if="ao && ao.record && ao.record.fought">
                            <span class="inline-flex items-center gap-1.5 mt-1.5 px-2 py-0.5 rounded-full bg-blue-500/10 text-[10px] font-black text-blue-700">
                                <i class="bi bi-fire"></i>
                                <span x-text="ao.record.won + '\u2013' + ao.record.lost"></span>
                            </span>
                        </template>
                    </span>
                    <template x-if="ao && ao.country">
                        <img :src="'https://flagcdn.com/w40/' + ao.country + '.png'" alt="" class="w-8 rounded shadow-sm flex-shrink-0">
                    </template>
                </span>
            </button>
        </section>

        {{-- ══════════════ The join code ══════════════ --}}
        <section class="m-card rounded-3xl p-4" x-show="! closed && code">
            <div class="flex items-start gap-3">
                <span class="w-11 h-11 rounded-2xl bg-primary/10 text-primary grid place-items-center flex-shrink-0">
                    <i class="bi bi-qr-code text-lg"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-foreground">{{ __('event-open_mat::messages.fill_code_tab') }}</p>
                    <p class="text-[11px] text-muted-foreground mt-0.5 leading-relaxed">{{ __('event-open_mat::messages.fill_code_hint') }}</p>
                </div>
            </div>
            <p class="mt-3 text-center text-3xl font-black tracking-[0.35em] text-foreground select-all" x-text="code"></p>

            {{-- The QR, on the device that is actually standing at the mat.
                 It was rendered on the desktop console and NOT here, which is
                 backwards: the phone in the organiser's hand is the thing people
                 arriving at the door are shown, and the code image was buried one
                 level down inside "who is in this corner?". --}}
            <template x-if="qrUrl">
                <div class="mt-3 flex justify-center">
                    <img :src="qrUrl" alt="" class="w-40 h-40 rounded-2xl bg-white border border-border p-1.5">
                </div>
            </template>

            <div class="mt-3 flex gap-2">
                <button type="button" @click="copyJoin()" class="flex-1 h-11 rounded-2xl bg-muted text-foreground font-bold text-xs inline-flex items-center justify-center gap-1.5">
                    <i class="bi bi-link-45deg"></i>{{ __('event-open_mat::messages.fill_code_copy') }}
                </button>
                <button type="button" @click="newCode()" class="flex-1 h-11 rounded-2xl bg-muted text-foreground font-bold text-xs inline-flex items-center justify-center gap-1.5">
                    <i class="bi bi-arrow-repeat"></i>{{ __('event-open_mat::messages.fill_code_new') }}
                </button>
            </div>
        </section>

        {{-- ══════════════ Fought here ══════════════ --}}
        <section class="m-card rounded-3xl p-4">
            <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('event-open_mat::messages.console_history') }}</p>
            <div class="mt-3 space-y-2">
                <template x-for="b in matBouts" :key="b.id">
                    <div class="flex items-center gap-2.5 rounded-2xl bg-muted/50 p-3">
                        <span class="flex-1 min-w-0">
                            <span class="flex items-center justify-between gap-2">
                                <span class="text-sm font-bold truncate" :class="b.winner === 'a' ? 'text-rose-600' : 'text-foreground/60'" x-text="b.aka"></span>
                                <span class="text-sm font-black text-foreground flex-shrink-0" x-text="b.aka_score"></span>
                            </span>
                            <span class="flex items-center justify-between gap-2 mt-0.5">
                                <span class="text-sm font-bold truncate" :class="b.winner === 'b' ? 'text-blue-600' : 'text-foreground/60'" x-text="b.ao"></span>
                                <span class="text-sm font-black text-foreground flex-shrink-0" x-text="b.ao_score"></span>
                            </span>
                        </span>
                        <span x-show="b.live" class="text-[10px] font-black uppercase text-green-700 bg-green-100 rounded-full px-2 py-1 flex-shrink-0">{{ __('event-open_mat::messages.launch_running') }}</span>
                    </div>
                </template>
            </div>
            <p x-show="matBouts.length === 0" class="text-sm text-muted-foreground text-center py-4">{{ __('event-open_mat::messages.console_history_empty') }}</p>
        </section>

        {{-- ══════════════ Hall screens, cameras, and what they broadcast ══
             One panel, one block per mat: the boards on it, the phones filming
             it, and whether it is on air. They were three panels asking the same
             question — what is happening on Mat 2 — and the answer belongs in
             one place. ══════════════ --}}
        {{-- Rendered whether or not this sport has wall boards: an open mat
             with no screen package is still filmed and still broadcast, so the
             panel opens for whichever fleets exist and simply offers no board
             slots when there are none to pair. --}}
        <x-court-screens
            :event="$e['key']"
            :mats="$om['mats']"
            :screens="$screens['screens'] ?? []"
            :surfaces="$screens ? ($screenSurfaces ?? ['bout','queue','control']) : []"
            :newUrl="$screenNewUrl ?? null"
            :color="$omColor" />
    </div>

    {{-- ══════════════ The one button ══════════════ --}}
    <template x-teleport="body">
        <div x-show="! closed" class="fixed inset-x-0 bottom-0 z-[60] px-4 pb-[calc(0.75rem+env(safe-area-inset-bottom))] pt-3"
             style="background: linear-gradient(to top, rgba(0,0,0,.45), transparent);">
            <a x-show="liveBout" :href="controlUrls[mat]"
               class="m-press w-full h-14 rounded-3xl text-white font-black text-base shadow-2xl inline-flex items-center justify-center gap-2"
               style="background: linear-gradient(120deg, #16A34A, #0EA5E9);">
                <i class="bi bi-sliders"></i>{{ __('event-open_mat::messages.console_resume') }}
            </a>
            <button type="button" x-show="! liveBout" @click="start()" :disabled="! ready || busy"
                    class="m-press w-full h-14 rounded-3xl text-white font-black text-base shadow-2xl disabled:opacity-40 inline-flex items-center justify-center gap-2"
                    style="background: linear-gradient(120deg, #F97316, #EF4444);">
                <template x-if="! busy">
                    <span class="inline-flex items-center gap-2"><i class="bi bi-play-circle-fill"></i>{{ __('event-open_mat::messages.console_start') }}</span>
                </template>
                <template x-if="busy"><i class="bi bi-arrow-repeat animate-spin"></i></template>
            </button>
        </div>
    </template>

    @include('event-open_mat::manage.fill-sheet')
</div>

@include('event-open_mat::manage.runtime')
@endsection
