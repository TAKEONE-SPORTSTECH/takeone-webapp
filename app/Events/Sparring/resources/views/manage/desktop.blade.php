@extends('layouts.app')

@section('title', __('event-sparring::messages.label').' · '.$e['title'])

{{--
    Sparring console — desktop.

    The same loop as the phone (pick red, pick blue, queue) laid out for a laptop
    at the mat side: the floor is the widest column because tapping faces is the
    action, the queue and the fought list sit beside it, and the mat chips scope
    both. Same Alpine component, same endpoints — one implementation with two
    layouts, never two behaviours.
--}}

@section('content')
@php
    $sp = $sparring ?? ['mats' => [], 'entrants' => [], 'bouts' => [], 'club_members' => [], 'control_urls' => [], 'closed' => true, 'max_mats' => 8];
    $spColor = '#0EA5E9';
@endphp

{{-- The page supplies its own wrapper padding: layouts.app's <main> has none,
     and the band below cancels `px-4 sm:px-6 lg:px-8 py-6` with negative margins.
     Without the wrapper the band overhung the viewport and every card under it
     sat flush against the screen edges. --}}
<div class="px-4 sm:px-6 lg:px-8 py-6" x-data="sparringConsole()" x-init="init()">

    {{-- ══════════════ Hero ══════════════ --}}
    <header class="-mx-4 sm:-mx-6 lg:-mx-8 -mt-6 px-8 pt-6 pb-20 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $spColor }}, {{ $spColor }}b0);">
        <div class="absolute -end-12 -top-12 w-56 h-56 rounded-full bg-white/10"></div>
        <div class="absolute end-24 bottom-6 w-28 h-28 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between relative z-50">
            <a href="{{ route('me.events') }}" class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                <i class="bi bi-chevron-left"></i>
            </a>
            <div class="flex items-center gap-2">
                <a :href="controlUrls[mat]" x-show="controlUrls[mat] && tableReady"
                   class="h-10 px-4 rounded-full bg-white text-sky-700 text-sm font-bold inline-flex items-center gap-2">
                    <i class="bi bi-sliders"></i><span x-text="tableLabel"></span>
                </a>
                {{-- Waiting, not broken: a mat with no bout has no table yet. --}}
                <span x-show="! tableReady" class="h-10 px-4 rounded-full bg-white/15 border border-white/25 backdrop-blur text-xs font-medium inline-flex items-center gap-2">
                    <i class="bi bi-info-circle"></i>{{ __('event-sparring::messages.console_table_needs_bout') }}
                </span>
                <button type="button" x-show="! closed" @click="endSession()"
                        class="h-10 px-4 rounded-full bg-white/15 border border-white/25 backdrop-blur text-sm font-bold inline-flex items-center gap-2">
                    <i class="bi bi-stop-circle"></i>{{ __('event-sparring::messages.console_end_session') }}
                </button>
            </div>
        </div>

        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-2 flex-wrap">
                <span x-show="! closed" class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider bg-white/20 border border-white/30 rounded-full px-2.5 py-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>{{ __('event-sparring::messages.launch_running') }}
                </span>
                <span x-show="closed" class="text-[10px] font-black uppercase tracking-wider bg-black/25 border border-white/20 rounded-full px-2.5 py-1">{{ __('event-sparring::messages.console_closed') }}</span>
                <span class="text-[10px] font-bold uppercase tracking-wider bg-white/15 border border-white/25 rounded-full px-2.5 py-1" x-text="matCountLabel"></span>
                <span class="text-[10px] font-bold uppercase tracking-wider bg-white/15 border border-white/25 rounded-full px-2.5 py-1" x-text="boutsLabel"></span>
            </div>
            <h1 class="text-3xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5"><i class="bi bi-building"></i>{{ $e['club'] ?? '' }}</p>
        </div>
    </header>

    <div class="-mt-14 relative z-10 space-y-6">

        {{-- ══════════════ Mats ══════════════ --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex flex-wrap items-center gap-2">
            <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground me-2">{{ __('event-sparring::messages.console_mats') }}</span>
            <template x-for="m in mats" :key="m">
                <button type="button" @click="mat = m"
                        class="px-4 h-10 rounded-xl font-bold text-sm border transition-colors"
                        :class="mat === m ? 'border-primary bg-primary text-white' : 'border-border hover:bg-muted/60'"
                        x-text="m"></button>
            </template>
            <button type="button" x-show="! closed && mats.length < {{ (int) $sp['max_mats'] }}" @click="addMat()"
                    class="w-10 h-10 rounded-xl border border-dashed border-border text-muted-foreground hover:text-primary hover:border-primary transition-colors">
                <i class="bi bi-plus-lg"></i>
            </button>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

            {{-- ══════════════ The floor ══════════════ --}}
            <section class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">{{ __('event-sparring::messages.console_floor') }}</h2>
                        <p class="text-sm text-muted-foreground mt-0.5">{{ __('event-sparring::messages.console_floor_hint') }}</p>
                    </div>
                    <button type="button" x-show="! closed" @click="peopleSheet = true"
                            class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium text-sm">
                        <i class="bi bi-person-plus me-2"></i>{{ __('event-sparring::messages.console_add_people') }}
                    </button>
                </div>

                <div class="mt-5 grid grid-cols-3 sm:grid-cols-5 lg:grid-cols-6 gap-3">
                    <template x-for="p in entrants" :key="p.id">
                        <button type="button" @click="pick(p)" :disabled="closed"
                                class="relative rounded-xl border p-2 text-center transition-all hover:shadow-sm"
                                :class="cornerOf(p.id) === 'aka' ? 'border-rose-500 bg-rose-50'
                                      : cornerOf(p.id) === 'ao' ? 'border-blue-500 bg-blue-50'
                                      : 'border-border'">
                            <span class="block w-12 h-16 mx-auto rounded-lg overflow-hidden bg-muted">
                                <template x-if="p.photo"><img :src="p.photo" :alt="p.name" class="w-full h-full object-cover"></template>
                                <template x-if="! p.photo"><span class="w-full h-full grid place-items-center text-muted-foreground"><i class="bi bi-person"></i></span></template>
                            </span>
                            <span class="block text-xs font-bold text-foreground mt-2 leading-tight truncate" x-text="p.name"></span>
                            <span class="block text-[10px] text-muted-foreground" x-text="p.bouts ? p.bouts + '' : ''"></span>
                            <span x-show="cornerOf(p.id)" class="absolute top-1.5 end-1.5 w-4 h-4 rounded-full grid place-items-center text-[9px] font-black text-white"
                                  :class="cornerOf(p.id) === 'aka' ? 'bg-rose-500' : 'bg-blue-500'"
                                  x-text="cornerOf(p.id) === 'aka' ? 'R' : 'B'"></span>
                        </button>
                    </template>
                </div>

                <p x-show="entrants.length === 0" class="text-sm text-muted-foreground text-center py-8">{{ __('event-sparring::messages.console_no_members') }}</p>

                {{-- The pair, and the one button that matters --}}
                <div x-show="aka || ao" x-transition class="mt-6 rounded-xl border border-border p-4">
                    <div class="flex flex-col sm:flex-row items-stretch gap-3">
                        <div class="flex-1 rounded-lg bg-rose-50 border border-rose-200 px-4 py-3">
                            <p class="text-[10px] font-black uppercase tracking-wider text-rose-500">{{ __('event-sparring::messages.console_pick_red') }}</p>
                            <p class="text-sm font-bold text-rose-700 truncate" x-text="aka ? aka.name : '—'"></p>
                        </div>
                        <div class="flex-1 rounded-lg bg-blue-50 border border-blue-200 px-4 py-3">
                            <p class="text-[10px] font-black uppercase tracking-wider text-blue-500">{{ __('event-sparring::messages.console_pick_blue') }}</p>
                            <p class="text-sm font-bold text-blue-700 truncate" x-text="ao ? ao.name : '—'"></p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="clearPick()" class="h-11 px-4 rounded-lg bg-muted text-foreground font-medium text-sm">
                                {{ __('event-sparring::messages.console_clear_pick') }}
                            </button>
                            <button type="button" @click="queueBout()" :disabled="! (aka && ao) || busy"
                                    class="h-11 px-5 rounded-lg text-white font-bold text-sm disabled:opacity-40"
                                    style="background: linear-gradient(120deg, #0EA5E9, #6366F1);">
                                <i class="bi bi-plus-circle me-1.5"></i>{{ __('event-sparring::messages.console_queue_it') }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            {{-- ══════════════ Queue + fought ══════════════ --}}
            <div class="space-y-6">
                <section class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-muted-foreground">{{ __('event-sparring::messages.console_queue') }}</h2>
                    <div class="mt-4 space-y-2">
                        <template x-for="b in queued" :key="b.id">
                            <div class="flex items-center gap-3 rounded-xl border border-border p-3">
                                <span class="w-7 h-7 rounded-lg bg-muted text-muted-foreground grid place-items-center text-[11px] font-black flex-shrink-0" x-text="b.no"></span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-sm font-bold text-rose-600 truncate" x-text="b.aka"></span>
                                    <span class="block text-sm font-bold text-blue-600 truncate" x-text="b.ao"></span>
                                </span>
                                <button type="button" x-show="! closed" @click="unqueue(b)"
                                        class="w-8 h-8 rounded-lg bg-muted text-muted-foreground grid place-items-center hover:bg-red-50 hover:text-red-600 transition-colors">
                                    <i class="bi bi-x-lg text-xs"></i>
                                </button>
                            </div>
                        </template>
                    </div>
                    <p x-show="queued.length === 0" class="text-sm text-muted-foreground text-center py-6">{{ __('event-sparring::messages.console_queue_empty') }}</p>
                </section>

                <section class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-muted-foreground">{{ __('event-sparring::messages.console_fought') }}</h2>
                    <div class="mt-4 space-y-2">
                        <template x-for="b in fought" :key="b.id">
                            <div class="rounded-xl bg-muted/50 p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-bold truncate" :class="b.winner === 'a' ? 'text-rose-600' : 'text-foreground/60'" x-text="b.aka"></span>
                                    <span class="text-sm font-black flex-shrink-0" x-text="b.aka_score ?? 0"></span>
                                </div>
                                <div class="flex items-center justify-between gap-2 mt-1">
                                    <span class="text-sm font-bold truncate" :class="b.winner === 'b' ? 'text-blue-600' : 'text-foreground/60'" x-text="b.ao"></span>
                                    <span class="text-sm font-black flex-shrink-0" x-text="b.ao_score ?? 0"></span>
                                </div>
                                <p class="text-[10px] font-bold uppercase text-muted-foreground mt-1.5" x-text="b.mat"></p>
                            </div>
                        </template>
                    </div>
                    <p x-show="fought.length === 0" class="text-sm text-muted-foreground text-center py-6">{{ __('event-sparring::messages.console_fought_empty') }}</p>
                </section>
            </div>
        </div>

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

    {{-- ══════════════ Add people ══════════════ --}}
    <template x-teleport="body">
        <div x-show="peopleSheet" class="fixed inset-0 z-[70]" x-cloak>
            <div class="absolute inset-0 bg-black/50" @click="peopleSheet = false" x-transition.opacity></div>
            <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:m-auto sm:h-fit sm:max-h-[80vh] sm:max-w-lg max-h-[92vh] flex flex-col bg-background rounded-t-3xl sm:rounded-3xl shadow-2xl"
                 x-transition>
                <div class="flex-shrink-0 px-6 pt-5 pb-4 border-b border-border">
                    <div class="flex items-center justify-between">
                        <p class="font-bold text-lg text-foreground">{{ __('event-sparring::messages.console_add_people') }}</p>
                        <button type="button" @click="peopleSheet = false" class="w-9 h-9 rounded-lg bg-muted grid place-items-center"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <input type="text" x-model="search" placeholder="{{ __('event-sparring::messages.console_search_members') }}"
                           class="w-full mt-4 px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                <div class="flex-1 overflow-y-auto px-6 py-4 space-y-2">
                    <template x-for="m in candidates" :key="m.id">
                        <button type="button" @click="toggleCandidate(m.id)"
                                class="w-full flex items-center gap-3 rounded-xl border p-2.5 text-start transition-colors"
                                :class="chosen.includes(m.id) ? 'border-primary bg-primary/5' : 'border-border hover:bg-muted/60'">
                            <span class="w-9 h-12 rounded-lg overflow-hidden bg-muted flex-shrink-0">
                                <template x-if="m.photo"><img :src="m.photo" class="w-full h-full object-cover" :alt="m.name"></template>
                                <template x-if="! m.photo"><span class="w-full h-full grid place-items-center text-muted-foreground"><i class="bi bi-person"></i></span></template>
                            </span>
                            <span class="flex-1 font-medium text-sm text-foreground truncate" x-text="m.name"></span>
                            <i class="bi bi-check-lg text-primary" x-show="chosen.includes(m.id)"></i>
                        </button>
                    </template>
                    <p x-show="candidates.length === 0" class="text-sm text-muted-foreground text-center py-6">{{ __('event-sparring::messages.console_no_members') }}</p>
                </div>
                <div class="flex-shrink-0 px-6 py-4 border-t border-border">
                    <button type="button" @click="addPeople()" :disabled="chosen.length === 0 || busy"
                            class="w-full h-12 rounded-xl text-white font-bold disabled:opacity-40"
                            style="background: linear-gradient(120deg, #0EA5E9, #6366F1);" x-text="addLabel"></button>
                </div>
            </div>
        </div>
    </template>
</div>

@push('scripts')
@include('event-sparring::manage.runtime')
@endpush
@endsection
