@extends($shell ?? 'members::layouts.personal-mobile')

@section('title', __('event-sport_championship::messages.workspace'))

{{--
    ═══════════════════════════════════════════════════════════════════════════
    THE CHAMPIONSHIP WORKSPACE — any sport with a recipe
    ═══════════════════════════════════════════════════════════════════════════

    Where the organiser RUNS a sport championship: a hub of divisions (and, when
    the sport is entered by pairs or teams, the sides), each drilling into one
    focused panel — draw it, record what happened, read the table, see who
    stood on the podium.

    The screen never decides a rule. How a set is won, whether a draw counts,
    who goes through from a group, how a heat is ranked — all of it is the
    server's (Runner → ResultRules / Formats / Standings), and every write
    returns the division as it now stands, which this re-draws (No-Reload).
    Other organisers are nudged over `realtime:events` {action:'competition'}
    and re-read their own copy.
--}}

@php $color = \App\Support\Palette::safe($e['color']); @endphp

@section('personal-content')
<div x-data="championshipWorkspace({
        uuid: @js($e['key']),
        c: @js($competition),
        urls: {
            data: @js(route('me.events.competition.data', $e['key'])),
            draw: @js(route('me.events.competition.draw', [$e['key'], '__ID__'])),
            knockout: @js(route('me.events.competition.knockout', [$e['key'], '__ID__'])),
            next: @js(route('me.events.competition.next-round', [$e['key'], '__ID__'])),
            final: @js(route('me.events.competition.final', [$e['key'], '__ID__'])),
            contest: @js(route('me.events.competition.contest', [$e['key'], '__ID__'])),
            performance: @js(route('me.events.competition.performance', [$e['key'], '__ID__'])),
            sides: @js(route('me.events.sides.store', $e['key'])),
            side: @js(route('me.events.sides.update', [$e['key'], '__ID__'])),
            sideMembers: @js(route('me.events.sides.members.add', [$e['key'], '__ID__'])),
            sideMember: @js(route('me.events.sides.members.remove', [$e['key'], '__ID__', '__M__'])),
        },
     })"
     class="-mx-4 -mt-4 pb-6">

    {{-- ===== The band ===== Design Rule #6. --}}
    <header class="m-hero px-5 pt-5 pb-16 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $color }}, {{ $color }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between gap-2 relative z-50">
            <a href="{{ route('me.events.show', $e['key']) }}" data-shell-link data-route="me.events"
               class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur inline-flex items-center justify-center text-white no-underline"
               aria-label="{{ __('personal.event_show_event') }}" title="{{ __('personal.event_show_event') }}">
                <i class="bi bi-chevron-left rtl:rotate-180"></i>
            </a>
            <div class="flex items-center gap-2">
                <a href="{{ route('me.events.manage', $e['key']) }}" data-shell-link data-route="me.events"
                   class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center"
                   aria-label="{{ __('personal.event_manage_title') }}" title="{{ __('personal.event_manage_title') }}">
                    <i class="bi bi-sliders text-base"></i>
                </a>
            </div>
        </div>

        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-trophy"></i> {{ __('event-sport_championship::messages.type_label') }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"
                      x-text="formatLabel(c.format)"></span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"
                      x-text="entrantLabel"></span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-lightning-charge"></i>{{ __('event-sport_championship::messages.workspace_sub') }}
            </p>
        </div>
    </header>

    <div class="px-4 -mt-10 relative z-10 space-y-4">

        {{-- ================= THE HUB ================= --}}
        <div x-show="!open" class="space-y-4 m-panel-in">

            {{-- Pairs and teams — only for a sport entered by them. --}}
            <template x-if="c.entrant !== 'individual'">
                <button type="button" @click="openPanel('sides')"
                        class="m-press w-full bg-white rounded-2xl shadow-lg border border-gray-100 p-4 flex items-center gap-3 text-start">
                    <span class="w-11 h-11 rounded-xl grid place-items-center flex-shrink-0 text-white" style="background: {{ $color }}">
                        <i class="bi bi-people-fill text-lg"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-foreground">{{ __('event-sport_championship::messages.sides_title') }}</span>
                        <span class="block text-xs text-muted-foreground mt-0.5" x-text="sidesSummary"></span>
                    </span>
                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :class="sidesReady ? 'bg-green-500' : 'bg-amber-400'"></span>
                    <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground"></i>
                </button>
            </template>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <template x-if="!c.divisions.length">
                    <div class="p-8 text-center">
                        <span class="mx-auto w-14 h-14 rounded-2xl bg-muted grid place-items-center text-muted-foreground"><i class="bi bi-grid text-2xl"></i></span>
                        <p class="text-sm text-muted-foreground mt-3">{{ __('event-sport_championship::messages.no_divisions') }}</p>
                    </div>
                </template>
                <template x-for="(d, i) in c.divisions" :key="d.id">
                    <button type="button" @click="openPanel(d.id)"
                            class="m-press w-full px-4 py-3.5 flex items-center gap-3 text-start hover:bg-muted/60 transition-colors"
                            :class="i && 'border-t border-gray-100'">
                        <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0"
                              :class="d.podium.length ? 'bg-amber-100 text-amber-600' : (d.contests.length || d.performances.length ? 'bg-accent text-primary' : 'bg-muted text-muted-foreground')">
                            <i class="bi" :class="d.podium.length ? 'bi-trophy-fill' : (d.contests.length || d.performances.length ? 'bi-play-fill' : 'bi-hourglass')"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold text-foreground" x-text="d.name"></span>
                            <span class="block text-xs text-muted-foreground mt-0.5" x-text="divisionSummary(d)"></span>
                        </span>
                        <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground"></i>
                    </button>
                </template>
            </div>
        </div>

        {{-- ================= A DIVISION ================= --}}
        <template x-if="div">
            <div class="space-y-4 m-panel-in">
                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-4">
                    <div class="flex items-center gap-3">
                        <button type="button" @click="open = null" aria-label="{{ __('shared.back') }}"
                                class="m-press w-10 h-10 rounded-full bg-muted grid place-items-center flex-shrink-0">
                            <i class="bi bi-chevron-left rtl:rotate-180"></i>
                        </button>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-lg font-black text-foreground leading-tight" x-text="div.name"></h2>
                            <p class="text-xs text-muted-foreground mt-0.5" x-text="divisionSummary(div)"></p>
                        </div>
                    </div>

                    {{-- The next thing to do, and only that. --}}
                    <div class="mt-4 grid gap-2">
                        <template x-if="!div.started">
                            <button type="button" @click="act('draw')" :disabled="busy || div.entrants < 2"
                                    class="m-press w-full rounded-xl py-3 text-sm font-bold text-white flex items-center justify-center gap-2 disabled:opacity-50"
                                    style="background: {{ $color }}">
                                <i class="bi bi-shuffle"></i>
                                <span x-text="div.contests.length || div.performances.length ? @js(__('event-sport_championship::messages.redraw')) : @js(__('event-sport_championship::messages.draw_division'))"></span>
                            </button>
                        </template>
                        <template x-if="div.can.knockout">
                            <button type="button" @click="act('knockout')" :disabled="busy"
                                    class="m-press w-full rounded-xl py-3 text-sm font-bold bg-primary text-white flex items-center justify-center gap-2">
                                <i class="bi bi-diagram-3 bracket-icon"></i>{{ __('event-sport_championship::messages.to_knockout') }}
                            </button>
                        </template>
                        <template x-if="div.can.next_round">
                            <button type="button" @click="act('next')" :disabled="busy"
                                    class="m-press w-full rounded-xl py-3 text-sm font-bold bg-primary text-white flex items-center justify-center gap-2">
                                <i class="bi bi-arrow-repeat"></i>{{ __('event-sport_championship::messages.next_round') }}
                            </button>
                        </template>
                        <template x-if="div.can.final">
                            <button type="button" @click="act('final')" :disabled="busy"
                                    class="m-press w-full rounded-xl py-3 text-sm font-bold bg-primary text-white flex items-center justify-center gap-2">
                                <i class="bi bi-flag-fill"></i>{{ __('event-sport_championship::messages.to_final') }}
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Podium — the answer, once there is one. --}}
                <template x-if="div.podium.length">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                        <x-podium-table source="div.podium" :title="__('event-sport_championship::messages.podium')" :color="$color" />
                    </div>
                </template>

                {{-- Tables: groups, round robin, Swiss. --}}
                <template x-for="t in div.tables" :key="t.group || 'all'">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-4 py-3 flex items-center gap-2 border-b border-gray-100">
                            <i class="bi bi-table text-primary"></i>
                            <h3 class="text-sm font-black text-foreground" x-text="t.group || @js(__('event-sport_championship::messages.standings'))"></h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-[13px]">
                                <thead>
                                    <tr class="text-[10px] uppercase tracking-wide text-muted-foreground">
                                        <th class="ps-4 py-2 text-start w-8">#</th>
                                        <th class="py-2 text-start"></th>
                                        <th class="py-2 w-8 text-center">P</th>
                                        <th class="py-2 w-8 text-center">W</th>
                                        <th class="py-2 w-8 text-center" x-show="c.draws">D</th>
                                        <th class="py-2 w-8 text-center">L</th>
                                        <th class="py-2 w-10 text-center">+/−</th>
                                        <th class="pe-4 py-2 w-10 text-center">Pts</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="r in t.rows" :key="r.key">
                                        <tr class="border-t border-gray-100">
                                            <td class="ps-4 py-2.5 font-black tabular-nums" :class="r.position <= (c.settings.advance || 0) && c.format === 'groups_knockout' ? 'text-primary' : 'text-muted-foreground'" x-text="r.position"></td>
                                            <td class="py-2.5 font-semibold text-foreground" x-text="r.name"></td>
                                            <td class="py-2.5 text-center tabular-nums" x-text="r.played"></td>
                                            <td class="py-2.5 text-center tabular-nums" x-text="r.won"></td>
                                            <td class="py-2.5 text-center tabular-nums" x-show="c.draws" x-text="r.drawn"></td>
                                            <td class="py-2.5 text-center tabular-nums" x-text="r.lost"></td>
                                            <td class="py-2.5 text-center tabular-nums text-muted-foreground" x-text="(r.difference > 0 ? '+' : '') + r.difference"></td>
                                            <td class="pe-4 py-2.5 text-center font-black tabular-nums" x-text="r.points"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>

                {{-- Contests, grouped by stage. --}}
                <template x-for="g in contestGroups" :key="g.label">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2">
                            <i class="bi text-primary" :class="g.knockout ? 'bi-diagram-3 bracket-icon' : 'bi-list-ol'"></i>
                            <h3 class="text-sm font-black text-foreground" x-text="g.label"></h3>
                            <span class="ms-auto text-[11px] font-bold text-muted-foreground tabular-nums" x-text="g.done + '/' + g.items.length"></span>
                        </div>
                        <template x-for="m in g.items" :key="m.id">
                            <button type="button" @click="openContest(m)" :disabled="!recordable(m)"
                                    class="w-full px-4 py-3 flex items-center gap-3 text-start border-t border-gray-100 first:border-t-0 transition-colors"
                                    :class="recordable(m) ? 'm-press hover:bg-muted/60' : 'cursor-default'">
                                <span class="w-7 text-[10px] font-black text-muted-foreground tabular-nums" x-text="m.no ? '#' + m.no : ''"></span>
                                <span class="min-w-0 flex-1 space-y-1">
                                    <span class="flex items-center gap-2">
                                        <span class="min-w-0 flex-1 text-[13px] break-words" :class="m.winner === 'a' ? 'font-black text-foreground' : 'font-semibold text-foreground/80'" x-text="m.a || '—'"></span>
                                        <span class="text-[12px] font-black tabular-nums" x-text="m.a_score ?? ''"></span>
                                    </span>
                                    <span class="flex items-center gap-2">
                                        <span class="min-w-0 flex-1 text-[13px] break-words" :class="m.winner === 'b' ? 'font-black text-foreground' : 'font-semibold text-foreground/80'" x-text="m.bye ? @js(__('event-sport_championship::messages.bye')) : (m.b || '—')"></span>
                                        <span class="text-[12px] font-black tabular-nums" x-text="m.b_score ?? ''"></span>
                                    </span>
                                    <template x-if="m.detail && m.detail.sets">
                                        <span class="block text-[11px] text-muted-foreground tabular-nums" x-text="m.detail.sets.map(s => s[0] + '–' + s[1]).join('  ')"></span>
                                    </template>
                                </span>
                                <i class="bi flex-shrink-0" :class="m.status === 'done' ? 'bi-check-circle-fill text-green-600' : (recordable(m) ? 'bi-pencil-square text-primary' : 'bi-clock text-muted-foreground')"></i>
                            </button>
                        </template>
                    </div>
                </template>

                {{-- Heats and finals: one row per entrant. --}}
                <template x-for="h in heatGroups" :key="h.label">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2">
                            <i class="bi text-primary" :class="h.final ? 'bi-flag-fill' : 'bi-list-ol'"></i>
                            <h3 class="text-sm font-black text-foreground" x-text="h.label"></h3>
                        </div>
                        <template x-for="p in h.items" :key="p.id">
                            <button type="button" @click="openPerformance(p)"
                                    class="m-press w-full px-4 py-3 flex items-center gap-3 text-start border-t border-gray-100 first:border-t-0 hover:bg-muted/60 transition-colors">
                                <span class="w-8 h-8 rounded-lg bg-muted grid place-items-center text-[11px] font-black tabular-nums flex-shrink-0" x-text="p.lane"></span>
                                <span class="min-w-0 flex-1 text-[13px] font-semibold text-foreground break-words" x-text="p.name"></span>
                                <span class="text-[13px] font-black tabular-nums" x-text="perfValue(p)"></span>
                                <span class="w-7 h-7 rounded-full grid place-items-center text-[11px] font-black flex-shrink-0"
                                      :class="p.rank === 1 ? 'bg-amber-400 text-white' : (p.rank === 2 ? 'bg-gray-300 text-foreground' : (p.rank === 3 ? 'bg-orange-400 text-white' : 'bg-muted text-muted-foreground'))"
                                      x-text="p.rank || ''"></span>
                            </button>
                        </template>
                    </div>
                </template>
            </div>
        </template>

        {{-- ================= PAIRS AND TEAMS ================= --}}
        <template x-if="open === 'sides'">
            <div class="space-y-4 m-panel-in">
                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-4 flex items-center gap-3">
                    <button type="button" @click="open = null" aria-label="{{ __('shared.back') }}"
                            class="m-press w-10 h-10 rounded-full bg-muted grid place-items-center flex-shrink-0">
                        <i class="bi bi-chevron-left rtl:rotate-180"></i>
                    </button>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-lg font-black text-foreground leading-tight">{{ __('event-sport_championship::messages.sides_title') }}</h2>
                        <p class="text-xs text-muted-foreground mt-0.5" x-text="sidesSummary"></p>
                    </div>
                    <button type="button" @click="sheet = 'side'; form.name = ''; form.category_id = c.divisions[0]?.id ?? null"
                            class="m-press h-10 px-4 rounded-full bg-primary text-white text-sm font-bold flex items-center gap-1.5 flex-shrink-0">
                        <i class="bi bi-plus-lg"></i>{{ __('event-sport_championship::messages.side_new') }}
                    </button>
                </div>

                <template x-if="!c.sides.length">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center text-sm text-muted-foreground">
                        {{ __('event-sport_championship::messages.sides_none') }}
                    </div>
                </template>

                <template x-for="s in c.sides" :key="s.uuid">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                        <div class="flex items-start gap-3">
                            <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0"
                                  :class="s.complete ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-600'">
                                <i class="bi" :class="s.kind === 'pair' ? 'bi-people' : 'bi-shield'"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-foreground break-words" x-text="s.display_name"></p>
                                <p class="text-xs text-muted-foreground mt-0.5">
                                    <span x-text="s.category || ''"></span>
                                    <span class="tabular-nums" x-text="' · ' + s.players + (s.size.max ? '/' + s.size.max : '')"></span>
                                </p>
                            </div>
                            <button type="button" @click="removeSide(s)" aria-label="{{ __('event-sport_championship::messages.delete_side') }}"
                                    class="m-press w-9 h-9 rounded-full text-red-600 hover:bg-red-50 grid place-items-center flex-shrink-0">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            <template x-for="m in s.members" :key="m.id">
                                <span class="inline-flex items-center gap-1.5 ps-3 pe-1 py-1 rounded-full bg-muted text-xs font-semibold text-foreground">
                                    <span x-text="m.name"></span>
                                    <button type="button" @click="removeMember(s, m)" class="w-5 h-5 rounded-full hover:bg-white grid place-items-center" aria-label="{{ __('event-sport_championship::messages.remove') }}">
                                        <i class="bi bi-x text-sm"></i>
                                    </button>
                                </span>
                            </template>
                            <button type="button" x-show="!s.size.max || s.players < s.size.max" @click="pickFor = s; sheet = 'member'"
                                    class="m-press inline-flex items-center gap-1 px-3 py-1 rounded-full border border-dashed border-primary text-primary text-xs font-bold">
                                <i class="bi bi-person-plus"></i>{{ __('event-sport_championship::messages.add_member') }}
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

    @include('event-sport_championship::partials.sheets', ['color' => $color])
</div>

@include('event-sport_championship::partials.runtime')
@endsection
