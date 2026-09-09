@php
    /*
     * The gallery, flattened.
     *
     * The payload arrives grouped by division (VideoLibrary::forEvent), which
     * is the right shape for a shelf-per-division but the wrong one for this
     * page: the standalone template is ONE column of cards with a chip row
     * filtering it, so the division becomes a FILTER rather than a heading.
     * Flattening here — once, in PHP — is what lets the chip row be a pure
     * client-side filter with no second request.
     *
     * `g` is the division index a card belongs to; the chips filter on it.
     */
    $divisions = $e['gallery']['divisions'] ?? [];
    $cards = [];

    foreach ($divisions as $gi => $div) {
        foreach ($div['bouts'] ?? [] as $b) {
            $a = $b['arena'] ?? [];

            if ($a === []) {
                continue;   // nothing to draw a card from
            }

            $red = $a['red'] ?? [];
            $blue = $a['blue'] ?? [];

            $cards[] = [
                'id' => 'b'.($b['match_no'] ?? count($cards)),
                'g' => $gi,
                'matchNo' => $b['match_no'] ?? null,

                /* The band across the top of the thumbnail. */
                'event' => $a['event'] ?? ($e['title'] ?? ''),
                'stage' => $a['stage'] ?? ($b['round'] ?? ''),
                'weight' => $a['weight'] ?? ($div['title'] ?? ''),

                /* The two corners. */
                'redName' => $red['name'] ?? __('events.bracket_tbd'),
                'blueName' => $blue['name'] ?? __('events.bracket_tbd'),
                'redClub' => $red['club'] ?? '',
                'blueClub' => $blue['club'] ?? '',
                /* The club's mark, beside its name. Transparent PNG, so it is
                   the bare image on a sizing box — never a white tile
                   (Design Rule #5). Null for an athlete entered as an
                   individual, which is not an error: BoutArena returns null
                   for a club that does not exist and the row hides itself. */
                'redLogo' => $red['club_logo'] ?? null,
                'blueLogo' => $blue['club_logo'] ?? null,
                'redTag' => $red['tag'] ?? __('events.corner_red'),
                'blueTag' => $blue['tag'] ?? __('events.corner_blue'),
                'redPhoto' => $red['photo'] ?? null,
                'bluePhoto' => $blue['photo'] ?? null,

                /* The line under the thumbnail. */
                'meta' => implode(' · ', array_filter([
                    $a['stage'] ?? ($b['round'] ?? null),
                    $a['weight'] ?? null,
                    ($a['court'] ?? null) ? __('events.bout_card_court').' '.$a['court'] : null,
                ])),

                /* The film, and the ticker that runs over it. */
                'poster' => $b['poster'] ?? null,
                'preview' => $b['preview'] ?? null,
                'duration' => (int) ($b['duration'] ?? 0),
                'sport' => $a['scoring']['sport'] ?? '',
                'rounds' => $a['scoring']['rounds'] ?? [],
                'points' => $a['scoring']['points'] ?? [],

                /*
                 * The delete endpoint, or null for no menu item.
                 *
                 * `me.events.bout.video.destroy` asks EventAccess::canManage
                 * and enforces it itself; offering the control to anybody else
                 * would be a button that exists to return 403 (Navigation
                 * Integrity). $canManage comes from PublicEventController.
                 */
                'deleteUrl' => (($canManage ?? false) && ($b['match_no'] ?? null))
                    ? route('me.events.bout.video.destroy', ['event' => $e['uuid'], 'matchNo' => $b['match_no']])
                    : null,
            ];
        }
    }

    /* The chip row: All, then one chip per division that actually has film. */
    $chips = [['key' => 'all', 'label' => __('events.public_gallery_filter_all'), 'count' => count($cards)]];

    foreach ($divisions as $gi => $div) {
        $n = count(array_filter($cards, fn ($c) => $c['g'] === $gi));

        if ($n > 0) {
            $chips[] = ['key' => (string) $gi, 'label' => $div['title'], 'count' => $n];
        }
    }
@endphp

{{--
    The public gallery — one column of match-video cards.

    ⚠️ THE CSS AND THE CARD MARKUP BELOW ARE `drafts/Video Gallery
    (standalone).html`, TRANSCRIBED. The VS intro (two panels sliding in, the
    stage band dropping, the corners rising, the VS slamming and then pulsing),
    the explode-out on play, the scorebar sliding up and the live-scoring feed
    are ONE choreography with hand-tuned delays. Editing a single keyframe or
    delay to "tidy" it is how that comes apart on a screen in a hall.

    What this file adds is only the DATA: every literal in the template is
    replaced by a value from `$e['gallery']` → App\Media\BoutArena, and nothing
    else moves.

    The one deliberate deviation is noted at `--vs-delay` below.

    THE HEADER IS NOT MINE. The band above this partial belongs to
    entry.public.section-mobile (Design Rule #6) and is untouched — so the
    template's own dark header, its title and its language button are
    deliberately absent, and the video count lives in the "All" chip instead.
--}}

@once
@push('styles')
{{-- The two display faces the thumbnail is drawn in. Same subset the member
     card loads, so a reader who has seen one page has them cached. --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Barlow+Condensed:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    /* ══ The template's keyframes, verbatim ══════════════════════════════ */
    @keyframes pgCardIn{from{opacity:0;transform:translateY(18px) scale(.98)}to{opacity:1;transform:none}}
    @keyframes pgSnackIn{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
    @keyframes pgChipIn{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:none}}
    @keyframes pgVsPanelL{from{transform:translateX(-105%)}to{transform:translateX(0)}}
    @keyframes pgVsPanelR{from{transform:translateX(105%)}to{transform:translateX(0)}}
    @keyframes pgVsDrop{from{opacity:0;transform:translateY(-14px)}to{opacity:1;transform:none}}
    @keyframes pgVsRise{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
    @keyframes pgVsSlam{0%{opacity:0;transform:translate(-50%,-52%) scale(3.2) rotate(-6deg)}60%{opacity:1;transform:translate(-50%,-52%) scale(.92) rotate(1deg)}80%{transform:translate(-50%,-52%) scale(1.06)}100%{opacity:1;transform:translate(-50%,-52%) scale(1)}}
    @keyframes pgVsPulse{0%,100%{text-shadow:0 0 10px rgba(255,215,120,.55),0 0 30px rgba(255,170,60,.3)}50%{text-shadow:0 0 22px rgba(255,220,130,1),0 0 55px rgba(255,170,60,.7)}}
    @keyframes pgVsOut{0%{opacity:1;transform:scale(1);filter:blur(0)}100%{opacity:0;transform:scale(1.6);filter:blur(6px)}}
    @keyframes pgSbIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
    @keyframes pgFeedIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
    @keyframes pgSbmPulse{0%,100%{opacity:1}50%{opacity:.25}}

    /* The card's own entrance. The template puts `animation-delay` on the
       wrapper while the animation sits on the child — and animation-delay does
       not inherit, so the 90 ms-per-card stagger it intends never actually
       runs. Carried on a custom property instead, which DOES inherit, so the
       stagger reads as designed. This is the one deviation. */
    .pg-card{animation:pgCardIn .45s cubic-bezier(.22,1,.36,1) backwards;animation-delay:var(--vs-delay,0ms)}

    /* `position:relative` so an open ⋯ menu can be lifted above the next
       card by raising this wrapper's z-index; without it the later sibling
       paints on top and the menu is half-hidden even once un-clipped. */
    .pg-wrap{position:relative;transition:all .38s cubic-bezier(.4,0,.2,1);overflow:visible;max-height:600px}
    .pg-wrap[data-removing="1"]{opacity:0;max-height:0;margin-bottom:-16px}
    [dir="ltr"] .pg-wrap[data-removing="1"]{transform:translateX(60px) scale(.95)}
    [dir="rtl"] .pg-wrap[data-removing="1"]{transform:translateX(-60px) scale(.95)}

    /* The thumbnail is drawn LTR whatever the page direction: red is the left
       panel on a mat in Manama exactly as it is anywhere else. */
    .pg-thumb{position:relative;aspect-ratio:16/9;overflow:hidden;border-radius:13px 13px 0 0;direction:ltr;cursor:pointer;
        background:radial-gradient(120% 90% at 50% 40%,#16161f 0%,#0a0a0e 65%,#050507 100%);
        font-family:'Barlow Condensed',system-ui,sans-serif}
    .pg-thumb video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;background:#050507}

    .pg-menu-item{display:flex;align-items:center;gap:8px;width:100%;border:none;background:none;padding:9px 10px;border-radius:8px;font-family:inherit;font-size:.8rem;cursor:pointer;text-align:start}
    .pg-menu-item:hover{background:#eef1f6}
    .pg-menu-item.pg-danger{font-weight:600;color:#b4483c}
    .pg-menu-item.pg-danger:hover{background:#fbeeec}
    .pg-dots:active{transform:scale(.92)}
    .pg-chip:active{transform:scale(.96)}
    .pg-del:active{transform:scale(.97)}

    /* An empty value is hidden rather than printed as a blank line — a bout
       with no club still composes. */
    .pg-nullable:empty{display:none}

    @media (prefers-reduced-motion:reduce){
        .pg-card,.pg-wrap,.pg-thumb *{animation:none!important;transition:none!important}
    }
</style>
@endpush
@endonce

{{-- `margin-top:2rem` cancels the shell's own `-mt-8`.

     Every other section is ONE panel and is meant to ride up over the band's
     tail. This one is a column of cards, and in the template the only thing
     that overlaps the header is the CHIP ROW (`margin:-26px`) — never a card.
     So the shell's pull is undone here and the template's own two offsets are
     restored verbatim: the chips ride up 26px, the list starts 22px BELOW the
     band. Without this a single-division event (no chip row) had its first
     card sitting on top of the header. --}}
@php
    /*
     * The shell pulls its body up over the band with `-mt-8` (-32px). In the
     * template the only thing that overlaps the header is the CHIP ROW, at
     * -26px; a card never does. So:
     *   chips shown  → +6px, which nets to the template's -26px overlap.
     *   no chips     → +32px, cancelling the pull so the first card sits BELOW
     *                  the band, 22px down via the list's own padding.
     * Vertical margins collapse through the shell's padding-only wrapper, so
     * these add to its -32px rather than nesting inside it.
     */
    $chipsShown = count($chips) > 2;
@endphp
<div x-data="publicGallery(@js($cards), @js($chipsShown))"
     x-init="boot()"
     class="px-0" style="margin-top:{{ $chipsShown ? '6px' : '2rem' }}">

    {{-- ═══ The chip row ═══════════════════════════════════════════════════
         Rides up over the band's tail, exactly as the template's does. Only
         drawn when there is more than one division to choose between: a chip
         row reading "All (3)" and nothing else is a control with no choice. --}}
    <template x-if="showFilters">
        <div style="margin:0;background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(11,19,43,.10);padding:8px;display:flex;gap:6px;overflow-x:auto;position:relative;z-index:2">
            @foreach($chips as $i => $chip)
                {{-- The stagger is passed INTO chipStyle rather than left in a
                     static style attribute: a string `:style` would wipe it
                     (see the card's note). --}}
                <button type="button" class="pg-chip"
                        @click="filter = '{{ $chip['key'] }}'"
                        :style="chipStyle('{{ $chip['key'] }}', {{ $i * 60 }})">
                    {{ $chip['label'] }}
                    <span :style="badgeStyle('{{ $chip['key'] }}')">{{ $chip['count'] }}</span>
                </button>
            @endforeach
        </div>
    </template>

    {{-- ═══ The cards ══════════════════════════════════════════════════════ --}}
    <div style="padding:22px 0 40px;display:flex;flex-direction:column;gap:16px">

        <template x-for="(v, i) in shown" :key="v.id">
            <div class="pg-wrap" :data-removing="removing[v.id] ? '1' : '0'"
                 :style="{ zIndex: menuFor === v.id ? 30 : 'auto' }">
                {{-- ⚠️ OBJECT syntax, never a string. Alpine's `:style` with a
                     string sets cssText and so WIPES the element's own inline
                     style attribute — which cost this card its gold border, its
                     radius, its shadow and its white body. Object syntax uses
                     setProperty and leaves the static styles alone. --}}
                <div class="pg-card" :style="{ '--vs-delay': (i * 90) + 'ms' }"
                     style="background:#fff;border-radius:16px;box-shadow:0 4px 16px rgba(11,19,43,.08);border-top:3px solid #d8b25f">

                    {{-- ── The thumbnail ─────────────────────────────────── --}}
                    <div class="pg-thumb"
                         @click="toggle(v)"
                         @mouseenter="hoverIn(v)"
                         @mouseleave="hoverOut(v)">

                        {{-- The film itself, under the VS composition. Muted and
                             inline so it can start without a gesture prompt. --}}
                        <template x-if="v.preview">
                            {{-- An HLS ladder is offered through <source> with its
                                 own type, which is how Safari/iOS pick it up and how
                                 every other browser silently declines — the simulated
                                 clock covers that case. A progressive file gets `src`
                                 directly. Never both, and never an empty `src`: the
                                 empty string resolves to THIS page and the browser
                                 fetches the HTML as media. --}}
                            <video :data-vid="v.id" preload="metadata" playsinline muted
                                   :poster="v.poster || ''"
                                   x-bind="v.preview.endsWith('.m3u8') ? {} : { src: v.preview }">
                                <template x-if="v.preview.endsWith('.m3u8')">
                                    <source :src="v.preview" type="application/vnd.apple.mpegurl">
                                </template>
                            </video>
                        </template>

                        {{-- ── The VS composition. Explodes outward on play. ── --}}
                        <div :style="'position:absolute;inset:0;' + (playing === v.id ? 'animation:pgVsOut .55s ease forwards;pointer-events:none' : '')">

                            {{-- Red panel --}}
                            <div style="position:absolute;left:0;top:0;bottom:0;width:56%;clip-path:polygon(0 0,100% 0,82% 100%,0 100%);background:oklch(0.28 0.09 25);overflow:hidden;animation:pgVsPanelL .8s cubic-bezier(.22,1,.36,1) both">
                                <template x-if="v.redPhoto">
                                    <img :src="v.redPhoto" alt="" loading="lazy" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:top center">
                                </template>
                                <div style="position:absolute;inset:0;background:linear-gradient(115deg,oklch(0.45 0.18 25 / 0.55) 0%,transparent 55%);pointer-events:none"></div>
                                <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(5,5,7,.95) 0%,rgba(5,5,7,.6) 25%,transparent 60%);pointer-events:none"></div>
                            </div>

                            {{-- Blue panel --}}
                            <div style="position:absolute;right:0;top:0;bottom:0;width:56%;clip-path:polygon(18% 0,100% 0,100% 100%,0 100%);background:oklch(0.28 0.09 255);overflow:hidden;animation:pgVsPanelR .8s cubic-bezier(.22,1,.36,1) both">
                                <template x-if="v.bluePhoto">
                                    <img :src="v.bluePhoto" alt="" loading="lazy" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:top center">
                                </template>
                                <div style="position:absolute;inset:0;background:linear-gradient(245deg,oklch(0.45 0.15 255 / 0.55) 0%,transparent 55%);pointer-events:none"></div>
                                <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(5,5,7,.95) 0%,rgba(5,5,7,.6) 25%,transparent 60%);pointer-events:none"></div>
                            </div>

                            {{-- The gold seam between them --}}
                            <div style="position:absolute;top:-6%;bottom:-6%;left:50%;width:2px;margin-left:-1px;transform:rotate(10deg);background:linear-gradient(to bottom,transparent,oklch(0.85 0.16 85 / .9) 20%,oklch(0.85 0.16 85 / .9) 80%,transparent);filter:blur(1px);pointer-events:none"></div>

                            {{-- Event · stage · weight, dropping in from the top --}}
                            <div style="position:absolute;top:8px;left:0;right:0;display:flex;flex-direction:column;align-items:center;gap:3px;animation:pgVsDrop .6s .3s cubic-bezier(.22,1,.36,1) both;pointer-events:none">
                                <div class="pg-nullable" style="font-size:8px;font-weight:700;letter-spacing:.32em;text-transform:uppercase;color:rgba(232,230,224,.92);text-shadow:0 1px 6px rgba(0,0,0,.9)" x-text="v.event"></div>
                                <div style="display:flex;align-items:center;gap:6px">
                                    <span style="height:1.5px;width:22px;background:linear-gradient(to left,oklch(0.85 0.16 85),transparent)"></span>
                                    <span style="font-family:'Anton',sans-serif;font-size:13px;letter-spacing:.25em;padding-left:.25em;color:oklch(0.85 0.16 85);text-transform:uppercase" x-text="v.stage"></span>
                                    <span style="height:1.5px;width:22px;background:linear-gradient(to right,oklch(0.85 0.16 85),transparent)"></span>
                                </div>
                                <div class="pg-nullable" style="font-family:'Anton',sans-serif;font-size:10px;letter-spacing:.2em;padding-left:.2em;color:#fff;text-shadow:0 1px 6px rgba(0,0,0,.9)" x-text="v.weight"></div>
                            </div>

                            {{-- Red corner, rising --}}
                            <div style="position:absolute;left:10px;bottom:9px;display:flex;flex-direction:column;align-items:flex-start;gap:3px;max-width:44%;animation:pgVsRise .6s .5s cubic-bezier(.22,1,.36,1) both;pointer-events:none">
                                <span style="font-size:7px;font-weight:800;letter-spacing:.3em;color:#fff;background:oklch(0.55 0.20 25);padding:2px 5px 2px 6px" x-text="v.redTag"></span>
                                <span style="font-family:'Anton',sans-serif;font-size:15px;line-height:1;text-transform:uppercase;color:#fff;text-shadow:0 3px 12px rgba(0,0,0,.8)" x-text="v.redName"></span>
                                <template x-if="v.redClub || v.redLogo">
                                    <span style="display:flex;align-items:center;gap:4px;min-width:0">
                                        <template x-if="v.redLogo">
                                            <img :src="v.redLogo" alt="" loading="lazy" style="width:11px;height:11px;object-fit:contain;flex:none">
                                        </template>
                                        <span style="font-size:8px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(232,230,224,.9);white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="v.redClub"></span>
                                    </span>
                                </template>
                            </div>

                            {{-- Blue corner, rising a beat later --}}
                            <div style="position:absolute;right:10px;bottom:9px;display:flex;flex-direction:column;align-items:flex-end;gap:3px;max-width:44%;text-align:right;animation:pgVsRise .6s .62s cubic-bezier(.22,1,.36,1) both;pointer-events:none">
                                <span style="font-size:7px;font-weight:800;letter-spacing:.3em;color:#fff;background:oklch(0.50 0.16 255);padding:2px 5px 2px 6px" x-text="v.blueTag"></span>
                                <span style="font-family:'Anton',sans-serif;font-size:15px;line-height:1;text-transform:uppercase;color:#fff;text-shadow:0 3px 12px rgba(0,0,0,.8)" x-text="v.blueName"></span>
                                <template x-if="v.blueClub || v.blueLogo">
                                    <span style="display:flex;align-items:center;gap:4px;min-width:0">
                                        <template x-if="v.blueLogo">
                                            <img :src="v.blueLogo" alt="" loading="lazy" style="width:11px;height:11px;object-fit:contain;flex:none">
                                        </template>
                                        <span style="font-size:8px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(226,238,255,.9);white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="v.blueClub"></span>
                                    </span>
                                </template>
                            </div>

                            {{-- The slam, then the pulse --}}
                            <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-52%);font-family:'Anton',sans-serif;font-style:italic;font-size:42px;line-height:1;color:#fffdf5;animation:pgVsSlam .5s .8s cubic-bezier(.22,1,.36,1) both,pgVsPulse 2.4s 1.4s ease-in-out infinite;pointer-events:none">VS</div>
                        </div>

                        {{-- ── Live scoring, only while this card plays ────── --}}
                        <template x-if="playing === v.id">
                            <div>
                                {{-- The feed, top-right --}}
                                <div style="position:absolute;top:6px;right:8px;width:128px;display:flex;flex-direction:column;align-items:flex-end;gap:3px;pointer-events:none">
                                    <div style="display:flex;align-items:center;gap:5px;font-size:7px;font-weight:600;letter-spacing:.25em;text-transform:uppercase;color:#d5cfc7;text-shadow:0 1px 6px rgba(0,0,0,.9)">
                                        <span style="width:5px;height:5px;border-radius:50%;background:#e8534a;animation:pgSbmPulse 1.6s infinite"></span>{{ __('events.bout_card_live_scoring') }}
                                    </div>
                                    <template x-for="p in feed(v)" :key="p.key">
                                        <div :style="`display:flex;align-items:center;justify-content:flex-end;gap:5px;padding:3px 6px;background:rgba(10,10,12,.62);border-right:2px solid ${p.col};animation:pgFeedIn .35s ease both`">
                                            <span style="font-size:7px;letter-spacing:.14em;color:#a09a92" x-text="p.ts"></span>
                                            <span style="font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#efe9e0" x-text="p.label"></span>
                                            <span :style="`font-size:10px;font-weight:800;color:${p.col}`" x-text="p.pts"></span>
                                        </div>
                                    </template>
                                </div>

                                {{-- The skewed scorebar --}}
                                <div style="position:absolute;left:8px;right:8px;bottom:8px;display:flex;align-items:stretch;height:36px;animation:pgSbIn .35s ease both;pointer-events:none">
                                    <div style="flex:1;min-width:0;transform:skewX(-9deg);overflow:hidden;background:linear-gradient(90deg,rgba(122,26,22,.94),rgba(180,52,44,.9));border-bottom:2px solid #ff6a5e">
                                        <div style="transform:skewX(9deg);height:100%;padding:0 10px;display:flex;align-items:center;gap:7px">
                                            <span style="flex:1;min-width:0;font-size:11px;font-weight:800;color:#fff;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="v.redName"></span>
                                            <span style="font-size:17px;font-weight:800;line-height:1;color:#fff;text-shadow:0 3px 10px rgba(0,0,0,.5)" x-text="score(v,'red')"></span>
                                        </div>
                                    </div>
                                    <div style="width:56px;flex:none;transform:skewX(-9deg);background:rgba(9,9,11,.92);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1px;border-bottom:2px solid #2c2a28">
                                        <span style="transform:skewX(9deg);font-size:6px;letter-spacing:.25em;color:#8f8a83;text-transform:uppercase" x-text="roundName(v)"></span>
                                        <span style="transform:skewX(9deg);font-size:13px;font-weight:800;color:#efe9e0;font-variant-numeric:tabular-nums" x-text="clock(v)"></span>
                                    </div>
                                    <div style="flex:1;min-width:0;transform:skewX(-9deg);overflow:hidden;background:linear-gradient(90deg,rgba(30,72,140,.9),rgba(18,44,92,.94));border-bottom:2px solid #6aa6ff">
                                        <div style="transform:skewX(9deg);height:100%;padding:0 10px;display:flex;align-items:center;gap:7px">
                                            <span style="font-size:17px;font-weight:800;line-height:1;color:#fff;text-shadow:0 3px 10px rgba(0,0,0,.5)" x-text="score(v,'blue')"></span>
                                            <span style="flex:1;min-width:0;font-size:11px;font-weight:800;color:#fff;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-align:right" x-text="v.blueName"></span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Progress --}}
                                <div style="position:absolute;left:0;right:0;bottom:0;height:3px;background:rgba(255,255,255,.15)">
                                    <div :style="`height:100%;background:#d8b25f;width:${progress(v)}%`"></div>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- ── Under the thumbnail: the names, the meta, the ⋯ ── --}}
                    <div style="padding:13px 14px 14px;display:flex;align-items:flex-start;gap:10px">
                        <div style="flex:1;min-width:0">
                            <div style="font-size:.92rem;font-weight:600;line-height:1.35">
                                <span x-text="v.redName"></span>
                                <span style="color:#8a6d20;font-weight:700">{{ __('events.bout_vs') }}</span>
                                <span x-text="v.blueName"></span>
                            </div>
                            <div class="pg-nullable" style="margin-top:3px;font-size:.76rem;color:#6b7689" x-text="v.meta"></div>
                        </div>

                        <div style="position:relative;flex:none">
                            <button type="button" class="pg-dots"
                                    @click.stop="menuFor = (menuFor === v.id ? null : v.id)"
                                    :aria-label="'{{ __('events.bout_card_options') }}'"
                                    style="width:36px;height:36px;border:none;background:#eef1f6;border-radius:10px;color:#1e2c4f;font-size:1.1rem;font-weight:700;cursor:pointer;line-height:1">⋯</button>

                            <template x-if="menuFor === v.id">
                                <div @click.stop
                                     style="position:absolute;inset-inline-end:0;top:42px;background:#fff;border:1px solid #d5dae3;border-radius:12px;box-shadow:0 10px 28px rgba(11,19,43,.16);padding:6px;z-index:5;min-width:150px;animation:pgCardIn .18s ease backwards">
                                    <button type="button" class="pg-menu-item" style="font-weight:500;color:#1e2c4f" @click="share(v)">{{ __('events.bout_card_share') }}</button>
                                    <template x-if="v.deleteUrl">
                                        <button type="button" class="pg-menu-item pg-danger" @click="askDelete(v)">{{ __('events.bout_card_delete') }}</button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        {{-- ═══ Nothing here ═══════════════════════════════════════════════ --}}
        <template x-if="shown.length === 0">
            <div style="text-align:center;padding:56px 24px;animation:pgCardIn .4s ease backwards">
                <div style="width:64px;height:64px;margin:0 auto 16px;border-radius:50%;background:#fff;border:1px solid #d5dae3;display:grid;place-items:center">
                    <span style="display:block;width:0;height:0;border-left:18px solid #d5dae3;border-top:11px solid transparent;border-bottom:11px solid transparent;margin-left:4px"></span>
                </div>
                <div style="font-size:.95rem;font-weight:600" x-text="all.length ? '{{ __('events.public_gallery_filtered_title') }}' : '{{ __('events.public_gallery_empty_title') }}'"></div>
                <div style="margin-top:4px;font-size:.8rem;color:#6b7689" x-text="all.length ? '{{ __('events.public_gallery_filtered_body') }}' : '{{ __('events.public_gallery_empty_body') }}'"></div>
            </div>
        </template>
    </div>

    {{-- ═══ Confirm sheet ══════════════════════════════════════════════════
         Teleported: it is `position:fixed`, and a transformed ancestor would
         make it resolve against that box instead of the viewport (Mobile Forms
         Must Be Mobile-Friendly). --}}
    <template x-teleport="body">
        <template x-if="confirmFor">
            <div @click="confirmFor = null"
                 style="position:fixed;inset:0;background:rgba(11,19,43,.5);display:grid;place-items:end center;z-index:60">
                <div @click.stop
                     style="width:100%;max-width:430px;background:#fff;border-radius:20px 20px 0 0;padding:22px 20px 28px;animation:pgSnackIn .25s cubic-bezier(.22,1,.36,1) backwards;font-family:'Poppins','Cairo',system-ui,sans-serif;color:#1e2c4f">
                    <div style="width:36px;height:4px;border-radius:2px;background:#d5dae3;margin:0 auto 18px"></div>
                    <div style="font-size:1.05rem;font-weight:700">{{ __('events.bout_card_delete_title') }}</div>
                    <div style="margin-top:5px;font-size:.82rem;color:#6b7689;line-height:1.55">
                        <span x-text="confirmLabel"></span> — {{ __('events.bout_card_delete_body') }}
                    </div>
                    <div style="display:flex;gap:10px;margin-top:20px;padding-bottom:env(safe-area-inset-bottom)">
                        <button type="button" @click="confirmFor = null"
                                style="flex:1;padding:13px;border:1px solid #d5dae3;background:#fff;border-radius:12px;font-family:inherit;font-size:.9rem;font-weight:600;color:#1e2c4f;cursor:pointer">{{ __('shared.cancel') }}</button>
                        <button type="button" class="pg-del" @click="doDelete(confirmFor)"
                                style="flex:1;padding:13px;border:none;background:#b4483c;border-radius:12px;font-family:inherit;font-size:.9rem;font-weight:600;color:#fff;cursor:pointer">{{ __('events.bout_video_delete_confirm') }}</button>
                    </div>
                </div>
            </div>
        </template>
    </template>

    {{-- ═══ The undo snackbar ══════════════════════════════════════════════
         The delete is held for the life of this bar and only sent when it
         expires — so UNDO really does undo, rather than apologising for
         something already gone. Competition footage cannot be re-filmed. --}}
    <template x-teleport="body">
        <template x-if="snackFor">
            <div style="position:fixed;left:50%;transform:translateX(-50%);bottom:calc(22px + env(safe-area-inset-bottom));width:calc(100% - 48px);max-width:382px;background:#0b132b;color:#fff;border-radius:14px;padding:13px 16px;display:flex;align-items:center;gap:12px;box-shadow:0 12px 32px rgba(11,19,43,.35);z-index:70;animation:pgSnackIn .3s cubic-bezier(.22,1,.36,1) backwards;font-family:'Poppins','Cairo',system-ui,sans-serif">
                <span style="flex:1;font-size:.8rem">{{ __('events.bout_card_deleted') }}</span>
                <button type="button" @click="undo()"
                        style="border:none;background:none;color:#d8b25f;font-family:inherit;font-size:.82rem;font-weight:700;letter-spacing:.04em;cursor:pointer;padding:4px 6px">{{ __('events.public_gallery_undo') }}</button>
            </div>
        </template>
    </template>
</div>

@once
@push('scripts')
<script>
/*
 * The public gallery's card list.
 *
 * The standalone template drives the scorebar off a SIMULATED clock, because it
 * has no video in it. Here there is real film, so the clock is the <video>'s
 * own currentTime — the same source the member card's ticker uses, so the two
 * can never disagree about when a point landed. When the film cannot actually
 * play (an HLS ladder in a browser with no native HLS, a network that stalls),
 * the simulated clock takes over at the template's 12x so the choreography
 * still runs rather than freezing at 0:00.
 */
function publicGallery(cards, showFilters) {
    return {
        all: cards,
        showFilters: showFilters,
        filter: 'all',
        menuFor: null,
        confirmFor: null,
        snackFor: null,
        removing: {},
        playing: null,
        t: 0,

        _timer: null,
        _raf: null,
        _real: false,
        _pending: null,      // { card, index, timeout } — the held delete
        _snackTimer: null,
        _hasHover: false,

        get shown() {
            return this.filter === 'all'
                ? this.all
                : this.all.filter(v => String(v.g) === this.filter);
        },

        get confirmLabel() {
            const v = this.all.find(x => x.id === this.confirmFor);
            return v ? `${v.redName} — ${v.blueName}` : '';
        },

        boot() {
            this._hasHover = window.matchMedia && window.matchMedia('(hover: hover)').matches;
            this._closeMenus = () => { if (this.menuFor) this.menuFor = null; };
            document.addEventListener('click', this._closeMenus);
        },

        destroy() {
            document.removeEventListener('click', this._closeMenus);
            this.stop();
            clearTimeout(this._snackTimer);
            this._flush();          // never lose a held delete on navigation
        },

        /* ── chips ─────────────────────────────────────────────────────── */
        chipStyle(key, delay) {
            const on = this.filter === key;
            return 'flex:none;display:flex;align-items:center;gap:7px;padding:9px 15px;border:none;border-radius:10px;font-family:inherit;font-size:.8rem;font-weight:600;cursor:pointer;transition:all .25s;animation:pgChipIn .4s ease backwards;'
                + 'animation-delay:' + (delay || 0) + 'ms;'
                + (on ? 'background:linear-gradient(100deg,#edcd85 0%,#d0a854 100%);color:#1e2c4f' : 'background:#eef1f6;color:#6b7689');
        },
        badgeStyle(key) {
            const on = this.filter === key;
            return 'font-size:.66rem;font-weight:600;padding:2px 7px;border-radius:8px;'
                + (on ? 'background:rgba(30,44,79,.14);color:#1e2c4f' : 'background:#fff;color:#6b7689');
        },

        /* ── playback ──────────────────────────────────────────────────── */
        _video(id) { return document.querySelector(`video[data-vid="${id}"]`); },

        toggle(v) {
            this.menuFor = null;
            this.playing === v.id ? this.stop() : this.play(v);
        },
        hoverIn(v) { if (this._hasHover && this.playing !== v.id) this.play(v); },
        hoverOut(v) { if (this._hasHover && this.playing === v.id) this.stop(); },

        play(v) {
            this.stop();
            this.playing = v.id;
            this.t = 0;
            this._real = false;

            const el = this._video(v.id);

            if (el) {
                // Safari/iOS play the ladder natively; elsewhere the <source>
                // simply never loads, and the simulated clock covers it.
                const p = el.play();
                if (p && p.catch) p.catch(() => {});

                const follow = () => {
                    if (this.playing !== v.id) return;
                    if (el.currentTime > 0.05) { this._real = true; this.t = el.currentTime; }
                    if (el.ended || (v.duration && this.t >= v.duration)) return this.stop();
                    this._raf = requestAnimationFrame(follow);
                };
                this._raf = requestAnimationFrame(follow);
            }

            // The template's own clock: +3 s every 250 ms (~12x), so a whole
            // bout's highlights read in about twenty seconds.
            this._timer = setInterval(() => {
                if (this.playing !== v.id) return;
                if (this._real) return;                     // real film is ahead of us
                const nt = this.t + 3;
                if (v.duration && nt >= v.duration) return this.stop();
                this.t = nt;
            }, 250);
        },

        stop() {
            clearInterval(this._timer); this._timer = null;
            if (this._raf) cancelAnimationFrame(this._raf);
            this._raf = null;
            const el = this.playing ? this._video(this.playing) : null;
            if (el) { try { el.pause(); el.currentTime = 0; } catch (e) {} }
            this.playing = null;
            this.t = 0;
            this._real = false;
        },

        /* ── the ticker ────────────────────────────────────────────────── */
        _seen(v) { return (v.points || []).filter(p => p.t <= this.t + 0.001); },

        score(v, side) {
            const s = this._seen(v);
            const last = s[s.length - 1];
            if (last && last.sr !== undefined && last.sr !== null) {
                return side === 'red' ? last.sr : last.sb;
            }
            return s.filter(p => p.side === side).reduce((a, p) => a + (p.pts || 0), 0);
        },

        feed(v) {
            return this._seen(v).slice(-3).reverse().map((p, i) => ({
                key: `${p.t}-${p.side}-${i}`,
                ts: this.fmt(p.t),
                label: p.label || p.action || 'Point',
                pts: '+' + (p.pts || 0),
                col: p.side === 'red' ? '#ff6a5e' : '#6aa6ff',
            }));
        },

        roundName(v) {
            const rs = v.rounds || [];
            if (!rs.length) return '';
            let cur = rs[0];
            for (const r of rs) if (this.t >= (r.start || 0)) cur = r;
            return cur.name || ('R' + cur.n);
        },

        clock(v) {
            const rs = v.rounds || [];
            let start = 0;
            for (const r of rs) if (this.t >= (r.start || 0)) start = r.start || 0;
            return this.fmt(Math.max(0, this.t - start));
        },

        progress(v) {
            return v.duration ? Math.min(100, (this.t / v.duration) * 100) : 0;
        },

        fmt(sec) {
            sec = Math.max(0, Math.floor(sec || 0));
            return Math.floor(sec / 60) + ':' + String(sec % 60).padStart(2, '0');
        },

        /* ── share ─────────────────────────────────────────────────────── */
        async share(v) {
            this.menuFor = null;
            const url = window.location.href;
            const title = `${v.redName} — ${v.blueName}`;
            try {
                if (navigator.share) return await navigator.share({ title, url });
                await navigator.clipboard.writeText(url);
                this._say(@js(__('events.public_gallery_link_copied')));
            } catch (e) { /* the reader cancelled the sheet — not an error */ }
        },

        /* ── delete, held for the length of the snackbar ───────────────── */
        askDelete(v) {
            this.menuFor = null;
            this.confirmFor = v.id;
        },

        doDelete(id) {
            const v = this.all.find(x => x.id === id);
            this.confirmFor = null;
            if (!v || !v.deleteUrl) return;

            if (this.playing === id) this.stop();
            this.removing = { ...this.removing, [id]: true };

            // Let the 380 ms collapse play before the row leaves the list.
            setTimeout(() => {
                const index = this.all.indexOf(v);
                this.all = this.all.filter(x => x.id !== id);
                this.removing = { ...this.removing, [id]: false };
                this.snackFor = id;

                clearTimeout(this._snackTimer);
                this._pending = { card: v, index };
                // The request goes when UNDO can no longer be pressed.
                this._snackTimer = setTimeout(() => { this.snackFor = null; this._flush(); }, 5000);
            }, 380);
        },

        undo() {
            clearTimeout(this._snackTimer);
            const p = this._pending;
            this._pending = null;
            this.snackFor = null;
            if (!p) return;
            const list = [...this.all];
            list.splice(Math.min(p.index, list.length), 0, p.card);
            this.all = list;
        },

        /* Send the held delete. Nothing has left the server until this runs. */
        _flush() {
            const p = this._pending;
            this._pending = null;
            if (!p) return;

            const token = document.querySelector('meta[name="csrf-token"]');

            fetch(p.card.deleteUrl, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
            .then(r => r.ok ? r.json().catch(() => ({ success: true })) : Promise.reject(r))
            .catch(() => {
                // The server said no, so the card was never really gone: put it
                // back rather than leaving the reader believing a deletion that
                // did not happen.
                const list = [...this.all];
                list.splice(Math.min(p.index, list.length), 0, p.card);
                this.all = list;
                this._say(@js(__('events.public_gallery_delete_failed')));
            });
        },

        _say(msg) {
            if (window.showToast) return window.showToast('info', msg);
            if (window.notice) return window.notice(msg);
        },
    };
}
</script>
@endpush
@endonce
