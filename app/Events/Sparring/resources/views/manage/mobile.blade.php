@extends('layouts.personal-mobile')

@section('title', __('event-sparring::messages.label').' · '.$e['title'])

{{--
    Sparring console — mobile. The coach's screen while training runs.

    ── Why this is not the championship console ────────────────────────────────
    A tournament console is a hub of jobs to be done before and after the day:
    entries, weigh-in, the draw, verification, a P&L. A sparring session has one
    job, repeated: put two of these people on that mat. So the whole screen is
    that loop — the floor, a pair, the queue — and everything else is absent
    rather than hidden.

    The loop: tap a face (red corner), tap another (blue corner), Queue. The mat
    chips above scope the queue to one mat, because a club with two mats runs two
    independent lists. Nothing here scores anything — that is the sport's own
    scoring table, one tap away and the same table a championship uses.

    Writes go to `me.events.action` (the package's performAction) and patch this
    page in place. Other coaches holding the same console are nudged over
    `realtime:events` and re-fetch, because what each of them may see differs.
--}}

@section('personal-content')
@php
    $sp = $sparring ?? ['mats' => [], 'entrants' => [], 'bouts' => [], 'club_members' => [], 'control_urls' => [], 'closed' => true, 'max_mats' => 8];
    $spColor = '#0EA5E9';
@endphp

<div class="-mx-4 -mt-4" x-data="sparringConsole()" x-init="init()">

    {{-- ══════════════ Hero ══════════════ --}}
    <header class="m-hero -mt-0 px-5 pt-5 pb-16 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $spColor }}, {{ $spColor }}b0);">
        <div class="absolute -end-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute end-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between relative z-50">
            <a href="{{ route('me.events') }}" class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                <i class="bi bi-arrow-left rtl:rotate-180"></i>
            </a>
            <button type="button" x-show="! closed" @click="endSession()"
                    class="h-10 px-4 rounded-full bg-white/15 border border-white/25 backdrop-blur text-xs font-bold inline-flex items-center gap-1.5">
                <i class="bi bi-stop-circle"></i>{{ __('event-sparring::messages.console_end_session') }}
            </button>
        </div>

        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span x-show="! closed" class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider bg-white/20 border border-white/30 rounded-full px-2.5 py-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>{{ __('event-sparring::messages.launch_running') }}
                </span>
                <span x-show="closed" class="text-[10px] font-black uppercase tracking-wider bg-black/25 border border-white/20 rounded-full px-2.5 py-1">{{ __('event-sparring::messages.console_closed') }}</span>
                <span class="text-[10px] font-bold uppercase tracking-wider bg-white/15 border border-white/25 rounded-full px-2.5 py-1"
                      x-text="matCountLabel"></span>
                <span class="text-[10px] font-bold uppercase tracking-wider bg-white/15 border border-white/25 rounded-full px-2.5 py-1"
                      x-text="boutsLabel"></span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-building"></i>{{ $e['club'] ?? '' }}
            </p>
        </div>
    </header>

    <div class="px-4 -mt-10 relative z-10 space-y-4 pb-28">

        {{-- ══════════════ Mats ══════════════ --}}
        <section class="m-card rounded-3xl p-4">
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('event-sparring::messages.console_mats') }}</p>
                <button type="button" x-show="! closed && mats.length < {{ (int) $sp['max_mats'] }}" @click="addMat()"
                        class="text-xs font-bold text-primary inline-flex items-center gap-1">
                    <i class="bi bi-plus-lg"></i>{{ __('event-sparring::messages.action_set_mats') }}
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

            <a :href="controlUrls[mat]" x-show="controlUrls[mat] && tableReady"
               class="m-press mt-3 w-full rounded-2xl py-3.5 text-white font-black text-sm inline-flex items-center justify-center gap-2"
               style="background: linear-gradient(120deg, #0EA5E9, #6366F1);">
                <i class="bi bi-sliders"></i><span x-text="tableLabel"></span>
            </a>
            {{-- The table cannot open on a mat with nothing on it (the sport's
                 console builds its mat list from the bouts). Say so, rather
                 than offer a link that 404s. --}}
            <p x-show="! tableReady" class="mt-3 text-xs text-muted-foreground text-center rounded-2xl bg-muted/60 py-3 px-3">
                <i class="bi bi-info-circle me-1"></i>{{ __('event-sparring::messages.console_table_needs_bout') }}
            </p>
        </section>

        {{-- ══════════════ The floor ══════════════ --}}
        <section class="m-card rounded-3xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('event-sparring::messages.console_floor') }}</p>
                    <p class="text-[11px] text-muted-foreground mt-0.5">{{ __('event-sparring::messages.console_floor_hint') }}</p>
                </div>
                <button type="button" x-show="! closed" @click="peopleSheet = true"
                        class="w-10 h-10 rounded-2xl bg-primary/10 text-primary grid place-items-center">
                    <i class="bi bi-person-plus text-lg"></i>
                </button>
            </div>

            <div class="mt-3 grid grid-cols-3 gap-2.5">
                <template x-for="p in entrants" :key="p.id">
                    <button type="button" @click="pick(p)" :disabled="closed"
                            class="relative rounded-2xl border p-2 text-center transition-colors"
                            :class="cornerOf(p.id) === 'aka' ? 'border-rose-500 bg-rose-50'
                                  : cornerOf(p.id) === 'ao' ? 'border-blue-500 bg-blue-50'
                                  : 'border-border bg-white'">
                        <span class="block w-9 h-12 mx-auto rounded-lg overflow-hidden bg-muted">
                            <template x-if="p.photo">
                                <img :src="p.photo" :alt="p.name" class="w-full h-full object-cover">
                            </template>
                            <template x-if="! p.photo">
                                <span class="w-full h-full grid place-items-center text-muted-foreground">
                                    <i class="bi bi-person"></i>
                                </span>
                            </template>
                        </span>
                        <span class="block text-[11px] font-bold text-foreground mt-1.5 leading-tight truncate" x-text="p.name"></span>
                        <span class="block text-[10px] text-muted-foreground" x-text="p.bouts ? p.bouts + '' : ''"></span>
                        <span x-show="cornerOf(p.id)" class="absolute top-1 end-1 w-4 h-4 rounded-full grid place-items-center text-[9px] font-black text-white"
                              :class="cornerOf(p.id) === 'aka' ? 'bg-rose-500' : 'bg-blue-500'"
                              x-text="cornerOf(p.id) === 'aka' ? 'R' : 'B'"></span>
                    </button>
                </template>
            </div>

            <p x-show="entrants.length === 0" class="text-sm text-muted-foreground text-center py-4">{{ __('event-sparring::messages.console_no_members') }}</p>
        </section>

        {{-- ══════════════ Queue on this mat ══════════════ --}}
        <section class="m-card rounded-3xl p-4">
            <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('event-sparring::messages.console_queue') }}</p>

            <div class="mt-3 space-y-2">
                <template x-for="b in queued" :key="b.id">
                    <div class="flex items-center gap-2.5 rounded-2xl border border-border bg-white p-3">
                        <span class="w-7 h-7 rounded-lg bg-muted text-muted-foreground grid place-items-center text-[11px] font-black flex-shrink-0" x-text="b.no"></span>
                        <span class="flex-1 min-w-0">
                            <span class="block text-sm font-bold text-rose-600 truncate" x-text="b.aka"></span>
                            <span class="block text-sm font-bold text-blue-600 truncate" x-text="b.ao"></span>
                        </span>
                        <button type="button" x-show="! closed" @click="unqueue(b)"
                                class="w-9 h-9 rounded-xl bg-muted text-muted-foreground grid place-items-center flex-shrink-0">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </template>
            </div>

            <p x-show="queued.length === 0" class="text-sm text-muted-foreground text-center py-4">{{ __('event-sparring::messages.console_queue_empty') }}</p>
        </section>

        {{-- ══════════════ Fought ══════════════ --}}
        <section class="m-card rounded-3xl p-4">
            <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('event-sparring::messages.console_fought') }}</p>

            <div class="mt-3 space-y-2">
                <template x-for="b in fought" :key="b.id">
                    <div class="flex items-center gap-2.5 rounded-2xl bg-muted/50 p-3">
                        <span class="flex-1 min-w-0">
                            <span class="flex items-center justify-between gap-2">
                                <span class="text-sm font-bold truncate" :class="b.winner === 'a' ? 'text-rose-600' : 'text-foreground/60'" x-text="b.aka"></span>
                                <span class="text-sm font-black text-foreground flex-shrink-0" x-text="b.aka_score ?? 0"></span>
                            </span>
                            <span class="flex items-center justify-between gap-2 mt-0.5">
                                <span class="text-sm font-bold truncate" :class="b.winner === 'b' ? 'text-blue-600' : 'text-foreground/60'" x-text="b.ao"></span>
                                <span class="text-sm font-black text-foreground flex-shrink-0" x-text="b.ao_score ?? 0"></span>
                            </span>
                        </span>
                        <span class="text-[10px] font-bold uppercase text-muted-foreground flex-shrink-0" x-text="b.mat"></span>
                    </div>
                </template>
            </div>

            <p x-show="fought.length === 0" class="text-sm text-muted-foreground text-center py-4">{{ __('event-sparring::messages.console_fought_empty') }}</p>
        </section>

        {{-- ══════════════ Hall screens ══════════════ --}}
        @if($screens)
            <x-court-screens
                :event="$e['key']"
                :mats="$sp['mats']"
                :screens="$screens['screens'] ?? []"
                :surfaces="$screenSurfaces ?? ['bout','queue','control']"
                :newUrl="$screenNewUrl ?? null"
                :color="$spColor" />
        @endif
    </div>

    {{-- ══════════════ The pair bar ══════════════
         Teleported: #shell-content children carry a transform, which would make
         a fixed bar resolve against the wrapper instead of the viewport. --}}
    <template x-teleport="body">
        <div x-show="aka || ao" x-transition.opacity
             class="fixed inset-x-0 bottom-0 z-[60] px-4 pb-[calc(0.75rem+env(safe-area-inset-bottom))] pt-3"
             style="background: linear-gradient(to top, rgba(0,0,0,.5), transparent);">
            <div class="rounded-3xl bg-white shadow-2xl border border-border p-3">
                <div class="flex items-center gap-2">
                    <span class="flex-1 min-w-0 rounded-2xl bg-rose-50 border border-rose-200 px-3 py-2">
                        <span class="block text-[9px] font-black uppercase tracking-wider text-rose-500">{{ __('event-sparring::messages.console_pick_red') }}</span>
                        <span class="block text-sm font-bold text-rose-700 truncate" x-text="aka ? aka.name : '—'"></span>
                    </span>
                    <span class="flex-1 min-w-0 rounded-2xl bg-blue-50 border border-blue-200 px-3 py-2">
                        <span class="block text-[9px] font-black uppercase tracking-wider text-blue-500">{{ __('event-sparring::messages.console_pick_blue') }}</span>
                        <span class="block text-sm font-bold text-blue-700 truncate" x-text="ao ? ao.name : '—'"></span>
                    </span>
                </div>
                <div class="flex items-center gap-2 mt-2.5">
                    <button type="button" @click="clearPick()" class="h-11 px-4 rounded-2xl bg-muted text-foreground font-bold text-sm">
                        {{ __('event-sparring::messages.console_clear_pick') }}
                    </button>
                    <button type="button" @click="queueBout()" :disabled="! (aka && ao) || busy"
                            class="flex-1 h-11 rounded-2xl text-white font-black text-sm disabled:opacity-40"
                            style="background: linear-gradient(120deg, #0EA5E9, #6366F1);">
                        <span x-show="! busy"><i class="bi bi-plus-circle me-1.5"></i>{{ __('event-sparring::messages.console_queue_it') }}</span>
                        <span x-show="busy"><i class="bi bi-arrow-repeat"></i></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- ══════════════ Add people — bottom sheet ══════════════ --}}
    <template x-teleport="body">
        <div x-show="peopleSheet" class="fixed inset-0 z-[70]" x-cloak>
            <div class="absolute inset-0 bg-black/50" @click="peopleSheet = false" x-transition.opacity></div>
            <div class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col bg-background rounded-t-3xl shadow-2xl"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
                <div class="flex-shrink-0 px-5 pt-3 pb-3 border-b border-border">
                    <div class="w-10 h-1 rounded-full bg-border mx-auto"></div>
                    <div class="flex items-center justify-between mt-3">
                        <p class="font-black text-lg text-foreground">{{ __('event-sparring::messages.console_add_people') }}</p>
                        <button type="button" @click="peopleSheet = false" class="w-9 h-9 rounded-xl bg-muted grid place-items-center">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <input type="text" x-model="search" placeholder="{{ __('event-sparring::messages.console_search_members') }}"
                           class="w-full mt-3 px-3 py-2.5 border border-border rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-3 space-y-2">
                    <template x-for="m in candidates" :key="m.id">
                        <button type="button" @click="toggleCandidate(m.id)"
                                class="w-full flex items-center gap-3 rounded-2xl border p-2.5 text-start transition-colors"
                                :class="chosen.includes(m.id) ? 'border-primary bg-primary/5' : 'border-border bg-white'">
                            <span class="w-9 h-12 rounded-lg overflow-hidden bg-muted flex-shrink-0">
                                <template x-if="m.photo"><img :src="m.photo" class="w-full h-full object-cover" :alt="m.name"></template>
                                <template x-if="! m.photo"><span class="w-full h-full grid place-items-center text-muted-foreground"><i class="bi bi-person"></i></span></template>
                            </span>
                            <span class="flex-1 font-bold text-sm text-foreground truncate" x-text="m.name"></span>
                            <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0"
                                  :class="chosen.includes(m.id) ? 'border-primary bg-primary' : 'border-border'">
                                <i class="bi bi-check text-white text-[11px]" x-show="chosen.includes(m.id)"></i>
                            </span>
                        </button>
                    </template>
                    <p x-show="candidates.length === 0" class="text-sm text-muted-foreground text-center py-6">{{ __('event-sparring::messages.console_no_members') }}</p>
                </div>

                <div class="flex-shrink-0 px-5 pt-3 border-t border-border" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="addPeople()" :disabled="chosen.length === 0 || busy"
                            class="w-full h-12 rounded-2xl text-white font-black disabled:opacity-40"
                            style="background: linear-gradient(120deg, #0EA5E9, #6366F1);"
                            x-text="addLabel"></button>
                </div>
            </div>
        </div>
    </template>
</div>

@include('event-sparring::manage.runtime')
@endsection
