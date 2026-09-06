{{-- `$shell` is shared ONLY on the sealed event routes (/e/{uuid}/admin/…), so with
     nothing shared this is the member shell exactly as before. See entry/shell. --}}
@extends($shell ?? 'layouts.personal-mobile')

@section('title', $e['title'])

{{--
    Event detail — mobile. DUMMY content driven by PersonalMobileController@eventShow.
    Stylish, animated single-event page: gradient cover, going/like actions,
    capacity, agenda timeline, attendees, location, share. Reuses the shared
    mobile motion vocabulary (m-hero, m-card, m-press, m-bar-fill, m-float) and
    design tokens. Wire the action buttons to real endpoints when ready.
--}}
@section('personal-content')
@php
    /* An event can now be priced entirely out of OPTIONS, with no base fee at
       all ("Gi 15 / No-Gi 15"). Reading the base line alone then says "free"
       and the money sections of this page disappear, so the payload's own
       option list has a say too. */
    $pPaid   = (!str_contains(strtolower($e['participant_fee']), 'free') && !str_contains(strtolower($e['participant_fee']), 'qualified')) || ! empty($e['fees']['options']);
    $byQual  = str_contains(strtolower($e['participant_fee']), 'qualified');
    $hasTicket = !empty($e['spectator']);
    $ticketPaid = ($hasTicket && !str_contains(strtolower($e['spectator']['fee']), 'free')) || ! empty($e['fees']['spectator_options']);

    // Why competing is not on offer — the exact sentence the server produced, so
    // the apology dialog gives the real reason rather than a generic refusal.
    // Defined up here because partials.event-show-script (included below) reads it.
    $whyNot = ($banned ?? false)
        ? ($eligReason ?? __('personal.event_show_removed_default'))
        : (($e['ended'] ?? false)
            ? __('personal.event_show_ended_msg')
            : ($byQual
                ? __('personal.event_show_entry_by_qualification')
                : ($eligReason ?? __('personal.event_show_not_eligible_default'))));
@endphp
<div @include('partials.event-show-script')
     class="-mx-4 -mt-4 pb-4">

    {{-- ===== Cover ===== --}}
    <header class="m-hero px-5 pt-5 pb-16 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $e['color'] }}, {{ $e['color'] }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        {{-- top bar (z-50 so the manage dropdown paints above the title block below) --}}
        <div class="flex items-center justify-between relative z-50">
{{-- ⚠️ AN ADDRESS, NOT A GESTURE.

                 `history.back()` was here, and on a surface people reach from a
                 shared link it is never safe: a link opened from WhatsApp has an
                 EMPTY history, and the `history.length > 1` guard does not save
                 it — a redirect earlier in the session makes the length pass and
                 the gesture then falls into whatever the browser remembers,
                 which inside the sealed event app was sometimes the platform.
                 Back now names where it goes (Design Rule #6) and gets there by
                 address. --}}
            @php
                /* Inside the sealed event app the root is the POSTER — the page
                   that was actually shared. On the platform it is the member's
                   events list, as before. */
                /* `key` is this payload's public identifier — the same one every
                   other route() call on this page uses. There is no `uuid` key. */
                $backHref = isset($shell) ? url('/e/'.$e['key']) : route('me.events');
                $backLabel = isset($shell) ? __('events.public_enrol_back_event') : __('personal.event_show_events');
            @endphp
            <a href="{{ $backHref }}"
               class="m-press inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold no-underline"
           aria-label="{{ $backLabel }}" title="{{ $backLabel }}">
                <i class="bi bi-chevron-left"></i>
            </a>
            <div class="flex items-center gap-2">
                {{-- The door to the console. Running the event is a different job
                     from reading this page, so it is one button out, not a set of
                     organiser tools threaded through the content. --}}
                @if(($canManage ?? false) || ($canOfficiate ?? false))
                    <a href="{{ route('me.events.manage', $e['key']) }}" data-shell-link data-route="me.events"
                       class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center" aria-label="{{ __('personal.event_manage_title') }}">
                        <i class="bi bi-sliders text-base"></i>
                    </a>
                @endif
                <x-qr-code
                    :url="$shareUrl ?? route('me.events.show', ['event' => $e['key']])"
                    :title="$e['title'] . ' — ' . __('personal.event_show_event')"
                    caption="{{ __('personal.event_show_qr_caption') }}"
                    :filename="'qr-event-' . $e['key']"
                    label=""
                    icon="bi-qr-code"
                    :poster-url="route('qr.event', ['event' => $e['key']])"
                    button-class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center text-white" />
                <button type="button" @click="$dispatch('share-event')"
                        class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center" aria-label="{{ __('personal.event_show_share') }}">
                    <i class="bi bi-share text-base"></i>
                </button>
            </div>
        </div>

        {{-- cancelled banner --}}
        <div x-show="cancelled" x-cloak class="relative z-10 mt-4 -mb-2 rounded-xl bg-white/20 backdrop-blur px-3 py-2 text-xs font-bold flex items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill"></i> {{ __('personal.event_show_cancelled_banner') }}
        </div>

        <div class="relative z-10 mt-6">
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
    </header>

    {{-- ===== Quick facts card (overlaps cover) ===== --}}
    <div class="px-4 -mt-10 relative z-10">
        <div class="bg-white rounded-3xl shadow-lg border border-gray-100 p-4">
            {{-- Quick facts are not just a summary — each one is the door to its
                 own fuller answer further down the page. When / how much / where,
                 and a tap takes you to the run-of-show, the way in, or the map.
                 The chip does the linking work the reader would otherwise do by
                 scrolling — which on a phone is most of the page. --}}
            <div class="grid grid-cols-3 gap-2 text-center">
                {{-- When: the day AND the time it starts. Lands on the
                     run-of-show row for the event's own date. --}}
                <button type="button" @click="jump(['run-start', 'how-it-runs'])"
                        class="m-press rounded-xl -m-1 p-1"
                        aria-label="{{ __('personal.event_show_how_it_runs') }}">
                    <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-calendar3"></i></div>
                    <p class="text-xs font-bold text-foreground mt-1.5 truncate">{{ $e['wday'] }} {{ $e['day'] }} {{ $e['mon'] }}</p>
                    <p class="text-[10px] text-muted-foreground truncate">{{ $e['time'] }}</p>
                </button>
                {{-- How much: lands on the Participate row and makes it announce
                     itself — "what does it cost" and "how do I get in" are the
                     same question asked twice. --}}
                <button type="button" @click="jump('join-participate')"
                        class="m-press border-x border-gray-100 py-1"
                        aria-label="{{ $byQual ? __('personal.event_show_entry') : __('personal.event_show_to_join') }}">
                    <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-cash-coin"></i></div>
                    <p class="text-xs font-bold text-foreground mt-1.5 truncate">{{ $e['participant_fee'] }}</p>
                    <p class="text-[10px] text-muted-foreground truncate">{{ $byQual ? __('personal.event_show_entry') : __('personal.event_show_to_join') }}</p>
                </button>
                {{-- Where: lands on the map. A button like its two neighbours —
                     one inert cell in a row of three tappable ones reads as
                     broken, not as restraint. --}}
                <button type="button" @click="jump('where')"
                        class="m-press min-w-0 rounded-xl -m-1 p-1"
                        aria-label="{{ __('personal.event_show_location') }}">
                    <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-geo-alt"></i></div>
                    <p class="text-xs font-bold text-foreground mt-1.5 truncate" title="{{ $e['location'] }}">{{ $e['location'] }}</p>
                    <p class="text-[10px] text-muted-foreground">{{ __('personal.event_show_venue') }}</p>
                </button>
            </div>

            {{-- Capacity — only when the organiser actually set one. With no
                 limit there are no "spots left" to count, and the bar would read
                 100% full for an event anyone can still join. --}}
            @if($e['capped'] ?? false)
                <div class="mt-5">
                    <div class="flex items-center justify-between text-[11px] mb-1.5">
                        <span class="font-semibold text-foreground"><span x-text="goingCount">{{ $e['going'] }}</span> {{ __('personal.event_show_going') }}</span>
                        <span class="text-muted-foreground"><span x-text="Math.max(0, cap - goingCount)">{{ max(0, $e['cap'] - $e['going']) }}</span> {{ __('personal.event_show_spots_left') }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-muted overflow-hidden">
                        <div class="m-bar-fill h-full rounded-full" :style="`width:${pct}%; background:{{ $e['color'] }}`" style="width: {{ round($e['going'] / $e['cap'] * 100) }}%"></div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ===== Winners / results ===== --}}
    <div class="px-4 mt-4" x-show="results.length > 0" x-cloak>
        <div class="m-card rounded-2xl p-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-trophy-fill text-amber-500"></i> {{ __('personal.event_show_winners') }}</h2>
            </div>
            <div class="mt-3 space-y-2">
                <template x-for="w in results" :key="w.place + '-' + w.name">
                    <div class="flex items-center gap-3 rounded-xl p-2.5" :style="`background:${medal(w.place)}12`">
                        <div class="w-9 h-9 rounded-full grid place-items-center text-white flex-shrink-0 font-black text-xs" :style="`background:${medal(w.place)}`">
                            <i class="bi" :class="w.place===1 ? 'bi-trophy-fill' : (w.place<=3 ? 'bi-award-fill' : 'bi-award')" x-show="w.place<=3"></i>
                            <span x-show="w.place>3" x-text="w.place"></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-foreground truncate" x-text="w.name"></p>
                            <p class="text-[11px] text-muted-foreground" x-text="w.place===1 ? '{{ __("personal.event_show_champion") }}' : (w.place===2 ? '{{ __("personal.event_show_runner_up") }}' : (w.place===3 ? '{{ __("personal.event_show_third_place") }}' : ('#' + w.place)))"></p>
                        </div>
                        <span class="text-[11px] font-black flex-shrink-0" :style="`color:${medal(w.place)}`" x-text="w.prize"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>


    @include('partials.event-detail-card-mobile')

    {{-- ===== League: standings + fixtures ===== --}}
    @if(!empty($league))
        @php $lg = $league; @endphp
        @if(!empty($lg['standings']))
            <div class="px-4 mt-4">
                <div class="m-card rounded-2xl p-4">
                    <h2 class="text-sm font-bold text-foreground flex items-center gap-2 mb-3"><i class="bi bi-table text-primary"></i> {{ __('personal.event_show_standings') }}</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-[12px]">
                            <thead>
                                <tr class="text-muted-foreground text-[10px] uppercase tracking-wide">
                                    <th class="text-start font-semibold pb-2 ps-1">#</th>
                                    <th class="text-start font-semibold pb-2">{{ __('personal.event_show_team') }}</th>
                                    <th class="font-semibold pb-2 w-7">{{ __('personal.event_show_col_played') }}</th>
                                    <th class="font-semibold pb-2 w-7">{{ __('personal.event_show_col_won') }}</th>
                                    <th class="font-semibold pb-2 w-7">{{ __('personal.event_show_col_drawn') }}</th>
                                    <th class="font-semibold pb-2 w-7">{{ __('personal.event_show_col_lost') }}</th>
                                    <th class="font-semibold pb-2 w-9">{{ __('personal.event_show_col_gd') }}</th>
                                    <th class="font-semibold pb-2 w-9 text-end pe-1">{{ __('personal.event_show_col_pts') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($lg['standings'] as $i => $row)
                                    <tr class="border-t border-gray-50 {{ $i < 3 ? 'font-semibold' : '' }}">
                                        <td class="py-2 ps-1 text-muted-foreground">{{ $i + 1 }}</td>
                                        <td class="py-2 text-foreground truncate max-w-[120px]">{{ $row['team'] }}</td>
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
            </div>
        @endif

        @if(!empty($lg['fixtures']))
            <div class="px-4 mt-4">
                <div class="m-card rounded-2xl p-4">
                    <h2 class="text-sm font-bold text-foreground flex items-center gap-2 mb-3"><i class="bi bi-calendar2-week text-primary"></i> {{ __('personal.event_show_fixtures') }}</h2>
                    <div class="space-y-2">
                        @foreach($lg['fixtures'] as $f)
                            @php $played = $f['home_score'] !== null && $f['away_score'] !== null; @endphp
                            <div class="flex items-center gap-2 rounded-xl bg-muted/40 px-3 py-2">
                                <span class="flex-1 text-end text-sm font-semibold text-foreground truncate">{{ $f['home'] }}</span>
                                @if($played)
                                    <span class="px-2 py-0.5 rounded-lg text-xs font-black text-white" style="background: {{ $e['color'] }};">{{ $f['home_score'] }} – {{ $f['away_score'] }}</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-lg text-[11px] font-bold bg-white text-muted-foreground border border-gray-100">{{ $f['date'] ?: __('personal.event_show_vs') }}</span>
                                @endif
                                <span class="flex-1 text-start text-sm font-semibold text-foreground truncate">{{ $f['away'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- ===== Draw · Officials · Participants — the three doors out of this page.
         Three identical full-width rows, icon beside the label rather than above
         it. They were squares in one row; at a third of a phone's width the label
         had 44px to live in, so "Participants" truncated and the count under it
         was unreadable. Stacked, all three are the same size and each one says
         what it is and how many are in it. The Draw row only exists when the
         event has categories. --}}
    @php
        $hasBrackets = !empty($e['categories']);
        $doors = [];

        if ($hasBrackets) {
            // Entrants, not divisions: "how many are in the draw" is the number
            // an organiser is checking, and a division count reads as full when
            // nobody has entered a single one of them.
            $athleteTotal = collect($e['categories'])->sum('joined');
            $doors[] = [
                'href' => route('me.events.bracket', $e['key']),
                'icon' => 'bi-diagram-3-fill bracket-icon',
                'label' => __('personal.event_show_tile_draw'),
                'sub' => $athleteTotal.' '.__('personal.event_show_entrants'),
            ];
        }

        $doors[] = [
            'href' => route('me.events.officiating', $e['key']),
            'icon' => 'bi-person-badge-fill',
            'label' => __('personal.event_show_tile_officials'),
            'sub' => trans_choice('personal.event_manage_officials_count', $e['officials_count'] ?? 0, ['count' => $e['officials_count'] ?? 0]),
        ];

        // The gallery — every bout that was filmed, grouped by division. Always
        // a door, like Participants: an event's footage is a place people go
        // looking for, and a door that only appears once something is behind it
        // cannot be found before then. The gallery says so itself when empty.
        $doors[] = [
            'href' => route('me.events.gallery', $e['key']),
            'icon' => 'bi-camera-reels-fill',
            'label' => __('events.bout_gallery_title'),
            'sub' => ($e['clips_count'] ?? 0) > 0
                ? trans_choice('events.bout_gallery_count', $e['clips_count'], ['count' => $e['clips_count']])
                : __('events.bout_gallery_none'),
        ];
    @endphp
    <div class="px-4 mt-4">
        <div class="space-y-3">
            @foreach($doors as $d)
                <a href="{{ $d['href'] }}" data-shell-link data-route="me.events"
                   class="m-press rounded-2xl p-4 text-white relative overflow-hidden shadow-lg flex items-center gap-3.5"
                   style="background: linear-gradient(135deg, {{ $e['color'] }}, #1f2937);">
                    <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10"></div>
                    <div class="relative w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                        {{-- inline-block via .bracket-icon: a bare <i> is an inline
                             box and CSS transforms do not apply to those, so the
                             bracket's quarter turn would silently do nothing. --}}
                        <i class="bi {{ $d['icon'] }} text-2xl"></i>
                    </div>
                    <div class="relative min-w-0 flex-1">
                        <h3 class="font-black text-base leading-tight">{{ $d['label'] }}</h3>
                        <p class="text-xs text-white/85 mt-0.5">{{ $d['sub'] }}</p>
                    </div>
                    <i class="bi bi-chevron-right text-white/80 relative flex-shrink-0"></i>
                </a>
            @endforeach

            {{-- Participants — the roster, on its own page. Bound to this page's
                 Alpine counters, so joining or removing someone updates the row
                 without a reload. --}}
            <a href="{{ route('me.events.people', $e['key']) }}" data-shell-link data-route="me.events"
               class="m-press rounded-2xl p-4 text-white relative overflow-hidden shadow-lg flex items-center gap-3.5"
               style="background: linear-gradient(135deg, {{ $e['color'] }}, #1f2937);">
                <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10"></div>
                <div class="relative w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                    <i class="bi bi-people-fill text-2xl"></i>
                </div>
                <div class="relative min-w-0 flex-1">
                    <h3 class="font-black text-base leading-tight">{{ $byQual ? __('personal.event_show_finalists') : __('personal.event_show_tile_participants') }}</h3>
                    <p class="text-xs text-white/85 mt-0.5">
                        <span x-text="goingCount">{{ $e['participants_total'] ?? $e['going'] }}</span> {{ __('personal.event_show_in') }}
                        @if($hasTicket)
                            · <span x-text="spectators">{{ $e['spectator']['count'] }}</span> {{ __('personal.event_show_spectators') }}
                        @endif
                    </p>
                </div>
                <i class="bi bi-chevron-right text-white/80 relative flex-shrink-0"></i>
            </a>

            {{-- Results — the same row as the doors above it, in gold: it is the
                 last thing to happen at an event, and the only one of these rows
                 that opens a sheet rather than a page. Appears only once the
                 finals are decided. --}}
            @if(!empty($e['bracket_results']))
                <button type="button" @click="showResultsOpen=true"
                        class="m-press w-full rounded-2xl p-4 text-white relative overflow-hidden shadow-lg flex items-center gap-3.5 text-start"
                        style="background: linear-gradient(135deg, #eab308, #78350f);">
                    <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10"></div>
                    <div class="relative w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                        <i class="bi bi-trophy-fill text-2xl"></i>
                    </div>
                    <div class="relative min-w-0 flex-1">
                        <h3 class="font-black text-base leading-tight">{{ __('personal.event_show_show_results') }}</h3>
                        <p class="text-xs text-white/85 mt-0.5">
                            {{ trans_choice('personal.event_show_results_divisions', count($e['bracket_results']), ['count' => count($e['bracket_results'])]) }}
                        </p>
                    </div>
                    <i class="bi bi-chevron-right text-white/80 relative flex-shrink-0"></i>
                </button>
            @endif
        </div>
    </div>


    {{-- ===== Agenda timeline ===== --}}
    @if(!empty($e['agenda']))
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-list-check text-primary"></i> {{ __('personal.event_show_schedule') }}</h2>
            <div class="mt-3 space-y-0">
                @foreach($e['agenda'] as $i => $a)
                    <div class="flex gap-3">
                        <div class="flex flex-col items-center">
                            <span class="w-3 h-3 rounded-full" style="background: {{ $e['color'] }};"></span>
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
    </div>
    @endif

    {{-- ===== Proof of payment =====
         The join / spectate CTAs that used to sit here are gone: the two pricing
         rows above ARE the way in now, and this card only repeated them — plus an
         "you're not eligible" notice that the participant row's own dialog now
         gives on tap. What stays is the proof-of-payment flow, which has nowhere
         else to live. --}}
    {{-- REMOVED 2026-09-02 at the user's request: the "Competing for" card
         (partials.event-representing) and the "Enroll your athletes" button and
         sheet (partials.event-squad-entry). Neither is included here any more.

         The club choice is NOT lost — event-representing-cards still renders
         inside the join sheet, which an already-registered athlete reaches
         through the participant row (startJoin lets a holder back in). The two
         partials are left on disk, uncalled, so restoring either is one line.

         What is included below is teleported markup only, no visible card:
         the proof-of-payment sheet (opened by openProof() from finishJoin) and
         the join sheet (opened by startJoin() from the participant and
         spectator rows). Both are load-bearing for those buttons. --}}
    <div class="px-4 mt-4">
        @include('partials.event-payment-proof')
        @include('partials.event-join-sheet')
    </div>

    {{-- In-place division confirmation (taekwondo) --}}
    <div x-show="joinedDivision" x-cloak class="px-4 mt-2">
        <div class="m-card rounded-2xl p-3 flex items-center gap-2 text-[12px]">
            <i class="bi bi-diagram-3 bracket-icon text-primary"></i>
            <span class="text-muted-foreground">{{ __('personal.event_show_placed_in') }} <span class="font-bold text-foreground" x-text="joinedDivision"></span></span>
        </div>
    </div>

    {{-- Share handler.
         Shares the URL the server decided on ($shareUrl): the PUBLIC page when
         the organiser has published one, the member page otherwise. It used to
         share a title and a sentence with no url at all, and its "copied"
         fallback copied nothing — so the only link anybody could actually pass
         on was the member page, which asks a stranger to log in. --}}
    <div @share-event.window="
            const url = @js($shareUrl ?? url()->current());
            if (navigator.share) {
                navigator.share({ title: @js($e['title']), text: @js(__('personal.event_show_share_text', ['title' => $e['title']])), url }).catch(() => {});
                return;
            }
            navigator.clipboard?.writeText(url).then(
                () => window.showToast('success', @js(__('personal.event_show_link_copied'))),
                () => window.showToast('info', url),
            );
         "></div>

    {{-- The join/spectate buttons sit LAST on purpose: someone scrolls the
         event to decide, and the decision belongs at the end of that read
         rather than interrupting it halfway down. --}}
    {{-- ===== Join / spectate =====
         Deliberately NOT in a card. Each button is already a self-contained
         object with its own surface and shadow; wrapping them in a card put a
         frame around a frame, and its "Entry & tickets" heading only said what
         the two buttons say more clearly themselves. --}}
    <div class="px-4 mt-4">

        {{-- Each row IS the way in: the row already names the thing, its
             price and who it is for, so a separate CTA underneath only
             repeated it. Rows that cannot be taken (ineligible, already
             registered, blocked, event over) stay on screen but disabled, so
             the price list still reads as a price list.

             A row shows a tick and turns green once you hold that place. --}}
        @php
            $locked = ($banned ?? false) || ($e['ended'] ?? false);
            // $whyNot is set at the top of this view — the script partial needs it.
            $canJoin = ! $locked && ($canCompete ?? true) && ! $byQual;
        @endphp

        {{-- ===== Join / spectate =====
             Same shape as the "Brackets & draws" card further down the page:
             a dark gradient, a soft circle bleeding off the corner, a
             translucent icon tile and a chevron. Those already read as
             pressable here, so the two ways INTO the event use the same
             language rather than inventing a second one.

             Green once you hold that place. The ineligible variant keeps the
             shape but goes slate, so it still invites a tap ("Why not?")
             without pretending to be the main action. --}}

        {{-- Participant — also the amount chip's landing point. No
             --m-attn-color: the row carries an Alpine :style binding and a
             second static style on the same element is asking for one to
             clobber the other, so the pulse uses the brand primary. --}}

        <button type="button" id="join-participate"
                x-show="entriesOpen || registered" x-cloak
                @click="{{ $canJoin ? "startJoin('participant')" : 'explainIneligible()' }}"
                :disabled="registered && !feeDue"
                class="m-press mt-3 w-full block rounded-2xl p-4 text-white text-start relative overflow-hidden
                       shadow-lg transition-all active:scale-[.98] disabled:cursor-not-allowed"
                :style="(going && !feeDue)
                    ? 'background: linear-gradient(135deg, #16a34a, #1f2937)'
                    : going
                    ? 'background: linear-gradient(135deg, #d97706, #1f2937)'
                    : 'background: linear-gradient(135deg, {{ $canJoin ? $e['color'] : '#64748b' }}, #1f2937)'">
            <div class="absolute -right-6 -top-6 w-28 h-28 rounded-full bg-white/10"></div>
            <div class="relative flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                    <i class="bi text-2xl" :class="going ? 'bi-check2-circle' : '{{ $canJoin ? 'bi-person-check' : 'bi-info-circle' }}'"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="font-black text-base leading-tight"
                        x-text="!going ? '{{ $byQual ? __('personal.event_show_take_part') : __('personal.event_show_join_participant') }}' : (feeDue ? '{{ __('personal.event_show_place_held_fee_due') }}' : '{{ __('personal.event_show_youre_participant') }}')">{{ $byQual ? __('personal.event_show_take_part') : __('personal.event_show_join_participant') }}</h3>
                    <p class="text-xs text-white/85 mt-0.5">
                        @if(!($canCompete ?? true)) {{ __('personal.event_show_not_eligible_spectators') }}
                        @elseif($byQual) {{ __('personal.event_show_reserved_finalists') }}
                        @elseif($pPaid) {{ __('personal.event_show_fee_paid_club') }}
                        @else {{ __('personal.event_show_free_members') }} @endif
                    </p>
                </div>
                <span class="text-sm font-black flex-shrink-0"
                      x-text="!going ? '{{ $canJoin ? $e['participant_fee'] : __('personal.event_show_cta_why') }}' : (feeDue ? '{{ __('personal.event_show_cta_fee_due') }}' : '{{ __('personal.event_show_cta_joined') }}')">{{ $canJoin ? $e['participant_fee'] : __('personal.event_show_cta_why') }}</span>
                <i class="bi bi-chevron-right text-white/80 flex-shrink-0"></i>
            </div>
        </button>

        {{-- Spectator ticket --}}
        @if($hasTicket)
            <button type="button" @click="startJoin('spectator')"
                    :disabled="(registered && !feeDue) || {{ $locked ? 'true' : 'false' }}"
                    class="m-press mt-2.5 w-full block rounded-2xl p-4 text-white text-start relative overflow-hidden
                           shadow-lg transition-all active:scale-[.98]
                           disabled:cursor-not-allowed disabled:opacity-60"
                    :style="(watching && !feeDue)
                        ? 'background: linear-gradient(135deg, #16a34a, #1f2937)'
                        : watching
                        ? 'background: linear-gradient(135deg, #d97706, #1f2937)'
                        : 'background: linear-gradient(135deg, {{ $ticketPaid ? '#7c3aed' : '#0284c7' }}, #1f2937)'">
                <div class="absolute -right-6 -top-6 w-28 h-28 rounded-full bg-white/10"></div>
                <div class="relative flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                        <i class="bi text-2xl" :class="watching ? 'bi-check2-circle' : 'bi-ticket-perforated'"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-black text-base leading-tight"
                            x-text="!watching ? '{{ __('personal.event_show_spectator_ticket') }}' : (feeDue ? '{{ __('personal.event_show_place_held_fee_due') }}' : '{{ __('personal.event_show_ticket_booked') }}')">{{ __('personal.event_show_spectator_ticket') }}</h3>
                        <p class="text-xs text-white/85 mt-0.5"><span x-text="spectators">{{ $e['spectator']['count'] }}</span> {{ __('personal.event_show_watching') }}{{ $ticketPaid ? ' · '.__('personal.event_show_entry_watch_matches') : ' · '.__('personal.event_show_free_to_watch') }}</p>
                    </div>
                    <span class="text-sm font-black flex-shrink-0"
                          x-text="!watching ? '{{ $e['spectator']['fee'] }}' : (feeDue ? '{{ __('personal.event_show_cta_fee_due') }}' : '{{ __('personal.event_show_cta_booked') }}')">{{ $e['spectator']['fee'] }}</span>
                    <i class="bi bi-chevron-right text-white/80 flex-shrink-0"></i>
                </div>
            </button>
        @endif
    </div>


    {{-- ===== Results sheet (combat — computed from brackets) ===== --}}
    @if(!empty($e['bracket_results']))
        <template x-teleport="body">
        <div x-show="showResultsOpen" x-cloak class="fixed inset-0 z-[60]" style="display:none;">
            <div class="absolute inset-0 bg-black/40" @click="showResultsOpen=false" x-transition.opacity></div>
            <div class="absolute bottom-0 inset-x-0 bg-white rounded-t-3xl max-h-[88vh] flex flex-col"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, #b45309, #d97706b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-trophy-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('personal.event_show_results_medals') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5 truncate">{{ $e['title'] }}</p>
                        </div>
                        <button type="button" @click="showResultsOpen=false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto p-4 space-y-3">
                    @foreach($e['bracket_results'] as $r)
                        <div class="rounded-2xl border border-gray-100 p-3">
                            <p class="text-sm font-bold text-foreground mb-2 flex items-center gap-2"><i class="bi bi-diagram-3 bracket-icon text-primary"></i> {{ $r['division'] }}</p>
                            <div class="space-y-1.5">
                                @foreach($r['medals'] as $m)
                                    @php $medal = [1 => ['🥇', '#f59e0b', __('personal.event_show_champion')], 2 => ['🥈', '#9ca3af', __('personal.event_show_runner_up')], 3 => ['🥉', '#b45309', __('personal.event_show_third_place')]][$m['place']]; @endphp
                                    <div class="flex items-center gap-3 rounded-xl p-2" style="background: {{ $medal[1] }}12;">
                                        <span class="text-2xl flex-shrink-0 leading-none">{{ $medal[0] }}</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-bold text-foreground truncate">{{ $m['name'] }}</p>
                                            <p class="text-[11px] text-muted-foreground">{{ $medal[2] }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        </template>
    @endif


</div>
@endsection
