@include('partials.event-now-glow')
{{--
    THE EVENT, IN ONE CARD — MOBILE.

    Extracted 2026-09-01 so the page a stranger opens (`/e/{uuid}`) and the page
    a member opens (`/me/events/{uuid}`) are LITERALLY the same markup, not two
    files that resemble each other until somebody edits one. Everything in it is
    poster-grade — about, run-of-show, divisions, requirements, documents,
    venue — which is exactly the set a public event may show.

    Reads only `$e` (and `$documents`), so its caller decides what exists: the
    public page is handed a curated payload by App\Events\Support\PublicEvent,
    and a section with nothing behind it simply does not render.

    Moved verbatim from personal/mobile/event-show.blade.php — no restyling,
    because the whole point is that neither page drifts.

    `$sectionVariant` is the ONE thing a caller may change about the look: pass
    'rule' for the quiet dash-and-label section headers the public page uses,
    or leave it unset for the dark full-bleed bands every other screen has.
    Both come out of the same <x-event-section-band>, so the two surfaces
    cannot drift into two components.
--}}
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
            {{-- An event with nothing written about it and no tags has no About
                 section at all — band included. An empty band is worse than a
                 missing one: it announces a heading and then hands the reader
                 five ems of white space, which reads as a page that failed to
                 load rather than an organiser who did not write a paragraph.
                 The next band below closes the gap on its own. --}}
            @if(trim((string) ($e['about'] ?? '')) !== '' || !empty($e['tags']))
            <x-event-section-band :color="$e['color']" icon="bi-info-circle"
                                  :title="__('personal.event_show_about')"
                                  :variant="$sectionVariant ?? 'band'" />

            <div class="p-5">
                @if(trim((string) ($e['about'] ?? '')) !== '')
                    {{-- The organiser writes this in a textarea: paragraphs are blank
                         lines and lists are lines starting "- ". `whitespace-pre-line`
                         honoured the newlines but produced one undifferentiated wall
                         of text — no paragraph rhythm, no list indent — because there
                         were no elements to style. The component builds real markup,
                         escaping the author's text before any tag is added. --}}
                    <x-prose-text :text="$e['about']" />
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
            @endif

            {{-- ===== The spine ===== --}}
            @if(!empty($e['phases']))
                <span id="how-it-runs" class="block"></span>
                <x-event-section-band :color="$e['color']" icon="bi-signpost-split"
                                      :title="__('personal.event_show_how_it_runs')"
                                  :variant="$sectionVariant ?? 'band'" />
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
                                 {{-- Never dimmed. A phase that has happened is not less true than one
                                      that has not — the agenda is a record as much as a plan, and
                                      fading the finished half makes a completed event look broken. --}}
                                 class="flex gap-3.5 rounded-xl"
                                 style="--m-attn-color: {{ $e['color'] }}80; --ev-now: {{ $e['color'] }};">

                                {{-- The date column: a calendar leaf, so the eye can
                                     run down the dates without reading a word. --}}
                                <div class="w-11 shrink-0 text-center pt-0.5">
                                    <span class="block text-[10px] font-black uppercase tracking-wider text-muted-foreground">{{ \App\Support\Cldr::skeleton($pdate, 'MMM', 'M') }}</span>
                                    <span class="block text-[22px] font-black leading-none mt-0.5 {{ $active ? '' : 'text-foreground' }}"
                                          style="{{ $active ? 'color:'.$e['color'].';' : '' }}">{{ $pdate ? $pdate->format('j') : '—' }}</span>
                                    <span class="block text-[10px] font-semibold text-muted-foreground mt-0.5">{{ \App\Support\Cldr::skeleton($pdate, 'EEE', 'D') }}</span>
                                </div>

                                {{-- The rail --}}
                                <div class="flex flex-col items-center pt-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 {{ $active ? 'ev-now-dot' : '' }}"
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
                                        <p class="text-[15px] font-bold leading-tight {{ $active ? 'ev-now-text' : 'text-foreground' }}"
                                           style="{{ $active ? 'color:'.$e['color'].';' : '' }}">{{ $ph['label'] }}</p>
                                        @if($active)
                                            <span class="ev-now px-1.5 py-0.5 rounded-full text-[9px] font-black text-white tracking-wide" style="background: {{ $e['color'] }};">{{ __('personal.event_show_now') }}</span>
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

                                    {{-- The day's facts as CHIPS, not one line
                                         glued together with middots: each is a
                                         separate thing to read, and a phone
                                         wraps them instead of running them off
                                         the edge. `note` is the fallback for an
                                         entry the engine has not given items to
                                         (enrolment opens, weigh-in, awards). --}}
                                    @if(!empty($ph['note_items']))
                                        <ul class="mt-1.5 space-y-0.5">
                                            @foreach($ph['note_items'] as $item)
                                                <li class="flex items-start gap-1.5 text-[12px] text-muted-foreground leading-snug">
                                                    <span class="flex-none" aria-hidden="true">&bull;</span>
                                                    <span>{{ $item }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @elseif(!empty($ph['note']))
                                        <p class="text-[12px] text-muted-foreground leading-snug mt-1">{{ $ph['note'] }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ===== What it costs =====
                 An event prices itself as a LIST now — a Gi entry, a No-Gi
                 entry, a late penalty — so the one-line "From BHD 5" on the
                 facts row owes the reader the rest of the answer, and it owes
                 it HERE, in the card that answers every other question about
                 the event. Ticking happens on the entry form; this is the menu.

                 Renders only where the caller's payload carries it: the public
                 poster is handed `fees` by App\Events\Support\PublicEvent, and
                 a member's own event page is not, so that page is untouched. --}}
            @if($e['fees']['any'] ?? false)
                <span id="fees" class="block"></span>
                <x-event-section-band :color="$e['color']" icon="bi-cash-coin"
                                      :title="__('events.public_fees_title')"
                                      :value="$e['participant_fee'] ?? null"
                                      :variant="$sectionVariant ?? 'band'" />

                <div class="p-5">
                    <div class="space-y-0">
                        @foreach($e['fees']['participant'] as $line)
                            <div class="flex items-center gap-3 py-2.5 @if(! $loop->first) border-t border-gray-100 @endif">
                                <span class="min-w-0 flex-1 text-[14px] font-bold text-foreground">{{ $line['label'] }}</span>
                                <span class="text-[14px] font-black text-foreground flex-shrink-0">{{ $line['amount'] }}</span>
                            </div>
                        @endforeach

                        @foreach($e['fees']['spectator'] as $line)
                            <div class="flex items-center gap-3 py-2.5 border-t border-gray-100">
                                <span class="min-w-0 flex-1 text-[14px] font-bold text-muted-foreground">
                                    {{ $line['label'] }}
                                    <span class="text-[11px] font-bold">· {{ __('events.public_fees_spectator') }}</span>
                                </span>
                                <span class="text-[14px] font-black text-muted-foreground flex-shrink-0">{{ $line['amount'] }}</span>
                            </div>
                        @endforeach
                    </div>

                    @if($e['fees']['late'])
                        {{-- Said here rather than discovered at the desk: a
                             charge somebody meets after the fact is the one
                             that gets argued about. --}}
                        <div class="flex items-start gap-2.5 mt-3 rounded-xl px-3.5 py-3" style="background: #b4530912;">
                            <i class="bi bi-clock-history mt-0.5" style="color:#b45309;"></i>
                            <p class="text-[12px] text-muted-foreground leading-snug m-0">
                                {{ __('events.public_fee_late_from', ['date' => $e['fees']['late']['from'], 'amount' => $e['fees']['late']['amount']]) }}
                            </p>
                        </div>
                    @endif

                    @if($e['fees']['participant'])
                        <p class="text-[11px] text-muted-foreground mt-2.5 mb-0">
                            {{ $e['fees']['multiple'] ? __('events.public_fees_multiple') : __('events.public_fees_single') }}
                        </p>
                    @endif
                </div>
            @endif

            {{-- Divisions — grouped by AGE GROUP and gender, not gender alone.
                 A championship running Cadet and Senior would otherwise show two
                 identical "Men" lists, and the age group — the first thing a
                 competitor checks — never appeared at all. --}}
            @if(!empty($e['divisions']))
                @php
                    /* The list, cut into the sections its headings mean.
                     *
                     * The parse that used to live here — and in the desktop
                     * twin, in a copy that had already drifted — is now
                     * App\Events\Support\DivisionSections, so both cards read
                     * one structure. A HEADING is a caption, never a chip and
                     * never a link to a draw; an empty one is already dropped
                     * upstream. Divisions before the first heading arrive in a
                     * section whose heading is null and render exactly as they
                     * always did.
                     */
                    $divSections = $e['division_sections'] ?? [[
                        'heading' => null,
                        'groups' => [],
                    ]];
                @endphp
                <x-event-section-band :color="$e['color']" icon="bi-diagram-3-fill"
                                      :title="__('personal.event_show_divisions')"
                                  :variant="$sectionVariant ?? 'band'" />
                @if(($sectionVariant ?? 'band') === 'rule')
                    {{-- The design keeps this block NEUTRAL: the weight chips are
                         plain grey and only the gender glyph is tinted, so a long
                         list of divisions reads as one table rather than as
                         alternating pink and blue bands. --}}
                    <div class="flex flex-col" style="padding:16px 20px 22px; gap:16px;">
                        @foreach($divSections as $sec)
                            @if($sec['heading'])
                                <p class="flex items-center" style="margin:0; font-size:12px; font-weight:800; gap:6px; color:#1e2c4f; text-transform:uppercase; letter-spacing:0.08em;">
                                    <i class="bi bi-bookmark-fill" style="color:#6b7689;"></i>{{ $sec['heading'] }}
                                </p>
                            @endif
                            @foreach($sec['groups'] as $g)
                                <div @if($sec['heading']) style="padding-inline-start:10px;" @endif>
                                    {{-- Under a heading the fallback caption is the
                                         word "Divisions" — the section's own title —
                                         so it is not drawn twice. --}}
                                    @if(! ($sec['heading'] && $g['fallback']))
                                        <p class="flex items-center" style="margin:0 0 8px; font-size:11.5px; font-weight:700; gap:6px; color:#1e2c4f;">
                                            <i class="bi {{ $g['female'] ? 'bi-gender-female' : 'bi-gender-male' }}" style="color:#6b7689;"></i>{{ $g['label'] }}
                                        </p>
                                    @endif
                                    <div class="flex flex-wrap" style="gap:6px;">
                                        @foreach($g['items'] as $w)
                                            <span style="padding:5px 12px; border-radius:10px; font-size:11.5px; font-weight:600; background:#eef1f6; color:#1e2c4f;">{{ $w }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                @else
                <div class="p-5">
                    <div class="space-y-3.5">
                        @foreach($divSections as $sec)
                            @if($sec['heading'])
                                {{-- The organiser's own caption over the divisions
                                     that follow it. Blue, like the group captions,
                                     because it is the same kind of thing — a label
                                     over chips — one step stronger. --}}
                                <p class="flex items-center gap-1.5 text-[12px] font-black uppercase tracking-[0.1em]"
                                   style="color: #3b82f6;">
                                    <i class="bi bi-bookmark-fill"></i>{{ $sec['heading'] }}
                                </p>
                            @endif
                            @foreach($sec['groups'] as $g)
                                @php $tint = $g['female'] ? '#ec4899' : '#3b82f6'; @endphp
                                <div @class(['ps-3' => (bool) $sec['heading']])>
                                    @if(! ($sec['heading'] && $g['fallback']))
                                        <p class="flex items-center gap-1.5 text-[12px] font-black mb-2" style="color: {{ $tint }};">
                                            <i class="bi {{ $g['female'] ? 'bi-gender-female' : 'bi-gender-male' }}"></i>{{ $g['label'] }}
                                        </p>
                                    @endif
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($g['items'] as $w)
                                            <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                                  style="background: {{ $tint }}14;">{{ $w }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>
                @endif
            @endif

            {{-- Requirements — a short contract, set as one. --}}
            @if(!empty($e['requirements']))
                <x-event-section-band :color="$e['color']" icon="bi-clipboard-check"
                                      :title="__('personal.event_show_requirements')"
                                  :variant="$sectionVariant ?? 'band'" />
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
            @if(!empty($documents))
                <x-event-section-band :color="$e['color']" icon="bi-paperclip"
                                      :title="__('personal.event_docs_heading')"
                                  :variant="$sectionVariant ?? 'band'" />
                <div class="p-5">
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
            @if($hasMap && ($sectionVariant ?? 'band') === 'rule')
                {{-- ===== The design's location block =====
                     Map and address are SEPARATE here: a 160px rounded frame
                     holding the map, then a row beneath carrying the venue and
                     the directions pill. The band variant overlays the address
                     on the map instead — see the branch below. --}}
                <x-event-section-band :color="$e['color']" icon="bi-geo-alt-fill"
                                      :title="__('personal.event_show_location')"
                                      :variant="'rule'" />

                <div style="padding:14px 20px 0;">
                    {{-- pointer-events disabled: this is a PREVIEW, and a map
                         that swallows a scroll gesture traps the page. The
                         directions link is how somebody acts on it. --}}
                    <div class="relative" style="border-radius:14px; overflow:hidden; border:1px solid #e6eaf1; height:160px;">
                        <iframe title="{{ __('personal.event_show_location') }} — {{ $e['location'] }}"
                                scrolling="no" loading="lazy" referrerpolicy="no-referrer"
                                src="{{ 'https://www.openstreetmap.org/export/embed.html?bbox='
                                        . round($e['lng'] - 0.016, 4) . '%2C' . round($e['lat'] - 0.011, 4) . '%2C'
                                        . round($e['lng'] + 0.016, 4) . '%2C' . round($e['lat'] + 0.011, 4)
                                        . '&layer=mapnik&marker=' . round($e['lat'], 4) . '%2C' . round($e['lng'], 4) }}"
                                style="width:100%; height:calc(100% + 24px); border:0; display:block; pointer-events:none;"></iframe>
                        <div class="absolute inset-0"></div>
                    </div>
                </div>

                <div class="flex items-center justify-between" style="padding:14px 20px 22px; gap:12px;">
                    <p class="min-w-0" style="margin:0; font-size:13.5px; font-weight:700; color:#1e2c4f;">{{ $e['location'] }}</p>
                    @if($dirHref)
                        <a href="{{ $dirHref }}" target="_blank" rel="noopener"
                           class="m-press inline-flex items-center flex-none"
                           style="gap:6px; padding:9px 14px; border-radius:12px; font-size:11.5px; font-weight:700; color:#fff; background: {{ $e['color'] }};">
                            <i class="bi bi-cursor-fill"></i>{{ __('personal.event_show_directions') }}
                        </a>
                    @endif
                </div>

            @elseif($hasMap)
                {{-- The map component is built for forms, so its root is space-y-4
                     and its inner wrapper space-y-2 — which put a 1rem margin above
                     the map and stopped it meeting the card's bottom edge. Zero it
                     for this instance only; the card's overflow-hidden then clips
                     the map into the rounded corners. --}}
                @php
                    // ONE id for the style block, the component and the script below.
                    // `$e['id']` is an int on /me and the first 8 hex of the uuid on the
                    // public poster, so it is a STRING here — an (int) cast turned
                    // "cac87b31" into 0 and the script then initialised a map id that
                    // did not exist. Stripped to [A-Za-z0-9] because it is written into
                    // a CSS selector, an HTML id and a script.
                    $mapId = 'evtmap'.preg_replace('/[^A-Za-z0-9]/', '', (string) $e['id']);
                @endphp
                <style>
                    #{{ $mapId }}Container,
                    #{{ $mapId }}Container > div { margin: 0 !important; }
                    #{{ $mapId }}Container > * + *,
                    #{{ $mapId }}Container > div > * + * { margin-top: 0 !important; }
                    #{{ $mapId }}Map { display: block; }
                </style>
                {{-- Named like every other section, so the map is announced
                     rather than just appearing at the foot of the card. The band
                     sits directly on the map — no padded gap between them. --}}
                <x-event-section-band :color="$e['color']" icon="bi-geo-alt-fill"
                                      :title="__('personal.event_show_location')"
                                  :variant="$sectionVariant ?? 'band'" />

                <div class="relative">
                    <x-location-map
                        :id="$mapId"
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
                        {{-- Cast at the point of use, not only where the payload is built: these
                             three land INSIDE a script, where Blade's escaping does
                             nothing, so a non-numeric value would be code. Both current
                             feeders already cast — this is the belt for a third one. --}}
                        var id = {{ \Illuminate\Support\Js::from($mapId) }}, lat = {{ (float) $e['lat'] }}, lng = {{ (float) $e['lng'] }}, tries = 0;
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
                                      :title="__('personal.event_show_location')"
                                  :variant="$sectionVariant ?? 'band'" />
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
