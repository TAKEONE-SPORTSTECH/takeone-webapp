@extends($shell ?? 'layouts.app')

@section('title', __('event-league::messages.title'))

{{--
    THE LEAGUE BOARD — desktop.

    The same season as the mobile screen and the same component behind it, laid
    out for a wide window rather than shrunk into one: the table keeps every
    column a table has (W D L, goals for and against), and the fixtures sit
    beside it in matchday cards instead of underneath. Separate file, per the
    mobile/desktop split — editing one can never break the other.
--}}

@php
    $color = $e['color'];
    $league = $league ?? ['teams' => [], 'fixtures' => [], 'table' => [], 'settings' => [], 'stage' => 'draft'];
    $canScore = ($canManage ?? false) || ($canArrange ?? false);
@endphp

@section('content')
<div x-data="leagueBoard({
        uuid: @js($e['key']),
        canManage: {{ ($canManage ?? false) ? 'true' : 'false' }},
        canScore: {{ $canScore ? 'true' : 'false' }},
        league: @js($league),
        urls: {
            data: @js(route('me.events.league.data', $e['key'])),
            teams: @js(route('me.events.league.teams.store', $e['key'])),
            generate: @js(route('me.events.league.fixtures.generate', $e['key'])),
            fixture: @js(route('me.events.league.fixtures.update', [$e['key'], '__ID__'])),
            team: @js(route('me.events.league.teams.destroy', [$e['key'], '__ID__'])),
        },
     })"
     class="px-4 sm:px-6 lg:px-8 py-6">

    {{-- ===== The band ===== --}}
    <div class="-mx-4 sm:-mx-6 lg:-mx-8 -mt-6 overflow-hidden shadow-sm mb-6 text-white relative"
         style="background: linear-gradient(150deg, {{ $color }}, {{ $color }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="relative px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            <div class="flex items-center justify-between gap-2 mb-4">
                <a href="{{ route('me.events.show', $e['key']) }}"
                   class="inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white hover:bg-white/25 transition-colors"
                   aria-label="{{ __('personal.event_show_event') }}" title="{{ __('personal.event_show_event') }}">
                    <i class="bi bi-chevron-left rtl:rotate-180"></i>
                </a>

                @if($canManage ?? false)
                    <div class="flex items-center gap-2">
                        <button type="button" @click="sheet = 'team'" title="{{ __('event-league::messages.add_team') }}"
                                class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center hover:bg-white/25 transition-colors">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                        <button type="button" @click="sheet = 'season'" :disabled="teams.length < 2" :class="teams.length < 2 && 'opacity-40'"
                                title="{{ __('event-league::messages.action_generate') }}"
                                class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center hover:bg-white/25 transition-colors">
                            <i class="bi bi-calendar-week"></i>
                        </button>
                        <a href="{{ route('me.events.manage', $e['key']) }}" title="{{ __('personal.event_manage_title') }}"
                           class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center hover:bg-white/25 transition-colors">
                            <i class="bi bi-sliders text-base"></i>
                        </a>
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-table"></i> {{ __('event-league::messages.title') }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur" x-text="stageLabel"></span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-white/20 backdrop-blur" x-show="fixtures.length" x-cloak x-text="playedLabel"></span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-building"></i>{{ $e['club'] ?? $e['host'] ?? '' }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ═══════════ The table ═══════════ --}}
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden" x-show="table.length" x-cloak>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-muted/50 text-[10px] font-black uppercase tracking-wider text-muted-foreground">
                                <th class="px-3 py-2 text-center w-10">#</th>
                                <th class="px-3 py-2 text-start">{{ __('event-league::messages.teams') }}</th>
                                <th class="px-2 py-2 text-center w-10">{{ __('event-league::messages.col_played') }}</th>
                                <th class="px-2 py-2 text-center w-10">{{ __('event-league::messages.col_won') }}</th>
                                <th class="px-2 py-2 text-center w-10">{{ __('event-league::messages.col_drawn') }}</th>
                                <th class="px-2 py-2 text-center w-10">{{ __('event-league::messages.col_lost') }}</th>
                                <th class="px-2 py-2 text-center w-12">{{ __('event-league::messages.col_difference') }}</th>
                                <th class="px-3 py-2 text-center w-14">{{ __('event-league::messages.col_points') }}</th>
                                <th class="px-3 py-2 text-center w-24">{{ __('event-league::messages.col_form') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, i) in table" :key="row.team_id">
                                <tr class="border-t border-gray-50 hover:bg-muted/30" :class="row.withdrawn && 'opacity-50'">
                                    <td class="px-3 py-2.5 text-center">
                                        <span class="w-6 h-6 rounded-lg grid place-items-center text-[11px] font-black mx-auto"
                                              :style="i === 0
                                                ? 'background: {{ $color }}1f; color: {{ $color }};'
                                                : 'background: hsl(220 15% 94%); color: hsl(215 15% 45%);'"
                                              x-text="row.position"></span>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <template x-if="row.logo">
                                                <span class="w-6 h-6 flex-shrink-0"><img :src="row.logo" :alt="row.name" class="w-full h-full object-contain"></span>
                                            </template>
                                            <span class="font-semibold text-foreground truncate" x-text="row.name"></span>
                                            <span class="text-[10px] font-bold text-amber-600" x-show="row.withdrawn" x-cloak>{{ __('event-league::messages.withdrawn') }}</span>
                                        </div>
                                    </td>
                                    <td class="px-2 py-2.5 text-center tabular-nums text-muted-foreground" x-text="row.played"></td>
                                    <td class="px-2 py-2.5 text-center tabular-nums text-muted-foreground" x-text="row.won"></td>
                                    <td class="px-2 py-2.5 text-center tabular-nums text-muted-foreground" x-text="row.drawn"></td>
                                    <td class="px-2 py-2.5 text-center tabular-nums text-muted-foreground" x-text="row.lost"></td>
                                    <td class="px-2 py-2.5 text-center tabular-nums font-semibold"
                                        :class="row.difference > 0 ? 'text-green-600' : (row.difference < 0 ? 'text-red-500' : 'text-muted-foreground')"
                                        x-text="(row.difference > 0 ? '+' : '') + row.difference"></td>
                                    <td class="px-3 py-2.5 text-center font-black tabular-nums" x-text="row.points"></td>
                                    <td class="px-3 py-2.5">
                                        <span class="flex items-center justify-center gap-1">
                                            <template x-for="(f, fi) in row.form" :key="fi">
                                                <span class="w-4 h-4 rounded text-[8px] font-black grid place-items-center text-white"
                                                      :class="f === 'W' ? 'bg-green-500' : (f === 'D' ? 'bg-gray-400' : 'bg-red-400')"
                                                      x-text="f"></span>
                                            </template>
                                        </span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <p class="px-3 py-2 bg-muted/30 text-[11px] text-muted-foreground">
                    {{ __('event-league::messages.points_rule', [
                        'win' => $league['settings']['points']['win'] ?? 3,
                        'draw' => $league['settings']['points']['draw'] ?? 1,
                    ]) }}
                </p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center" x-show="!table.length" x-cloak>
                <span class="w-14 h-14 rounded-2xl bg-muted grid place-items-center mx-auto">
                    <i class="bi bi-table text-2xl text-muted-foreground"></i>
                </span>
                <p class="text-base font-bold text-foreground mt-4">{{ __('event-league::messages.no_teams_title') }}</p>
                <p class="text-sm text-muted-foreground mt-1">{{ __('event-league::messages.no_teams_body') }}</p>
            </div>

            {{-- The squad list, and the way out of it. --}}
            @if($canManage ?? false)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4" x-show="teams.length" x-cloak>
                    <h3 class="text-sm font-bold text-foreground mb-3 flex items-center gap-2">
                        <i class="bi bi-people-fill text-primary"></i> {{ __('event-league::messages.teams') }}
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                        <template x-for="team in teams" :key="team.id">
                            <div class="flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-100">
                                <span class="min-w-0 flex-1 text-sm font-semibold text-foreground truncate" x-text="team.name"></span>
                                <button type="button" @click="removeTeam(team)" x-show="!team.withdrawn"
                                        class="w-7 h-7 rounded-lg grid place-items-center text-red-500 hover:bg-red-50 transition-colors"
                                        :aria-label="'{{ __('event-league::messages.remove_team') }} ' + team.name">
                                    <i class="bi bi-x-lg text-[11px]"></i>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            @endif
        </div>

        {{-- ═══════════ The fixtures ═══════════ --}}
        <div class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center" x-show="!fixtures.length" x-cloak>
                <i class="bi bi-calendar-week text-2xl text-muted-foreground"></i>
                <p class="text-sm font-bold text-foreground mt-3">{{ __('event-league::messages.no_fixtures_title') }}</p>
                <p class="text-[12px] text-muted-foreground mt-1">{{ __('event-league::messages.no_fixtures_body') }}</p>
            </div>

            <template x-for="day in matchdays" :key="day.no">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-4 py-2.5 text-white flex items-center justify-between"
                         :style="'background: linear-gradient(135deg, {{ $color }}, {{ $color }}b0);'">
                        <span class="text-[12px] font-black" x-text="day.label"></span>
                        <span class="text-[10.5px] text-white/85" x-text="day.date"></span>
                    </div>
                    <template x-for="fx in day.fixtures" :key="fx.id">
                        <button type="button" @click="canScore && openResult(fx)" :disabled="!canScore"
                                class="w-full flex items-center gap-2 px-3 py-2.5 border-t border-gray-50 text-start"
                                :class="canScore && 'hover:bg-muted/40 transition-colors'">
                            <span class="min-w-0 flex-1 text-[13px] font-semibold text-foreground truncate text-end"
                                  :class="fx.played && fx.home_score > fx.away_score && 'font-black'" x-text="fx.home.name"></span>
                            <span class="flex-shrink-0 px-2.5 py-1 rounded-lg text-[12px] font-black tabular-nums"
                                  :class="fx.played ? 'bg-foreground text-white' : 'bg-muted text-muted-foreground'"
                                  x-text="fx.played ? (fx.home_score + ' – ' + fx.away_score) : 'v'"></span>
                            <span class="min-w-0 flex-1 text-[13px] font-semibold text-foreground truncate"
                                  :class="fx.played && fx.away_score > fx.home_score && 'font-black'" x-text="fx.away.name"></span>
                        </button>
                    </template>
                </div>
            </template>
        </div>
    </div>

    {{-- The same sheet as mobile: a desktop dialog is a sheet on this platform. --}}
    @include('event-league::partials.sheets', ['color' => $color])
</div>

@include('event-league::partials.runtime')
@endsection
