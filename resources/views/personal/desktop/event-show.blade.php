@extends('layouts.app')

@section('title', $e['title'])

@php
    $pPaid   = !str_contains(strtolower($e['participant_fee']), 'free') && !str_contains(strtolower($e['participant_fee']), 'qualified');
    $byQual  = str_contains(strtolower($e['participant_fee']), 'qualified');
    $hasTicket = !empty($e['spectator']);
    $ticketPaid = $hasTicket && !str_contains(strtolower($e['spectator']['fee']), 'free');
@endphp

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-6" @include('partials.event-show-script')>

    @include('partials.personal-desktop-subnav')

    <a href="{{ route('me.events') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-muted-foreground hover:text-primary transition-colors mb-4">
        <i class="bi bi-arrow-left"></i> {{ __('personal.personal_events_title') }}
    </a>

    {{-- ===== Cover ===== --}}
    <div class="rounded-2xl overflow-hidden shadow-sm mb-6 text-white relative" style="background: linear-gradient(150deg, {{ $e['color'] }}, {{ $e['color'] }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>
        <div class="relative p-6 sm:p-8">
            <div class="flex items-center justify-end gap-2 mb-4">
                <x-qr-code
                    :url="route('me.events.show', ['event' => $e['key']])"
                    :title="$e['title'] . ' — ' . __('personal.event_show_event')"
                    caption="{{ __('personal.event_show_qr_caption') }}"
                    :filename="'qr-event-' . $e['key']"
                    label=""
                    icon="bi-qr-code"
                    :poster-url="route('qr.event', ['event' => $e['key']])"
                    button-class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center text-white hover:bg-white/25 transition-colors" />
                <button type="button" @click="$dispatch('share-event')"
                        class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center hover:bg-white/25 transition-colors" aria-label="{{ __('personal.event_show_share') }}">
                    <i class="bi bi-share text-base"></i>
                </button>
                @if($canManage ?? false)
                    <div class="relative" @click.outside="manageOpen=false">
                        <button type="button" @click="manageOpen=!manageOpen"
                                class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center hover:bg-white/25 transition-colors" aria-label="{{ __('personal.event_show_manage') }}">
                            <i class="bi bi-three-dots-vertical text-base"></i>
                        </button>
                        <div x-show="manageOpen" x-cloak
                             x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                             class="absolute end-0 top-12 z-40 w-48 bg-white rounded-xl shadow-lg border border-gray-100 py-1 text-foreground"
                             style="transform-origin: top right;">
                            <button type="button" @click="goEdit()"
                                    class="w-full text-start flex items-center gap-2.5 px-3 py-2 text-sm hover:bg-muted transition-colors">
                                <i class="bi bi-pencil"></i> {{ __('personal.event_show_edit_event') }}
                            </button>
                            @if(!($isTkd ?? false))
                                <button type="button" @click="openResults()"
                                        class="w-full text-start flex items-center gap-2.5 px-3 py-2 text-sm hover:bg-muted transition-colors">
                                    <i class="bi bi-trophy"></i> {{ __('personal.event_show_set_winners') }}
                                </button>
                            @endif
                            <button type="button" @click="cancelEvent()" x-show="!cancelled"
                                    class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-amber-600 hover:bg-amber-50 transition-colors">
                                <i class="bi bi-slash-circle"></i> {{ __('personal.event_show_cancel_event') }}
                            </button>
                            <button type="button" @click="deleteEvent()"
                                    class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                <i class="bi bi-trash"></i> {{ __('personal.event_show_delete_event') }}
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            <div x-show="cancelled" x-cloak class="mb-4 rounded-xl bg-white/20 backdrop-blur px-3 py-2 text-xs font-bold flex items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill"></i> {{ __('personal.event_show_cancelled_banner') }}
            </div>

            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi {{ $e['icon'] }}"></i> {{ $e['type'] }}
                </span>
                @if(!empty($e['sport_label']))
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi {{ $e['sport_icon'] ?? 'bi-dribbble' }}"></i> {{ $e['sport_label'] }}</span>
                @endif
                @if(($e['scope'] ?? 'internal') !== 'internal' && !empty($e['scope_label']))
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-broadcast"></i> {{ $e['scope_label'] }}</span>
                @endif
                @if($pPaid || $byQual)
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-cash-coin"></i> {{ __('personal.event_show_paid_entry') }}</span>
                @endif
                @if($ticketPaid)
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-ticket-perforated"></i> {{ __('personal.event_show_ticketed') }}</span>
                @endif
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-building"></i>{{ $e['club'] }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6 items-start">
        {{-- ===== Left: content ===== --}}
        <div class="space-y-4 min-w-0">

            {{-- Winners --}}
            <div x-show="results.length > 0" x-cloak class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-trophy-fill text-amber-500"></i> {{ __('personal.event_show_winners') }}</h2>
                    @if($canManage ?? false)
                        <button type="button" @click="openResults()" class="text-xs font-bold text-primary px-2 py-1 rounded-lg bg-accent hover:bg-accent/70 transition-colors"><i class="bi bi-pencil"></i> {{ __('shared.edit') }}</button>
                    @endif
                </div>
                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <template x-for="w in results" :key="w.place + '-' + w.name">
                        <div class="flex items-center gap-3 rounded-xl p-2.5" :style="`background:${medal(w.place)}12`">
                            <div class="w-9 h-9 rounded-full grid place-items-center text-white flex-shrink-0 font-black text-xs" :style="`background:${medal(w.place)}`">
                                <i class="bi" :class="w.place===1 ? 'bi-trophy-fill' : (w.place<=3 ? 'bi-award-fill' : 'bi-award')" x-show="w.place<=3"></i>
                                <span x-show="w.place>3" x-text="w.place"></span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-foreground truncate" x-text="w.name"></p>
                                <p class="text-xs text-muted-foreground" x-text="w.place===1 ? '{{ __("personal.event_show_champion") }}' : (w.place===2 ? '{{ __("personal.event_show_runner_up") }}' : (w.place===3 ? '{{ __("personal.event_show_third_place") }}' : ('#' + w.place)))"></p>
                            </div>
                            <span class="text-xs font-black flex-shrink-0" :style="`color:${medal(w.place)}`" x-text="w.prize"></span>
                        </div>
                    </template>
                </div>
            </div>

            @if(($canManage ?? false) && !($isTkd ?? false))
                <div x-show="results.length === 0">
                    <button type="button" @click="openResults()"
                            class="w-full py-3 rounded-2xl border-2 border-dashed border-gray-200 text-sm font-bold text-foreground flex items-center justify-center gap-2 hover:border-gray-300 transition-colors">
                        <i class="bi bi-trophy"></i> {{ __('personal.event_show_record_winners') }}
                    </button>
                </div>
            @endif

            @if(!empty($e['bracket_results']) || ($finance ?? false))
                <div class="flex gap-2">
                    @if(!empty($e['bracket_results']))
                        <button type="button" @click="showResultsOpen=true"
                                class="flex-1 py-3 rounded-2xl text-white text-sm font-bold flex items-center justify-center gap-2 hover:opacity-90 transition-opacity" style="background: {{ $e['color'] }};">
                            <i class="bi bi-trophy-fill"></i> {{ __('personal.event_show_show_results') }}
                        </button>
                    @endif
                    @if($finance ?? false)
                        <button type="button" @click="financeOpen=true"
                                class="flex-1 py-3 rounded-2xl border-2 text-sm font-bold flex items-center justify-center gap-2 hover:bg-gray-50 transition-colors"
                                style="border-color: {{ $e['color'] }}; color: {{ $e['color'] }};">
                            <i class="bi bi-cash-stack"></i> {{ __('personal.event_show_finance') }}
                        </button>
                    @endif
                </div>
            @endif

            {{-- About --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-info-circle text-primary"></i> {{ __('personal.event_show_about') }}</h2>
                <p class="text-sm text-muted-foreground leading-relaxed mt-2">{{ $e['about'] }}</p>
                <div class="flex flex-wrap gap-2 mt-3">
                    @foreach($e['tags'] as $t)
                        <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-muted text-muted-foreground">#{{ $t }}</span>
                    @endforeach
                </div>
            </div>

            {{-- Prize & divisions --}}
            @if(!empty($e['prize']) || !empty($e['divisions']))
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-trophy text-primary"></i> {{ __('personal.event_show_prize_divisions') }}</h2>
                    @if(!empty($e['prize']))
                        <div class="mt-3 rounded-xl p-3 flex items-center gap-3" style="background: {{ $e['color'] }}0d; border: 1px solid {{ $e['color'] }}26;">
                            <div class="w-10 h-10 rounded-xl grid place-items-center text-white flex-shrink-0" style="background: {{ $e['color'] }};"><i class="bi bi-award-fill text-lg"></i></div>
                            <div>
                                <p class="text-[10px] text-muted-foreground uppercase tracking-wide">{{ __('personal.event_show_prize_pool') }}</p>
                                <p class="text-sm font-black text-foreground">{{ $e['prize'] }}</p>
                            </div>
                        </div>
                    @endif
                    @if(!empty($e['divisions']))
                        <div class="flex flex-wrap gap-2 mt-3">
                            @foreach($e['divisions'] as $d)
                                <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-muted text-foreground">{{ $d }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            {{-- League --}}
            @if(!empty($e['league']))
                @php $lg = $e['league']; @endphp
                @if(!empty($lg['standings']))
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                        <h2 class="text-sm font-bold text-foreground flex items-center gap-2 mb-3"><i class="bi bi-table text-primary"></i> {{ __('personal.event_show_standings') }}</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-muted-foreground text-xs uppercase tracking-wide">
                                        <th class="text-start font-semibold pb-2 ps-1">#</th>
                                        <th class="text-start font-semibold pb-2">{{ __('personal.event_show_team') }}</th>
                                        <th class="font-semibold pb-2 w-8">{{ __('personal.event_show_col_played') }}</th>
                                        <th class="font-semibold pb-2 w-8">{{ __('personal.event_show_col_won') }}</th>
                                        <th class="font-semibold pb-2 w-8">{{ __('personal.event_show_col_drawn') }}</th>
                                        <th class="font-semibold pb-2 w-8">{{ __('personal.event_show_col_lost') }}</th>
                                        <th class="font-semibold pb-2 w-10">{{ __('personal.event_show_col_gd') }}</th>
                                        <th class="font-semibold pb-2 w-10 text-end pe-1">{{ __('personal.event_show_col_pts') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($lg['standings'] as $i => $row)
                                        <tr class="border-t border-gray-50 {{ $i < 3 ? 'font-semibold' : '' }}">
                                            <td class="py-2 ps-1 text-muted-foreground">{{ $i + 1 }}</td>
                                            <td class="py-2 text-foreground truncate max-w-[160px]">{{ $row['team'] }}</td>
                                            <td class="py-2 text-center text-muted-foreground">{{ $row['p'] }}</td>
                                            <td class="py-2 text-center text-muted-foreground">{{ $row['w'] }}</td>
                                            <td class="py-2 text-center text-muted-foreground">{{ $row['d'] }}</td>
                                            <td class="py-2 text-center text-muted-foreground">{{ $row['l'] }}</td>
                                            <td class="py-2 text-center text-muted-foreground">{{ $row['gd'] > 0 ? '+' : '' }}{{ $row['gd'] }}</td>
                                            <td class="py-2 text-end pe-1 font-black" style="color: {{ $e['color'] }};">{{ $row['pts'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if(!empty($lg['fixtures']))
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                        <h2 class="text-sm font-bold text-foreground flex items-center gap-2 mb-3"><i class="bi bi-calendar2-week text-primary"></i> {{ __('personal.event_show_fixtures') }}</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach($lg['fixtures'] as $f)
                                @php $played = $f['home_score'] !== null && $f['away_score'] !== null; @endphp
                                <div class="flex items-center gap-2 rounded-xl bg-muted/40 px-3 py-2">
                                    <span class="flex-1 text-end text-sm font-semibold text-foreground truncate">{{ $f['home'] }}</span>
                                    @if($played)
                                        <span class="px-2 py-0.5 rounded-lg text-xs font-black text-white" style="background: {{ $e['color'] }};">{{ $f['home_score'] }} – {{ $f['away_score'] }}</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-lg text-xs font-bold bg-white text-muted-foreground border border-gray-100">{{ $f['date'] ?: __('personal.event_show_vs') }}</span>
                                    @endif
                                    <span class="flex-1 text-start text-sm font-semibold text-foreground truncate">{{ $f['away'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif

            {{-- Requirements --}}
            @if(!empty($e['requirements']))
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-clipboard-check text-primary"></i> {{ __('personal.event_show_requirements') }}</h2>
                    <ul class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2.5">
                        @foreach($e['requirements'] as $req)
                            <li class="flex items-start gap-2.5 text-sm text-muted-foreground">
                                <span class="w-5 h-5 rounded-full grid place-items-center flex-shrink-0 mt-0.5 text-white text-[10px]" style="background: {{ $e['color'] }};"><i class="bi bi-check-lg"></i></span>
                                <span>{{ $req }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Tournament timeline --}}
            @if(!empty($e['phases']))
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-signpost-split text-primary"></i> Tournament timeline</h2>
                    <div class="mt-3">
                        @foreach($e['phases'] as $i => $ph)
                            @php
                                $pdate  = !empty($ph['date']) ? rescue(fn () => \Carbon\Carbon::parse($ph['date']), null, false) : null;
                                $today  = \Carbon\Carbon::today();
                                $done   = $pdate && $pdate->lt($today);
                                $active = $pdate && $pdate->isSameDay($today);
                                $dot = $done ? '#10b981' : ($active ? $e['color'] : '#d1d5db');
                            @endphp
                            <div class="flex gap-3">
                                <div class="flex flex-col items-center">
                                    <span class="w-7 h-7 rounded-full grid place-items-center text-white text-[11px] flex-shrink-0" style="background: {{ $dot }};">
                                        <i class="bi {{ $done ? 'bi-check-lg' : $ph['icon'] }}"></i>
                                    </span>
                                    @if(!$loop->last)<span class="w-0.5 flex-1 my-1" style="background: {{ $done ? '#10b981' : '#e5e7eb' }};"></span>@endif
                                </div>
                                <div class="pb-4 -mt-0.5 min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-sm font-bold {{ $active ? '' : 'text-foreground' }}" style="{{ $active ? 'color:'.$e['color'] : '' }}">{{ $ph['label'] }}</p>
                                        <span class="text-xs font-semibold text-muted-foreground flex-shrink-0">{{ $pdate ? $pdate->format('M j') : '' }}</span>
                                    </div>
                                    <p class="text-xs text-muted-foreground">{{ $ph['note'] }}</p>
                                    @if($active)<span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[9px] font-bold text-white" style="background: {{ $e['color'] }};">NOW</span>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Brackets & draws --}}
            @if(!empty($e['categories']))
                @php
                    $catCount = count($e['categories']);
                    $athleteTotal = collect($e['categories'])->sum('joined');
                @endphp
                <a href="{{ route('me.events.bracket', $e['key']) }}"
                   class="block rounded-2xl p-5 text-white relative overflow-hidden shadow-md hover:shadow-lg transition-shadow"
                   style="background: linear-gradient(135deg, {{ $e['color'] }}, #1f2937);">
                    <div class="absolute -right-6 -top-6 w-28 h-28 rounded-full bg-white/10"></div>
                    <div class="relative flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                            <i class="bi bi-diagram-3-fill text-2xl"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-black text-base leading-tight">{{ __('personal.event_show_brackets_draws') }}</h3>
                            <p class="text-xs text-white/85 mt-0.5">{{ $catCount }} {{ \Illuminate\Support\Str::plural(strtolower($e['division_label'] ?? 'category'), $catCount) }} · {{ $athleteTotal }} {{ __('personal.event_show_entrants') }} · {{ __('personal.event_show_live_results') }}</p>
                        </div>
                        <i class="bi bi-chevron-right text-white/80"></i>
                    </div>
                </a>
            @endif

            {{-- Agenda --}}
            @if(!empty($e['agenda']))
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-list-check text-primary"></i> {{ __('personal.event_show_schedule') }}</h2>
                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-8">
                    @foreach($e['agenda'] as $i => $a)
                        <div class="flex gap-3">
                            <div class="flex flex-col items-center">
                                <span class="w-3 h-3 rounded-full flex-shrink-0" style="background: {{ $e['color'] }};"></span>
                                @if(!$loop->last)<span class="w-0.5 flex-1 bg-gray-100 my-1"></span>@endif
                            </div>
                            <div class="pb-4 -mt-1">
                                @php $at = !empty($a['t']) ? rescue(fn () => \Carbon\Carbon::parse($a['t']), null, false) : null; @endphp
                                <p class="text-xs font-bold text-foreground">{{ $at ? $at->format('M j · g:i A') : $a['t'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $a['d'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Participants --}}
            <div>
                @php $showTabs = $hasTicket || ($canManage ?? false); @endphp
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5" x-data="{ rtab: 'participants' }">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold text-foreground flex items-center gap-2">
                            <i class="bi bi-people text-primary"></i> {{ $byQual ? __('personal.event_show_finalists') : __('personal.event_show_whos_joined') }}
                        </h2>
                        @unless($showTabs)
                            <span class="text-xs font-semibold text-primary" x-text="`${goingCount} {{ __('personal.event_show_in') }}`">{{ $e['participants_total'] ?? $e['going'] }} {{ __('personal.event_show_in') }}</span>
                        @endunless
                    </div>

                    @if($showTabs)
                        <div class="flex gap-2 mt-3 overflow-x-auto">
                            <button type="button" @click="rtab='participants'"
                                    class="flex-1 py-1.5 rounded-lg text-xs font-bold border-2 transition-colors flex items-center justify-center gap-1.5 whitespace-nowrap"
                                    :class="rtab==='participants' ? 'border-primary bg-accent text-primary' : 'border-gray-200 text-muted-foreground hover:border-gray-300'">
                                <i class="bi bi-person-arms-up"></i> {{ __('personal.event_show_participants') }}
                                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-primary/10 text-primary" x-text="goingCount">{{ $e['participants_total'] }}</span>
                            </button>
                            @if($hasTicket)
                                <button type="button" @click="rtab='spectators'"
                                        class="flex-1 py-1.5 rounded-lg text-xs font-bold border-2 transition-colors flex items-center justify-center gap-1.5 whitespace-nowrap"
                                        :class="rtab==='spectators' ? 'border-primary bg-accent text-primary' : 'border-gray-200 text-muted-foreground hover:border-gray-300'">
                                    <i class="bi bi-eye"></i> {{ __('personal.event_show_spectators') }}
                                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-primary/10 text-primary" x-text="spectators">{{ $e['spectators_total'] }}</span>
                                </button>
                            @endif
                            @if($canManage ?? false)
                                <button type="button" @click="rtab='blocked'"
                                        class="flex-1 py-1.5 rounded-lg text-xs font-bold border-2 transition-colors flex items-center justify-center gap-1.5 whitespace-nowrap"
                                        :class="rtab==='blocked' ? 'border-primary bg-accent text-primary' : 'border-gray-200 text-muted-foreground hover:border-gray-300'">
                                    <i class="bi bi-shield-x"></i> {{ __('personal.event_show_blocked') }}
                                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-primary/10 text-primary" x-text="blockedCount">{{ count($e['bans_list'] ?? []) }}</span>
                                </button>
                            @endif
                        </div>
                    @endif

                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2.5" @if($showTabs) x-show="rtab==='participants'" x-transition @endif>
                        @forelse($e['participants'] as $i => $pp)
                            @php $initials = collect(explode(' ', $pp['name']))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode(''); @endphp
                            <div class="flex items-center gap-3" @if($pp['id'] ?? false) id="prow-{{ $pp['id'] }}" @endif>
                                <div class="w-9 h-9 rounded-full grid place-items-center text-white text-[11px] font-bold flex-shrink-0"
                                     style="background: hsl({{ ($i * 67) % 360 }} 55% 58%);">{{ $initials }}</div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-foreground truncate">{{ $pp['name'] }}</p>
                                    @php
                                        $bits = array_filter([
                                            $pp['gender'] ?? null,
                                            $pp['category'] ?? null,
                                            $pp['weight_class'] ?? null,
                                        ]);
                                    @endphp
                                    <p class="text-xs text-muted-foreground truncate">{{ $bits ? implode(' · ', $bits) : $pp['meta'] }}</p>
                                </div>
                                @if(($pp['paid'] ?? true))
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-50 text-green-600 flex-shrink-0"><i class="bi bi-check2"></i> {{ __('personal.event_show_joined') }}</span>
                                @else
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-600 flex-shrink-0"><i class="bi bi-hourglass-split"></i> {{ __('personal.event_show_pending') }}</span>
                                @endif
                                @if(($canManage ?? false) && ($pp['id'] ?? false))
                                    <x-event-moderate-menu :id="$pp['id']" :name="$pp['name']" />
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-muted-foreground text-center py-3 sm:col-span-2">{{ __('personal.event_show_no_competitors') }}</p>
                        @endforelse
                        @php $more = max(($e['participants_total'] ?? count($e['participants'])) - count($e['participants']), 0); @endphp
                        @if($more > 0)
                            <p class="text-xs text-muted-foreground text-center pt-1 sm:col-span-2">+ {{ $more }} {{ __('personal.event_show_more') }}</p>
                        @endif
                    </div>

                    @if($hasTicket)
                        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2.5" x-show="rtab==='spectators'" x-cloak x-transition>
                            @forelse($e['spectators_list'] as $i => $sp)
                                @php $sinitials = collect(explode(' ', $sp['name']))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode(''); @endphp
                                <div class="flex items-center gap-3" @if($sp['id'] ?? false) id="srow-{{ $sp['id'] }}" @endif>
                                    <div class="w-9 h-9 rounded-full grid place-items-center text-white text-[11px] font-bold flex-shrink-0"
                                         style="background: hsl({{ (($i + 3) * 53) % 360 }} 45% 60%);">{{ $sinitials }}</div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-foreground truncate">{{ $sp['name'] }}</p>
                                        <p class="text-xs text-muted-foreground truncate">{{ __('personal.event_show_spectator') }}{{ str_contains(strtolower($e['spectator']['fee']),'free') ? '' : ' · '.$e['spectator']['fee'] }}</p>
                                    </div>
                                    @if(($sp['paid'] ?? true))
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-sky-50 text-sky-600 flex-shrink-0"><i class="bi bi-ticket-perforated"></i> {{ str_contains(strtolower($e['spectator']['fee']),'free') ? __('personal.event_show_pass') : __('personal.event_show_ticket') }}</span>
                                    @else
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-600 flex-shrink-0"><i class="bi bi-hourglass-split"></i> {{ __('personal.event_show_pending') }}</span>
                                    @endif
                                    @if(($canManage ?? false) && ($sp['id'] ?? false))
                                        <x-event-moderate-menu :id="$sp['id']" :name="$sp['name']" />
                                    @endif
                                </div>
                            @empty
                                <p class="text-xs text-muted-foreground text-center py-3 sm:col-span-2">{{ __('personal.event_show_no_spectators') }}</p>
                            @endforelse
                            @php $smore = max(($e['spectators_total'] ?? 0) - count($e['spectators_list'] ?? []), 0); @endphp
                            @if($smore > 0)
                                <p class="text-xs text-muted-foreground text-center pt-1 sm:col-span-2">+ {{ $smore }} {{ __('personal.event_show_more') }}</p>
                            @endif
                        </div>
                    @endif

                    @if($canManage ?? false)
                        <div class="mt-3" x-show="rtab==='blocked'" x-cloak x-transition>
                            <div id="blocked-list" class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2.5">
                                @foreach($e['bans_list'] ?? [] as $bn)
                                    @php $binit = collect(explode(' ', $bn['name']))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode(''); @endphp
                                    <div id="brow-{{ $bn['id'] }}" class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full grid place-items-center text-white text-[11px] font-bold flex-shrink-0 bg-gray-400">{{ $binit }}</div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-semibold text-foreground truncate">{{ $bn['name'] }}</p>
                                            <p class="text-xs text-muted-foreground truncate">{{ $bn['scope'] === 'club' ? __('personal.event_show_blacklisted_scope') : __('personal.event_show_blocked_scope') }}</p>
                                        </div>
                                        <button type="button" @click="unblock({{ $bn['id'] }})"
                                                class="text-[10px] font-bold px-2.5 py-1 rounded-full border border-gray-200 text-foreground hover:bg-muted flex-shrink-0 transition-colors">
                                            <i class="bi bi-arrow-counterclockwise"></i> {{ __('personal.event_show_unblock') }}
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                            <p id="blocked-empty" class="text-xs text-muted-foreground text-center py-3" @if(count($e['bans_list'] ?? [])) style="display:none" @endif>{{ __('personal.event_show_no_blocked') }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Location --}}
            @php
                $locUrl = (is_string($e['location_url'] ?? null) && preg_match('#^https?://#i', $e['location_url'])) ? $e['location_url'] : null;
            @endphp
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                @if(!empty($e['lat']) && !empty($e['lng']))
                    <x-location-map
                        :id="'evtmapdesktop'.$e['id']"
                        :lat="$e['lat']" :lng="$e['lng']"
                        :draggable="false" :readonly="true" :show-address="false" :show-coords="false" :show-labels="false"
                        height="14rem" :zoom="15" map-class="bg-muted/30" />
                    <script>
                        (function () {
                            var id = 'evtmapdesktop{{ $e['id'] }}', lat = {{ $e['lat'] }}, lng = {{ $e['lng'] }}, tries = 0;
                            (function go() {
                                if (window.LocationMap) {
                                    window.LocationMap.create({ id: id, defaultLat: lat, defaultLng: lng, zoom: 15, draggable: false, readonly: true });
                                } else if (tries++ < 60) {
                                    setTimeout(go, 100);
                                }
                            })();
                        })();
                    </script>
                @elseif($locUrl)
                    <a href="{{ $locUrl }}" target="_blank" rel="noopener"
                       class="h-40 flex flex-col items-center justify-center gap-1 hover:opacity-90 transition-opacity" style="background: linear-gradient(135deg, {{ $e['color'] }}22, {{ $e['color'] }}11);">
                        <i class="bi bi-geo-alt-fill text-3xl" style="color: {{ $e['color'] }};"></i>
                        <span class="text-xs font-bold text-primary">{{ __('personal.event_show_open_google_maps') }}</span>
                    </a>
                @else
                    <div class="h-40 relative grid place-items-center"
                         style="background: linear-gradient(135deg, {{ $e['color'] }}22, {{ $e['color'] }}11);">
                        <i class="bi bi-geo-alt-fill text-3xl" style="color: {{ $e['color'] }};"></i>
                    </div>
                @endif
                <div class="p-5 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-foreground">{{ $e['location'] }}</p>
                        <p class="text-xs text-muted-foreground mt-0.5">{{ $e['address'] }}</p>
                    </div>
                    @php
                        $dirHref = (!empty($e['lat']) && !empty($e['lng']))
                            ? 'https://www.google.com/maps/dir/?api=1&destination=' . $e['lat'] . ',' . $e['lng']
                            : ($locUrl
                                ?: ($e['location'] && $e['location'] !== 'TBA' ? 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($e['location']) : null));
                    @endphp
                    @if($dirHref)
                        <a href="{{ $dirHref }}" target="_blank" rel="noopener"
                           class="flex-shrink-0 px-3 py-1.5 rounded-lg bg-accent text-primary text-xs font-bold flex items-center gap-1.5 hover:bg-accent/70 transition-colors">
                            <i class="bi bi-cursor"></i> {{ __('personal.event_show_directions') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- ===== Right sidebar ===== --}}
        <aside class="space-y-4 lg:sticky lg:top-20">
            {{-- Quick facts --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div>
                        <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-calendar3"></i></div>
                        <p class="text-xs font-bold text-foreground mt-1.5">{{ $e['wday'] }} {{ $e['day'] }}</p>
                        <p class="text-[10px] text-muted-foreground">{{ $e['mon'] }}</p>
                    </div>
                    <div class="border-x border-gray-100">
                        <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-clock"></i></div>
                        <p class="text-xs font-bold text-foreground mt-1.5">{{ $e['time'] }}</p>
                        <p class="text-[10px] text-muted-foreground">{{ $e['duration'] }}</p>
                    </div>
                    <div>
                        <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-cash-coin"></i></div>
                        <p class="text-xs font-bold text-foreground mt-1.5">{{ $e['participant_fee'] }}</p>
                        <p class="text-[10px] text-muted-foreground">{{ $byQual ? __('personal.event_show_entry') : __('personal.event_show_to_join') }}</p>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="flex items-center justify-between text-xs mb-1.5">
                        <span class="font-semibold text-foreground"><span x-text="goingCount">{{ $e['going'] }}</span> {{ __('personal.event_show_going') }}</span>
                        <span class="text-muted-foreground"><span x-text="cap - goingCount">{{ $e['cap'] - $e['going'] }}</span> {{ __('personal.event_show_spots_left') }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-muted overflow-hidden">
                        <div class="h-full rounded-full" :style="`width:${pct}%; background:{{ $e['color'] }}`" style="width: {{ round($e['going'] / $e['cap'] * 100) }}%"></div>
                    </div>
                </div>
            </div>

            {{-- Like / interest row --}}
            <div class="flex items-center gap-2">
                <button type="button" @click="toggleLike()"
                        class="flex-1 py-2.5 rounded-xl border text-sm font-semibold flex items-center justify-center gap-2 transition-colors"
                        :class="liked ? 'bg-red-50 border-red-200 text-red-600' : 'bg-white border-gray-100 text-muted-foreground hover:bg-gray-50'">
                    <i class="bi" :class="liked ? 'bi-heart-fill' : 'bi-heart'"></i>
                    <span x-text="likes">36</span>
                </button>
                <button type="button" @click="window.showToast('info','{{ __("personal.event_show_reminder_set") }}')"
                        class="flex-1 py-2.5 rounded-xl border border-gray-100 bg-white text-muted-foreground text-sm font-semibold flex items-center justify-center gap-2 hover:bg-gray-50 transition-colors">
                    <i class="bi bi-bell"></i> {{ __('personal.event_show_remind') }}
                </button>
            </div>

            {{-- Pricing & tickets --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-tag text-primary"></i> {{ __('personal.event_show_entry_tickets') }}</h2>

                <div class="mt-3 flex items-center gap-3 rounded-xl border border-gray-100 p-3">
                    <div class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 {{ $pPaid ? 'bg-amber-50 text-amber-600' : 'bg-green-50 text-green-600' }}"><i class="bi bi-person-check text-lg"></i></div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-foreground">{{ $byQual ? __('personal.event_show_take_part') : __('personal.event_show_join_participant') }}</p>
                        <p class="text-xs text-muted-foreground">
                            @if(!($canCompete ?? true)) <span class="text-amber-600 font-semibold">{{ __('personal.event_show_not_eligible_spectators') }}</span>
                            @elseif($byQual) {{ __('personal.event_show_reserved_finalists') }}
                            @elseif($pPaid) {{ __('personal.event_show_fee_paid_club') }}
                            @else {{ __('personal.event_show_free_members') }} @endif
                        </p>
                    </div>
                    <span class="text-sm font-black flex-shrink-0 {{ $pPaid ? 'text-amber-600' : 'text-foreground' }}">{{ $e['participant_fee'] }}</span>
                </div>

                @if($hasTicket)
                    <div class="mt-2 flex items-center gap-3 rounded-xl border border-gray-100 p-3">
                        <div class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 {{ $ticketPaid ? 'bg-purple-50 text-primary' : 'bg-sky-50 text-sky-600' }}"><i class="bi bi-ticket-perforated text-lg"></i></div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-foreground">{{ __('personal.event_show_spectator_ticket') }}</p>
                            <p class="text-xs text-muted-foreground"><span x-text="spectators">{{ $e['spectator']['count'] }}</span> {{ __('personal.event_show_watching') }}{{ $ticketPaid ? ' · '.__('personal.event_show_entry_watch_matches') : ' · '.__('personal.event_show_free_to_watch') }}</p>
                        </div>
                        <span class="text-sm font-black flex-shrink-0 {{ $ticketPaid ? 'text-primary' : 'text-sky-600' }}">{{ $e['spectator']['fee'] }}</span>
                    </div>
                    <button type="button" @click="toggleWatch()" :disabled="registered || {{ ($banned ?? false) ? 'true' : 'false' }}"
                            class="mt-3 w-full py-2.5 rounded-xl font-bold text-sm flex items-center justify-center gap-2 transition-colors border disabled:cursor-not-allowed disabled:opacity-60"
                            :class="watching ? 'bg-green-50 text-green-700 border-green-200' : (going ? 'bg-muted text-muted-foreground border-gray-100' : 'border-gray-200 text-foreground hover:bg-gray-50')">
                        <i class="bi" :class="watching ? 'bi-check2-circle' : 'bi-ticket-perforated'"></i>
                        <span x-text="watching ? '{{ __("personal.event_show_ticket_booked") }}' : (going ? '{{ __("personal.event_show_youre_participant") }}' : '{{ ($banned ?? false) ? __('personal.event_show_not_available') : ($ticketPaid ? __('personal.event_show_buy_ticket_watch', ['fee' => $e['spectator']['fee']]) : __('personal.event_show_get_free_pass')) }}')"></span>
                    </button>
                @endif
            </div>

            {{-- Join action --}}
            <div>
                @if($e['ended'] ?? false)
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center gap-3 text-muted-foreground">
                        <i class="bi bi-flag-fill text-lg"></i>
                        <div class="leading-tight">
                            <p class="text-sm font-bold text-foreground">{{ __('personal.event_show_ended_title') }}</p>
                            <p class="text-xs">{{ __('personal.event_show_ended_msg') }}</p>
                        </div>
                    </div>
                @elseif($banned ?? false)
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center gap-3">
                        <i class="bi bi-shield-x text-lg text-red-500"></i>
                        <div class="leading-tight">
                            <p class="text-sm font-bold text-foreground">{{ __('personal.event_show_cant_join_title') }}</p>
                            <p class="text-xs text-muted-foreground">{{ $eligReason ?? __('personal.event_show_removed_default') }}</p>
                        </div>
                    </div>
                @elseif($byQual)
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center gap-3">
                        <div class="leading-tight">
                            <p class="text-[10px] text-muted-foreground uppercase tracking-wide">{{ __('personal.event_show_ticket') }}</p>
                            <p class="text-base font-black text-foreground">{{ $hasTicket ? $e['spectator']['fee'] : '—' }}</p>
                        </div>
                        <button type="button" @click="toggleWatch()" :disabled="registered"
                                class="flex-1 py-3 rounded-xl font-bold text-sm flex items-center justify-center gap-2 transition-colors disabled:cursor-not-allowed hover:opacity-90"
                                :class="watching ? 'bg-green-50 text-green-700 border border-green-200' : 'text-white'"
                                :style="watching ? '' : 'background: {{ $e['color'] }}'">
                            <i class="bi" :class="watching ? 'bi-check2-circle' : 'bi-ticket-perforated'"></i>
                            <span x-text="watching ? '{{ __("personal.event_show_ticket_booked") }}' : '{{ __("personal.event_show_buy_ticket_watch_short") }}'"></span>
                        </button>
                    </div>
                @elseif(!($canCompete ?? true))
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                        <div class="flex items-start gap-2.5">
                            <i class="bi bi-info-circle-fill text-base mt-0.5" style="color: {{ $e['color'] }};"></i>
                            <p class="text-sm text-muted-foreground leading-snug">{{ $eligReason ?? __('personal.event_show_not_eligible_default') }}</p>
                        </div>
                        @if($hasTicket)
                            <div class="mt-3 flex items-center gap-3">
                                <div class="leading-tight">
                                    <p class="text-[10px] text-muted-foreground uppercase tracking-wide">{{ __('personal.event_show_ticket') }}</p>
                                    <p class="text-base font-black text-foreground">{{ $e['spectator']['fee'] }}</p>
                                </div>
                                <button type="button" @click="toggleWatch()" :disabled="registered"
                                        class="flex-1 py-3 rounded-xl font-bold text-sm flex items-center justify-center gap-2 transition-colors disabled:cursor-not-allowed hover:opacity-90"
                                        :class="watching ? 'bg-green-50 text-green-700 border border-green-200' : 'text-white'"
                                        :style="watching ? '' : 'background: {{ $e['color'] }}'">
                                    <i class="bi" :class="watching ? 'bi-check2-circle' : 'bi-ticket-perforated'"></i>
                                    <span x-text="watching ? '{{ __("personal.event_show_ticket_booked") }}' : '{{ __('personal.event_show_join_spectator') }}{{ $ticketPaid ? ' · '.$e['spectator']['fee'] : '' }}'"></span>
                                </button>
                            </div>
                        @else
                            <div class="mt-3 w-full py-3 rounded-xl bg-muted text-muted-foreground text-sm font-bold flex items-center justify-center gap-2">
                                <i class="bi bi-lock"></i> Spectating not available
                            </div>
                        @endif
                    </div>
                @else
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center gap-3">
                        <div class="leading-tight">
                            <p class="text-[10px] text-muted-foreground uppercase tracking-wide">{{ $pPaid ? __('personal.event_show_entry_fee') : __('personal.event_show_entry') }}</p>
                            <p class="text-base font-black text-foreground">{{ $e['participant_fee'] }}</p>
                        </div>
                        <button type="button" @click="toggleGoing()" :disabled="registered"
                                class="flex-1 py-3 rounded-xl font-bold text-sm flex items-center justify-center gap-2 transition-colors disabled:cursor-not-allowed hover:opacity-90"
                                :class="going ? 'bg-green-50 text-green-700 border border-green-200' : (watching ? 'bg-muted text-muted-foreground' : 'text-white')"
                                :style="(going || watching) ? '' : 'background: {{ $e['color'] }}'">
                            <i class="bi" :class="going ? 'bi-check2-circle' : (watching ? 'bi-ticket-perforated' : 'bi-plus-circle')"></i>
                            <span x-text="going ? '{{ __("personal.event_show_youre_going") }}' : (watching ? '{{ __("personal.event_show_youre_watching") }}' : '{{ $pPaid ? __('personal.event_show_register_fee', ['fee' => $e['participant_fee']]) : __('personal.event_show_join_event') }}')"></span>
                        </button>
                    </div>
                @endif

                <div x-show="joinedDivision" x-cloak class="mt-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-3 flex items-center gap-2 text-xs">
                    <i class="bi bi-diagram-3 text-primary"></i>
                    <span class="text-muted-foreground">{{ __('personal.event_show_placed_in') }} <span class="font-bold text-foreground" x-text="joinedDivision"></span></span>
                </div>
            </div>
        </aside>
    </div>

    <div x-init="$el.addEventListener('share-event-fired', () => {})"
         @share-event.window="
            if (navigator.share) { navigator.share({ title: '{{ addslashes($e['title']) }}', text: '{{ addslashes(__('personal.event_show_share_text', ['title' => $e['title']])) }}' }).catch(()=>{}); }
            else { window.showToast('success', '{{ __('personal.event_show_link_copied') }}'); }
         "></div>

    {{-- ===== Set-winners modal ===== --}}
    @if($canManage ?? false)
        <div x-show="resultsOpen" x-cloak class="fixed inset-0 z-[60]" style="display:none;">
            <div class="absolute inset-0 bg-black/40" @click="resultsOpen=false" x-transition.opacity></div>
            <div x-show="resultsOpen" x-cloak
                 x-transition:enter="transition ease-out duration-250" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 class="fixed inset-0 z-[60] flex items-center justify-center p-4">
                <div class="w-full max-w-lg max-h-[85vh] flex flex-col bg-white rounded-2xl shadow-2xl" @click.outside="resultsOpen=false">
                    <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-black text-foreground flex items-center gap-2"><i class="bi bi-trophy-fill text-amber-500"></i> {{ __('personal.event_show_winners_results') }}</h3>
                        <button type="button" @click="resultsOpen=false" class="w-8 h-8 rounded-full bg-muted grid place-items-center hover:bg-gray-200 transition-colors"><i class="bi bi-x-lg text-xs"></i></button>
                    </div>

                    <datalist id="event-participants">
                        @foreach($e['participants'] as $pp)
                            <option value="{{ $pp['name'] }}"></option>
                        @endforeach
                    </datalist>

                    <div class="flex-1 overflow-y-auto p-4 space-y-3">
                        <p class="text-xs text-muted-foreground">{{ __('personal.event_show_add_podium_help') }}</p>
                        <template x-for="(w, i) in winners" :key="i">
                            <div class="rounded-2xl border border-gray-100 p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-bold px-2 py-0.5 rounded-full text-white" :style="`background:${medal(w.place)}`"
                                          x-text="w.place===1 ? '{{ __("personal.event_show_place_1st") }}' : (w.place===2 ? '{{ __("personal.event_show_place_2nd") }}' : (w.place===3 ? '{{ __("personal.event_show_place_3rd") }}' : '#' + w.place))"></span>
                                    <button type="button" @click="removeWinner(i)" class="text-xs text-red-500 font-semibold hover:underline"><i class="bi bi-trash"></i> {{ __('personal.event_show_remove_btn') }}</button>
                                </div>
                                <input type="text" list="event-participants" x-model="w.name" placeholder="{{ __('personal.event_show_ph_winner_name') }}"
                                       class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm mb-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                                <div class="flex items-center gap-2">
                                    <input type="number" min="1" x-model="w.place" placeholder="{{ __('personal.event_show_ph_place') }}"
                                           class="w-20 px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                                    <input type="text" x-model="w.prize" placeholder="{{ __('personal.event_show_ph_prize') }}"
                                           class="flex-1 px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                                </div>
                            </div>
                        </template>
                        <button type="button" @click="addWinner()" class="w-full py-2.5 rounded-xl border-2 border-dashed border-gray-200 text-sm font-bold text-muted-foreground hover:border-gray-300 transition-colors">
                            <i class="bi bi-plus-lg"></i> {{ __('personal.event_show_add_place') }}
                        </button>
                    </div>

                    <div class="p-4 border-t border-gray-100">
                        <button type="button" @click="saveResults()" :disabled="busy"
                                class="w-full py-3.5 rounded-xl text-white font-bold text-sm flex items-center justify-center gap-2 disabled:opacity-60 hover:opacity-90 transition-opacity" style="background: {{ $e['color'] }};">
                            <i class="bi" :class="busy ? 'bi-arrow-repeat animate-spin' : 'bi-check2-circle'"></i>
                            <span x-text="busy ? '{{ __("personal.event_show_saving") }}' : '{{ __("personal.event_show_save_winners") }}'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== Results sheet (combat) ===== --}}
    @if(!empty($e['bracket_results']))
        <div x-show="showResultsOpen" x-cloak class="fixed inset-0 z-[60]" style="display:none;">
            <div class="absolute inset-0 bg-black/40" @click="showResultsOpen=false" x-transition.opacity></div>
            <div x-show="showResultsOpen" x-cloak
                 x-transition:enter="transition ease-out duration-250" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 class="fixed inset-0 z-[60] flex items-center justify-center p-4">
                <div class="w-full max-w-lg max-h-[85vh] flex flex-col bg-white rounded-2xl shadow-2xl" @click.outside="showResultsOpen=false">
                    <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-black text-foreground flex items-center gap-2"><i class="bi bi-trophy-fill text-amber-500"></i> {{ __('personal.event_show_results_medals') }}</h3>
                        <button type="button" @click="showResultsOpen=false" class="w-8 h-8 rounded-full bg-muted grid place-items-center hover:bg-gray-200 transition-colors"><i class="bi bi-x-lg text-xs"></i></button>
                    </div>
                    <div class="flex-1 overflow-y-auto p-4 space-y-3">
                        @foreach($e['bracket_results'] as $r)
                            <div class="rounded-2xl border border-gray-100 p-3">
                                <p class="text-sm font-bold text-foreground mb-2 flex items-center gap-2"><i class="bi bi-diagram-3 text-primary"></i> {{ $r['division'] }}</p>
                                <div class="space-y-1.5">
                                    @foreach($r['medals'] as $m)
                                        @php $medal = [1 => ['🥇', '#f59e0b', __('personal.event_show_champion')], 2 => ['🥈', '#9ca3af', __('personal.event_show_runner_up')], 3 => ['🥉', '#b45309', __('personal.event_show_third_place')]][$m['place']]; @endphp
                                        <div class="flex items-center gap-3 rounded-xl p-2" style="background: {{ $medal[1] }}12;">
                                            <span class="text-2xl flex-shrink-0 leading-none">{{ $medal[0] }}</span>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-bold text-foreground truncate">{{ $m['name'] }}</p>
                                                <p class="text-xs text-muted-foreground">{{ $medal[2] }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== Finance modal ===== --}}
    @if($finance ?? false)
        <div x-show="financeOpen" x-cloak class="fixed inset-0 z-[60]" style="display:none;">
            <div class="absolute inset-0 bg-black/40" @click="financeOpen=false" x-transition.opacity></div>
            <div x-show="financeOpen" x-cloak
                 x-transition:enter="transition ease-out duration-250" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 class="fixed inset-0 z-[60] flex items-center justify-center p-4">
                <div class="w-full max-w-lg max-h-[90vh] flex flex-col bg-white rounded-2xl shadow-2xl" @click.outside="financeOpen=false">
                    <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-black text-foreground flex items-center gap-2"><i class="bi bi-cash-stack text-green-600"></i> {{ __('personal.event_show_event_finance') }}</h3>
                        <button type="button" @click="financeOpen=false" class="w-8 h-8 rounded-full bg-muted grid place-items-center hover:bg-gray-200 transition-colors"><i class="bi bi-x-lg text-xs"></i></button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-4 space-y-4">
                        <div class="rounded-2xl border border-gray-100 p-3">
                            <p class="text-xs font-bold uppercase tracking-wide text-muted-foreground mb-2">{{ __('personal.event_show_money_collected') }}</p>
                            <div class="flex items-center justify-between text-sm py-1">
                                <span class="text-muted-foreground"><span x-text="fin.paid_participants"></span> {{ __('personal.event_show_paid_entries') }} <span x-text="money(fin.participant_fee)"></span></span>
                                <span class="font-bold text-foreground" x-text="money(fin.participant_revenue)"></span>
                            </div>
                            <template x-if="fin.spectator_enabled">
                                <div class="flex items-center justify-between text-sm py-1">
                                    <span class="text-muted-foreground"><span x-text="fin.paid_spectators"></span> {{ __('personal.event_show_tickets_x') }} <span x-text="money(fin.spectator_fee)"></span></span>
                                    <span class="font-bold text-foreground" x-text="money(fin.spectator_revenue)"></span>
                                </div>
                            </template>
                            <div class="flex items-center justify-between text-sm pt-2 mt-1 border-t border-gray-100">
                                <span class="font-bold text-foreground">{{ __('personal.event_show_total_revenue') }}</span>
                                <span class="font-black text-green-600" x-text="money(fin.revenue)"></span>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-gray-100 p-3">
                            <p class="text-xs font-bold uppercase tracking-wide text-muted-foreground mb-2">{{ __('personal.event_show_expenses') }}</p>
                            <div class="space-y-1.5">
                                <template x-for="x in fin.expenses" :key="x.id">
                                    <div class="flex items-center gap-2 text-sm">
                                        <span class="flex-1 min-w-0 truncate text-foreground" x-text="x.label"></span>
                                        <span class="font-bold text-red-600" x-text="'− ' + money(x.amount)"></span>
                                        <button type="button" @click="removeExpense(x.id)" class="w-7 h-7 rounded-lg bg-muted grid place-items-center text-red-500 flex-shrink-0 hover:bg-red-50 transition-colors"><i class="bi bi-x-lg text-[10px]"></i></button>
                                    </div>
                                </template>
                                <p x-show="!fin.expenses.length" class="text-xs text-muted-foreground text-center py-1">{{ __('personal.event_show_no_expenses') }}</p>
                            </div>
                            <div class="flex items-center gap-2 mt-3 pt-3 border-t border-gray-100">
                                <input x-model="newExpLabel" type="text" placeholder="{{ __('personal.event_show_ph_expense') }}"
                                       class="flex-1 min-w-0 px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                <div class="relative w-28 flex-shrink-0">
                                    <span class="absolute start-2 top-1/2 -translate-y-1/2 text-xs font-bold text-muted-foreground pointer-events-none" x-text="fin.currency"></span>
                                    <input x-model="newExpAmount" type="number" min="0" step="0.001" inputmode="decimal" placeholder="0"
                                           class="w-full ps-12 pe-2 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                </div>
                                <button type="button" @click="addExpense()" :disabled="busy" class="w-9 h-9 rounded-xl bg-primary text-white grid place-items-center flex-shrink-0 disabled:opacity-50 hover:bg-primary/90 transition-colors"><i class="bi bi-plus-lg"></i></button>
                            </div>
                            <div class="flex items-center justify-between text-sm pt-2 mt-2 border-t border-gray-100">
                                <span class="font-bold text-foreground">{{ __('personal.event_show_total_expenses') }}</span>
                                <span class="font-black text-red-600" x-text="'− ' + money(expensesTotal)"></span>
                            </div>
                        </div>

                        <div class="rounded-2xl p-4 flex items-center justify-between" :class="profit >= 0 ? 'bg-green-50' : 'bg-red-50'">
                            <span class="text-sm font-black" :class="profit >= 0 ? 'text-green-700' : 'text-red-700'">{{ __('personal.event_show_profit') }}</span>
                            <span class="text-lg font-black" :class="profit >= 0 ? 'text-green-700' : 'text-red-700'" x-text="money(profit)"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
@endsection
