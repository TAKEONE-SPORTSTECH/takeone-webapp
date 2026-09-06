@extends('layouts.app')

@section('title', __('event-open_mat::messages.label').' · '.$e['title'])

{{--
    Open Mat console — desktop.

    The same two boxes and one button as the phone, laid out for a laptop at the
    mat side: the corners sit side by side as they do on the mat itself, red on
    the left, blue on the right, with the swap between them. The code and the
    history take the column beside them, because on a wide screen there is room
    to show the join code permanently rather than only inside the sheet.

    Same Alpine component, same endpoints, same sheet — one implementation with
    two layouts, never two behaviours.
--}}

@section('content')
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

{{-- The page supplies its own wrapper padding: layouts.app's <main> has none,
     and the band below cancels `px-4 sm:px-6 lg:px-8 py-6` with negative margins.
     Without the wrapper the band overhung the viewport and every card under it
     sat flush against the screen edges. --}}
<div class="px-4 sm:px-6 lg:px-8 py-6" x-data="openMatConsole(@js($boot))" x-init="init()">

    {{-- ══════════════ Hero ══════════════ --}}
    <header class="-mx-4 sm:-mx-6 lg:-mx-8 -mt-6 px-8 pt-6 pb-20 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $omColor }}, {{ $omColor }}b0);">
        <div class="absolute -end-12 -top-12 w-56 h-56 rounded-full bg-white/10"></div>
        <div class="absolute end-24 bottom-6 w-28 h-28 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between relative z-50">
            <a href="{{ route('me.events') }}"
               class="inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold"
           aria-label="{{ __('nav.events') }}" title="{{ __('nav.events') }}">
                <i class="bi bi-chevron-left"></i>
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
            <h1 class="text-3xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-building"></i>{{ $e['club'] ?? '' }}
            </p>
        </div>
    </header>

    <div class="-mt-12 relative z-10 space-y-6 pb-10">

        {{-- ══════════════ Mats ══════════════ --}}
        <section class="bg-white rounded-xl shadow-sm border border-gray-100 p-4" x-show="mats.length > 1 || ! closed">
            <div class="flex items-center gap-3 flex-wrap">
                <p class="text-xs font-bold uppercase tracking-wider text-muted-foreground">{{ __('event-open_mat::messages.console_mats') }}</p>
                <template x-for="m in mats" :key="m">
                    <button type="button" @click="mat = m"
                            class="px-4 h-10 rounded-lg font-bold text-sm border transition-colors"
                            :class="mat === m ? 'border-primary bg-primary text-white' : 'border-border bg-white text-foreground hover:bg-muted/40'"
                            x-text="m"></button>
                </template>
                <button type="button" x-show="! closed && mats.length < maxMats" @click="addMat()"
                        class="h-10 px-3 rounded-lg border border-dashed border-border text-xs font-bold text-primary inline-flex items-center gap-1">
                    <i class="bi bi-plus-lg"></i>{{ __('event-open_mat::messages.console_add_mat') }}
                </button>

                <div class="ms-auto flex items-center gap-2" x-show="! closed && ! sportLocked && sports.length > 1">
                    <p class="text-xs font-bold uppercase tracking-wider text-muted-foreground">{{ __('event-open_mat::messages.console_sport') }}</p>
                    <template x-for="s in sports" :key="s.key">
                        <button type="button" @click="setSport(s.key)"
                                class="px-4 h-10 rounded-lg font-bold text-sm border transition-colors inline-flex items-center gap-2"
                                :class="sport === s.key ? 'border-primary bg-primary text-white' : 'border-border bg-white text-foreground hover:bg-muted/40'">
                            <i :class="s.icon"></i><span x-text="s.label"></span>
                        </button>
                    </template>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

            {{-- ══════════════ The two corners ══════════════ --}}
            <section class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="grid grid-cols-1 md:grid-cols-[1fr_auto_1fr] gap-4 items-center">

                    {{-- Red --}}
                    <button type="button" @click="openSheet('aka')" :disabled="closed"
                            class="rounded-2xl p-5 text-start relative overflow-hidden border-2 transition-colors min-h-[168px]"
                            :class="aka ? 'border-rose-500 bg-rose-50' : 'border-dashed border-rose-300 bg-white hover:bg-rose-50/40'">
                        <span class="absolute -end-8 -top-8 w-32 h-32 rounded-full bg-rose-500/10"></span>
                        <span class="relative block">
                            <span class="text-[10px] font-black uppercase tracking-wider text-rose-500">{{ __('event-open_mat::messages.console_red') }}</span>
                            <span class="flex items-center gap-3 mt-2.5">
                                <span class="w-16 h-[85px] rounded-xl overflow-hidden bg-rose-100 flex-shrink-0 grid place-items-center">
                                    <template x-if="aka"><img :src="aka.photo || aka.fallback" :alt="aka.name" class="w-full h-full object-cover"></template>
                                    <template x-if="! aka"><i class="bi bi-plus-lg text-rose-400 text-3xl"></i></template>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-xl font-black text-rose-700 truncate"
                                          x-text="aka ? aka.name : '{{ __('event-open_mat::messages.console_empty_corner') }}'"></span>
                                    <span class="block text-xs text-rose-600/70 mt-1"
                                          x-text="aka ? (aka.club || (aka.member ? '{{ __('event-open_mat::messages.fill_search_tab') }}' : '{{ __('event-open_mat::messages.fill_guest_tab') }}')) : '{{ __('event-open_mat::messages.console_tap_to_fill') }}'"></span>
                                    <template x-if="aka && aka.record && aka.record.fought">
                                        <span class="inline-flex items-center gap-1.5 mt-1.5 px-2 py-0.5 rounded-full bg-rose-500/10 text-[11px] font-black text-rose-700">
                                            <i class="bi bi-fire"></i>
                                            <span x-text="aka.record.won + '\u2013' + aka.record.lost"></span>
                                        </span>
                                    </template>
                                    <template x-if="aka && aka.country">
                                        <img :src="'https://flagcdn.com/w40/' + aka.country + '.png'" alt="" class="w-8 rounded shadow-sm mt-2">
                                    </template>
                                </span>
                            </span>
                        </span>
                    </button>

                    {{-- Swap --}}
                    <div class="flex md:flex-col items-center justify-center gap-2">
                        <span class="hidden md:block text-2xl font-black text-muted-foreground">VS</span>
                        <button type="button" @click="swap()" :disabled="closed || (! aka && ! ao)"
                                class="w-11 h-11 rounded-full bg-white border border-border grid place-items-center text-muted-foreground disabled:opacity-40 hover:bg-muted/40 transition-colors"
                                title="{{ __('event-open_mat::messages.console_swap') }}">
                            <i class="bi bi-arrow-left-right"></i>
                        </button>
                    </div>

                    {{-- Blue --}}
                    <button type="button" @click="openSheet('ao')" :disabled="closed"
                            class="rounded-2xl p-5 text-start relative overflow-hidden border-2 transition-colors min-h-[168px]"
                            :class="ao ? 'border-blue-500 bg-blue-50' : 'border-dashed border-blue-300 bg-white hover:bg-blue-50/40'">
                        <span class="absolute -end-8 -bottom-8 w-32 h-32 rounded-full bg-blue-500/10"></span>
                        <span class="relative block">
                            <span class="text-[10px] font-black uppercase tracking-wider text-blue-500">{{ __('event-open_mat::messages.console_blue') }}</span>
                            <span class="flex items-center gap-3 mt-2.5">
                                <span class="w-16 h-[85px] rounded-xl overflow-hidden bg-blue-100 flex-shrink-0 grid place-items-center">
                                    <template x-if="ao"><img :src="ao.photo || ao.fallback" :alt="ao.name" class="w-full h-full object-cover"></template>
                                    <template x-if="! ao"><i class="bi bi-plus-lg text-blue-400 text-3xl"></i></template>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-xl font-black text-blue-700 truncate"
                                          x-text="ao ? ao.name : '{{ __('event-open_mat::messages.console_empty_corner') }}'"></span>
                                    <span class="block text-xs text-blue-600/70 mt-1"
                                          x-text="ao ? (ao.club || (ao.member ? '{{ __('event-open_mat::messages.fill_search_tab') }}' : '{{ __('event-open_mat::messages.fill_guest_tab') }}')) : '{{ __('event-open_mat::messages.console_tap_to_fill') }}'"></span>
                                    <template x-if="ao && ao.record && ao.record.fought">
                                        <span class="inline-flex items-center gap-1.5 mt-1.5 px-2 py-0.5 rounded-full bg-blue-500/10 text-[11px] font-black text-blue-700">
                                            <i class="bi bi-fire"></i>
                                            <span x-text="ao.record.won + '\u2013' + ao.record.lost"></span>
                                        </span>
                                    </template>
                                    <template x-if="ao && ao.country">
                                        <img :src="'https://flagcdn.com/w40/' + ao.country + '.png'" alt="" class="w-8 rounded shadow-sm mt-2">
                                    </template>
                                </span>
                            </span>
                        </span>
                    </button>
                </div>

                <div class="mt-6" x-show="! closed">
                    <a x-show="liveBout" :href="controlUrls[mat]"
                       class="w-full h-14 rounded-xl text-white font-black text-base inline-flex items-center justify-center gap-2"
                       style="background: linear-gradient(120deg, #16A34A, #0EA5E9);">
                        <i class="bi bi-sliders"></i>{{ __('event-open_mat::messages.console_resume') }}
                    </a>
                    <button type="button" x-show="! liveBout" @click="start()" :disabled="! ready || busy"
                            class="w-full h-14 rounded-xl text-white font-black text-base disabled:opacity-40 inline-flex items-center justify-center gap-2 transition-opacity"
                            style="background: linear-gradient(120deg, #F97316, #EF4444);">
                        <template x-if="! busy">
                            <span class="inline-flex items-center gap-2"><i class="bi bi-play-circle-fill"></i>{{ __('event-open_mat::messages.console_start') }}</span>
                        </template>
                        <template x-if="busy"><i class="bi bi-arrow-repeat animate-spin"></i></template>
                    </button>
                </div>
            </section>

            {{-- ══════════════ Join code ══════════════ --}}
            <section class="bg-white rounded-xl shadow-sm border border-gray-100 p-6" x-show="! closed && code">
                <div class="flex items-start gap-3">
                    <span class="w-11 h-11 rounded-xl bg-primary/10 text-primary grid place-items-center flex-shrink-0">
                        <i class="bi bi-qr-code text-lg"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-foreground">{{ __('event-open_mat::messages.fill_code_tab') }}</p>
                        <p class="text-xs text-muted-foreground mt-0.5 leading-relaxed">{{ __('event-open_mat::messages.fill_code_hint') }}</p>
                    </div>
                </div>

                <p class="mt-4 text-center text-3xl font-black tracking-[0.3em] text-foreground select-all" x-text="code"></p>
                <template x-if="qrUrl">
                    <div class="mt-4 flex justify-center">
                        <img :src="qrUrl" alt="" class="w-44 h-44 rounded-xl bg-white border border-border">
                    </div>
                </template>

                <div class="mt-4 flex gap-2">
                    <button type="button" @click="copyJoin()" class="flex-1 h-10 rounded-lg bg-muted text-foreground font-bold text-xs inline-flex items-center justify-center gap-1.5 hover:bg-accent transition-colors">
                        <i class="bi bi-link-45deg"></i>{{ __('event-open_mat::messages.fill_code_copy') }}
                    </button>
                    <button type="button" @click="newCode()" class="flex-1 h-10 rounded-lg bg-muted text-foreground font-bold text-xs inline-flex items-center justify-center gap-1.5 hover:bg-accent transition-colors">
                        <i class="bi bi-arrow-repeat"></i>{{ __('event-open_mat::messages.fill_code_new') }}
                    </button>
                </div>
            </section>
        </div>

        {{-- ══════════════ Fought here ══════════════ --}}
        <section class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <p class="text-xs font-bold uppercase tracking-wider text-muted-foreground">{{ __('event-open_mat::messages.console_history') }}</p>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                <template x-for="b in matBouts" :key="b.id">
                    <div class="flex items-center gap-3 rounded-xl bg-muted/50 p-3">
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
            <p x-show="matBouts.length === 0" class="text-sm text-muted-foreground text-center py-6">{{ __('event-open_mat::messages.console_history_empty') }}</p>
        </section>

        {{-- ══════════════ Hall screens, cameras, and what they broadcast ══
             One panel, one block per mat: the boards on it, the phones filming
             it, and whether it is on air. ══════════════ --}}
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

    @include('event-open_mat::manage.fill-sheet')
</div>

@include('event-open_mat::manage.runtime')
@endsection
