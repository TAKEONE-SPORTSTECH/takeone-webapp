{{--
    Hall screens — the wall displays showing this event's mats.

    On the page it is a panel: one row per paired screen, each saying which mat
    it shows and whether it is actually alive, plus the button that adds one.
    Pairing itself lives in a sheet (a centered dialog from `sm:` up): scan the
    code on the screen, then say which mat it stands next to.

    Why a scan AND a typed code: the screen prints its code under the QR for a
    reason — hall lighting, a dirty lens, a phone whose engine has no
    BarcodeDetector. Either route ends in the same six characters, so neither is
    a fallback bolted on; they are two ways into one step.

    Standalone: all state, requests and DOM updates live in this file's Alpine
    component. It borrows exactly one shared thing — the app's QR scanner
    overlay (partials/qr-scanner), opened in hand-back mode so the value comes
    home on `court-screens:scanned` instead of navigating away.

    Security posture (mirrored server-side on every endpoint — what this renders
    is cosmetic): the pairing code is public by design, printed a metre tall on a
    wall. It is not the credential. The credential is the session plus canManage
    on THIS event, which is why the event is in the URL and never in the form.

    Writes patch in place (No Page Reload rule) and other organisers running the
    same event are nudged over MQTT (`realtime:events`, {action:'screens'}) — a
    refresh signal, so each console re-fetches what IT is allowed to see.

    Cameras live here too, and for the reason the panel exists at all: the
    question is never "what screens are paired" but "what is on Mat 2?" — and a
    mat's four camera positions are part of that answer exactly as its three
    boards are. They are a fourth kind of row in the same mat block, paired by
    the same scan and unpaired by the same press. What differs is only what a
    camera IS: a sport-neutral device on its own fleet, capped at four per mat,
    which records rather than draws.

    Props:
      event      ClubEvent uuid (public key)
      mats       string[]  the mats this event actually runs on, from the draw
      screens    array of {id,label,court,surface,live,last_seen}
      cameras    array of {id,angle,court,label,recording,live,battery,storage_free_*}
      cameraMax  how many cameras one mat may hold
      color      event colour, for the accents
--}}
@props([
    'event',
    /* Shortcuts the HOST supplies: the hall's own surfaces — the run-day board,
       the scoring table. They are not devices this panel pairs; they are the
       screens those devices SHOW, so they belong at the top of this sheet
       rather than in the console's tile column two rows above it (moved
       2026-09-06). The host builds them because only it knows which exist for
       this viewer and this sport: the board is organiser-only, the scoring
       table is gated on `canScore` and on the sport having a mat at all. */
    'shortcuts' => [],
    'mats' => [],
    'screens' => [],
    // The phones filming these mats. Empty is a real answer — an event nobody
    // is filming still shows the positions, the same way an unfilled board slot
    // is drawn rather than hidden.
    'cameras' => [],
    'cameraMax' => 4,
    // Which slots this event's package can actually SERVE. Offering one it
    // cannot ends with a screen in a hall showing an error and no way back, so
    // the panel asks rather than assumes — score control exists for Taekwondo
    // and not (yet) for Karate.
    'surfaces' => ['bout', 'queue', 'control'],
    // The address to open ON a screen so it joins THIS event's fleet. Shown in
    // the pairing sheet because a screen has to exist before it has a code.
    'newUrl' => null,
    'color' => '#7c3aed',
    // sheet: render as one console row that opens a bottom sheet holding the mat
    // blocks. A console is a column of doors; a hall's whole wiring laid out
    // among them is the longest thing on the page and rarely the reason you came.
    'sheet' => false,
])
@php
    // Reaches a style attribute — whitelisted like every other event surface.
    $csColor = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $color) ? $color : '#7c3aed';
@endphp

<div x-data="courtScreens({
        screens: @js(array_values($screens)),
        mats: @js(array_values($mats)),
        listUrl: @js(route('me.events.screens', $event)),
        pairUrl: @js(route('me.events.screens.pair', $event)),
        base: @js(url('/me/events/'.$event.'/screens')),
        cameras: @js(array_values($cameras)),
        cameraMax: @js((int) $cameraMax),
        camerasUrl: @js(route('me.events.cameras', $event)),
        camerasBase: @js(url('/me/events/'.$event.'/cameras')),
        {{-- Live broadcasting was removed from this server. The panel keeps
             its camera controls, which are about RECORDING. --}}
        liveUrl: null,
        liveBase: null,
        liveStoreUrl: null,
        allowed: @js(array_values($surfaces)),
        eventKey: @js($event),
     })"
     x-init="watch()"
     @court-screens:scanned.window="onScan($event.detail)"
     @realtime:events.window="onRealtime($event.detail)"
     @class(['bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden' => ! $sheet])>

@if($sheet)
    {{-- The row. Same card as every other door on the console, carrying how many
         of the hall's slots are filled so an organiser knows whether to open it. --}}
    <button type="button" @click="panel = true"
            class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
        <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 text-white"
              style="background: linear-gradient(140deg, {{ $csColor }}, {{ $csColor }}b0);"><i class="bi bi-tv-fill text-lg"></i></span>
        <span class="min-w-0 flex-1">
            <span class="block text-sm font-bold text-foreground">{{ __('personal.event_screens_title') }}</span>
            <span class="block text-[11px] text-muted-foreground mt-0.5" x-text="standing()"></span>
        </span>
        <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0"></i>
    </button>

    {{-- The panel, as a sheet. Teleported to <body> so the mobile shell's
         transformed wrapper cannot become its containing block and clip it. --}}
    <template x-teleport="body">
    <div x-show="panel" x-cloak class="fixed inset-0" style="z-index:70" @keydown.escape.window="panel = false">
        <div x-show="panel" x-transition.opacity class="absolute inset-0 bg-black/50" @click="panel = false"></div>
        <div x-show="panel"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
             class="absolute inset-x-0 bottom-0 flex flex-col bg-white rounded-t-3xl shadow-2xl sm:mx-auto sm:max-w-lg"
             style="max-height:92vh">
        {{-- Header band: the sheet says what it is before it says what is in it --}}
        <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
             style="background: linear-gradient(150deg, {{ $csColor }}, {{ $csColor }}b0);">
            <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
            <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

            <div class="relative flex items-start gap-3">
                <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                    <i class="bi bi-tv-fill text-xl"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="text-lg font-black leading-tight">{{ __('personal.event_screens_title') }}</h3>
                    {{-- `truncate`: the title, an icon tile and two round
                         controls already share this row, so the text column is
                         narrow. Without it a sub-line wraps to two lines and the
                         whole band gets taller — and a translation can do that
                         even when the English fits. --}}
                    <p class="text-[12px] text-white/85 mt-0.5 truncate">{{ __('personal.event_screens_sub') }}</p>
                </div>

                {{-- Sound lives here, behind the gear: what the screens PLAY is a setting
                     of the screens, and it is set once while the hall is being rigged.
                     Opens <x-event-screen-audio>'s sheet, which listens on the window. --}}
                <button type="button" @click="$dispatch('open-screen-audio')"
                        class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform"
                        aria-label="{{ __('events.screen_audio_title') }}" title="{{ __('events.screen_audio_title') }}">
                    <i class="bi bi-gear-fill"></i>
                </button>

                <button type="button" @click="panel = false" aria-label="{{ __('shared.close') }}"
                        class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            {{-- How many of the hall's slots are actually filled --}}
            <div class="relative mt-3 flex flex-wrap gap-1.5" x-show="screens.length" x-cloak>
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold">
                    <i class="bi bi-tv-fill"></i><span x-text="screens.length"></span>
                </span>
            </div>
        </div>
        <div class="flex-1 min-h-0 overflow-y-auto"
             style="padding-bottom: calc(0.5rem + env(safe-area-inset-bottom));">
@endif

@if(! $sheet)
    {{-- Header: what this panel is, and the count as its standing.
         Sheet mode carries the same facts in its gradient band above. --}}
    <div class="px-4 pt-4 pb-3 flex items-start gap-3">
        <span class="w-10 h-10 rounded-2xl grid place-items-center flex-shrink-0 text-white"
              style="background: linear-gradient(140deg, {{ $csColor }}, {{ $csColor }}b0);">
            <i class="bi bi-tv-fill"></i>
        </span>
        <div class="min-w-0 flex-1">
            <h3 class="text-sm font-bold text-foreground">{{ __('personal.event_screens_title') }}</h3>
            <p class="text-[11px] text-muted-foreground mt-0.5">{{ __('personal.event_screens_sub') }}</p>
        </div>
        <span x-show="screens.length" x-cloak
              class="flex-shrink-0 px-2.5 py-1 rounded-full text-[11px] font-bold bg-muted text-muted-foreground"
              x-text="screens.length"></span>

        {{-- Sound lives here, behind the gear: what the screens PLAY is a setting
             of the screens, and it is set once while the hall is being rigged.
             Opens <x-event-screen-audio>'s sheet, which listens on the window. --}}
        <button type="button" @click="$dispatch('open-screen-audio')"
                class="m-press flex-shrink-0 w-9 h-9 rounded-full bg-muted text-muted-foreground grid place-items-center hover:bg-accent hover:text-primary transition-colors"
                aria-label="{{ __('events.screen_audio_title') }}" title="{{ __('events.screen_audio_title') }}">
            <i class="bi bi-gear-fill text-sm"></i>
        </button>
    </div>
@endif

    {{-- ── One block per mat, three slots each ──────────────────────────────
         A hall is not a bag of screens: every mat has a scoreboard, an
         upcoming-matches board and a scoring table, and the question an
         organiser actually has is "which of Mat 2's three is still missing?".
         A flat list could not answer that — it could only show what already
         existed, which is the half of the truth that needs no attention.

         So the panel is the hall's own shape. Every slot is drawn whether or
         not it is filled: a filled one says it is alive and offers to unpair,
         an empty one offers to pair. Pressing Pair on a slot already knows the
         mat and the job, so the sheet has one thing left to ask. --}}
    @if(! empty($shortcuts))
        {{-- Space, no rule: these two are the hall's own surfaces and the mats
             below are the devices pointed at them, so they want separating —
             but by air rather than by a line. --}}
        <div class="px-4 pt-4 pb-2 mb-3 space-y-2">
            @foreach($shortcuts as $s)
                <a href="{{ $s['href'] }}"
                   @if(empty($s['external'])) data-shell-link data-route="me.events" @endif
                   class="m-press bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3 no-underline">
                    <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 {{ $s['tone'] }}">
                        <i class="bi {{ $s['icon'] }} text-lg"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-foreground truncate">{{ $s['label'] }}</span>
                        <span class="block text-[11px] text-muted-foreground truncate mt-0.5">{{ $s['sub'] }}</span>
                    </span>
                    <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0 rtl:rotate-180"></i>
                </a>
            @endforeach
        </div>
    @endif

    <div x-show="! mats.length" x-cloak class="px-4 pb-4">
        <div class="rounded-2xl border border-dashed border-gray-200 bg-muted/30 px-4 py-6 text-center">
            <i class="bi bi-diagram-3 text-2xl text-muted-foreground/50"></i>
            <p class="text-xs font-bold text-foreground mt-2">{{ __('personal.event_screens_no_mats') }}</p>
        </div>
    </div>

    <div class="px-4 pb-4 space-y-3">
        <template x-for="m in mats" :key="m">
            <div class="rounded-2xl border border-gray-100 overflow-hidden">

                {{-- The mat plate borrows the hall board's own look — dark,
                     condensed, all caps — so this reads as the thing bolted to
                     the wall rather than as another list header. --}}
                <div class="flex items-center gap-2 px-3 py-2 bg-[#101016]">
                    <span class="text-white text-xs font-black uppercase tracking-wider" x-text="m"></span>
                    <span class="flex-1"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider"
                          :class="filledCount(m) === surfaces.length ? 'text-green-400' : 'text-white/40'"
                          x-text="filledCount(m) + '/' + surfaces.length"></span>
                </div>

                <template x-for="sf in surfaces" :key="m + '|' + sf.key">
                    <div class="border-t border-gray-100 first:border-t-0">

                        {{-- Every screen doing this job on this mat, not just the
                             first. A hall often wants two of a board — one at each
                             end, or one facing the seats — and a duplicate used to
                             be invisible here: not a slot (the slot showed the
                             first one) and not a stray (its mat and job are both
                             valid), so it was live on a wall with no way to unpair
                             it. Score control is the exception and stays single:
                             two devices writing one mat's score is a contradiction,
                             and the server refuses it. --}}
                        <template x-for="(sc, si) in screensFor(m, sf.key)" :key="sc.id">
                            <div class="flex items-center gap-3 px-3 py-2.5" :class="si > 0 ? 'border-t border-gray-50' : ''">
                                <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0 bg-primary/10 text-primary">
                                    <i class="bi" :class="sf.icon"></i>
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center gap-1.5">
                                        <span class="text-xs font-bold text-foreground truncate" x-text="sf.label"></span>
                                        {{-- Which of the two it is, when there are two. --}}
                                        <span x-show="screensFor(m, sf.key).length > 1"
                                              class="flex-shrink-0 px-1.5 py-0.5 rounded-md text-[9px] font-black bg-muted text-muted-foreground"
                                              x-text="'#' + (si + 1)"></span>
                                        {{-- The slot that can WRITE results is called
                                             out: "which of these is the scoring tablet"
                                             is the question asked in a hurry. --}}
                                        <span x-show="sf.key === 'control'"
                                              class="flex-shrink-0 px-1.5 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider bg-amber-100 text-amber-700"
                                              x-text="@js(__('personal.event_screens_writes'))"></span>
                                    </span>

                                    {{-- A live screen pulses; a silent one is a flat
                                         amber dot. A board nobody has looked at since
                                         setup is exactly what to catch before the hall
                                         fills. --}}
                                    <span class="flex items-center gap-1.5 mt-0.5">
                                        <span class="relative flex w-2 h-2 flex-shrink-0">
                                            <span x-show="sc.live" class="absolute inline-flex w-full h-full rounded-full bg-green-500 opacity-60 animate-ping"></span>
                                            <span class="relative inline-flex w-2 h-2 rounded-full" :class="sc.live ? 'bg-green-500' : 'bg-amber-500'"></span>
                                        </span>
                                        <span class="text-[11px] font-bold" :class="sc.live ? 'text-green-600' : 'text-amber-600'"
                                              x-text="sc.live ? @js(__('personal.event_screens_live')) : (sc.last_seen || @js(__('personal.event_screens_never_seen')))"></span>
                                    </span>
                                </span>

                                <button type="button" @click="unpair(sc)" :disabled="busy === sc.id"
                                        class="m-press flex-shrink-0 w-9 h-9 rounded-xl grid place-items-center text-muted-foreground hover:bg-red-50 hover:text-red-600 transition-colors disabled:opacity-40"
                                        :aria-label="@js(__('personal.event_screens_unpair'))">
                                    <i class="bi" :class="busy === sc.id ? 'bi-arrow-repeat animate-spin' : 'bi-x-circle'"></i>
                                </button>
                            </div>
                        </template>

                        {{-- Empty: pair, already knowing the mat and the job.
                             Already filled and the job takes more than one: offer
                             another, quietly — it is the uncommon case. --}}
                        <div x-show="canAddMore(m, sf.key)" x-cloak
                             class="flex items-center gap-3 px-3 py-2.5"
                             :class="screensFor(m, sf.key).length ? 'border-t border-gray-50' : ''">
                            <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0 bg-muted text-muted-foreground/60">
                                <i class="bi" :class="sf.icon"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs font-bold text-foreground truncate"
                                      x-text="screensFor(m, sf.key).length ? @js(__('personal.event_screens_add_another')) : sf.label"></span>
                                <span class="block text-[11px] text-muted-foreground/70 mt-0.5" x-text="sf.hint"></span>
                            </span>
                            <button type="button" @click="start(m, sf.key)"
                                    class="m-press flex-shrink-0 h-9 px-3 rounded-xl text-white text-[11px] font-bold inline-flex items-center gap-1.5"
                                    style="background: linear-gradient(140deg, {{ $csColor }}, {{ $csColor }}b0);">
                                <i class="bi" :class="screensFor(m, sf.key).length ? 'bi-plus-lg' : 'bi-qr-code-scan'"></i><span x-text="screensFor(m, sf.key).length ? @js(__('personal.event_screens_add')) : @js(__('personal.event_screens_pair_short'))"></span>
                            </button>
                        </div>
                    </div>
                                </template>

                {{-- ── The mat's cameras ────────────────────────────────────
                     Beneath the boards because that is the order they are set
                     up in, and inside the mat block because a camera belongs to
                     a mat as completely as a scoreboard does — it starts and
                     stops with THIS mat's bouts and nothing else.

                     Four positions, listed by angle: the angle is not
                     decoration, it is how a clip is identified afterwards, so
                     it is what the row leads with. --}}
                <div class="border-t border-gray-100 bg-muted/20">
                    <div class="flex items-center gap-2 px-3 py-1.5">
                        <i class="bi bi-camera-video-fill text-[11px] text-muted-foreground/70"></i>
                        <span class="text-[10px] font-black uppercase tracking-wider text-muted-foreground">{{ __('personal.event_cameras_title') }}</span>
                        <span class="flex-1"></span>
                        <span class="text-[10px] font-bold"
                              :class="camerasFor(m).length ? 'text-foreground/60' : 'text-muted-foreground/50'"
                              x-text="camerasFor(m).length + '/' + cameraMax"></span>
                    </div>

                    <template x-for="cam in camerasFor(m)" :key="cam.id">
                        <div class="border-t border-gray-100 bg-white">
                        <div class="flex items-center gap-3 px-3 py-2.5">
                            <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0 text-xs font-black"
                                  :class="cam.recording ? 'bg-red-100 text-red-600' : 'bg-primary/10 text-primary'"
                                  x-text="cam.angle ?? '?'"></span>

                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-1.5">
                                    <span class="text-xs font-bold text-foreground truncate"
                                          x-text="cam.label || (@js(__('personal.event_cameras_one')) + ' ' + (cam.angle ?? ''))"></span>
                                    {{-- Rolling right now. The one state on this
                                         panel that changes by itself. --}}
                                    <span x-show="cam.recording" x-cloak
                                          class="flex-shrink-0 px-1.5 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider bg-red-600 text-white">REC</span>
                                    {{-- On air is not the same as recording and
                                         must not borrow its badge: one is who can
                                         watch now, the other is what is kept. --}}
                                    <span x-show="cam.on_air" x-cloak
                                          class="flex-shrink-0 px-1.5 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider bg-red-100 text-red-700">LIVE</span>
                                </span>

                                <span class="flex items-center gap-2 mt-0.5 flex-wrap">
                                    <span class="flex items-center gap-1.5">
                                        <span class="relative flex w-2 h-2 flex-shrink-0">
                                            <span x-show="cam.live" class="absolute inline-flex w-full h-full rounded-full bg-green-500 opacity-60 animate-ping"></span>
                                            <span class="relative inline-flex w-2 h-2 rounded-full" :class="cam.live ? 'bg-green-500' : 'bg-amber-500'"></span>
                                        </span>
                                        <span class="text-[11px] font-bold" :class="cam.live ? 'text-green-600' : 'text-amber-600'"
                                              x-text="cam.live ? @js(__('personal.event_screens_live')) : (cam.last_seen || @js(__('personal.event_screens_never_seen')))"></span>
                                    </span>
                                    {{-- The number that decides whether this phone
                                         lasts the day, said before the final rather
                                         than after it. --}}
                                    <span x-show="cam.storage_free_gb !== null && cam.storage_free_gb !== undefined" x-cloak
                                          class="text-[11px] font-bold"
                                          :class="(cam.storage_free_percent ?? 100) < 10 ? 'text-amber-600' : 'text-muted-foreground'"
                                          x-text="cam.storage_free_gb + ' GB'"></span>
                                    <span x-show="cam.battery !== null && cam.battery !== undefined" x-cloak
                                          class="text-[11px] font-bold"
                                          :class="(cam.battery ?? 100) < 20 ? 'text-amber-600' : 'text-muted-foreground'"
                                          x-text="cam.battery + '%'"></span>
                                    <span x-show="cam.clips" x-cloak class="text-[11px] text-muted-foreground"
                                          x-text="cam.clips + ' ' + @js(__('personal.event_cameras_clips'))"></span>
                                </span>

                                {{-- Three honest answers, never one hedged one:
                                     it is on air and this many are watching; it
                                     has been told to go live and has not yet; or
                                     its feed is off. --}}
                                <span class="block text-[11px] mt-0.5 truncate"
                                      :class="cam.on_air ? 'text-red-700 font-bold' : (cam.broadcasting ? 'text-amber-600 font-bold' : 'text-muted-foreground/70')"
                                      x-text="feedLine(cam)"></span>
                            </span>

                            {{-- Watching it is a different act from running it,
                                 so it is a quiet icon beside the switch. --}}
                            <a x-show="cam.on_air" x-cloak :href="cam.watch_url"
                               class="m-press flex-shrink-0 w-9 h-9 rounded-xl bg-white border border-red-200 text-red-700 grid place-items-center"
                               :aria-label="@js(__('personal.event_live_watch'))">
                                <i class="bi bi-play-fill"></i>
                            </a>

                            {{-- THIS camera's feed, on or off.
                                 The switch the app took away from whoever holds
                                 the phone and then gave to nobody: a paired
                                 camera broadcast until it was unpaired. It is a
                                 property of the camera, not of the mat — four
                                 lenses on one mat are four feeds, and an
                                 organiser turns one off without touching the
                                 other three. --}}
                            <button type="button" @click="toggleFeed(cam)" :disabled="busy === 'cam-' + cam.id"
                                    class="m-press flex-shrink-0 w-9 h-9 rounded-xl grid place-items-center transition-colors disabled:opacity-40"
                                    :class="cam.broadcasting ? 'bg-red-600 text-white' : 'bg-muted text-muted-foreground/70 hover:bg-muted'"
                                    :aria-label="cam.broadcasting ? @js(__('personal.event_live_stop')) : @js(__('personal.event_live_go'))">
                                <i class="bi" :class="busy === 'cam-' + cam.id ? 'bi-arrow-repeat animate-spin' : (cam.broadcasting ? 'bi-stop-fill' : 'bi-broadcast')"></i>
                            </button>

                            <button type="button" @click="unpairCamera(cam)" :disabled="busy === 'cam-' + cam.id"
                                    class="m-press flex-shrink-0 w-9 h-9 rounded-xl grid place-items-center text-muted-foreground hover:bg-red-50 hover:text-red-600 transition-colors disabled:opacity-40"
                                    :aria-label="@js(__('personal.event_cameras_unpair'))">
                                <i class="bi" :class="busy === 'cam-' + cam.id ? 'bi-arrow-repeat animate-spin' : 'bi-x-circle'"></i>
                            </button>
                        </div>

                        {{-- Footage this phone is holding.
                             Its own line rather than three more icons in the row
                             above: that row is about the LIVE feed — rolling, on
                             air, paired — and these are about files already
                             recorded. Shown only when there is something to act
                             on, so a camera that has filmed nothing stays quiet.

                             "Free space" asks; it does not command. The phone
                             refuses anything the server has not confirmed it
                             holds, because an un-uploaded bout is the only copy
                             of a fight that happened once. --}}
                        <div x-show="cam.clips" x-cloak
                             class="flex items-center gap-1.5 px-3 pb-2.5 flex-wrap">
                            <span class="text-[10px] font-black uppercase tracking-wider text-muted-foreground/70 me-auto"
                                  x-text="cam.clips + ' ' + @js(__('personal.event_cameras_clips'))"></span>

                            <button type="button" @click="footage(cam, 'upload', 'all')"
                                    :disabled="busy === 'foot-' + cam.id"
                                    class="m-press inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg bg-muted text-[11px] font-bold text-foreground hover:bg-accent transition-colors disabled:opacity-40">
                                <i class="bi" :class="busy === 'foot-' + cam.id ? 'bi-arrow-repeat animate-spin' : 'bi-cloud-upload'"></i>
                                {{ __('personal.event_cameras_send_all') }}
                            </button>

                            <button type="button" @click="footage(cam, 'play')"
                                    :disabled="busy === 'foot-' + cam.id"
                                    class="m-press inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg bg-muted text-[11px] font-bold text-foreground hover:bg-accent transition-colors disabled:opacity-40">
                                <i class="bi bi-play-btn"></i>{{ __('personal.event_cameras_play_last') }}
                            </button>

                            <button type="button" @click="footage(cam, 'purge', 'all')"
                                    :disabled="busy === 'foot-' + cam.id"
                                    class="m-press inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg bg-white border border-gray-200 text-[11px] font-bold text-muted-foreground hover:border-red-200 hover:text-red-600 transition-colors disabled:opacity-40">
                                <i class="bi bi-eraser"></i>{{ __('personal.event_cameras_free_space') }}
                            </button>
                        </div>
                        </div>
                    </template>

                    {{-- A phone filming this mat through the BROWSER, with no
                         app on it at all. It is a camera as much as the others —
                         a lens pointed at this mat — so it belongs in this list
                         and not in a section of its own, with the same Watch and
                         the same switch. It simply has no angle, no battery and
                         no clips to report. --}}
                    <template x-for="w in webCamsFor(m)" :key="w.id">
                        <div class="flex items-center gap-3 px-3 py-2.5 border-t border-gray-100 bg-white">
                            <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0 bg-red-600 text-white">
                                <i class="bi bi-phone"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-1.5">
                                    <span class="text-xs font-bold text-foreground truncate">{{ __('personal.event_live_web_camera') }}</span>
                                    <span class="flex-shrink-0 px-1.5 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider bg-red-100 text-red-700">LIVE</span>
                                </span>
                                <span class="block text-[11px] text-red-700 font-bold mt-0.5"
                                      x-text="(w.viewers || 0) + ' ' + @js(__('personal.event_live_watching')) + ' · ' + clock(w.duration)"></span>
                            </span>
                            <a :href="w.watch_url"
                               class="m-press flex-shrink-0 w-9 h-9 rounded-xl bg-white border border-red-200 text-red-700 grid place-items-center"
                               :aria-label="@js(__('personal.event_live_watch'))">
                                <i class="bi bi-play-fill"></i>
                            </a>
                            <button type="button" @click="cutStream(w)" :disabled="busy === 'live-' + (w.court ?? w.id)"
                                    class="m-press flex-shrink-0 w-9 h-9 rounded-xl bg-red-600 text-white grid place-items-center disabled:opacity-40"
                                    :aria-label="@js(__('personal.event_live_stop'))">
                                <i class="bi" :class="busy === 'live-' + (w.court ?? w.id) ? 'bi-arrow-repeat animate-spin' : 'bi-stop-fill'"></i>
                            </button>
                        </div>
                    </template>

    {{-- Filming with the phone in your hand, no app needed: a browser
         viewfinder on this mat. Kept beside the pairing row because they are the
         same decision — which phone films this mat — reached by two doors.

         ⚠️ SHOWN ONLY WHEN THE SERVER CAN ACTUALLY DO IT. Live broadcasting was
         removed from this box on 2026-08-27, so `liveStoreUrl` is null and both
         buttons here were dead: "Open camera" fetched null, which the browser
         resolves as the CURRENT page, so it silently did nothing at all. It read
         as the no-app answer to "how do I film this mat" and sent an organiser
         round in circles while the real answer — install the camera app — sat one
         row below it. A control that cannot work must not be on the glass
         (Navigation Integrity: no dead ends). Restore the row by giving
         `liveStoreUrl` a real endpoint; nothing else here needs to change. --}}
                    <div x-show="liveStoreUrl" x-cloak class="flex items-center gap-3 px-3 py-2.5 border-t border-gray-100">
                        <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0 bg-muted text-muted-foreground/60">
                            <i class="bi bi-phone"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-xs font-bold text-foreground truncate">{{ __('personal.event_live_web_camera') }}</span>
                            <span class="block text-[11px] text-muted-foreground/70 mt-0.5">{{ __('personal.event_live_web_camera_hint') }}</span>
                        </span>
                        <button type="button" @click="handMat(m)" :disabled="busy === 'live-' + m"
                                class="m-press flex-shrink-0 w-9 h-9 rounded-xl bg-muted text-foreground grid place-items-center disabled:opacity-40"
                                :aria-label="@js(__('personal.event_live_hand'))">
                            <i class="bi bi-qr-code"></i>
                        </button>
                        <button type="button" @click="openCameraFor(m)" :disabled="busy === 'live-' + m"
                                class="m-press flex-shrink-0 h-9 px-3 rounded-xl text-white text-[11px] font-bold inline-flex items-center gap-1.5 disabled:opacity-60"
                                style="background: linear-gradient(140deg, {{ $csColor }}, {{ $csColor }}b0);">
                            <i class="bi" :class="busy === 'live-' + m ? 'bi-arrow-repeat animate-spin' : 'bi-camera-video'"></i>
                            <span>{{ __('personal.event_live_open_camera') }}</span>
                        </button>
                    </div>

                    {{-- Room for another: the same scan, pressed from the mat it
                         is for, so the sheet has only the code left to ask. --}}
                    <div x-show="camerasFor(m).length < cameraMax" x-cloak
                         class="flex items-center gap-3 px-3 py-2.5 border-t border-gray-100">
                        <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0 bg-muted text-muted-foreground/60">
                            <i class="bi bi-camera-video"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-xs font-bold text-foreground truncate"
                                  x-text="camerasFor(m).length ? @js(__('personal.event_cameras_add_another')) : @js(__('personal.event_cameras_one'))"></span>
                            <span class="block text-[11px] text-muted-foreground/70 mt-0.5">{{ __('personal.event_cameras_hint') }}</span>
                        </span>
                        <button type="button" @click="start(m, 'camera')"
                                class="m-press flex-shrink-0 h-9 px-3 rounded-xl text-white text-[11px] font-bold inline-flex items-center gap-1.5"
                                style="background: linear-gradient(140deg, {{ $csColor }}, {{ $csColor }}b0);">
                            <i class="bi" :class="camerasFor(m).length ? 'bi-plus-lg' : 'bi-qr-code-scan'"></i><span x-text="camerasFor(m).length ? @js(__('personal.event_screens_add')) : @js(__('personal.event_screens_pair_short'))"></span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        {{-- Screens that predate the three slots, or sit on a mat the draw no
             longer has. Shown rather than hidden: a board is on a wall
             somewhere whatever this panel thinks, and one that cannot be seen
             here cannot be unpaired either. --}}
        <template x-if="strays().length">
            <div class="rounded-2xl border border-dashed border-gray-200 overflow-hidden">
                <div class="px-3 py-2 bg-muted/50">
                    <span class="text-[10px] font-black uppercase tracking-wider text-muted-foreground">{{ __('personal.event_screens_other') }}</span>
                </div>
                <template x-for="s in strays()" :key="s.id">
                    <div class="flex items-center gap-3 px-3 py-2.5 border-t border-gray-100">
                        <span class="flex-shrink-0 px-2.5 py-1.5 rounded-lg bg-[#101016] text-white text-[10px] font-black uppercase tracking-wider"
                              x-text="s.court"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-xs font-bold text-foreground truncate" x-text="s.surface_label"></span>
                            <span class="block text-[11px] text-muted-foreground truncate"
                                  x-text="s.live ? @js(__('personal.event_screens_live')) : (s.last_seen || @js(__('personal.event_screens_never_seen')))"></span>
                        </span>
                        <button type="button" @click="unpair(s)" :disabled="busy === s.id"
                                class="m-press flex-shrink-0 w-9 h-9 rounded-xl grid place-items-center text-muted-foreground hover:bg-red-50 hover:text-red-600 transition-colors disabled:opacity-40"
                                :aria-label="@js(__('personal.event_screens_unpair'))">
                            <i class="bi" :class="busy === s.id ? 'bi-arrow-repeat animate-spin' : 'bi-x-circle'"></i>
                        </button>
                    </div>
                </template>
            </div>
        </template>

        {{-- On air on a mat this panel has no block for — a stream started on a
             court the draw has since dropped, or a lab feed left running. It is
             ON somebody's screen whatever this panel thinks, and one that cannot
             be seen here cannot be stopped either. --}}
        <template x-if="strayLive().length">
            <div class="rounded-2xl border border-dashed border-red-200 overflow-hidden">
                <div class="px-3 py-2 bg-red-50">
                    <span class="text-[10px] font-black uppercase tracking-wider text-red-700">{{ __('personal.event_live_on_air') }}</span>
                </div>
                <template x-for="s in strayLive()" :key="s.id">
                    <div class="flex items-center gap-3 px-3 py-2.5 border-t border-gray-100 bg-white">
                        <span class="w-9 h-9 rounded-xl bg-red-600 text-white grid place-items-center flex-shrink-0">
                            <i class="bi bi-broadcast-pin"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-xs font-bold text-foreground truncate" x-text="s.label"></span>
                            <span class="block text-[11px] text-muted-foreground"
                                  x-text="(s.viewers || 0) + ' ' + @js(__('personal.event_live_watching')) + ' · ' + clock(s.duration)"></span>
                        </span>
                        <a :href="s.watch_url"
                           class="m-press flex-shrink-0 w-9 h-9 rounded-xl bg-white border border-red-200 text-red-700 grid place-items-center"
                           :aria-label="@js(__('personal.event_live_watch'))">
                            <i class="bi bi-play-fill"></i>
                        </a>
                        <button type="button" @click="cutStream(s)" :disabled="busy === 'live-' + (s.court ?? s.id)"
                                class="m-press flex-shrink-0 h-9 px-3 rounded-xl bg-red-600 text-white text-[11px] font-bold inline-flex items-center gap-1.5 disabled:opacity-60">
                            <i class="bi" :class="busy === 'live-' + (s.court ?? s.id) ? 'bi-arrow-repeat animate-spin' : 'bi-stop-fill'"></i>
                            <span>{{ __('personal.event_live_stop') }}</span>
                        </button>
                    </div>
                </template>
            </div>
        </template>
    </div>

@if($sheet)
        </div>
        </div>
    </div>
    </template>
@endif

    {{-- Pairing — a sheet on a phone, a centered dialog on a wide screen.
         Teleported to <body> so the mobile shell's stagger transform can't
         become the containing block for a fixed overlay and clip it. --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex flex-col justify-end sm:items-center sm:justify-center sm:p-4">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/50" @click="close()"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full sm:translate-y-4 sm:scale-95 sm:opacity-0"
                 x-transition:enter-end="translate-y-0 sm:scale-100 sm:opacity-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0 sm:scale-100 sm:opacity-100"
                 x-transition:leave-end="translate-y-full sm:translate-y-4 sm:scale-95 sm:opacity-0"
                 class="relative max-h-[92vh] w-full sm:max-w-md flex flex-col bg-background rounded-t-3xl sm:rounded-2xl shadow-2xl overflow-hidden">

                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl sm:rounded-t-2xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $csColor }}, {{ $csColor }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-tv-fill text-xl"></i>
                        </span>
                        {{-- The sheet says WHICH screen it is about. Pressing a
                             slot is the choice; repeating it as two more steps
                             inside would be asking twice. --}}
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight truncate" x-text="court"></h3>
                            <p class="text-[12px] text-white/85 mt-0.5 truncate" x-text="surfaceLabel()"></p>
                        </div>
                        <button type="button" @click="close()" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>

                {{-- Body scrolls; the action below stays reachable on a phone --}}
                <div class="flex-1 overflow-y-auto min-h-0 px-5 py-4 space-y-5">

                    {{-- Step 0 — the screen has to exist before it has a code.

                         Not decoration: a fleet is per sport, so a screen must
                         be opened at THIS event's address or its code will not
                         be found here. Getting that wrong used to surface as
                         "that code does not match a screen waiting to be
                         paired", which blames the code and hides the mistake. --}}
                    @if ($newUrl)
                        <details class="rounded-2xl border border-gray-100 bg-muted/30 overflow-hidden">
                            <summary class="px-4 py-3 cursor-pointer text-xs font-bold text-foreground flex items-center gap-2">
                                <i class="bi bi-1-circle text-primary"></i>
                                {{ __('personal.event_screens_open_first') }}
                            </summary>
                            <div class="px-4 pb-4 text-center">
                                <p class="text-[11px] text-muted-foreground mb-3">{{ __('personal.event_screens_open_hint') }}</p>
                                <div class="inline-block bg-white p-2.5 rounded-xl border border-gray-100">
                                    {!! \App\Support\Qr::svg($newUrl, 150, 2) !!}
                                </div>
                                <p class="mt-3 text-[11px] font-mono text-foreground break-all select-all">{{ $newUrl }}</p>
                            </div>
                        </details>
                    @endif

                    {{-- Step 1 — the code --}}
                    <div>
                        <button type="button" @click="scan()"
                                class="m-press w-full rounded-2xl border-2 border-dashed border-primary/30 bg-primary/5 px-4 py-5 text-center hover:bg-primary/10 transition-colors">
                            <i class="bi bi-qr-code-scan text-2xl text-primary"></i>
                            <span class="block text-sm font-bold text-primary mt-1.5">{{ __('personal.event_screens_scan') }}</span>
                        </button>

                        <div class="flex items-center gap-3 my-3">
                            <span class="h-px flex-1 bg-gray-200"></span>
                            <span class="text-[11px] text-muted-foreground">{{ __('personal.event_screens_code_or') }}</span>
                            <span class="h-px flex-1 bg-gray-200"></span>
                        </div>

                        {{-- Uppercased and stripped as it is typed: the alphabet
                             has no vowels and no 0/O/1/I, so a code read off a
                             wall cannot be mistyped into a different valid one. --}}
                        <input type="text" x-model="code" @input="code = clean(code)"
                               inputmode="latin" autocapitalize="characters" autocomplete="off" spellcheck="false" maxlength="6"
                               placeholder="{{ __('personal.event_screens_code_ph') }}"
                               class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-center text-lg font-black tracking-[0.4em] uppercase focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>

                </div>

                {{-- Sticky action, clear of the home indicator --}}
                <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 bg-background"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="submit()" :disabled="busy === 'pair'"
                            class="m-press w-full h-12 rounded-xl text-white text-sm font-bold inline-flex items-center justify-center gap-2 disabled:opacity-60"
                            style="background: linear-gradient(140deg, {{ $csColor }}, {{ $csColor }}b0);">
                        <i class="bi" :class="busy === 'pair' ? 'bi-arrow-repeat animate-spin' : 'bi-broadcast-pin'"></i>
                        {{ __('personal.event_screens_confirm') }}
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- ── Handing a mat's camera to a phone ─────────────────────────────
         A volunteer standing at the mat should not be sent a link or asked to
         type one: the organiser holds this up and they scan it, exactly the way
         a screen is paired. Fetched from the server because the code is per
         stream, and drawn by our own encoder because a hall's wifi is captive
         or filtered as often as not. --}}
    <template x-teleport="body">
        <div x-show="liveQr" x-cloak class="fixed inset-0" style="z-index:75" @click="liveQr = null"
             @keydown.escape.window="liveQr = null">
            <div x-show="liveQr" x-transition.opacity class="absolute inset-0 bg-gray-900/60"></div>
            <div class="absolute inset-0 grid place-items-center p-6">
                <div x-show="liveQr"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                     class="bg-white rounded-3xl shadow-2xl p-5 text-center max-w-xs w-full" @click.stop>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('personal.event_live_scan_title') }}</p>
                    <p class="text-base font-black text-foreground mt-1" x-text="liveQr?.label"></p>

                    {{-- White ground always: a scanner looks for dark modules on
                         a light field. --}}
                    {{-- Server-rendered, offline: a hall's wifi is captive or
                         filtered as often as not, and this must not depend on
                         reaching anything. One fixed address (/camera), so it is
                         drawn once with the page rather than fetched per stream —
                         the per-stream endpoint it used to call died with the
                         live server on 2026-08-27 and had been showing a broken
                         image ever since. --}}
                    <div class="mt-3 bg-white rounded-2xl p-2 inline-block [&>svg]:w-56 [&>svg]:h-56 [&>svg]:block">
                        {!! \App\Support\Qr::svg(url('/camera'), 224) !!}
                    </div>
                    <p class="text-[11px] font-mono text-muted-foreground mt-2 break-all">{{ preg_replace('#^https?://#', '', url('/camera')) }}</p>

                    <p class="text-[11px] text-muted-foreground mt-3 leading-snug">{{ __('personal.event_live_scan_hint') }}</p>
                    <p class="text-[11px] text-muted-foreground/70 mt-1.5 leading-snug flex items-start gap-1.5 text-start">
                        <i class="bi bi-record-circle mt-0.5"></i><span>{{ __('personal.event_live_recorded') }}</span>
                    </p>

                    <button type="button" @click="liveQr = null"
                            class="mt-4 w-full py-2.5 rounded-xl bg-muted text-foreground text-sm font-bold">{{ __('shared.close') }}</button>
                </div>
            </div>
        </div>
    </template>
</div>

@once
    {{-- Deliberately INLINE, not @push('scripts') — pushed scripts land in
         #shell-scripts, OUTSIDE <main id="shell-content">, so after an in-shell
         navigation the definition never arrives and x-data calls an undefined
         courtScreens(). Inline, it ships with the content and re-runs on every
         swap. Guarded, because Alpine keeps the reference it already has. --}}
    <script>
        window.courtScreens = window.courtScreens || function (config) {
            return {
                screens: config.screens || [],
                // The mats' cameras. Same panel, same mat blocks, different
                // fleet — see the note at the top of this file.
                cameras: config.cameras || [],
                cameraMax: config.cameraMax || 4,
                camerasUrl: config.camerasUrl,
                camerasBase: config.camerasBase,
                /* What each mat is BROADCASTING. Same panel, same mat blocks, a
                   third fleet — see the note at the top of this file. Both
                   halves are needed and they answer different questions:
                   `liveStreams` is what is on air (viewers, how long),
                   `liveMats` is what could be (is a phone standing by). */
                liveStreams: [],
                liveMats: [],
                liveUrl: config.liveUrl,
                liveBase: config.liveBase,
                liveStoreUrl: config.liveStoreUrl,
                liveQr: null,
                // The host a scanned QR came from, when it is not this one. Sent
                // with the pair so the server can name both and write it down.
                from: null,
                mats: config.mats || [],
                listUrl: config.listUrl,
                pairUrl: config.pairUrl,
                base: config.base,
                eventKey: config.eventKey,
                // `panel` is the whole section when it renders as a sheet;
                // `open` stays the PAIRING sheet, as it always was.
                panel: false,
                open: false,
                busy: null,
                code: '',
                court: '',
                // Which slot is being filled. Both are set by pressing a slot,
                // never chosen inside the sheet — the press IS the choice.
                surface: 'bout',

                /* The three screens every mat has. This list is the panel's
                   shape as much as its vocabulary: one block per mat, one row
                   per entry here, drawn whether or not it is filled. */
                surfaces: [
                    { key: 'bout',    icon: 'bi-trophy',  label: @js(__('personal.event_screens_surface_bout')),    hint: @js(__('personal.event_screens_surface_bout_hint')) },
                    { key: 'queue',   icon: 'bi-list-ol', label: @js(__('personal.event_screens_surface_queue')),   hint: @js(__('personal.event_screens_surface_queue_hint')) },
                    { key: 'control', icon: 'bi-sliders', label: @js(__('personal.event_screens_surface_control')), hint: @js(__('personal.event_screens_surface_control_hint')) },
                ].filter(sf => (config.allowed || []).includes(sf.key)),

                /** The screen filling one slot, or undefined. */
                screenFor(mat, surface) {
                    return this.screens.find(s => s.court === mat && s.surface === surface);
                },

                /** EVERY screen doing this job on this mat — a hall may want two. */
                screensFor(mat, surface) {
                    return this.screens.filter(s => s.court === mat && s.surface === surface);
                },

                /**
                 * Whether this slot will take another screen. Score control never
                 * doubles up — one mat, one device that can write the score — and
                 * the endpoint enforces that too, so the button is not the guard.
                 */
                canAddMore(mat, surface) {
                    if (surface === 'control') return this.screensFor(mat, surface).length === 0;
                    return true;
                },

                /** This mat's cameras, in angle order — the order they were placed. */
                camerasFor(mat) {
                    return this.cameras
                        .filter(c => c.court === mat)
                        .sort((a, b) => (a.angle || 0) - (b.angle || 0));
                },

                /** How many of a mat's three slots are filled — the mat's standing. */
                filledCount(mat) {
                    return this.surfaces.filter(sf => this.screenFor(mat, sf.key)).length;
                },

                /**
                 * The row's sub-line when this section is a door: how much of the
                 * hall is actually wired, which is the one thing worth knowing
                 * without opening it.
                 */
                standing() {
                    if (! this.mats.length) return @js(__('personal.event_screens_no_mats'));
                    const total = this.mats.length * this.surfaces.length;
                    const filled = this.mats.reduce((n, m) => n + this.filledCount(m), 0);
                    const line = filled + ' / ' + total;

                    // Cameras are named on this line only when some exist, and
                    // then the recording count takes over — a hall with four
                    // phones rolling wants to see that without opening anything.
                    // A mat that is ON AIR outranks everything else on this
                    // line: it is the one state somebody may need to end in a
                    // hurry, and it must not be hidden behind a closed door.
                    const onAir = this.liveStreams.filter(s => s.live).length;

                    if (onAir) return line + ' · ' + onAir + ' ' + @js(__('personal.event_live_word'));

                    if (! this.cameras.length) return line;

                    const rolling = this.cameras.filter(c => c.recording).length;

                    return line + ' · ' + (rolling
                        ? rolling + ' ' + @js(__('personal.event_cameras_rolling'))
                        : this.cameras.length + ' ' + @js(__('personal.event_cameras_word')));
                },

                /* Paired screens that fit no slot: an older one that follows the
                   mat, or one on a mat the draw has since dropped. They exist on
                   a wall whatever this panel thinks, so they are shown — a
                   screen that cannot be seen here cannot be unpaired either. */
                strays() {
                    return this.screens.filter(s =>
                        ! this.mats.includes(s.court) ||
                        ! this.surfaces.some(sf => sf.key === s.surface)
                    );
                },

                /* ── Feeds ────────────────────────────────────────────────
                   A feed belongs to a CAMERA, not to a mat: four lenses on one
                   mat are four feeds, switched on and off one at a time. Intent
                   and fact stay apart everywhere here — `broadcasting` is the
                   order the console gave, `on_air` is what the media server
                   actually sees — because a red dot over a mat with no picture
                   behind it is worse than no dot at all. */

                /** What this camera's feed is doing, in words. */
                feedLine(cam) {
                    if (cam.on_air) {
                        return (cam.viewers || 0) + ' ' + @js(__('personal.event_live_watching'))
                            + ' · ' + this.clock(cam.air_seconds);
                    }

                    return cam.broadcasting
                        ? @js(__('personal.event_live_waiting'))
                        : @js(__('personal.event_live_feed_off'));
                },

                /** Switch one camera's feed on or off. */
                async toggleFeed(cam) {
                    if (this.busy) return;

                    // Ending a broadcast people are watching is worth a beat of
                    // thought; starting one is not.
                    if (cam.broadcasting && cam.on_air) {
                        const ok = await window.confirmAction({
                            title: @js(__('personal.event_live_stop_confirm')),
                            message: @js(__('personal.event_live_stop_body')),
                            type: 'danger',
                            confirmText: @js(__('personal.event_live_stop')),
                        });

                        if (! ok) return;
                    }

                    const on = ! cam.broadcasting;
                    this.busy = 'cam-' + cam.id;

                    try {
                        const res = await fetch(this.camerasBase + '/' + cam.id + '/broadcast', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': this.csrf(),
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ on: on }),
                        });
                        const data = await res.json().catch(() => ({}));

                        if (! res.ok || ! data.success) {
                            window.showToast && window.showToast('error', @js(__('personal.event_live_feed_failed')));
                            return;
                        }

                        this.cameras = data.cameras || this.cameras;
                        window.showToast && window.showToast('success', on
                            ? @js(__('personal.event_live_feed_started'))
                            : @js(__('personal.event_live_feed_stopped')));

                        // The picture takes a moment to arrive; look again
                        // shortly rather than waiting out the panel's own beat.
                        if (on) setTimeout(() => this.reloadCameras(), 3000);
                        await this.reloadLive();
                    } catch (e) {
                        window.showToast && window.showToast('error', @js(__('personal.event_live_feed_failed')));
                    } finally {
                        this.busy = null;
                    }
                },

                /* A phone filming this mat through the browser rather than the
                   app. It has no camera row of its own, so it is drawn from the
                   stream — and only when it is actually on air, because an idle
                   browser stream is a page somebody opened and left. */
                webCamsFor(mat) {
                    return this.liveStreams.filter(s =>
                        s.live
                        && String(s.court) === String(mat)
                        && ! this.cameras.some(c => c.stream_id && c.stream_id === s.id)
                    );
                },

                clock(seconds) {
                    const s = Math.max(0, seconds | 0);
                    return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
                },

                csrf() { return document.querySelector('meta[name="csrf-token"]')?.content || ''; },

                /**
                 * Show the code that hands this mat to a phone with no app.
                 *
                 * A mat with no browser stream gets one first: an organiser
                 * holding out a QR should not have to open a camera page on
                 * their own device to bring one into existence.
                 */
                async handMat(mat) {
                    // Somebody ELSE's phone films it: show them the address, they
                    // scan it, their phone enrols and shows a code. A phone with
                    // the camera app installed is offered the app by Android; a
                    // phone without one gets the browser camera and works anyway.
                    this.liveQr = { url: @js(url('/camera')), label: mat };
                },

                /** Film this mat with the phone in your hand: a browser viewfinder. */
                async openCameraFor(mat) {
                    if (this.busy) return;

                    // THIS phone becomes the camera. It enrols at /camera through
                    // the same door the app uses, shows a code, and is paired into
                    // one of this mat's camera slots like any other lens — so the
                    // organiser holding the console can also be the one filming.
                    window.location.href = @js(route('camera.web'));
                },

                /** The browser stream reserved for this mat, if any. */
                streamFor(mat) {
                    const row = this.liveMats.find(x => String(x.court) === String(mat));
                    return (row && row.stream) || null;
                },

                /** Create (or find) this mat's browser stream. Its payload, or null. */
                async reserveStream(mat) {
                    const row = this.liveMats.find(x => String(x.court) === String(mat));

                    try {
                        const res = await fetch(this.liveStoreUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrf(),
                            },
                            // The bout on that mat travels with it, so the
                            // recording lands on the right fight when there is one.
                            body: JSON.stringify({ court: String(mat), match_id: (row && row.match_id) || null }),
                        });
                        const data = await res.json().catch(() => ({}));

                        if (! res.ok || ! data.success) {
                            window.showToast && window.showToast('error', @js(__('personal.event_live_failed')));
                            return null;
                        }

                        await this.reloadLive();

                        return Object.assign({}, data.stream, { broadcast_url: data.broadcast_url });
                    } catch (e) {
                        window.showToast && window.showToast('error', @js(__('personal.event_live_failed')));
                        return null;
                    }
                },

                /** What is on air on this event, and which mats have a stream. */
                async reloadLive() {
                    try {
                        const res = await fetch(this.liveUrl, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            cache: 'no-store',
                        });

                        if (! res.ok) return;

                        const data = await res.json();
                        this.liveStreams = data.streams || [];
                        this.liveMats = Array.isArray(data.mats) ? data.mats : [];
                    } catch (e) { /* a console that could not refresh keeps what it had */ }
                },

                /* On air on a court this panel draws no block for. Same reason
                   stray screens are listed: it is running somewhere, and what
                   cannot be seen here cannot be stopped here either. */
                strayLive() {
                    return this.liveStreams.filter(s =>
                        s.live && ! this.mats.some(m => String(m) === String(s.court))
                    );
                },

                /** Stop one broadcast, wherever this panel is showing it. */
                async cutStream(on) {
                    if (! on || this.busy) return;

                    const ok = await window.confirmAction({
                        title: @js(__('personal.event_live_stop_confirm')),
                        message: @js(__('personal.event_live_stop_body')),
                        type: 'danger',
                        confirmText: @js(__('personal.event_live_stop')),
                    });

                    if (! ok) return;

                    this.busy = 'live-' + (on.court ?? on.id);

                    try {
                        const res = await fetch(this.liveBase + '/' + on.id + '/stop', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': this.csrf(),
                            },
                        });

                        if (! res.ok) {
                            window.showToast && window.showToast('error', @js(__('personal.event_live_stop_failed')));
                            return;
                        }

                        window.showToast && window.showToast('success', @js(__('personal.event_live_stopped')));
                        await this.reloadLive();
                    } catch (e) {
                        window.showToast && window.showToast('error', @js(__('personal.event_live_stop_failed')));
                    } finally {
                        this.busy = null;
                    }
                },

                /**
                 * Show the code that hands this mat to a phone.
                 *
                 * A mat with no stream gets one first: an organiser holding out
                 * a QR should not have to open a camera page on their own device
                 * to bring one into existence.
                 */
                async handMat(mat) {
                    // Somebody ELSE's phone films it: show them the address, they
                    // scan it, their phone enrols and shows a code. A phone with
                    // the camera app installed is offered the app by Android; a
                    // phone without one gets the browser camera and works anyway.
                    this.liveQr = { url: @js(url('/camera')), label: mat };
                },

                /** No camera answering: open a viewfinder on THIS device instead. */
                async openCameraFor(mat) {
                    if (this.busy) return;

                    // THIS phone becomes the camera. It enrols at /camera through
                    // the same door the app uses, shows a code, and is paired into
                    // one of this mat's camera slots like any other lens — so the
                    // organiser holding the console can also be the one filming.
                    window.location.href = @js(route('camera.web'));
                },

                /** Create (or find) this mat's stream. Returns its payload, or null. */
                async reserveStream(mat) {
                    const row = this.liveMats.find(x => String(x.court) === String(mat));

                    try {
                        const res = await fetch(this.liveStoreUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrf(),
                            },
                            // The bout on that mat travels with it, so the
                            // recording lands on the right fight when there is one.
                            body: JSON.stringify({ court: String(mat), match_id: (row && row.match_id) || null }),
                        });
                        const data = await res.json().catch(() => ({}));

                        if (! res.ok || ! data.success) {
                            window.showToast && window.showToast('error', @js(__('personal.event_live_failed')));
                            return null;
                        }

                        await this.reloadLive();

                        return Object.assign({}, data.stream, { broadcast_url: data.broadcast_url });
                    } catch (e) {
                        window.showToast && window.showToast('error', @js(__('personal.event_live_failed')));
                        return null;
                    }
                },

                /** What is on air, and which mats have a phone waiting. */
                async reloadLive() {
                    try {
                        const res = await fetch(this.liveUrl, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            cache: 'no-store',
                        });

                        if (! res.ok) return;

                        const data = await res.json();
                        this.liveStreams = data.streams || [];
                        this.liveMats = Array.isArray(data.mats) ? data.mats : [];
                    } catch (e) { /* a console that could not refresh keeps what it had */ }
                },

                /**
                 * Take a camera off its mat.
                 *
                 * Unpair, never revoke: the phone keeps its token, sees itself
                 * free on its next poll, and comes back showing a fresh pairing
                 * code — which is what somebody moving a camera to another mat
                 * wants. A rolling camera is stopped by the server first, so it
                 * cannot keep filling its disk with a bout it has left.
                 */
                /**
                 * Ask a camera to do something with footage it already holds.
                 *
                 * Deliberately thin: the phone is the authority on what may
                 * actually happen to a file, so this reports what was ASKED and
                 * lets the camera's own beat report what was done. Pretending
                 * otherwise would have the console claim a bout was deleted
                 * while the phone was still refusing to delete it.
                 */
                async footage(cam, action, clip = null) {
                    if (this.busy) return;

                    // Clearing footage is the one that cannot be taken back, so
                    // it is the one that asks first.
                    if (action === 'purge') {
                        const ok = await window.confirmAction({
                            title: @js(__('personal.event_cameras_free_space')),
                            message: @js(__('personal.event_cameras_free_space_body')),
                            type: 'warning',
                            confirmText: @js(__('personal.event_cameras_free_space_go')),
                        });

                        if (! ok) return;
                    }

                    this.busy = 'foot-' + cam.id;

                    try {
                        const res = await fetch(this.camerasBase + '/' + cam.id + '/footage', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({ action: action, clip: clip }),
                        });

                        const data = await res.json().catch(() => ({}));

                        if (! res.ok || ! data.success) {
                            window.showToast && window.showToast('error', data.message || @js(__('personal.event_verify_failed')));
                            return;
                        }

                        window.showToast && window.showToast('success', data.message);
                    } catch (e) {
                        window.showToast && window.showToast('error', @js(__('personal.event_verify_failed')));
                    } finally {
                        this.busy = null;
                    }
                },

                async unpairCamera(cam) {
                    const ok = await window.confirmAction({
                        title: @js(__('personal.event_cameras_unpair')),
                        message: @js(__('personal.event_cameras_unpair_confirm')),
                        type: 'danger',
                        confirmText: @js(__('personal.event_cameras_unpair')),
                    });

                    if (! ok) return;

                    this.busy = 'cam-' + cam.id;

                    try {
                        const response = await fetch(this.camerasBase + '/' + cam.id, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                            },
                            credentials: 'same-origin',
                        });

                        const data = await response.json();

                        if (! response.ok) throw new Error(data.message || '');

                        this.cameras = data.cameras || [];
                        window.showToast('success', @js(__('personal.event_cameras_unpaired')));
                    } catch (e) {
                        window.showToast('error', @js(__('personal.event_cameras_unpair_failed')));
                    } finally {
                        this.busy = null;
                    }
                },

                /** The cameras again, after a nudge from another console. */
                async reloadCameras() {
                    try {
                        const response = await fetch(this.camerasUrl, {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });

                        if (! response.ok) return;

                        const data = await response.json();
                        this.cameras = data.cameras || [];
                    } catch (e) { /* a console that could not refresh keeps what it had */ }
                },

                surfaceLabel() {
                    return (this.surfaces.find(sf => sf.key === this.surface) || {}).label || '';
                },

                /** Pair the screen for one mat's one job. */
                /**
                 * Pressing Pair goes straight to the scanner.
                 *
                 * The mat and the job are already known — the press IS that
                 * choice — so the only thing left is the screen's code, and the
                 * scanner reads it. It is opened in hand-back mode with a typed
                 * fallback inside it, so a code that will not scan (glare, a dead
                 * camera, a screen across the hall) is still one field away. The
                 * sheet is no longer part of this path.
                 */
                start(mat, surface) {
                    this.code = '';
                    this.court = mat;
                    this.surface = surface;
                    window.dispatchEvent(new CustomEvent('qr-scan:open', { detail: {
                        emit: 'court-screens:scanned',
                        title: @js(__('personal.event_screens_pair')) + ' · ' + mat,
                        manual: true,
                        manualLabel: @js(__('personal.event_screens_code_or')),
                        manualPlaceholder: @js(__('personal.event_screens_code_ph')),
                        manualLength: 6,
                    } }));
                },

                close() {
                    this.open = false;
                    this.busy = null;
                },

                /** The pairing alphabet: no vowels, no 0/O/1/I. */
                clean(v) {
                    return String(v || '').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 6);
                },

                /**
                 * A code, or nothing — never a truncation.
                 *
                 * `clean()` normalises what a person is TYPING, so trimming as
                 * they go is right there. A SCANNED value is different: it is
                 * whole or it is the wrong QR, and slicing it to six characters
                 * manufactures a code that looks perfectly valid and exists
                 * nowhere. Refuse instead, so the message can say "wrong QR".
                 */
                strict(v) {
                    const c = String(v || '').toUpperCase().replace(/[^A-Z0-9]/g, '');

                    return c.length === 6 ? c : '';
                },

                /**
                 * Hand-back mode, so the shared scanner returns the value here
                 * instead of navigating to it — the scan is a step inside this
                 * flow, not a way out of the page.
                 */
                scan() {
                    window.dispatchEvent(new CustomEvent('qr-scan:open', {
                        detail: { emit: 'court-screens:scanned', title: @js(__('personal.event_screens_scan_title')) },
                    }));
                },

                /**
                 * The screen's QR encodes the claim URL, so the code is its last
                 * path segment — but a code typed or scanned bare is just as
                 * valid. Take whichever shape arrives and keep the six characters.
                 *
                 * ⚠️ THE HOSTNAME IS PART OF THE ANSWER, and throwing it away was
                 * a real, repeated, unfixable-looking bug.
                 *
                 * A pairing code is six characters in ONE server's database.
                 * takeone.bh and stage.takeone.bh are separate installations with
                 * separate data. This used to parse the scanned URL, keep only
                 * `.pathname`, and POST the last segment to whatever host the
                 * console happened to be open on — so a camera enrolled on
                 * production, scanned by an organiser working on stage, sent a
                 * code stage had never issued. The server answered, correctly and
                 * uselessly, "no camera is waiting with that code", which reads as
                 * a broken app and sent people to reopen it. Retrying could never
                 * help: nothing about a retry changes the host.
                 *
                 * The shared scanner already solved this on its NAVIGATE path
                 * (partials/qr-scanner.blade.php — it follows a QR to the host
                 * that printed it, and its comment says why). Hand-back mode
                 * returns before that code runs, so the fix has to live here.
                 */
                onScan(detail) {
                    const raw = (detail && detail.value) || '';
                    let found = '';
                    let url = null;

                    try {
                        url = new URL(raw, window.location.origin);
                    } catch (_) {
                        url = null;
                    }

                    if (url && (url.protocol === 'http:' || url.protocol === 'https:')) {
                        const there = url.hostname.toLowerCase();
                        const here = window.location.hostname.toLowerCase();

                        // Somebody else's QR entirely. A poster is world-writable;
                        // never act on one that is not ours.
                        if (window.takeoneIsOwnHost && ! window.takeoneIsOwnHost(there)) {
                            window.showToast && window.showToast('error', @js(__('header.scan_foreign_host')));
                            return;
                        }

                        // Ours, but the OTHER environment.
                        //
                        // The two hosts are separate installations with separate
                        // databases, so this code cannot be looked up here. Saying
                        // only that leaves somebody holding a phone with no next
                        // step — so the sheet opens on THIS server's camera
                        // address. Scan it with the same phone and it enrols
                        // here, which is the actual fix. Explaining a problem is
                        // not the same as handing over the answer.
                        //
                        // ⚠️ It is REPORTED, not silently swallowed. Refusing on
                        // the client and returning meant the attempt never
                        // reached the server, so the pairing log — the one thing
                        // built to end this guessing — recorded nothing at all,
                        // and a failure the organiser could see was invisible to
                        // everyone trying to help them. The submit carries the
                        // scanned host so the SERVER decides and writes it down;
                        // the toast below is the same answer, shown immediately.
                        if (there !== here) {
                            this.from = there;
                            this.submit();
                            return;
                        }

                        const parts = url.pathname.split('/').filter(Boolean);
                        found = this.strict(parts[parts.length - 1] || '');
                    } else {
                        // Not a URL: a bare code, read aloud or typed into the
                        // scanner's manual field.
                        found = this.strict(raw);
                    }

                    // A QR that is not a pairing code at all — a board URL ending
                    // in a 40-character token, an event page ending in a uuid, the
                    // venue's wifi poster. `clean()` would have TRUNCATED any of
                    // those to six plausible characters and submitted a code that
                    // never existed; `strict()` refuses instead, so the organiser
                    // is told they scanned the wrong thing rather than being sent
                    // to hunt a code that was never real.
                    if (found === '') {
                        window.showToast && window.showToast('error', @js(__('personal.event_screens_not_a_code')));
                        return;
                    }

                    this.code = found;

                    // A good code is the whole answer: pair now. Showing a sheet
                    // that repeats what was just scanned and asks for the mat
                    // already chosen is a confirmation step with nothing in it.
                    // A bad code never reaches here, and a REFUSED one surfaces as
                    // the endpoint's own message — then the sheet opens so the
                    // code can be corrected by hand.
                    if (this.court) {
                        this.submit();
                        return;
                    }

                    this.open = true;
                },

                async submit() {
                    // One pairing at a time. A scan that arrives twice — a frame
                    // resolving late, a double-tapped confirm — used to send two
                    // POSTs carrying the same code, and both created a screen.
                    if (this.busy === 'pair') return;

                    const code = this.clean(this.code);
                    const court = (this.court || '').trim();

                    if (code.length !== 6) { window.showToast && window.showToast('error', @js(__('personal.event_screens_code_needed'))); return; }
                    if (! court) { window.showToast && window.showToast('error', @js(__('personal.event_screens_mat_needed'))); return; }

                    this.busy = 'pair';

                    try {
                        const res = await fetch(this.pairUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({ code: code, court: court, surface: this.surface, from_host: this.from || undefined }),
                        });
                        const data = await res.json().catch(() => ({}));

                        if (! res.ok || ! data.success) {
                            window.showToast && window.showToast('error', data.message || @js(__('personal.event_verify_failed')));
                            // Came straight from a scan and the server said no:
                            // open the sheet so the code can be fixed by hand
                            // rather than leaving the organiser with a toast and
                            // no way forward.
                            this.open = true;
                            return;
                        }

                        // A camera answers with the mats' cameras, a board with
                        // the mats' screens. One door, two fleets — take
                        // whichever the server sent and leave the other alone.
                        this.screens = data.screens || this.screens;
                        if (data.cameras) this.cameras = data.cameras;
                        window.showToast && window.showToast('success', data.message);
                        this.close();
                    } catch (e) {
                        window.showToast && window.showToast('error', @js(__('personal.event_verify_failed')));
                    } finally {
                        this.busy = null;
                    }
                },

                async unpair(screen) {
                    const ok = await window.confirmAction({
                        title: @js(__('personal.event_screens_unpair_confirm')),
                        message: @js(__('personal.event_screens_unpair_body')),
                        type: 'danger',
                        confirmText: @js(__('personal.event_screens_unpair')),
                    });

                    if (! ok) return;

                    this.busy = screen.id;

                    try {
                        const res = await fetch(this.base + '/' + screen.id, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                        });
                        const data = await res.json().catch(() => ({}));

                        if (! res.ok || ! data.success) {
                            window.showToast && window.showToast('error', data.message || @js(__('personal.event_verify_failed')));
                            return;
                        }

                        this.screens = data.screens || [];
                        window.showToast && window.showToast('success', data.message);
                    } catch (e) {
                        window.showToast && window.showToast('error', @js(__('personal.event_verify_failed')));
                    } finally {
                        this.busy = null;
                    }
                },

                /**
                 * Another organiser paired or unpaired a screen at the same
                 * venue. The push carries no screens — only that they changed —
                 * so re-fetch and be authorized on the way, rather than trusting
                 * a payload that arrived over a shared channel.
                 */
                onRealtime(detail) {
                    if (! detail || detail.event !== this.eventKey) return;

                    // Two nudges, one panel: a board changed, or a camera did.
                    if (detail.action === 'screens') this.refresh();
                    if (detail.action === 'cameras') this.reloadCameras();
                    // Another organiser put a mat on air, or took one off.
                    if (detail.action === 'live') this.reloadLive();
                },

                /* Liveness is the one thing on this panel that changes with
                   nobody doing anything: a screen is unplugged, a television is
                   switched on, a tablet goes to sleep. Nothing pushes it —
                   `last_seen` is written by the screens themselves — so the
                   panel re-reads quietly while it is on the page.

                   Thirty seconds against a ten-minute liveness window: slow
                   enough to be nothing, fast enough that an organiser walking
                   the hall sees a dot change before they reach the screen.
                   Stops when the page is hidden, because a laptop in a bag is
                   not watching anything. */
                beat: null,

                watch() {
                    var self = this;

                    // What is on air is wanted immediately, not in thirty
                    // seconds: a console opened mid-session must show the mats
                    // that are already broadcasting.
                    this.reloadLive();

                    if (this.beat) return;

                    this.beat = setInterval(function () {
                        if (document.visibilityState === 'visible') self.refresh();
                    }, 30000);

                    /* A broadcast moves faster than a screen does — it starts,
                       gathers viewers and ends inside one bout — so it has its
                       own shorter beat rather than riding the thirty-second one.
                       Ten seconds is what the standalone panel used and it was
                       right; nothing here is heavier than it was there. */
                    this.liveBeat = setInterval(function () {
                        if (document.visibilityState === 'visible') self.reloadLive();
                    }, 10000);
                },

                liveBeat: null,

                async refresh() {
                    try {
                        const res = await fetch(this.listUrl, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        const data = await res.json().catch(() => ({}));
                        if (res.ok && data.success) this.screens = data.screens || [];
                    } catch (_) { /* a console that cannot refresh keeps what it has */ }

                    // The same beat carries the cameras. Their liveness moves for
                    // the same reasons a screen's does — a phone sleeps, a battery
                    // dies, somebody walks out of the hall with one — and REC
                    // turns on and off with the bouts without anyone touching
                    // this page.
                    await this.reloadCameras();
                },
            };
        };
    </script>
@endonce
