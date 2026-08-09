@extends('layouts.personal-mobile')

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
    $pPaid   = !str_contains(strtolower($e['participant_fee']), 'free') && !str_contains(strtolower($e['participant_fee']), 'qualified');
    $byQual  = str_contains(strtolower($e['participant_fee']), 'qualified');
    $hasTicket = !empty($e['spectator']);
    $ticketPaid = $hasTicket && !str_contains(strtolower($e['spectator']['fee']), 'free');

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
            <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('me.events') }}')"
               class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center" aria-label="{{ __('shared.back') }}">
                <i class="bi bi-arrow-left text-lg"></i>
            </button>
            <div class="flex items-center gap-2">
                {{-- Owner-only: the event's P&L. Sits with the other cover actions
                     rather than in the page body — it is a tool for running the
                     event, not part of reading it. --}}
                @if($finance ?? false)
                    <button type="button" @click="financeOpen=true"
                            class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center" aria-label="{{ __('personal.event_show_finance') }}">
                        <i class="bi bi-cash-stack text-base"></i>
                    </button>
                @endif
                <x-qr-code
                    :url="route('me.events.show', ['event' => $e['key']])"
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
                @if($canManage ?? false)
                    <div class="relative" @click.outside="manageOpen=false">
                        <button type="button" @click="manageOpen=!manageOpen"
                                class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center" aria-label="{{ __('personal.event_show_manage') }}">
                            <i class="bi bi-three-dots-vertical text-base"></i>
                        </button>
                        <div x-show="manageOpen" x-cloak
                             x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                             class="absolute end-0 top-12 z-40 w-44 bg-white rounded-xl shadow-lg border border-gray-100 py-1 text-foreground"
                             style="transform-origin: top right;">
                            <button type="button" @click="goEdit()"
                                    class="w-full text-start flex items-center gap-2.5 px-3 py-2 text-sm hover:bg-muted transition-colors">
                                <i class="bi bi-pencil"></i> {{ __('personal.event_show_edit_event') }}
                            </button>
                            @if(($manual_results ?? true))
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
                @if($canManage ?? false)
                    <button type="button" @click="openResults()" class="m-press text-[11px] font-bold text-primary px-2 py-1 rounded-lg bg-accent"><i class="bi bi-pencil"></i> {{ __('shared.edit') }}</button>
                @endif
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

    {{-- ===== Manager: record winners (finished events) ===== --}}
    {{-- Types that derive their podium from their own engine (brackets, tables) opt out via allowsManualResults(). --}}
    @if(($canManage ?? false) && ($manual_results ?? true))
        <div class="px-4 mt-4" x-show="results.length === 0">
            <button type="button" @click="openResults()"
                    class="m-press w-full py-3 rounded-2xl border-2 border-dashed border-gray-200 text-sm font-bold text-foreground flex items-center justify-center gap-2">
                <i class="bi bi-trophy"></i> {{ __('personal.event_show_record_winners') }}
            </button>
        </div>
    @endif

    {{-- ===== Show results (everyone, when finals decided) =====
         Finance used to sit beside this as a second full-width button; it is an
         owner-only tool, not something a competitor acts on while reading the
         event, so it moved up to the cover's action row next to the QR button. --}}
    @if(!empty($e['bracket_results']))
        <div class="px-4 mt-4">
            <button type="button" @click="showResultsOpen=true"
                    class="m-press w-full py-3 rounded-2xl text-white text-sm font-bold flex items-center justify-center gap-2" style="background: {{ $e['color'] }};">
                <i class="bi bi-trophy-fill"></i> {{ __('personal.event_show_show_results') }}
            </button>
        </div>
    @endif

    {{-- ===== The event, in one card =====
         Five sections, five different voices — not five copies of
         icon + eyebrow + text, which is what made the first attempt read flat.

         The timeline is the spine: it carries the only facts a competitor has to
         act on (when entries close, when to make weight, when to be there), so it
         gets the date column, the rail and the full type scale. Everything else
         is quieter so that peak is legible. --}}
    @php
        // Only ever allow http(s) URLs into an href (defence-in-depth vs javascript: URIs).
        $locUrl = (is_string($e['location_url'] ?? null) && preg_match('#^https?://#i', $e['location_url'])) ? $e['location_url'] : null;
        // Turn-by-turn directions: coords > pasted link > place name.
        $dirHref = (!empty($e['lat']) && !empty($e['lng']))
            ? 'https://www.google.com/maps/dir/?api=1&destination=' . $e['lat'] . ',' . $e['lng']
            : ($locUrl ?: ($e['location'] && $e['location'] !== 'TBA'
                ? 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($e['location'])
                : null));
        $hasMap = !empty($e['lat']) && !empty($e['lng']);
    @endphp
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl overflow-hidden">

            {{-- Every section is announced by the same full-bleed dark band, so the
                 card reads as one object with a repeating beat rather than a stack
                 of differently-styled panels. The prize band was the original of
                 this shape; the rest now match it. --}}
            <x-event-section-band :color="$e['color']" icon="bi-info-circle"
                                  :title="__('personal.event_show_about')" />

            <div class="p-5">
                @if(trim((string) ($e['about'] ?? '')) !== '')
                    <p class="text-[15px] leading-relaxed text-foreground">{{ $e['about'] }}</p>
                @endif

                @if(!empty($e['tags']))
                    <div class="flex flex-wrap gap-1.5 mt-4">
                        @foreach($e['tags'] as $t)
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold"
                                  style="background: {{ $e['color'] }}14; color: {{ $e['color'] }};">#{{ $t }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ===== The spine ===== --}}
            @if(!empty($e['phases']))
                <span id="how-it-runs" class="block"></span>
                <x-event-section-band :color="$e['color']" icon="bi-signpost-split"
                                      :title="__('personal.event_show_how_it_runs')" />
                <div class="p-5">
                    <div>
                        @foreach($e['phases'] as $ph)
                            @php
                                // Status is derived from the date — past = done, today = now, future = upcoming.
                                $pdate  = !empty($ph['date']) ? rescue(fn () => \Carbon\Carbon::parse($ph['date']), null, false) : null;
                                $today  = \Carbon\Carbon::today();
                                $done   = $pdate && $pdate->lt($today);
                                $active = $pdate && $pdate->isSameDay($today);
                                // The phase on the event's own day — where the date
                                // chip lands. First match only, so an opening day
                                // with several phases cannot claim the id twice.
                                $isStart = $pdate && $pdate->toDateString() === ($e['date_iso'] ?? null) && ! ($startTagged ?? false);
                                $startTagged = ($startTagged ?? false) || $isStart;
                            @endphp
                            <div @if($isStart) id="run-start" @endif
                                 class="flex gap-3.5 rounded-xl {{ $done ? 'opacity-45' : '' }}"
                                 style="--m-attn-color: {{ $e['color'] }}80;">

                                {{-- The date column: a calendar leaf, so the eye can
                                     run down the dates without reading a word. --}}
                                <div class="w-11 shrink-0 text-center pt-0.5">
                                    <span class="block text-[10px] font-black uppercase tracking-wider text-muted-foreground">{{ $pdate ? $pdate->format('M') : '' }}</span>
                                    <span class="block text-[22px] font-black leading-none mt-0.5 {{ $active ? '' : 'text-foreground' }}"
                                          style="{{ $active ? 'color:'.$e['color'].';' : '' }}">{{ $pdate ? $pdate->format('j') : '—' }}</span>
                                    <span class="block text-[10px] font-semibold text-muted-foreground mt-0.5">{{ $pdate ? $pdate->format('D') : '' }}</span>
                                </div>

                                {{-- The rail --}}
                                <div class="flex flex-col items-center pt-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 {{ $active ? 'ring-4' : '' }}"
                                          style="background: {{ $done ? '#10b981' : ($active ? $e['color'] : '#d1d5db') }};{{ $active ? ' box-shadow: 0 0 0 4px '.$e['color'].'26;' : '' }}"></span>
                                    @if(!$loop->last)
                                        <span class="w-px flex-1 my-1.5" style="background: {{ $done ? '#10b98159' : '#e5e7eb' }};"></span>
                                    @endif
                                </div>

                                {{-- No trailing padding on the last step: it stacked
                                     with the section's own p-5 and left a gap under
                                     the timeline. --}}
                                <div class="min-w-0 flex-1 {{ $loop->last ? '' : 'pb-6' }}">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-[15px] font-bold leading-tight {{ $active ? '' : 'text-foreground' }}"
                                           style="{{ $active ? 'color:'.$e['color'].';' : '' }}">{{ $ph['label'] }}</p>
                                        @if($active)
                                            <span class="px-1.5 py-0.5 rounded-full text-[9px] font-black text-white tracking-wide" style="background: {{ $e['color'] }};">{{ __('personal.event_show_now') }}</span>
                                        @endif
                                    </div>

                                    {{-- Time reads as a fact you act on, so it is set
                                         in the foreground next to a clock, not greyed
                                         out beside the date. --}}
                                    @if(!empty($ph['time']))
                                        <p class="inline-flex items-center gap-1.5 mt-1.5 text-[12px] font-bold text-foreground">
                                            <i class="bi bi-clock text-[11px]" style="color: {{ $e['color'] }};"></i>{{ $ph['time'] }}
                                        </p>
                                    @endif

                                    {{-- A competition day runs play → break → play.
                                         Three lines, because that is three different
                                         things to be somewhere for. --}}
                                    @if(!empty($ph['segments']))
                                        <div class="mt-2 space-y-1">
                                            @foreach($ph['segments'] as $seg)
                                                @php $isBreak = ($seg['kind'] ?? '') === 'break'; @endphp
                                                <p class="flex items-center gap-1.5 text-[12px] font-bold {{ $isBreak ? 'text-muted-foreground' : 'text-foreground' }}">
                                                    <i class="bi {{ $isBreak ? 'bi-cup-hot-fill' : 'bi-clock' }} text-[11px]"
                                                       style="color: {{ $isBreak ? '#9ca3af' : $e['color'] }};"></i>
                                                    <span class="w-10 flex-shrink-0 font-semibold {{ $isBreak ? '' : 'text-muted-foreground' }}">{{ $seg['label'] }}</span>
                                                    <span>{{ $seg['time'] }}</span>
                                                </p>
                                            @endforeach
                                        </div>
                                    @endif

                                    <p class="text-[12px] text-muted-foreground leading-snug mt-1">{{ $ph['note'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Divisions — grouped by AGE GROUP and gender, not gender alone.
                 A championship running Cadet and Senior would otherwise show two
                 identical "Men" lists, and the age group — the first thing a
                 competitor checks — never appeared at all. --}}
            @if(!empty($e['divisions']))
                @php
                    // Division names are generated as "{Age} {Men|Women} {label} kg"
                    // by AbstractCombatSport::divisionName(), so they parse back
                    // reliably. Anything that does not match is kept whole rather
                    // than mangled.
                    $divGroups = [];
                    foreach ($e['divisions'] as $d) {
                        if (preg_match('/^(.*?)\s*\b(Men|Women)\b\s*(.*)$/i', $d, $m)) {
                            $age    = trim($m[1]);
                            $female = strcasecmp($m[2], 'Women') === 0;
                            $weight = trim($m[3]) !== '' ? trim($m[3]) : $d;
                            $label  = trim($age.' '.($female ? __('personal.event_show_women') : __('personal.event_show_men')));
                        } else {
                            $female = false;
                            $weight = $d;
                            $label  = __('personal.event_show_divisions');
                        }
                        $key = ($female ? 'f' : 'm').'|'.$label;
                        $divGroups[$key]['label']  = $label;
                        $divGroups[$key]['female'] = $female;
                        $divGroups[$key]['items'][] = $weight;
                    }
                    // No sort: PHP keeps insertion order, which is the divisions'
                    // own sort_order — the sequence the organiser arranged them in.
                    // Imposing an alphabetical order here would quietly override it.
                @endphp
                <x-event-section-band :color="$e['color']" icon="bi-diagram-3-fill"
                                      :title="__('personal.event_show_divisions')" />
                <div class="p-5">
                    <div class="space-y-3.5">
                        @foreach($divGroups as $g)
                            @php $tint = $g['female'] ? '#ec4899' : '#3b82f6'; @endphp
                            <div>
                                <p class="flex items-center gap-1.5 text-[12px] font-black mb-2" style="color: {{ $tint }};">
                                    <i class="bi {{ $g['female'] ? 'bi-gender-female' : 'bi-gender-male' }}"></i>{{ $g['label'] }}
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($g['items'] as $w)
                                        <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                              style="background: {{ $tint }}14;">{{ $w }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Requirements — a short contract, set as one. --}}
            @if(!empty($e['requirements']))
                <x-event-section-band :color="$e['color']" icon="bi-clipboard-check"
                                      :title="__('personal.event_show_requirements')" />
                <div class="p-5">
                    <ul class="space-y-2">
                        @foreach($e['requirements'] as $req)
                            <li class="flex items-start gap-2.5 text-[13px] text-foreground/85 leading-snug">
                                <i class="bi bi-check-circle-fill text-[13px] mt-0.5 flex-shrink-0" style="color: {{ $e['color'] }};"></i>
                                <span>{{ $req }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Documents — rulebook, entry form, schedule. The section only
                 exists when there is something to download, unless you are the
                 organiser, who needs the uploader to put the first one there. --}}
            @if(!empty($documents) || ($canManage ?? false))
                <x-event-section-band :color="$e['color']" icon="bi-paperclip"
                                      :title="__('personal.event_docs_heading')" />
                <div class="p-5">
                    <x-event-documents :event="$e['key']" :documents="$documents ?? []"
                                       :can-manage="$canManage ?? false" :color="$e['color']" />
                </div>
            @endif

            {{-- Venue — the map IS the section. The address sits on it under a
                 scrim rather than in a row above it, so the card closes on one
                 object instead of two stacked. --}}
            <span id="where" class="block"></span>
            @if($hasMap)
                {{-- The map component is built for forms, so its root is space-y-4
                     and its inner wrapper space-y-2 — which put a 1rem margin above
                     the map and stopped it meeting the card's bottom edge. Zero it
                     for this instance only; the card's overflow-hidden then clips
                     the map into the rounded corners. --}}
                <style>
                    #evtmap{{ $e['id'] }}Container,
                    #evtmap{{ $e['id'] }}Container > div { margin: 0 !important; }
                    #evtmap{{ $e['id'] }}Container > * + *,
                    #evtmap{{ $e['id'] }}Container > div > * + * { margin-top: 0 !important; }
                    #evtmap{{ $e['id'] }}Map { display: block; }
                </style>
                {{-- Named like every other section, so the map is announced
                     rather than just appearing at the foot of the card. The band
                     sits directly on the map — no padded gap between them. --}}
                <x-event-section-band :color="$e['color']" icon="bi-geo-alt-fill"
                                      :title="__('personal.event_show_location')" />

                <div class="relative">
                    <x-location-map
                        :id="'evtmap'.$e['id']"
                        :lat="$e['lat']" :lng="$e['lng']"
                        :draggable="false" :readonly="true" :show-address="false" :show-coords="false" :show-labels="false"
                        height="11rem" :zoom="15" map-class="bg-muted/30" />

                    {{-- pointer-events-none so the scrim never eats a map drag;
                         the link re-enables them for itself. --}}
                    <div class="absolute inset-x-0 bottom-0 z-[400] p-4 pt-10 pointer-events-none"
                         style="background: linear-gradient(to top, rgba(17,24,39,.88), rgba(17,24,39,0));">
                        <div class="flex items-end justify-between gap-3">
                            {{-- No eyebrow here: the section heading above the map
                                 already says what this is. --}}
                            <div class="min-w-0">
                                <p class="text-[15px] font-black text-white leading-tight truncate">{{ $e['location'] }}</p>
                                @if(!empty($e['address']) && $e['address'] !== $e['location'])
                                    <p class="text-[11px] text-white/70 truncate mt-0.5">{{ $e['address'] }}</p>
                                @endif
                            </div>
                            @if($dirHref)
                                <a href="{{ $dirHref }}" target="_blank" rel="noopener"
                                   class="m-press pointer-events-auto flex-shrink-0 px-3 py-2 rounded-xl bg-white text-[12px] font-black
                                          flex items-center gap-1.5 shadow-lg"
                                   style="color: {{ $e['color'] }};">
                                    <i class="bi bi-cursor-fill"></i> {{ __('personal.event_show_directions') }}
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
                <script>
                    (function () {
                        var id = 'evtmap{{ $e['id'] }}', lat = {{ $e['lat'] }}, lng = {{ $e['lng'] }}, tries = 0;
                        (function go() {
                            if (window.LocationMap) {
                                window.LocationMap.create({ id: id, defaultLat: lat, defaultLng: lng, zoom: 15, draggable: false, readonly: true });
                            } else if (tries++ < 60) {
                                setTimeout(go, 100);
                            }
                        })();
                    })();
                </script>
            @else
                {{-- No coordinates: the venue still has to be findable. --}}
                <x-event-section-band :color="$e['color']" icon="bi-geo-alt-fill"
                                      :title="__('personal.event_show_location')" />
                <div class="px-5 py-4 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-black text-foreground leading-tight">{{ $e['location'] }}</p>
                    </div>
                    @if($dirHref)
                        <a href="{{ $dirHref }}" target="_blank" rel="noopener"
                           class="m-press flex-shrink-0 px-3 py-2 rounded-xl text-white text-[12px] font-black flex items-center gap-1.5"
                           style="background: {{ $e['color'] }};">
                            <i class="bi bi-cursor-fill"></i> {{ __('personal.event_show_directions') }}
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>

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

    {{-- ===== Brackets & draws entry ===== --}}
    @if(!empty($e['categories']))
        @php
            $catCount = count($e['categories']);
            $athleteTotal = collect($e['categories'])->sum('joined');
        @endphp
        <div class="px-4 mt-4">
            <a href="{{ route('me.events.bracket', $e['key']) }}" data-shell-link data-route="me.events"
               class="block m-press rounded-2xl p-4 text-white relative overflow-hidden shadow-lg"
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
        </div>
    @endif

    {{-- ===== Before we start =====
         Run-day work for the people who do it, above the roster and the draw
         because until the event is started this is the job. The component
         decides what is editable; the controller has already decided whether
         this viewer receives a list at all. --}}
    @if($canOfficiate ?? false)
        <div class="px-4 mt-4">
            <x-event-checklist :event="$e['key']"
                               :items="$checklist ?? []"
                               :can-manage="$canManage"
                               :can-check="true"
                               :started="$e['started'] ?? false"
                               :overridden="$e['start_overridden'] ?? false"
                               :color="$e['color']" />
        </div>
    @endif

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

    {{-- ===== Who's joined — opens on its own page =====
         The roster used to sit inline here: three tabs and up to 48 names that
         every visitor had to scroll past to reach the location and the join
         button. It is now one card that opens the full list, matching how
         Brackets & draws is reached from this screen. --}}
    <div class="px-4 mt-4">
        <a href="{{ route('me.events.people', $e['key']) }}" data-shell-link data-route="me.events"
           class="block m-press rounded-2xl p-4 text-white relative overflow-hidden shadow-lg"
           style="background: linear-gradient(135deg, {{ $e['color'] }}, #1f2937);">
            <div class="absolute -right-6 -top-6 w-28 h-28 rounded-full bg-white/10"></div>
            <div class="relative flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                    <i class="bi bi-people-fill text-2xl"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="font-black text-base leading-tight">{{ $byQual ? __('personal.event_show_finalists') : __('personal.event_show_whos_joined') }}</h3>
                    {{-- Bound to the page's own Alpine counters, so joining or
                         removing someone updates this card without a reload —
                         the same numbers the tabs used to show. --}}
                    <p class="text-xs text-white/85 mt-0.5">
                        <span x-text="goingCount">{{ $e['participants_total'] ?? $e['going'] }}</span> {{ __('personal.event_show_in') }}
                        @if($hasTicket)
                            · <span x-text="spectators">{{ $e['spectator']['count'] }}</span> {{ __('personal.event_show_spectators') }}
                        @endif
                    </p>
                    {{-- There was a second card below this one ("Verification
                         desk") listing the same competitors with buttons on them.
                         The buttons moved onto the roster rows, so this card is
                         now the single door — officials are told so here. --}}
                    @if($canOfficiate ?? false)
                        <p class="text-[11px] text-white/70 mt-0.5 flex items-center gap-1">
                            <i class="bi bi-clipboard2-check"></i>{{ __('personal.event_verify_open_sub') }}
                        </p>
                    @endif
                </div>
                <i class="bi bi-chevron-right text-white/80"></i>
            </div>
        </a>
    </div>

    {{-- ===== Proof of payment =====
         The join / spectate CTAs that used to sit here are gone: the two pricing
         rows above ARE the way in now, and this card only repeated them — plus an
         "you're not eligible" notice that the participant row's own dialog now
         gives on tap. What stays is the proof-of-payment flow, which has nowhere
         else to live. --}}
    <div class="px-4 mt-4">
        @include('partials.event-payment-proof')
        @include('partials.event-join-sheet')
    </div>

    {{-- In-place division confirmation (taekwondo) --}}
    <div x-show="joinedDivision" x-cloak class="px-4 mt-2">
        <div class="m-card rounded-2xl p-3 flex items-center gap-2 text-[12px]">
            <i class="bi bi-diagram-3 text-primary"></i>
            <span class="text-muted-foreground">{{ __('personal.event_show_placed_in') }} <span class="font-bold text-foreground" x-text="joinedDivision"></span></span>
        </div>
    </div>

    {{-- Share handler (dummy) --}}
    <div x-init="$el.addEventListener('share-event-fired', () => {})"
         @share-event.window="
            if (navigator.share) { navigator.share({ title: '{{ addslashes($e['title']) }}', text: '{{ addslashes(__('personal.event_show_share_text', ['title' => $e['title']])) }}' }).catch(()=>{}); }
            else { window.showToast('success', '{{ __('personal.event_show_link_copied') }}'); }
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

    {{-- ===== Set-winners modal (managers) — teleported to body so the fixed
             overlay anchors to the viewport, not the transformed shell content. --}}
    @if($canManage ?? false)
        <template x-teleport="body">
        <div x-show="resultsOpen" x-cloak class="fixed inset-0 z-[60]" style="display:none;">
            <div class="absolute inset-0 bg-black/40" @click="resultsOpen=false" x-transition.opacity></div>
            <div class="absolute bottom-0 inset-x-0 bg-white rounded-t-3xl max-h-[85vh] flex flex-col"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-black text-foreground flex items-center gap-2"><i class="bi bi-trophy-fill text-amber-500"></i> {{ __('personal.event_show_winners_results') }}</h3>
                    <button type="button" @click="resultsOpen=false" class="m-press w-8 h-8 rounded-full bg-muted grid place-items-center"><i class="bi bi-x-lg text-xs"></i></button>
                </div>

                {{-- list of participant names for autocomplete --}}
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
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full text-white" :style="`background:${medal(w.place)}`"
                                      x-text="w.place===1 ? '{{ __("personal.event_show_place_1st") }}' : (w.place===2 ? '{{ __("personal.event_show_place_2nd") }}' : (w.place===3 ? '{{ __("personal.event_show_place_3rd") }}' : '#' + w.place))"></span>
                                <button type="button" @click="removeWinner(i)" class="m-press text-[11px] text-red-500 font-semibold"><i class="bi bi-trash"></i> {{ __('personal.event_show_remove_btn') }}</button>
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
                    <button type="button" @click="addWinner()" class="m-press w-full py-2.5 rounded-xl border-2 border-dashed border-gray-200 text-sm font-bold text-muted-foreground">
                        <i class="bi bi-plus-lg"></i> {{ __('personal.event_show_add_place') }}
                    </button>
                </div>

                <div class="p-4 border-t border-gray-100">
                    <button type="button" @click="saveResults()" :disabled="busy"
                            class="m-press w-full py-3.5 rounded-2xl text-white font-bold text-sm flex items-center justify-center gap-2 disabled:opacity-60" style="background: {{ $e['color'] }};">
                        <i class="bi" :class="busy ? 'bi-arrow-repeat animate-spin' : 'bi-check2-circle'"></i>
                        <span x-text="busy ? '{{ __("personal.event_show_saving") }}' : '{{ __("personal.event_show_save_winners") }}'"></span>
                    </button>
                </div>
            </div>
        </div>
        </template>
    @endif

    {{-- ===== Results sheet (combat — computed from brackets) ===== --}}
    @if(!empty($e['bracket_results']))
        <template x-teleport="body">
        <div x-show="showResultsOpen" x-cloak class="fixed inset-0 z-[60]" style="display:none;">
            <div class="absolute inset-0 bg-black/40" @click="showResultsOpen=false" x-transition.opacity></div>
            <div class="absolute bottom-0 inset-x-0 bg-white rounded-t-3xl max-h-[88vh] flex flex-col"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-black text-foreground flex items-center gap-2"><i class="bi bi-trophy-fill text-amber-500"></i> {{ __('personal.event_show_results_medals') }}</h3>
                    <button type="button" @click="showResultsOpen=false" class="m-press w-8 h-8 rounded-full bg-muted grid place-items-center"><i class="bi bi-x-lg text-xs"></i></button>
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

    {{-- ===== Finance sheet (owner only) ===== --}}
    @if($finance ?? false)
        <template x-teleport="body">
        <div x-show="financeOpen" x-cloak class="fixed inset-0 z-[60]" style="display:none;">
            <div class="absolute inset-0 bg-black/40" @click="financeOpen=false" x-transition.opacity></div>
            <div class="absolute bottom-0 inset-x-0 bg-white rounded-t-3xl max-h-[90vh] flex flex-col"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-black text-foreground flex items-center gap-2"><i class="bi bi-cash-stack text-green-600"></i> {{ __('personal.event_show_event_finance') }}</h3>
                    <button type="button" @click="financeOpen=false" class="m-press w-8 h-8 rounded-full bg-muted grid place-items-center"><i class="bi bi-x-lg text-xs"></i></button>
                </div>

                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    {{-- Revenue --}}
                    <div class="rounded-2xl border border-gray-100 p-3">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">{{ __('personal.event_show_money_collected') }}</p>
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

                    {{-- Expenses --}}
                    <div class="rounded-2xl border border-gray-100 p-3">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">{{ __('personal.event_show_expenses') }}</p>
                        <div class="space-y-1.5">
                            <template x-for="x in fin.expenses" :key="x.id">
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="flex-1 min-w-0 truncate text-foreground" x-text="x.label"></span>
                                    <span class="font-bold text-red-600" x-text="'− ' + money(x.amount)"></span>
                                    <button type="button" @click="removeExpense(x.id)" class="m-press w-7 h-7 rounded-lg bg-muted grid place-items-center text-red-500 flex-shrink-0"><i class="bi bi-x-lg text-[10px]"></i></button>
                                </div>
                            </template>
                            <p x-show="!fin.expenses.length" class="text-[11px] text-muted-foreground text-center py-1">{{ __('personal.event_show_no_expenses') }}</p>
                        </div>
                        {{-- Add expense --}}
                        <div class="flex items-center gap-2 mt-3 pt-3 border-t border-gray-100">
                            <input x-model="newExpLabel" type="text" placeholder="{{ __('personal.event_show_ph_expense') }}"
                                   class="flex-1 min-w-0 px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                            <div class="relative w-28 flex-shrink-0">
                                <span class="absolute start-2 top-1/2 -translate-y-1/2 text-[11px] font-bold text-muted-foreground pointer-events-none" x-text="fin.currency"></span>
                                <input x-model="newExpAmount" type="number" min="0" step="0.001" inputmode="decimal" placeholder="0"
                                       class="w-full ps-12 pe-2 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                            </div>
                            <button type="button" @click="addExpense()" :disabled="busy" class="m-press w-9 h-9 rounded-xl bg-primary text-white grid place-items-center flex-shrink-0 disabled:opacity-50"><i class="bi bi-plus-lg"></i></button>
                        </div>
                        <div class="flex items-center justify-between text-sm pt-2 mt-2 border-t border-gray-100">
                            <span class="font-bold text-foreground">{{ __('personal.event_show_total_expenses') }}</span>
                            <span class="font-black text-red-600" x-text="'− ' + money(expensesTotal)"></span>
                        </div>
                    </div>

                    {{-- Profit --}}
                    <div class="rounded-2xl p-4 flex items-center justify-between" :class="profit >= 0 ? 'bg-green-50' : 'bg-red-50'">
                        <span class="text-sm font-black" :class="profit >= 0 ? 'text-green-700' : 'text-red-700'">{{ __('personal.event_show_profit') }}</span>
                        <span class="text-lg font-black" :class="profit >= 0 ? 'text-green-700' : 'text-red-700'" x-text="money(profit)"></span>
                    </div>
                </div>
            </div>
        </div>
        </template>
    @endif

</div>
@endsection
