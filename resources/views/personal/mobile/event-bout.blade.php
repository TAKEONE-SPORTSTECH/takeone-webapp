{{-- `$shell` is shared ONLY on the sealed event routes (/e/{uuid}/admin/…), so with
     nothing shared this is the member shell exactly as before. See entry/shell. --}}
@extends($shell ?? 'layouts.personal-mobile')

@section('title', $bout['a']['name'].' vs '.$bout['b']['name'])

{{--
    One bout — mobile.

    The page a match video links back to (VIDEO-INTEGRATION.md §6.6): someone
    watching a clip on the video platform taps the bout name and arrives here to
    see WHERE in the event it sat — division, round, mat, day — who fought, and
    how it ended.

    The two corners are the page. Everything else is context around them, so the
    layout is a single vertical duel: red corner, the result between them, blue
    corner. Names and crests link out (profile, club page) only where §6.6 allows
    it; a missing link is rendered as plain text with no trace that a link was
    withheld.
--}}
@php
    $color = $e['color'] ?? '#7c3aed';
    $decided = $bout['winner'] !== null;

    /*
     * Corner colours, keyed by the corner the athlete actually fought in — not by
     * their slot in the draw. Seeding decides the slot, the mat decides the corner,
     * and bout 12 of the National Team Selection Trials proved they disagree: the
     * athlete in slot 'a' fought in blue. boutSide() supplies the recorded corner
     * and falls back to the old assumption only when none was recorded.
     *
     * Literal values rather than Tailwind utilities: a class only exists if the CSS
     * build has seen it, and bg-blue-400 appears nowhere else in the project, so it
     * was never generated and the blue corner drew no bar at all. A corner colour
     * must not depend on which classes a past build happened to include. Values are
     * Tailwind's own rose/blue -400, -50, -200, -600.
     */
    $corners = [
        'red'  => ['bar' => '#fb7185', 'score' => '#e11d48'],
        'blue' => ['bar' => '#60a5fa', 'score' => '#2563eb'],
    ];

    /*
     * Round and phase are two different facts — "Final" is where the bout sits in
     * the draw, "Knockout" is which bracket it belongs to — so both are shown.
     * Collapsing them with `phase ?: round` dropped the round, which meant a FINAL
     * announced itself only as "Knockout": the single most important thing about
     * the bout went missing. Deduped case-insensitively for the events that store
     * the same word in both columns.
     */
    $stageChips = collect([$bout['division'], $bout['round'], $bout['phase']])
        ->filter(fn ($v) => trim((string) $v) !== '')
        ->unique(fn ($v) => mb_strtolower(trim((string) $v)))
        ->values()
        ->all();

    // date_iso is the COMPARABLE form of the day (the run-of-show timeline matches
    // on it); printing it put a raw "2026-08-09" on the page. The event view
    // already carries the display parts.
    $eventDay = trim(($e['mon'] ?? '').' '.($e['day'] ?? '')) ?: '—';
@endphp

@section('personal-content')

    {{-- Hero band: full-bleed. -mx-4 -mt-4 cancels the shell's px-4 py-4, so the
         band meets the screen edges while the cards below keep their inset. --}}
    <header class="m-hero -mx-4 -mt-4 px-5 pt-5 pb-20 text-white relative overflow-hidden"
            style="background: {{ \App\Support\Palette::pageBand($color, isset($shell)) }};">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between relative z-50">
            <a href="{{ route('me.events.show', $e['key']) }}"
               class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center"
               aria-label="{{ __('personal.back') }}">
                <i class="bi bi-chevron-left"></i>
            </a>
            <div class="flex items-center gap-2">
            @if ($canManage)
                {{-- The button and its sheet share one Alpine scope: the sheet is
                     teleported to <body> to escape the hero band, and x-teleport keeps
                     the scope with it, so its fields bind to this component. --}}
                <div x-data="boutEditor(@js($bout), {
                         update: @js(route('me.events.bout.update', ['event' => $e['key'], 'matchNo' => $bout['match_no']])),
                         competitors: @js(route('me.events.bout.competitors', ['event' => $e['key'], 'matchNo' => $bout['match_no']])),
                         officials: @js(route('me.events.officials', $e['key'])),
                     }, {
                         none: @js(__('events.bout_winner_none')),
                         loadFailed: @js(__('shared.something_went_wrong')),
                         saveFailed: @js(__('shared.something_went_wrong')),
                     })">
                    <button type="button" @click="open = true"
                            class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0"
                            title="{{ __('events.bout_edit') }}" aria-label="{{ __('events.bout_edit') }}">
                        <i class="bi bi-pencil-square"></i>
                    </button>
                <template x-teleport="body">
                    <div x-show="open" x-cloak class="fixed inset-0" style="z-index:70" @keydown.escape.window="open = false">
                        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/50" @click="open = false"></div>
                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                             class="absolute inset-x-0 bottom-0 flex flex-col bg-background rounded-t-3xl shadow-2xl" style="max-height:92vh">
                            <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                                 style="background: linear-gradient(150deg, {{ $color }}, {{ $color }}b0);">
                                <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                                <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                                <div class="relative flex items-start gap-3">
                                    <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                                        <i class="bi bi-pencil-square text-xl"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-lg font-black leading-tight">{{ __('events.bout_edit') }}</h3>
                                        <p class="text-[12px] text-white/85 mt-0.5 truncate" x-text="(f.a_name || '\u2014') + ' \u00b7 ' + (f.b_name || '\u2014')"></p>
                                    </div>
                                    <button type="button" @click="open = false" aria-label="{{ __('shared.close') }}"
                                            class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="flex-1 min-h-0 overflow-y-auto px-5 pb-2 space-y-4">

                    <p class="text-[11px] leading-relaxed text-muted-foreground">{{ __('events.bout_edit_live_note') }}</p>

                    {{-- Corners. Mutually exclusive, so picking one side sets the other. --}}
                    <div>
                        <p class="text-[10px] font-extrabold uppercase tracking-[0.12em] text-muted-foreground mb-2">{{ __('events.bout_corners') }}</p>
                        <div class="space-y-2">
                            <template x-for="side in ['a', 'b']" :key="'c' + side">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-gray-900 truncate flex-1" x-text="f[side + '_name'] || '—'"></span>
                                    <template x-for="c in ['red', 'blue']" :key="side + c">
                                        <button type="button" @click="pickCorner(side, c)"
                                                class="w-16 h-9 rounded-lg text-[11px] font-bold border-2 transition-colors"
                                                :style="f[side + '_corner'] === c
                                                    ? 'border-color:' + (c === 'red' ? '#fb7185' : '#60a5fa') + ';background:' + (c === 'red' ? '#fff1f2' : '#eff6ff') + ';color:' + (c === 'red' ? '#e11d48' : '#2563eb')
                                                    : 'border-color:#e5e7eb;background:#fff;color:#9ca3af'"
                                                x-text="c.toUpperCase()"></button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Scores + winner --}}
                    <div>
                        <p class="text-[10px] font-extrabold uppercase tracking-[0.12em] text-muted-foreground mb-2">{{ __('events.bout_scores') }}</p>
                        <div class="grid grid-cols-2 gap-3">
                            <template x-for="side in ['a', 'b']" :key="'s' + side">
                                <div>
                                    <label class="block text-[11px] text-muted-foreground mb-1 truncate" x-text="f[side + '_name'] || '—'"></label>
                                    <input type="number" min="0" max="999" inputmode="numeric" x-model="f[side + '_score']"
                                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm tabular-nums focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                                </div>
                            </template>
                        </div>
                    </div>

                    <div>
                        <p class="text-[10px] font-extrabold uppercase tracking-[0.12em] text-muted-foreground mb-2">{{ __('events.bout_winner') }}</p>
                        <div class="grid grid-cols-3 gap-2">
                            <template x-for="opt in winnerOptions" :key="'w' + String(opt.v)">
                                <button type="button" @click="f.winner = opt.v"
                                        class="h-10 rounded-xl border-2 text-[11px] font-bold truncate px-2 transition-colors"
                                        :class="f.winner === opt.v ? 'border-transparent bg-primary text-white' : 'border-gray-200 bg-white text-muted-foreground'"
                                        x-text="opt.l"></button>
                            </template>
                        </div>
                    </div>

                    {{-- Athletes: a lookup, not free text. The list is this bout's
                         own division, so an entrant cannot be placed in a category
                         they never entered. --}}
                    <div>
                        <p class="text-[10px] font-extrabold uppercase tracking-[0.12em] text-muted-foreground mb-1">{{ __('events.bout_athletes') }}</p>
                        <p class="text-[11px] text-muted-foreground mb-2">{{ __('events.bout_athletes_hint') }}</p>

                        <div class="space-y-2">
                            <template x-for="side in ['a', 'b']" :key="'ath' + side">
                                <div class="relative" @click.outside="openPick = null">
                                    <button type="button" @click="openPick = (openPick === side ? null : side); entQ = ''"
                                            class="w-full flex items-center gap-3 px-3 py-2.5 border border-gray-200 rounded-xl bg-white text-start">
                                        <span class="w-1.5 h-8 rounded-full flex-shrink-0"
                                              :style="'background:' + (f[side + '_corner'] === 'red' ? '#fb7185' : (f[side + '_corner'] === 'blue' ? '#60a5fa' : '#e5e7eb'))"></span>
                                        <span class="w-7 h-9 rounded-md overflow-hidden bg-muted grid place-items-center flex-shrink-0">
                                            <template x-if="picked(side) && picked(side).photo">
                                                <img :src="picked(side).photo" alt="" class="w-full h-full object-cover">
                                            </template>
                                            <template x-if="!(picked(side) && picked(side).photo)">
                                                <i class="bi bi-person text-muted-foreground text-xs"></i>
                                            </template>
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-semibold text-gray-900 truncate" x-text="f[side + '_name'] || '—'"></span>
                                            <span class="block text-[11px] text-muted-foreground truncate"
                                                  x-text="picked(side) ? [picked(side).country, picked(side).club].filter(Boolean).join(' · ') : ''"></span>
                                        </span>
                                        <i class="bi bi-chevron-down text-muted-foreground text-xs transition-transform" :class="openPick === side ? 'rotate-180' : ''"></i>
                                    </button>

                                    {{-- In normal flow, not absolute: an absolutely
                                         positioned panel is clipped by the sheet's own
                                         scrolling body. --}}
                                    <div x-show="openPick === side" x-cloak
                                         class="mt-2 border border-gray-200 rounded-xl bg-white overflow-hidden">
                                        <div class="p-2 border-b border-gray-100">
                                            <input type="text" x-model="entQ" placeholder="{{ __('events.bout_pick_athlete') }}"
                                                   class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                                        </div>
                                        <div class="overflow-y-auto" style="max-height:40vh">
                                            <template x-for="ent in entrantsFiltered" :key="side + '-' + ent.id">
                                                <button type="button" @click="pickAthlete(side, ent)"
                                                        class="w-full flex items-center gap-2.5 px-3 py-2 text-start hover:bg-muted/60 transition-colors">
                                                    <span class="w-6 h-8 rounded-md overflow-hidden bg-muted grid place-items-center flex-shrink-0">
                                                        <template x-if="ent.photo"><img :src="ent.photo" alt="" class="w-full h-full object-cover"></template>
                                                        <template x-if="!ent.photo"><i class="bi bi-person text-[10px] text-muted-foreground"></i></template>
                                                    </span>
                                                    <span class="min-w-0 flex-1">
                                                        <span class="block text-sm font-semibold text-foreground truncate" x-text="ent.name"></span>
                                                        <span class="block text-[10px] text-muted-foreground truncate" x-text="[ent.country, ent.club].filter(Boolean).join(' · ')"></span>
                                                    </span>
                                                    <i class="bi bi-check2 text-primary" x-show="f[side + '_competitor_id'] === ent.id"></i>
                                                </button>
                                            </template>
                                            <p x-show="!entrantsFiltered.length" x-cloak class="text-[11px] text-muted-foreground text-center py-3">
                                                {{ __('personal.personal_event_officials_no_matches') }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Officials, as rows: Add official, then pick the position and
                         the person on that row.

                         Appointed to the CHAMPIONSHIP, not to this one bout —
                         event_officials carries no match column — so the heading says
                         so rather than implying otherwise. --}}
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <p class="text-[10px] font-extrabold uppercase tracking-[0.12em] text-muted-foreground">{{ __('events.bout_officials') }}</p>
                            <button type="button" @click="addOfficialRow()"
                                    class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-primary text-primary text-[11px] font-bold">
                                <i class="bi bi-plus-lg"></i>{{ __('events.bout_officials_add') }}
                            </button>
                        </div>
                        <p class="text-[10px] text-muted-foreground mb-2">{{ __('events.bout_officials_hint') }}</p>

                        <div class="space-y-2">
                            <template x-for="(row, i) in offRows" :key="'row' + row.key">
                                <div class="border border-gray-100 rounded-xl p-2.5 space-y-2">
                                    {{-- Position --}}
                                    <div class="relative" @click.outside="row.openRole = false">
                                        <button type="button" @click="row.openRole = !row.openRole; row.roleQ = ''"
                                                class="w-full flex items-center gap-2.5 px-3 py-2 border border-gray-200 rounded-lg bg-white text-start">
                                            <i class="bi bi-person-badge text-muted-foreground"></i>
                                            <span class="flex-1 text-sm font-semibold truncate"
                                                  :class="row.role ? 'text-gray-900' : 'text-muted-foreground'"
                                                  x-text="row.role ? roleLabel(row.role) : @js(__('events.bout_officials_role'))"></span>
                                            <i class="bi bi-chevron-down text-xs text-muted-foreground transition-transform" :class="row.openRole ? 'rotate-180' : ''"></i>
                                        </button>
                                        <div x-show="row.openRole" x-cloak class="mt-1.5 border border-gray-200 rounded-lg bg-white overflow-hidden">
                                            <div class="p-2 border-b border-gray-100">
                                                <input type="text" x-model="row.roleQ" placeholder="{{ __('events.bout_officials_role_search') }}"
                                                       class="w-full px-2.5 py-1.5 border border-gray-200 rounded-md text-[13px] focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                                            </div>
                                            <div class="overflow-y-auto" style="max-height:34vh">
                                                <template x-for="r in rolesFor(row)" :key="row.key + r.value">
                                                    <button type="button" @click="setRole(row, r.value)"
                                                            class="w-full flex items-center gap-2 px-3 py-2 text-start hover:bg-muted/60 transition-colors">
                                                        <span class="flex-1 text-[13px] truncate" x-text="r.label"></span>
                                                        <i class="bi bi-check2 text-primary" x-show="row.role === r.value"></i>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Person --}}
                                    <div class="relative" @click.outside="row.openPerson = false">
                                        <button type="button" @click="row.openPerson = !row.openPerson"
                                                class="w-full flex items-center gap-2.5 px-3 py-2 border border-gray-200 rounded-lg bg-white text-start">
                                            <span class="w-6 h-8 rounded-md overflow-hidden bg-muted grid place-items-center flex-shrink-0">
                                                <template x-if="row.photo"><img :src="row.photo" alt="" class="w-full h-full object-cover"></template>
                                                <template x-if="!row.photo"><i class="bi bi-person text-[10px] text-muted-foreground"></i></template>
                                            </span>
                                            <span class="flex-1 min-w-0">
                                                <span class="block text-sm font-semibold truncate"
                                                      :class="row.name ? 'text-gray-900' : 'text-muted-foreground'"
                                                      x-text="row.name || @js(__('events.bout_officials_search'))"></span>
                                                <span class="block text-[10px] text-muted-foreground truncate" x-text="row.sub || ''"></span>
                                            </span>
                                            <i class="bi bi-chevron-down text-xs text-muted-foreground transition-transform" :class="row.openPerson ? 'rotate-180' : ''"></i>
                                        </button>
                                        <div x-show="row.openPerson" x-cloak class="mt-1.5 border border-gray-200 rounded-lg bg-white overflow-hidden">
                                            <div class="p-2 border-b border-gray-100">
                                                <input type="text" x-model="row.q" @input.debounce.300ms="searchPeople(row)"
                                                       placeholder="{{ __('events.bout_officials_search') }}"
                                                       class="w-full px-2.5 py-1.5 border border-gray-200 rounded-md text-[13px] focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                                            </div>
                                            <div class="overflow-y-auto" style="max-height:34vh">
                                                <template x-for="c in (row.results || [])" :key="row.key + 'c' + c.id">
                                                    <button type="button" @click="choosePerson(row, c)" :disabled="busy"
                                                            class="w-full flex items-center gap-2.5 px-3 py-2 text-start hover:bg-muted/60 transition-colors disabled:opacity-50">
                                                        <span class="w-6 h-8 rounded-md overflow-hidden bg-muted grid place-items-center flex-shrink-0">
                                                            <template x-if="c.avatar"><img :src="c.avatar" alt="" class="w-full h-full object-cover"></template>
                                                            <template x-if="!c.avatar"><i class="bi bi-person text-[10px] text-muted-foreground"></i></template>
                                                        </span>
                                                        <span class="min-w-0 flex-1">
                                                            <span class="block text-[13px] font-semibold text-foreground truncate" x-text="c.name"></span>
                                                            <span class="block text-[10px] text-muted-foreground truncate" x-text="[c.email, c.phone].filter(Boolean).join(' · ')"></span>
                                                        </span>
                                                    </button>
                                                </template>
                                                <p x-show="!(row.results || []).length" x-cloak class="text-[11px] text-muted-foreground text-center py-3"
                                                   x-text="row.q ? @js(__('personal.personal_event_officials_no_matches')) : @js(__('events.bout_officials_type_to_search'))"></p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex justify-end">
                                        <button type="button" @click="removeRow(row)" :disabled="busy"
                                                class="flex items-center gap-1.5 text-[11px] font-semibold text-red-500 disabled:opacity-50">
                                            <i class="bi bi-trash3"></i>{{ __('shared.delete') }}
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <p x-show="!offRows.length" x-cloak class="text-[11px] text-muted-foreground py-1">{{ __('events.bout_officials_none') }}</p>
                        </div>
                    </div>

                            </div>

                            <div class="flex-shrink-0 flex gap-2 px-5 pt-2" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                                <button type="button" @click="open = false"
                                        class="flex-1 h-12 rounded-xl border border-gray-200 bg-white text-sm font-bold text-muted-foreground">
                                    {{ __('shared.cancel') }}
                                </button>
                                <button type="button" @click="save()" :disabled="saving"
                                        class="h-12 rounded-xl bg-primary text-white text-sm font-bold disabled:opacity-50" style="flex:1.4">
                                    {{ __('shared.save') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
                </div>
            @endif
            <a href="{{ $bout['bracket_url'] }}"
               class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0"
               title="{{ __('events.bout_view_draw') }}" aria-label="{{ __('events.bout_view_draw') }}">
                <i class="bi bi-diagram-3 bracket-icon"></i>
            </a>
            {{-- Only when there is something watchable, so the page never shows a
                 play button that goes nowhere. Ours opens in place; a legacy
                 external URL (nothing writes those any more) opens in a tab. --}}
            @if ($bout['video_url'])
                @php $boutVideoIsOurs = \Illuminate\Support\Str::startsWith($bout['video_url'], url('/')); @endphp
                <a href="{{ $bout['video_url'] }}"
                   @if ($boutVideoIsOurs) data-shell-link data-route="me.events" @else target="_blank" rel="noopener" @endif
                   class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0"
                   title="{{ __('events.bout_watch') }}" aria-label="{{ __('events.bout_watch') }}">
                    <i class="bi bi-play-fill" style="margin-inline-start:1px"></i>
                </a>
            @endif
            </div>
        </div>

        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                @if ($bout['match_no'])
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-white/20 backdrop-blur">
                        #{{ $bout['match_no'] }}
                    </span>
                @endif
                @foreach ($stageChips as $chip)
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-white/15 backdrop-blur">{{ $chip }}</span>
                @endforeach
            </div>
            {{-- Event first, matchup beneath it. --}}
            <a href="{{ route('me.events.show', $e['key']) }}"
               class="block text-2xl font-black mt-3 leading-tight">{{ $e['title'] }}</a>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-people-fill"></i>
                <span class="truncate">
                    {{ $bout['a']['name'] ?: __('events.bout_tbd') }}
                    <span class="text-white/60">{{ __('events.bout_vs') }}</span>
                    {{ $bout['b']['name'] ?: __('events.bout_tbd') }}
                </span>
            </p>
        </div>
    </header>

    {{-- Where it took place --}}
    <div class="-mt-12 relative z-10 space-y-4">
        <div class="m-card bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
            <div class="grid grid-cols-3 gap-3 text-center">
                @foreach ([
                    ['bi-geo-alt', __('events.bout_mat'), $bout['court'] ? __('events.bout_mat_n', ['n' => $bout['court']]) : '—'],
                    ['bi-calendar3', __('events.bout_day'), $bout['day'] ? __('events.bout_day_n', ['n' => $bout['day']]) : $eventDay],
                    ['bi-clock', __('events.bout_time'), $bout['scheduled_time'] ?: '—'],
                ] as [$icon, $label, $value])
                    <div>
                        <i class="bi {{ $icon }} text-primary text-lg"></i>
                        <p class="text-[11px] text-muted-foreground mt-1">{{ $label }}</p>
                        <p class="text-sm font-bold text-gray-900">{{ $value }}</p>
                    </div>
                @endforeach
            </div>
        </div>


        {{-- The duel --}}
        <div class="m-card bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            @foreach (['a', 'b'] as $side)
                @php
                    $c = $bout[$side];
                    // The colour follows the athlete's corner, so a bout where the
                    // draw slots and the mat corners disagree still reads correctly.
                    $tone = $corners[$c['corner']] ?? $corners['red'];
                @endphp

                @if ($side === 'b')
                    {{-- The result sits between the corners, where a scoreboard puts it --}}
                    <div class="flex items-center gap-3 px-4">
                        <div class="h-px flex-1 bg-gray-100"></div>
                        <span class="text-[11px] font-bold text-muted-foreground tracking-wider">
                            {{ $decided ? __('events.bout_result') : __('events.bout_status_'.($bout['status'] ?: 'upcoming')) }}
                        </span>
                        <div class="h-px flex-1 bg-gray-100"></div>
                    </div>
                @endif

                <div class="p-4 flex items-center gap-3 relative group {{ $c['profile_url'] ? 'hover:bg-muted/30 transition-colors' : '' }}">
                    {{-- The whole row leads to the athlete. An overlay anchor rather
                         than wrapping the row, so the club link inside keeps its own
                         destination: the overlay sits above the text (z-1) and below
                         the links that must stay their own (z-2). --}}
                    @if ($c['profile_url'])
                        <a href="{{ $c['profile_url'] }}" class="absolute inset-0 z-[1]"
                           aria-label="{{ $c['name'] ?: __('events.bout_tbd') }}"></a>
                    @endif
                    {{-- Corner colour and face are ONE plate: the stripe is the
                         portrait's left edge, not a separate bar beside it. Portrait
                         3:4, the platform's profile-picture ratio. --}}
                    <span class="flex items-stretch h-12 rounded-lg overflow-hidden flex-shrink-0">
                        <span class="w-1.5 flex-shrink-0" style="background:{{ $tone['bar'] }}"></span>
                        <span class="w-9 bg-muted grid place-items-center overflow-hidden">
                            @if ($c['photo'])
                                <img src="{{ $c['photo'] }}" alt="" class="w-full h-full object-cover">
                            @else
                                <x-gender-avatar :gender="$c['gender']" class="w-full h-full" />
                            @endif
                        </span>
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            @if ($c['profile_url'])
                                <a href="{{ $c['profile_url'] }}" class="relative z-[2] font-bold text-gray-900 truncate hover:text-primary group-hover:text-primary transition-colors">
                                    {{ $c['name'] ?: __('events.bout_tbd') }}
                                </a>
                            @else
                                <span class="font-bold text-gray-900 truncate">{{ $c['name'] ?: __('events.bout_tbd') }}</span>
                            @endif
                            @if ($c['won'])
                                <i class="bi bi-trophy-fill text-amber-500 text-sm flex-shrink-0"></i>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 mt-1 text-xs text-muted-foreground">
                            {{-- The real flag, not just the ISO letters. flag-icons is
                                 loaded by layouts.app; the code is lowercased for its
                                 class and kept as a label for screen readers and for
                                 any code the sprite has no flag for. --}}
                            @if ($c['country'])
                                <span class="flex items-center gap-1.5 flex-shrink-0">
                                    <span class="fi fi-{{ strtolower($c['country']) }} rounded-sm"
                                          style="width:18px;height:13px;background-size:cover;box-shadow:0 0 0 1px rgba(0,0,0,.08)"
                                          role="img" aria-label="{{ strtoupper($c['country']) }}"></span>
                                    <span class="font-semibold">{{ strtoupper($c['country']) }}</span>
                                </span>
                            @endif
                            @if ($c['club'])
                                <span class="flex items-center gap-1 min-w-0">
                                    @if ($c['club']['logo'])
                                        <span class="w-4 h-4 flex-shrink-0">
                                            <img src="{{ file_url($c['club']['logo']) }}" alt=""
                                                 class="w-full h-full object-contain">
                                        </span>
                                    @endif
                                    @if ($c['club']['url'])
                                        <a href="{{ $c['club']['url'] }}" class="relative z-[2] truncate hover:text-primary transition-colors">{{ $c['club']['name'] }}</a>
                                    @else
                                        <span class="truncate">{{ $c['club']['name'] }}</span>
                                    @endif
                                </span>
                            @endif
                            @if ($c['seed'])
                                <span>{{ __('events.bout_seed_n', ['n' => $c['seed']]) }}</span>
                            @endif
                        </div>
                    </div>

                    <span class="text-2xl font-black tabular-nums flex-shrink-0" style="color:{{ $c['score'] === null ? '#d1d5db' : $tone['score'] }}">
                        {{ $c['score'] ?? '–' }}
                    </span>
                </div>
            @endforeach
        </div>


        {{-- Who officiated. Appointed to the championship, not to this single bout
             (event_officials carries no match), so the hint says as much. --}}
        @if (! empty($officials))
            <div class="m-card bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                <div class="flex items-baseline justify-between gap-2 mb-3">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground">{{ __('events.bout_officials') }}</p>
                    <p class="text-[10px] text-muted-foreground/80">{{ __('events.bout_officials_hint') }}</p>
                </div>
                <div class="space-y-2.5">
                    @foreach ($officials as $o)
                        <div class="flex items-center gap-3 relative group {{ $o['profile_url'] ? 'rounded-xl -mx-1 px-1 py-0.5 hover:bg-muted/30 transition-colors' : '' }}">
                            {{-- The whole row leads to the official, same overlay-anchor
                                 device as the corners above. --}}
                            @if ($o['profile_url'])
                                <a href="{{ $o['profile_url'] }}" class="absolute inset-0 z-[1]"
                                   aria-label="{{ $o['name'] }}"></a>
                            @endif
                            {{-- One plate, same device as the corners above. Amber reads
                                 as the third party on the mat. --}}
                            <span class="flex items-stretch h-12 rounded-lg overflow-hidden flex-shrink-0">
                                <span class="w-1.5 flex-shrink-0" style="background:#fbbf24"></span>
                                <span class="w-9 bg-muted grid place-items-center overflow-hidden text-muted-foreground">
                                    @if ($o['photo'])
                                        <img src="{{ $o['photo'] }}" alt="" class="w-full h-full object-cover">
                                    @else
                                        <i class="bi bi-person-badge"></i>
                                    @endif
                                </span>
                            </span>
                            <div class="min-w-0 flex-1">
                                @if ($o['profile_url'])
                                    <a href="{{ $o['profile_url'] }}" class="relative z-[2] block font-semibold text-sm text-gray-900 truncate hover:text-primary group-hover:text-primary transition-colors">{{ $o['name'] }}</a>
                                @else
                                    <span class="block font-semibold text-sm text-gray-900 truncate">{{ $o['name'] }}</span>
                                @endif
                                <p class="text-[11px] text-muted-foreground flex items-center gap-1.5">
                                    {{-- An official's flag is their nationality; omitted
                                         silently when none is on file. --}}
                                    @if ($o['country'])
                                        <span class="fi fi-{{ strtolower($o['country']) }} rounded-sm"
                                              style="width:16px;height:12px;background-size:cover;box-shadow:0 0 0 1px rgba(0,0,0,.08)"
                                              role="img" aria-label="{{ $o['country'] }}"></span>
                                    @endif
                                    {{ $o['role_label'] }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection

@if ($canManage)
    @push('scripts')
            <script>
                function boutEditor(bout, urls, labels) {
                    return {
                        open: false, saving: false, busy: false,
                        f: {
                            a_corner: bout.a.corner, b_corner: bout.b.corner,
                            a_score: bout.a.score, b_score: bout.b.score,
                            winner: bout.winner,
                            a_name: bout.a.name, b_name: bout.b.name,
                            a_competitor_id: null, b_competitor_id: null,
                        },
                        entrants: [], entQ: '', openPick: null,
                        officials: [], roles: [],

                        init() {
                            // Loaded when the sheet is first opened, not on page load:
                            // a viewer who never edits should not pay for either call.
                            this.$watch('open', (v) => { if (v && !this.entrants.length) this.load(); });
                        },
                        async load() {
                            try {
                                const [ent, off] = await Promise.all([
                                    fetch(urls.competitors, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(r => r.json()),
                                    fetch(urls.officials, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(r => r.json()),
                                ]);
                                this.entrants = ent.competitors || [];
                                this.officials = off.officials || [];
                                this.roles = off.roles || [];
                                // Rebuild the rows from the appointments, so the panel
                                // shows what the event holds rather than an empty list.
                                this.offRows = this.officials.map((o) => {
                                    const row = this.blankRow(o.role);
                                    row.id = o.id;
                                    row.name = o.name;
                                    row.sub = this.roleLabel(o.role);
                                    row.photo = o.avatar || null;
                                    return row;
                                });
                                // Match the names already on the sheet back to entries,
                                // so the pickers open on the right people.
                                ['a', 'b'].forEach((side) => {
                                    const hit = this.entrants.find(e => e.name === this.f[side + '_name']);
                                    if (hit) this.f[side + '_competitor_id'] = hit.id;
                                });
                            } catch (e) { window.showToast && window.showToast('error', labels.loadFailed); }
                        },
                        get entrantsFiltered() {
                            const q = (this.entQ || '').toLowerCase();
                            return this.entrants.filter(e => !q || (e.name || '').toLowerCase().includes(q)
                                || (e.club || '').toLowerCase().includes(q));
                        },
                        picked(side) { return this.entrants.find(e => e.id === this.f[side + '_competitor_id']) || null; },
                        pickAthlete(side, ent) {
                            const other = side === 'a' ? 'b' : 'a';
                            // Taking the athlete already in the other corner would make a
                            // bout against oneself, so the corners swap instead.
                            if (this.f[other + '_competitor_id'] === ent.id) {
                                this.f[other + '_competitor_id'] = this.f[side + '_competitor_id'];
                                this.f[other + '_name'] = this.f[side + '_name'];
                            }
                            this.f[side + '_competitor_id'] = ent.id;
                            this.f[side + '_name'] = ent.name;
                            this.openPick = null;
                        },
                        roleLabel(v) { return (this.roles.find(r => r.value === v) || {}).label || v; },
                        get winnerOptions() {
                            return [
                                { v: 'a', l: this.f.a_name || 'A' },
                                { v: 'b', l: this.f.b_name || 'B' },
                                { v: null, l: labels.none },
                            ];
                        },
                        pickCorner(side, colour) {
                            const other = side === 'a' ? 'b' : 'a';
                            this.f[side + '_corner'] = colour;
                            this.f[other + '_corner'] = colour === 'red' ? 'blue' : 'red';
                        },
                        // ── Officials, as rows ───────────────────────────────
                        // A row is a draft until a person is chosen; choosing one
                        // appoints them, so an organiser never has to remember a
                        // separate save for something they plainly just did.
                        offRows: [], rowSeq: 0,

                        blankRow(role) {
                            return {
                                key: ++this.rowSeq, id: null, role: role || null,
                                name: null, sub: null, photo: null,
                                q: '', results: [], openRole: false, openPerson: false, roleQ: '',
                            };
                        },
                        addOfficialRow() {
                            // Defaults to the sport's first position, which is the
                            // referee — the one most often being added.
                            this.offRows.push(this.blankRow(this.roles.length ? this.roles[0].value : null));
                        },
                        rolesFor(row) {
                            const q = (row.roleQ || '').toLowerCase();
                            return this.roles.filter(r => !q || (r.label || '').toLowerCase().includes(q));
                        },
                        async setRole(row, role) {
                            row.openRole = false;
                            const previous = row.role;
                            row.role = role;
                            // An existing appointment is re-roled in place, so its
                            // history and its linked expense survive the change.
                            if (row.id) {
                                const ok = await this.saveRole(row);
                                if (!ok) row.role = previous;
                            }
                        },
                        async saveRole(row) {
                            this.busy = true;
                            let ok = false;
                            try {
                                const res = await fetch(urls.officials + '/' + row.id, {
                                    method: 'PUT',
                                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                                    credentials: 'same-origin',
                                    body: JSON.stringify({ role: row.role, compensation: 'volunteer' }),
                                });
                                const d = await res.json().catch(() => ({}));
                                if (!res.ok || !d.success) throw new Error(d.message || labels.saveFailed);
                                ok = true;
                            } catch (e) { window.showToast && window.showToast('error', e.message); }
                            this.busy = false;
                            return ok;
                        },
                        async searchPeople(row) {
                            if (!row.q) { row.results = []; return; }
                            try {
                                // The pool follows the position: a mat role searches the
                                // platform, a permission role the host club.
                                const d = await fetch(urls.officials + '?role=' + encodeURIComponent(row.role || '') + '&q=' + encodeURIComponent(row.q),
                                    { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(r => r.json());
                                row.results = d.candidates || [];
                            } catch (e) { row.results = []; }
                        },
                        async choosePerson(row, c) {
                            if (this.busy || !row.role) return;
                            this.busy = true;
                            try {
                                const res = await fetch(urls.officials, {
                                    method: 'POST',
                                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                                    credentials: 'same-origin',
                                    // Volunteer by default; what an official is paid is set
                                    // on the event's officials screen, beside the ledger.
                                    body: JSON.stringify({ user_id: c.id, role: row.role, compensation: 'volunteer' }),
                                });
                                const d = await res.json().catch(() => ({}));
                                if (!res.ok || !d.success) throw new Error(d.message || labels.saveFailed);
                                window.showToast && window.showToast('success', d.message);
                                row.openPerson = false; row.q = ''; row.results = [];
                                await this.load();
                            } catch (e) { window.showToast && window.showToast('error', e.message); }
                            this.busy = false;
                        },
                        async removeRow(row) {
                            // A draft row was never saved, so it just goes.
                            if (!row.id) { this.offRows = this.offRows.filter(r => r.key !== row.key); return; }
                            if (this.busy) return;
                            this.busy = true;
                            try {
                                const res = await fetch(urls.officials + '/' + row.id, {
                                    method: 'DELETE',
                                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                                    credentials: 'same-origin',
                                });
                                const d = await res.json().catch(() => ({}));
                                if (!res.ok || !d.success) throw new Error(d.message || labels.saveFailed);
                                window.showToast && window.showToast('success', d.message);
                                await this.load();
                            } catch (e) { window.showToast && window.showToast('error', e.message); }
                            this.busy = false;
                        },

                        csrf() { return document.querySelector('meta[name=csrf-token]')?.content || ''; },
                        async save() {
                            if (this.saving) return;
                            this.saving = true;
                            try {
                                const res = await fetch(urls.update, {
                                    method: 'PUT',
                                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                                    credentials: 'same-origin',
                                    body: JSON.stringify({
                                        a_corner: this.f.a_corner, b_corner: this.f.b_corner,
                                        a_score: this.f.a_score === '' ? null : this.f.a_score,
                                        b_score: this.f.b_score === '' ? null : this.f.b_score,
                                        winner: this.f.winner,
                                        a_name: this.f.a_name, b_name: this.f.b_name,
                                        a_competitor_id: this.f.a_competitor_id,
                                        b_competitor_id: this.f.b_competitor_id,
                                    }),
                                });
                                const d = await res.json().catch(() => ({}));
                                if (!res.ok || !d.success) throw new Error(d.message || labels.saveFailed);
                                window.showToast && window.showToast('success', d.message);
                                this.open = false;
                                // Scores, corners, faces and the play button all come from
                                // this data, so the page is re-read rather than patched.
                                window.location.reload();
                            } catch (e) { window.showToast && window.showToast('error', e.message); }
                            this.saving = false;
                        },
                    };
                }
            </script>
    @endpush
@endif
