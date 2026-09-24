{{-- `$shell` is shared only on the sealed event routes; without it this is the
     member shell, exactly like every other event screen. --}}
@extends($shell ?? 'members::layouts.personal-mobile')

@section('title', __('event-league::messages.title'))

{{--
    ═══════════════════════════════════════════════════════════════════════════
    THE LEAGUE BOARD — mobile
    ═══════════════════════════════════════════════════════════════════════════

    A league has no draw, so this screen stands where the bracket does: the tile
    every event page carries as "Brackets & draws" opens the TABLE here, and the
    reader never meets a bracket that was never cut.

    Two views of one season, on the same segmented tray the roster uses:

      TABLE     positions, a points column that carries the weight, and the last
                five results as a strip — the three things anybody opens a
                league for, in one glance.
      FIXTURES  grouped by matchday, each one a band. An organiser or an
                appointed official taps a match to enter the score; everybody
                else reads it.

    Everything writes over AJAX and patches in place (No-Reload), and the table
    is re-read from the response rather than re-derived in the browser — the
    server owns the order, including the head-to-head tie-break no client should
    be reimplementing.
--}}

@php
    $color = $e['color'];
    $league = $league ?? ['teams' => [], 'fixtures' => [], 'table' => [], 'settings' => [], 'stage' => 'draft'];
    $canScore = ($canManage ?? false) || ($canArrange ?? false);
@endphp

@section('personal-content')
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
     class="-mx-4 -mt-4 pb-4">

    {{-- ===== The band ===== Design Rule #6: full-bleed, the subject's colour,
         two soft circles, the identity under the control row. --}}
    <header class="m-hero px-5 pt-5 pb-14 text-white relative overflow-hidden"
            style="background: {{ \App\Support\Palette::pageBand($color, isset($shell)) }};">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between gap-2 relative z-50">
            <a href="{{ ($sealed ?? false) ? url('/e/'.$e['key'].'/admin/manage') : route('me.events.show', $e['key']) }}"
               data-shell-link data-route="me.events"
               class="m-press inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white no-underline"
               aria-label="{{ __('personal.event_show_event') }}" title="{{ __('personal.event_show_event') }}">
                <i class="bi bi-chevron-left rtl:rotate-180"></i>
            </a>

            @if($canManage ?? false)
                <div class="flex items-center gap-2">
                    <a href="{{ route('me.events.manage', $e['key']) }}" data-shell-link data-route="me.events"
                       class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center"
                       aria-label="{{ __('personal.event_manage_title') }}">
                        <i class="bi bi-sliders text-base"></i>
                    </a>
                </div>
            @endif
        </div>

        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-table"></i> {{ __('event-league::messages.title') }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"
                      x-text="stageLabel"></span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-white/20 backdrop-blur"
                      x-show="fixtures.length" x-cloak x-text="playedLabel"></span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-building"></i>{{ $e['club'] ?? $e['host'] ?? '' }}
            </p>
        </div>
    </header>

    {{-- The content rides up over the band's tail. --}}
    <div class="px-4 -mt-10 relative z-10 space-y-4 mobile-stagger">

        {{-- ===== Two views of one season ===== --}}
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-1.5 flex gap-1.5" role="tablist">
            <template x-for="t in tabs" :key="t.key">
                <button type="button" role="tab" @click="tab = t.key" :aria-selected="tab === t.key"
                        :class="tab === t.key ? 'bg-primary text-white shadow-sm' : 'text-muted-foreground hover:bg-muted/60'"
                        class="m-press flex-1 rounded-xl py-2.5 px-3 text-sm font-bold transition-colors flex items-center justify-center gap-2">
                    <i class="bi" :class="t.icon"></i>
                    <span x-text="t.label"></span>
                    <span class="text-[11px] font-black tabular-nums px-1.5 py-0.5 rounded-full"
                          :class="tab === t.key ? 'bg-white/20' : 'bg-muted'" x-text="t.count"></span>
                </button>
            </template>
        </div>

        {{-- ═══════════════ TABLE ═══════════════ --}}
        <div x-show="tab === 'table'" x-cloak class="space-y-3">
            <template x-if="table.length">
                <div class="m-card rounded-2xl overflow-hidden">
                    {{-- Column heads, sized to the row beneath them. --}}
                    <div class="flex items-center gap-2 px-3 py-2 bg-muted/50 text-[10px] font-black uppercase tracking-wider text-muted-foreground">
                        <span class="w-6 text-center">#</span>
                        <span class="flex-1">{{ __('event-league::messages.teams') }}</span>
                        <span class="w-5 text-center">{{ __('event-league::messages.col_played') }}</span>
                        <span class="w-5 text-center hidden xs:inline">{{ __('event-league::messages.col_won') }}</span>
                        <span class="w-6 text-center">{{ __('event-league::messages.col_difference') }}</span>
                        <span class="w-8 text-center">{{ __('event-league::messages.col_points') }}</span>
                    </div>

                    <template x-for="(row, i) in table" :key="row.team_id">
                        <div class="flex items-center gap-2 px-3 py-2.5 border-t border-gray-50"
                             :class="row.withdrawn && 'opacity-50'">
                            {{-- The position rail: the champion's place is worth
                                 marking, and nothing else is invented. --}}
                            <span class="w-6 h-6 rounded-lg grid place-items-center text-[11px] font-black flex-shrink-0"
                                  :style="i === 0
                                    ? 'background: {{ $color }}1f; color: {{ $color }};'
                                    : 'background: hsl(220 15% 94%); color: hsl(215 15% 45%);'"
                                  x-text="row.position"></span>

                            <div class="min-w-0 flex-1 flex items-center gap-2">
                                <template x-if="row.logo">
                                    <span class="w-6 h-6 flex-shrink-0">
                                        <img :src="row.logo" :alt="row.name" class="w-full h-full object-contain">
                                    </span>
                                </template>
                                <span class="min-w-0">
                                    <span class="block text-[13px] font-bold text-foreground truncate" x-text="row.name"></span>
                                    <span class="flex items-center gap-0.5 mt-1" x-show="row.form.length" x-cloak>
                                        <template x-for="(f, fi) in row.form" :key="fi">
                                            <span class="w-1.5 h-1.5 rounded-full"
                                                  :class="f === 'W' ? 'bg-green-500' : (f === 'D' ? 'bg-gray-300' : 'bg-red-400')"></span>
                                        </template>
                                    </span>
                                </span>
                            </div>

                            <span class="w-5 text-center text-[12px] tabular-nums text-muted-foreground" x-text="row.played"></span>
                            <span class="w-5 text-center text-[12px] tabular-nums text-muted-foreground hidden xs:inline" x-text="row.won"></span>
                            <span class="w-6 text-center text-[12px] tabular-nums font-semibold"
                                  :class="row.difference > 0 ? 'text-green-600' : (row.difference < 0 ? 'text-red-500' : 'text-muted-foreground')"
                                  x-text="(row.difference > 0 ? '+' : '') + row.difference"></span>
                            <span class="w-8 text-center text-sm font-black text-foreground tabular-nums" x-text="row.points"></span>
                        </div>
                    </template>

                    <p class="px-3 py-2 bg-muted/30 text-[10.5px] text-muted-foreground">
                        {{ __('event-league::messages.points_rule', [
                            'win' => $league['settings']['points']['win'] ?? 3,
                            'draw' => $league['settings']['points']['draw'] ?? 1,
                        ]) }}
                    </p>
                </div>
            </template>

            {{-- Nothing yet — and the organiser is told what to do about it. --}}
            <template x-if="!table.length">
                <div class="m-card rounded-2xl p-6 text-center">
                    <span class="w-12 h-12 rounded-2xl bg-muted grid place-items-center mx-auto">
                        <i class="bi bi-table text-xl text-muted-foreground"></i>
                    </span>
                    <p class="text-sm font-bold text-foreground mt-3">{{ __('event-league::messages.no_teams_title') }}</p>
                    <p class="text-[12px] text-muted-foreground mt-1">{{ __('event-league::messages.no_teams_body') }}</p>
                </div>
            </template>

            {{-- The organiser's own controls, under the table rather than over it. --}}
            @if($canManage ?? false)
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" @click="sheet = 'team'"
                            class="m-press rounded-2xl p-3 bg-white border border-gray-100 shadow-sm flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-xl bg-primary/10 text-primary grid place-items-center flex-shrink-0">
                            <i class="bi bi-plus-lg"></i>
                        </span>
                        <span class="text-[12.5px] font-bold text-foreground text-start leading-tight">{{ __('event-league::messages.add_team') }}</span>
                    </button>

                    <button type="button" @click="sheet = 'season'" :disabled="teams.length < 2"
                            :class="teams.length < 2 && 'opacity-40'"
                            class="m-press rounded-2xl p-3 bg-white border border-gray-100 shadow-sm flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0"
                              style="background: {{ $color }}1f; color: {{ $color }};">
                            <i class="bi bi-calendar-week"></i>
                        </span>
                        <span class="text-[12.5px] font-bold text-foreground text-start leading-tight">{{ __('event-league::messages.action_generate') }}</span>
                    </button>
                </div>

                {{-- The teams themselves, so a side can be taken out again. --}}
                <div class="m-card rounded-2xl p-3" x-show="teams.length" x-cloak>
                    <p class="text-[10px] font-black uppercase tracking-wider text-muted-foreground mb-2">{{ __('event-league::messages.teams') }}</p>
                    <div class="space-y-1">
                        <template x-for="team in teams" :key="team.id">
                            <div class="flex items-center gap-2 px-2 py-1.5 rounded-xl hover:bg-muted/50">
                                <span class="min-w-0 flex-1 text-[13px] font-semibold text-foreground truncate" x-text="team.name"></span>
                                <span class="text-[10px] font-bold text-amber-600" x-show="team.withdrawn" x-cloak>{{ __('event-league::messages.withdrawn') }}</span>
                                <button type="button" @click="removeTeam(team)" x-show="!team.withdrawn"
                                        class="m-press w-7 h-7 rounded-lg grid place-items-center text-red-500 hover:bg-red-50"
                                        :aria-label="'{{ __('event-league::messages.remove_team') }} ' + team.name">
                                    <i class="bi bi-x-lg text-[11px]"></i>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            @endif
        </div>

        {{-- ═══════════════ FIXTURES ═══════════════ --}}
        <div x-show="tab === 'fixtures'" x-cloak class="space-y-3">
            <template x-if="!fixtures.length">
                <div class="m-card rounded-2xl p-6 text-center">
                    <span class="w-12 h-12 rounded-2xl bg-muted grid place-items-center mx-auto">
                        <i class="bi bi-calendar-week text-xl text-muted-foreground"></i>
                    </span>
                    <p class="text-sm font-bold text-foreground mt-3">{{ __('event-league::messages.no_fixtures_title') }}</p>
                    <p class="text-[12px] text-muted-foreground mt-1">{{ __('event-league::messages.no_fixtures_body') }}</p>
                </div>
            </template>

            <template x-for="day in matchdays" :key="day.no">
                <div class="m-card rounded-2xl overflow-hidden">
                    <div class="px-4 py-2.5 text-white flex items-center justify-between"
                         :style="'background: linear-gradient(135deg, {{ $color }}, {{ $color }}b0);'">
                        <span class="text-[12px] font-black" x-text="day.label"></span>
                        <span class="text-[10.5px] text-white/85" x-text="day.date"></span>
                    </div>

                    <div>
                        <template x-for="fx in day.fixtures" :key="fx.id">
                            <button type="button" @click="canScore && openResult(fx)"
                                    :disabled="!canScore"
                                    class="w-full flex items-center gap-2 px-3 py-3 border-t border-gray-50 text-start"
                                    :class="canScore && 'm-press hover:bg-muted/40'">
                                <span class="min-w-0 flex-1 text-[13px] font-semibold text-foreground truncate text-end"
                                      :class="fx.played && fx.home_score > fx.away_score && 'font-black'"
                                      x-text="fx.home.name"></span>

                                <span class="flex-shrink-0 px-2.5 py-1 rounded-lg text-[12px] font-black tabular-nums"
                                      :class="fx.played ? 'bg-foreground text-white' : 'bg-muted text-muted-foreground'"
                                      x-text="fx.played ? (fx.home_score + ' – ' + fx.away_score) : 'v'"></span>

                                <span class="min-w-0 flex-1 text-[13px] font-semibold text-foreground truncate"
                                      :class="fx.played && fx.away_score > fx.home_score && 'font-black'"
                                      x-text="fx.away.name"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>

    @include('event-league::partials.sheets', ['color' => $color])
</div>

@include('event-league::partials.runtime')
@endsection
