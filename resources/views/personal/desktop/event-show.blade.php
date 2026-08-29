@extends('layouts.app')

@section('title', $e['title'])

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

    $locked  = ($banned ?? false) || ($e['ended'] ?? false);
    $canJoin = ! $locked && ($canCompete ?? true) && ! $byQual;
@endphp

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-6" @include('partials.event-show-script')>

    {{-- ===== Cover =====
         Full-bleed: the negative margins cancel the page wrapper's padding so the
         band runs edge to edge, and the top offset lets it sit flush under the
         navbar. No hub tab strip here — this is a drill-down, and the back
         control lives inside the cover. --}}
    <div class="-mx-4 sm:-mx-6 lg:-mx-8 -mt-6 overflow-hidden shadow-sm mb-6 text-white relative" style="background: linear-gradient(150deg, {{ $e['color'] }}, {{ $e['color'] }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>
        {{-- Inner padding mirrors the page wrapper's, so the hero text stays on the
             same vertical axis as the content below it at every breakpoint. --}}
        <div class="relative px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            <div class="flex items-center justify-between gap-2 mb-4">
                <a href="{{ route('me.events') }}"
                   class="inline-flex items-center gap-2 h-10 ps-3 pe-4 rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold hover:bg-white/25 transition-colors">
                    <i class="bi bi-arrow-left rtl:rotate-180"></i>{{ __('personal.personal_events_title') }}
                </a>

                <div class="flex items-center gap-2">
                {{-- Owner-only: the event's P&L. Sits with the other cover actions
                     rather than in the page body — it is a tool for running the
                     event, not part of reading it. --}}
                {{-- The door to the console. Running the event is a different job
                     from reading this page, so it is one button out, not a set of
                     organiser tools threaded through the content. --}}
                @if(($canManage ?? false) || ($canOfficiate ?? false))
                    <a href="{{ route('me.events.manage', $e['key']) }}"
                       class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center hover:bg-white/25 transition-colors" title="{{ __('personal.event_manage_title') }}">
                        <i class="bi bi-sliders text-base"></i>
                    </a>
                @endif
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
                </div>
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


            {{-- Finance used to sit beside this as a second button; it is an
                 owner-only tool, not something a competitor acts on while reading
                 the event, so it moved up to the cover's action row next to the QR
                 button — matching the mobile view. --}}
            @if(!empty($e['bracket_results']))
                <button type="button" @click="showResultsOpen=true"
                        class="w-full py-3 rounded-2xl text-white text-sm font-bold flex items-center justify-center gap-2 hover:opacity-90 transition-opacity" style="background: {{ $e['color'] }};">
                    <i class="bi bi-trophy-fill"></i> {{ __('personal.event_show_show_results') }}
                </button>
            @endif

            {{-- ===== The event, in one card =====
                 Ported from the mobile view, which is where this information design
                 was worked out: five sections in five different voices rather than
                 five copies of icon + eyebrow + text. The timeline is the spine —
                 it carries the only facts a competitor has to act on (when entries
                 close, when to make weight, when to be there), so it gets the date
                 column, the rail and the full type scale. Everything else is
                 quieter so that peak stays legible. --}}
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

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

                {{-- Every section is announced by the same full-bleed dark band, so
                     the card reads as one object with a repeating beat rather than a
                     stack of differently-styled panels. The prize band was the
                     original of this shape; the rest now match it. --}}
                <x-event-section-band :color="$e['color']" icon="bi-info-circle"
                                      :title="__('personal.event_show_about')" />

                <div class="p-6">
                    @if(trim((string) ($e['about'] ?? '')) !== '')
                        <p class="text-[15px] leading-relaxed text-foreground max-w-prose">{{ $e['about'] }}</p>
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
                    <div class="p-6">
                        <div>
                            @foreach($e['phases'] as $ph)
                                @php
                                    // Status is derived from the date — past = done, today = now, future = upcoming.
                                    $pdate  = !empty($ph['date']) ? rescue(fn () => \Carbon\Carbon::parse($ph['date']), null, false) : null;
                                    $today  = \Carbon\Carbon::today();
                                    $done   = $pdate && $pdate->lt($today);
                                    $active = $pdate && $pdate->isSameDay($today);
                                    // The phase that falls on the event's own day is where
                                    // the date chip lands. Only the first match is tagged —
                                    // a multi-phase opening day would otherwise claim the id
                                    // more than once.
                                    $isStart = $pdate && $pdate->toDateString() === ($e['date_iso'] ?? null) && ! ($startTagged ?? false);
                                    $startTagged = ($startTagged ?? false) || $isStart;
                                @endphp
                                <div @if($isStart) id="run-start" @endif
                                     {{-- Never dimmed. A phase that has happened is not less true than one
                                      that has not — the agenda is a record as much as a plan, and
                                      fading the finished half makes a completed event look broken. --}}
                                 class="flex gap-3.5 rounded-xl"
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
                                         with the section's own padding and left a gap
                                         under the timeline. --}}
                                    <div class="min-w-0 flex-1 {{ $loop->last ? '' : 'pb-6' }}">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="text-[15px] font-bold leading-tight {{ $active ? '' : 'text-foreground' }}"
                                               style="{{ $active ? 'color:'.$e['color'].';' : '' }}">{{ $ph['label'] }}</p>
                                            @if($active)
                                                <span class="px-1.5 py-0.5 rounded-full text-[9px] font-black text-white tracking-wide" style="background: {{ $e['color'] }};">{{ __('personal.event_show_now') }}</span>
                                            @elseif($done)
                                                {{-- A phase that has already happened says so. The rows are no longer
                                                     dimmed (a finished event is not a broken one), so "when did this
                                                     stop being ahead of me?" needs saying in words. --}}
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[9px] font-black tracking-wide bg-green-50 text-green-700">
                                                    <i class="bi bi-check-circle-fill text-[8px]"></i>{{ __('personal.event_show_phase_done') }}
                                                </span>
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
                    <div class="p-6">
                        {{-- Desktop has the width mobile does not: run the gender/age
                             groups two-up instead of stacking them. --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
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
                    <div class="p-6">
                        <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-2">
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
                @if(!empty($documents))
                    <x-event-section-band :color="$e['color']" icon="bi-paperclip"
                                          :title="__('personal.event_docs_heading')" />
                    <div class="p-6">
                        {{-- Read-only here. Uploading and deleting is organiser work
                             and lives in the console. --}}
                        <x-event-documents :event="$e['key']" :documents="$documents ?? []"
                                           :can-manage="false" :color="$e['color']" />
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
                        #evtmapdesktop{{ $e['id'] }}Container,
                        #evtmapdesktop{{ $e['id'] }}Container > div { margin: 0 !important; }
                        #evtmapdesktop{{ $e['id'] }}Container > * + *,
                        #evtmapdesktop{{ $e['id'] }}Container > div > * + * { margin-top: 0 !important; }
                        #evtmapdesktop{{ $e['id'] }}Map { display: block; }
                    </style>
                    {{-- The band sits directly on the map — no padded gap between. --}}
                    <x-event-section-band :color="$e['color']" icon="bi-geo-alt-fill"
                                          :title="__('personal.event_show_location')" />

                    <div class="relative">
                        <x-location-map
                            :id="'evtmapdesktop'.$e['id']"
                            :lat="$e['lat']" :lng="$e['lng']"
                            :draggable="false" :readonly="true" :show-address="false" :show-coords="false" :show-labels="false"
                            height="15rem" :zoom="15" map-class="bg-muted/30" />

                        {{-- pointer-events-none so the scrim never eats a map drag;
                             the link re-enables them for itself. --}}
                        <div class="absolute inset-x-0 bottom-0 z-[400] p-5 pt-10 pointer-events-none"
                             style="background: linear-gradient(to top, rgba(17,24,39,.88), rgba(17,24,39,0));">
                            <div class="flex items-end justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[15px] font-black text-white leading-tight truncate">{{ $e['location'] }}</p>
                                    @if(!empty($e['address']) && $e['address'] !== $e['location'])
                                        <p class="text-[11px] text-white/70 truncate mt-0.5">{{ $e['address'] }}</p>
                                    @endif
                                </div>
                                @if($dirHref)
                                    <a href="{{ $dirHref }}" target="_blank" rel="noopener"
                                       class="pointer-events-auto flex-shrink-0 px-3 py-2 rounded-xl bg-white text-[12px] font-black
                                              flex items-center gap-1.5 shadow-lg hover:shadow-xl transition-shadow"
                                       style="color: {{ $e['color'] }};">
                                        <i class="bi bi-cursor-fill"></i> {{ __('personal.event_show_directions') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
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
                @else
                    {{-- No coordinates: the venue still has to be findable. --}}
                    <x-event-section-band :color="$e['color']" icon="bi-geo-alt-fill"
                                          :title="__('personal.event_show_location')" />
                    <div class="px-6 py-5 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-black text-foreground leading-tight">{{ $e['location'] }}</p>
                        </div>
                        @if($dirHref)
                            <a href="{{ $dirHref }}" target="_blank" rel="noopener"
                               class="flex-shrink-0 px-3 py-2 rounded-xl text-white text-[12px] font-black flex items-center gap-1.5 hover:opacity-90 transition-opacity"
                               style="background: {{ $e['color'] }};">
                                <i class="bi bi-cursor-fill"></i> {{ __('personal.event_show_directions') }}
                            </a>
                        @endif
                    </div>
                @endif
            </div>

            {{-- League --}}
            @if(!empty($league))
                @php $lg = $league; @endphp
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

            {{-- Requirements, the tournament timeline and the venue now live in the
                 single event card above (ported from mobile), not as separate cards. --}}

            {{-- Brackets & draws, the verification desk and the roster now live
                 in the sidebar under "Management" — they are ways OUT of this page,
                 not part of reading the event. --}}

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

        </div>

        {{-- ===== Right sidebar =====
             Static on purpose — it scrolls away with the article rather than
             tracking the viewport. Do not reintroduce `sticky`/`top-*` here. --}}
        <aside class="space-y-4">
            {{-- Quick facts --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                {{-- Quick facts are not just a summary — each one is the door to
                     its own fuller answer further down the page. When / how much
                     / where, and a tap takes you to the run-of-show, the way in,
                     or the map. The chip does the linking work the reader would
                     otherwise do by scrolling. --}}
                <div class="grid grid-cols-3 gap-2 text-center">
                    {{-- When: the day AND the time it starts, in one cell. Lands
                         on the run-of-show row for the event's own date. --}}
                    <button type="button" @click="jump(['run-start', 'how-it-runs'])"
                            class="rounded-xl -m-1 p-1 hover:bg-muted/50 transition-colors"
                            aria-label="{{ __('personal.event_show_how_it_runs') }}">
                        <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-calendar3"></i></div>
                        <p class="text-xs font-bold text-foreground mt-1.5 truncate">{{ $e['wday'] }} {{ $e['day'] }} {{ $e['mon'] }}</p>
                        <p class="text-[10px] text-muted-foreground truncate">{{ $e['time'] }}</p>
                    </button>
                    {{-- How much: lands on the Participate row and makes it
                         announce itself, because "what does it cost" and "how do
                         I get in" are the same question asked twice. --}}
                    <button type="button" @click="jump('join-participate')"
                            class="border-x border-gray-100 hover:bg-muted/50 transition-colors py-1"
                            aria-label="{{ $byQual ? __('personal.event_show_entry') : __('personal.event_show_to_join') }}">
                        <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-cash-coin"></i></div>
                        <p class="text-xs font-bold text-foreground mt-1.5 truncate">{{ $e['participant_fee'] }}</p>
                        <p class="text-[10px] text-muted-foreground truncate">{{ $byQual ? __('personal.event_show_entry') : __('personal.event_show_to_join') }}</p>
                    </button>
                    {{-- Where: lands on the map. Made a button for the same reason
                         as its two neighbours — one inert cell in a row of three
                         tappable ones reads as broken, not as restraint. --}}
                    <button type="button" @click="jump('where')"
                            class="rounded-xl -m-1 p-1 hover:bg-muted/50 transition-colors"
                            aria-label="{{ __('personal.event_show_location') }}">
                        <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-geo-alt"></i></div>
                        <p class="text-xs font-bold text-foreground mt-1.5 truncate" title="{{ $e['location'] }}">{{ $e['location'] }}</p>
                        <p class="text-[10px] text-muted-foreground">{{ __('personal.event_show_venue') }}</p>
                    </button>
                </div>

                {{-- Capacity — only when the organiser actually set one. With no
                     limit there are no "spots left" to count, and the bar would
                     read 100% full for an event anyone can still join. --}}
                @if($e['capped'] ?? false)
                    <div class="mt-4">
                        <div class="flex items-center justify-between text-xs mb-1.5">
                            <span class="font-semibold text-foreground"><span x-text="goingCount">{{ $e['going'] }}</span> {{ __('personal.event_show_going') }}</span>
                            <span class="text-muted-foreground"><span x-text="Math.max(0, cap - goingCount)">{{ max(0, $e['cap'] - $e['going']) }}</span> {{ __('personal.event_show_spots_left') }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-muted overflow-hidden">
                            <div class="h-full rounded-full" :style="`width:${pct}%; background:{{ $e['color'] }}`" style="width: {{ round($e['going'] / $e['cap'] * 100) }}%"></div>
                        </div>
                    </div>
                @endif
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

            {{-- ===== Join / spectate =====
                 Ported from mobile, which replaced the old "Entry & tickets" card
                 plus a separate CTA underneath. Each row IS the way in: it already
                 names the thing, its price and who it is for, so a CTA below only
                 repeated it. Rows that cannot be taken (ineligible, already
                 registered, blocked, event over) stay on screen but disabled, so
                 the price list still reads as a price list.

                 Same shape as the "Brackets & draws" card in the left column: dark
                 gradient, a soft circle bleeding off the corner, a translucent icon
                 tile and a chevron. Those already read as pressable, so the ways
                 INTO the event use that language rather than inventing a second.

                 Green once you hold that place; amber while a fee is outstanding.
                 The ineligible variant keeps the shape but goes slate, so it still
                 invites a tap ("Why not?") without pretending to be the main
                 action. --}}

            {{-- Participant --}}
            {{-- id is the amount chip's landing point. No --m-attn-color here:
                 the row already carries an Alpine :style binding, and a second
                 static style on the same element is asking for one to clobber
                 the other. The pulse falls back to the brand primary, which
                 reads against every state this row takes. --}}

            <button type="button" id="join-participate"
                    x-show="entriesOpen || registered" x-cloak
                    @click="{{ $canJoin ? "startJoin('participant')" : 'explainIneligible()' }}"
                    :disabled="registered && !feeDue"
                    class="w-full block rounded-2xl p-4 text-white text-start relative overflow-hidden
                           shadow-lg transition-all hover:shadow-xl disabled:cursor-not-allowed"
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
                    <i class="bi bi-chevron-right text-white/80 flex-shrink-0 rtl:rotate-180"></i>
                </div>
            </button>

            {{-- Spectator ticket --}}
            @if($hasTicket)
                <button type="button" @click="startJoin('spectator')"
                        :disabled="(registered && !feeDue) || {{ $locked ? 'true' : 'false' }}"
                        class="w-full block rounded-2xl p-4 text-white text-start relative overflow-hidden
                               shadow-lg transition-all hover:shadow-xl
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
                        <i class="bi bi-chevron-right text-white/80 flex-shrink-0 rtl:rotate-180"></i>
                    </div>
                </button>
            @endif

            {{-- ===== Before we start =====
                 Run-day work, so it sits with the other run-day tools rather than
                 in the reading column — and above Management, because until the
                 event is started this is the thing the organiser is here to do.
                 The component decides what is editable; the controller has
                 already decided whether this viewer gets a list at all. --}}

            {{-- ===== Management =====
                 The ways OUT of this page, gathered in one place under the join
                 rows: the draw, the verification desk, and the roster. They used to
                 be scattered down the left column between the reading material.

                 Cards are the compact variant of the same dark-gradient object the
                 join rows use — narrower tile and no second line of stats, because
                 the sidebar is 360px, not the full column. --}}
            @php
                $hasBrackets = !empty($e['categories']);
                $showManagement = true;   // the roster link is always available
            @endphp
            @if($showManagement)
                <div class="pt-2">
                    <h2 class="text-[11px] font-black uppercase tracking-[0.14em] text-muted-foreground mb-2 px-1">
                        {{ __('personal.event_show_management') }}
                    </h2>

                    <div class="space-y-2">
                        {{-- Draw --}}
                        @if($hasBrackets)
                            @php
                                $catCount = count($e['categories']);
                                $athleteTotal = collect($e['categories'])->sum('joined');
                            @endphp
                            <a href="{{ route('me.events.bracket', $e['key']) }}"
                               class="block rounded-2xl p-4 text-white relative overflow-hidden shadow-md hover:shadow-lg transition-shadow"
                               style="background: linear-gradient(135deg, {{ $e['color'] }}, #1f2937);">
                                <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10"></div>
                                <div class="relative flex items-center gap-3">
                                    <div class="w-11 h-11 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                                        <i class="bi bi-diagram-3-fill bracket-icon text-xl"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="font-black text-[15px] leading-tight">{{ __('personal.event_show_tile_draw') }}</h3>
                                        <p class="text-[11px] text-white/85 mt-0.5 truncate">{{ $athleteTotal }} {{ __('personal.event_show_entrants') }}</p>
                                    </div>
                                    <i class="bi bi-chevron-right text-white/80 flex-shrink-0 rtl:rotate-180"></i>
                                </div>
                            </a>
                        @endif

                        {{-- Officials — the officiating sheet. Reading only for
                             everyone, the organiser included; appointing lives on
                             the edit screen, where the authority to appoint is. --}}
                        <a href="{{ route('me.events.officiating', $e['key']) }}"
                           class="block rounded-2xl p-4 text-white relative overflow-hidden shadow-md hover:shadow-lg transition-shadow"
                           style="background: linear-gradient(135deg, {{ $e['color'] }}, #1f2937);">
                            <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10"></div>
                            <div class="relative flex items-center gap-3">
                                <div class="w-11 h-11 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                                    <i class="bi bi-person-badge-fill text-xl"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-black text-[15px] leading-tight">{{ __('personal.event_show_tile_officials') }}</h3>
                                    <p class="text-[11px] text-white/85 mt-0.5 truncate">{{ trans_choice('personal.event_manage_officials_count', $e['officials_count'] ?? 0, ['count' => $e['officials_count'] ?? 0]) }}</p>
                                </div>
                                <i class="bi bi-chevron-right text-white/80 flex-shrink-0 rtl:rotate-180"></i>
                            </div>
                        </a>

                        {{-- Who's joined — the full roster, and the only door to it.
                             There was a second card here ("Verification desk") that
                             opened a screen listing these same people with buttons
                             on them; the buttons now live on the roster rows, so one
                             card is the whole answer. Counts are bound to this page's
                             Alpine state, so joining or removing someone updates the
                             card without a reload. --}}
                        <a href="{{ route('me.events.people', $e['key']) }}"
                           class="block rounded-2xl p-4 text-white relative overflow-hidden shadow-md hover:shadow-lg transition-shadow"
                           style="background: linear-gradient(135deg, {{ $e['color'] }}, #1f2937);">
                            <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10"></div>
                            <div class="relative flex items-center gap-3">
                                <div class="w-11 h-11 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                                    <i class="bi bi-people-fill text-xl"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-black text-[15px] leading-tight">{{ $byQual ? __('personal.event_show_finalists') : __('personal.event_show_tile_participants') }}</h3>
                                    <p class="text-[11px] text-white/85 mt-0.5 truncate">
                                        <span x-text="goingCount">{{ $e['participants_total'] ?? $e['going'] }}</span> {{ __('personal.event_show_in') }}
                                        @if($hasTicket)
                                            · <span x-text="spectators">{{ $e['spectator']['count'] }}</span> {{ __('personal.event_show_spectators') }}
                                        @endif
                                    </p>
                                </div>
                                <i class="bi bi-chevron-right text-white/80 flex-shrink-0 rtl:rotate-180"></i>
                            </div>
                        </a>

                        {{-- Gallery — every filmed bout, grouped by division.
                             Always shown, like Who's joined: footage is a place
                             people go looking for, and the gallery states its
                             own empty case. --}}
                            <a href="{{ route('me.events.gallery', $e['key']) }}"
                               class="block rounded-2xl p-4 text-white relative overflow-hidden shadow-md hover:shadow-lg transition-shadow"
                               style="background: linear-gradient(135deg, {{ $e['color'] }}, #1f2937);">
                                <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10"></div>
                                <div class="relative flex items-center gap-3">
                                    <div class="w-11 h-11 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                                        <i class="bi bi-camera-reels-fill text-xl"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="font-black text-[15px] leading-tight">{{ __('events.bout_gallery_title') }}</h3>
                                        <p class="text-[11px] text-white/85 mt-0.5 truncate">
                                            {{ ($e['clips_count'] ?? 0) > 0
                                                ? trans_choice('events.bout_gallery_count', $e['clips_count'], ['count' => $e['clips_count']])
                                                : __('events.bout_gallery_none') }}
                                        </p>
                                    </div>
                                    <i class="bi bi-chevron-right text-white/80 flex-shrink-0 rtl:rotate-180"></i>
                                </div>
                            </a>
                    </div>
                </div>
            @endif

            {{-- The rows above ARE the join action; the old duplicate CTA chain
                 that lived here is gone (mobile dropped it for the same reason).
                 What stays is the proof-of-payment flow and the join sheet, which
                 have nowhere else to live. --}}
            <div>

                {{-- Which club this athlete competes for, and — for a coach —
                     the door to entering a whole squad. --}}
                <div class="space-y-3 mb-3">
                    @include('partials.event-representing')
                    @include('partials.event-squad-entry')
                </div>

                {{-- Optional manual proof-of-payment (paid participant events) --}}
                @include('partials.event-payment-proof')

                {{-- The join sheet: what it costs, how to pay, and the choice to
                     settle later. Desktop was registering people straight through
                     toggleGoing()/toggleWatch() and never showed it, so a paid
                     event gave no account number to pay into. Both views now go
                     through startJoin(). --}}
                @include('partials.event-join-sheet')

                <div x-show="joinedDivision" x-cloak class="mt-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-3 flex items-center gap-2 text-xs">
                    <i class="bi bi-diagram-3 bracket-icon text-primary"></i>
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
                                <p class="text-sm font-bold text-foreground mb-2 flex items-center gap-2"><i class="bi bi-diagram-3 bracket-icon text-primary"></i> {{ $r['division'] }}</p>
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

</div>
@endsection
