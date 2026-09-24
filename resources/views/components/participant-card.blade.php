@props([
    /* ── REQUIRED ─────────────────────────────────────────────────────────
       `person` is one row of an entrant payload. It reads these keys, all
       optional but `name`:
         name · uuid · gender · age · life_stage · country · club · club_logo
         photo · activity · belt_icon (App\Sports\Combat\BeltIcon::for)
         division_rows [{id, name}]
       Anything absent simply does not draw — the card has no required row
       beyond the name. */
    'person',

    /* ── WHO IS READING ───────────────────────────────────────────────────
       `ready` is the payment / weigh-in state and the weight: [status, paid,
       weighed, weight, weight_verified]. Pass NULL for a reader not entitled
       to it and those two rows are ABSENT — never greyed, because a greyed
       badge still says there is something to grey. Scoping is the caller's
       job and deliberately so: this component draws what it is given. */
    'ready' => null,

    /* Where a tap goes. Null makes the card inert rather than a door to a
       page that would refuse the reader. */
    'href' => null,

    /* May this reader open the organiser's sheet (verify / enrol / profile)?
       When false the card carries neither the sheet nor the ids it works
       from. */
    'manage' => false,

    /* The event this card belongs to, as a uuid. Required for anything the
       organiser's sheet does; a card with no event simply has no sheet. */
    'event' => null,

    /* The registration the verification sheet opens on for this person.
       Organiser only, and the reason the sheet needs no round trip. */
    'entry' => null,

    /* ── WHERE A DIVISION CHIP GOES ───────────────────────────────────────
       `drawUrl` is the page that draws a group; the chip appends
       `?division=<id>`. Null makes the chips inert and `drawNote` is what
       they say on tap — "the draw is not out yet", in the host's own words. */
    'drawUrl' => null,
    'drawNote' => null,

    /* An Alpine expression that decides whether this card is shown, for a
       host with its own search box. Null renders the card unconditionally. */
    'show' => null,

    /* Where the scroll position is remembered under, so a reader who taps a
       card and comes back lands where they were. Defaults to the event. */
    'placeKey' => null,

    /* The event's own colour, for the organiser sheet's header band (Design
       Rule #8). Falls back to the platform accent, so a host that has no
       colour to give still gets a band that is on palette rather than a grey
       one. Whitelisted below before it reaches a `style` attribute — it is
       organiser-supplied. */
    'color' => null,

    /* ── MAY THIS READER CORRECT THE ENTRY? ───────────────────────────────
       Adds a pencil to the sheet's HEADER, beside the ✕ — not a fourth action
       row. Correcting a name or a photograph is an operation ON the person the
       band is already naming, so it belongs in the band with them; the rows
       below are places to GO.

       Like the verification desk, the component only OPENS the door: it
       dispatches `participant-edit` and the HOST supplies the editor it
       already owns (the organiser's roster has one; the public page does
       not). So this is opt-in, and a host that has no editor simply never
       draws the pencil rather than drawing one that does nothing. */
    'canEdit' => false,

    /* ── MAY THIS READER TAKE THEM OFF THE LIST? ──────────────────────────
       The last row of the sheet, in red. Same contract as the pencil: the
       component dispatches `participant-remove` and the host does the
       work, because striking a name off is the host's endpoint, its
       confirmation and its list to patch afterwards. */
    'canRemove' => false,
])

@php
    /* A hex colour or nothing. The band interpolates this straight into a
       gradient, and `#hex + b0` is the alpha suffix the whole platform uses —
       which is HEX ONLY: an `hsl(...)` here would produce `hsl(...)b0`, an
       invalid gradient, a dropped declaration and white text on white
       (Design Rule #8's first non-negotiable, which has shipped broken once). */
    $sheetColor = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $color) ? $color : '#7c6bf5';
@endphp

{{--
    ONE ENTRANT, as a card. `x-participant-card`

    Ported from drafts/participant-card.html on 2026-09-16 and used as drawn:
    the markup, the class names and the data-* contract are the draft's, and
    only the values are ours.

    ── Standalone (CLAUDE.md → Components Are Standalone) ────────────────────

    Drop it anywhere. It brings its own stylesheet, its own photo lightbox, its
    own tap behaviour and — for an organiser — its own action sheet, each
    emitted `@once` however many cards a page draws. There is no page script to
    remember, no partial to include first, no init to call.

    Two things it deliberately does NOT own, because they belong to the host:

      · WHO MAY SEE WHAT. It draws the `ready` payload it is handed and never
        decides who is entitled to one. The entry list scopes that per viewer
        (PublicEventController::readinessFor); another host will scope it its
        own way, and neither can be got wrong by editing this file.

      · THE VERIFICATION SHEET ITSELF. The organiser's action dispatches
        `open-verification` with `{entry}` on `window`, which is the event the
        shared desk already listens for. A host that renders the desk gets a
        working button; one that does not renders no such action. The desk is
        `personal.partials.event-people` in sheet-only mode — see
        entry/public/sections/partials/verification-desk.

    ── Events it dispatches ──────────────────────────────────────────────────

      open-verification  {entry}   open the desk on this person
      participant-enrolled {uuid}  groups were saved for this person
      participant-edit        correct this entry — {entry, uuid, name, club}
                                   Only with `can-edit`. The host owns the
                                   editor; this says WHICH entry to open it on.
      participant-remove      take them off the list — {entry, name}
                                   Only with `can-remove`. The host confirms,
                                   calls its endpoint and patches its own list.

    ── Where it is used ──────────────────────────────────────────────────────

      /e/{uuid}/participants  — the public entry list.
--}}

@php
    /* A chip is a door only when the host gave it somewhere to go. */
    $drawOpen = filled($drawUrl);

    $female = strcasecmp((string) ($person['gender'] ?? ''), 'Female') === 0;
    $genderKey = $female ? 'female' : 'male';

    /* The face, at the size this card paints — the door authorises the SOURCE
       exactly as it does the original, and falls back to it when no rendition
       can be drawn (thumb_url). The lightbox asks for `s=full`, which is not a
       known size and therefore serves the original: the draft's own swap. */
    $photo = ($person['photo'] ?? null)
        ? thumb_url($person['photo'], 'card')
        : asset('images/avatars/'.$genderKey.'.jpg');

    $cc = strtolower((string) ($person['country'] ?? ''));
    $flag = preg_match('/^[a-z]{2}$/', $cc) ? asset('images/flags/'.$cc.'.svg') : null;

    /* The rank, resolved server-side by App\Sports\Combat\BeltIcon — the one
       place the belt's colour maths lives. `outline` is set only for a belt
       pale enough to vanish on white, which is why the draft's outline rects
       are conditional here and hardcoded there. */
    $belt = $person['belt_icon'] ?? null;

    $divisions = $person['division_rows'] ?? [];
    $status = $ready['status'] ?? null;

    /*
     * ===== Two badges: ONE SHORT WORD each, state in the colour and the icon
     *
     * Split from the draft's single pill on 2026-09-16, and shortened the same
     * day at the user's request: "the moment you use long wording the things
     * will warp and destroy the shape of the card". A badge reading "Awaiting
     * weigh-in" beside one reading "Not paid" is most of the card's width, on a
     * line that also carries the age chip and the weight.
     *
     * So the WORD names the subject and never changes — Paid, Weighed — and
     * the STATE is carried by the colour and by the icon beside it. Not colour
     * alone: a tick, a cross and an hourglass are three different shapes, which
     * is what keeps this readable to somebody who cannot tell green from red.
     * The full sentence is not thrown away either — it is the badge's `title`
     * and its aria-label, so a pointer and a screen reader still get "Awaiting
     * weigh-in" while the card stays one line.
     *
     * The colour tokens are the draft's own and unchanged: `ready` green,
     * `paid` amber, `pending` red. Money is binary — settled or not — so it is
     * only ever green or red. The scale has a middle: waiting for the weigh-in
     * is not a failure, it is the ordinary state of an entry on the morning.
     */
    $paidYes = (bool) ($ready['paid'] ?? false);
    $weighedYes = (bool) ($ready['weighed'] ?? false);

    /*
     * ===== WHY A PERSON WHO PAID CAN STILL BE RED =====
     *
     * The badge is about the PERSON and says "everything they owe is settled".
     * An athlete entered in Gi and No-Gi owes twice, and the card goes green
     * only when both are paid — a green badge over one outstanding fee is the
     * half-truth a desk acts on.
     *
     * But red with no explanation reads as "has not paid", which is wrong for
     * somebody who has paid for one of two (reported 2026-09-18: "how come some
     * people have paid and the payment indicator is red"). So when the host
     * counts the entries for us, a PART-paid card says which — in the badge's
     * long text, not on its face: the word stays "Paid" and the card keeps its
     * shape, and the sentence is what a pointer and a screen reader get.
     */
    $paidOf = $ready['paid_count'] ?? null;
    $paidTotal = $ready['entries_count'] ?? null;
    $partPaid = ! $paidYes && $paidOf !== null && $paidTotal > 1 && $paidOf > 0;

    $badges = $ready === null ? [] : [
        [
            'state' => $paidYes ? 'ready' : 'pending',
            'icon' => $paidYes ? 'bi-check-circle-fill' : 'bi-x-circle-fill',
            'word' => __('personal.event_card_paid'),
            'full' => match (true) {
                $paidYes => __('personal.event_card_paid'),
                $partPaid => __('personal.event_card_paid_part', ['paid' => $paidOf, 'total' => $paidTotal]),
                default => __('personal.event_card_not_paid'),
            },
        ],
        [
            'state' => $weighedYes ? 'ready' : 'paid',
            'icon' => $weighedYes ? 'bi-check-circle-fill' : 'bi-hourglass-split',
            'word' => __('personal.event_card_weighed'),
            'full' => $weighedYes
                ? __('personal.event_card_weighed_in')
                : __('personal.event_card_awaiting_weigh_in'),
        ],
    ];
@endphp

<article class="pcard" data-gender="{{ $genderKey }}"
         @if($status) data-status="{{ $status }}" @endif
         data-weight-verified="{{ ($ready['weight_verified'] ?? false) ? 'true' : 'false' }}"
         {{-- The person's public key, and ONLY for the reader who acts on it.
              It is the organiser sheet's handle — the enrolment door and the
              profile are both addressed by it. A guest's card does nothing with
              it, and a key nobody needs is a key that should not be in the page
              (CLAUDE.md → Unpredictable Resource Identifiers is defence in
              depth, and this is the depth). --}}
         @if($manage) data-member-id="{{ $person['uuid'] ?? '' }}" @endif
         {{-- Which ENTRY the verification sheet opens on. Resolved with the
              page rather than asked for on tap, so the desk opens on the person
              who was tapped instead of on the list of everybody. Organiser
              only — a registration id is internal, and nobody else can act on
              one. --}}
         @if($manage && $entry) data-entry="{{ $entry }}" @endif
         @if($href) data-href="{{ $href }}" @endif
         @if($show) x-show="{{ $show }}" x-cloak @endif
         {{-- ⚠️ THE HOST'S OWN ATTRIBUTES, last so they win.

              This is what lets the organiser's roster keep multi-select without
              the card knowing anything about it: the page attaches its own
              `@click.capture` (which swallows the tap while selecting, BEFORE
              onCardTap can act on it), its `::class` ring and its
              `::role`/`::aria-checked`, and the card stays the card.

              Alpine bindings are written `::class` on the TAG — inside a
              component tag a single colon means a bound PHP prop, and Blade
              would try to evaluate the Alpine expression as PHP. --}}
         {{ $attributes }}
         role="button" tabindex="0" onclick="onCardTap(this)" onkeydown="onCardKey(event, this)">
    <span class="gbar"></span>
    <div class="body">
        {{-- Photo: 3:4 locked; tap = lightbox (stopPropagation so card tap doesn't fire) --}}
        <div class="photo" onclick="event.stopPropagation();openLightbox(this.querySelector('img').src)">
            <img src="{{ $photo }}" alt="{{ $person['name'] }}" loading="lazy">
        </div>
        <div class="info">
            <div class="row nowrap">
                <span class="name" dir="auto">{{ $person['name'] }}</span>
                @if($flag)
                    <img class="flag" src="{{ $flag }}" alt="{{ $person['country'] }}">
                @endif
            </div>
            @if($badges)
                <div class="row">
                    @foreach($badges as $b)
                        <span class="status" data-status="{{ $b['state'] }}"
                              title="{{ $b['full'] }}" aria-label="{{ $b['full'] }}">
                            <span><i class="bi {{ $b['icon'] }}" style="font-size:10px"></i>{{ $b['word'] }}</span>
                        </span>
                    @endforeach
                </div>
            @endif
            <div class="row">
                @if($person['life_stage'] ?? null)
                    <span class="chip">{{ $person['life_stage'] }}</span>
                @endif
                @if(($person['age'] ?? null) !== null)
                    <span class="meta"><i class="bi bi-gender-{{ $genderKey }}"></i>{{ __('personal.event_card_years', ['n' => $person['age']]) }}</span>
                @endif
                @if($ready && ($ready['weight'] ?? null) !== null)
                    <span class="weight"><i class="bi bi-speedometer2"></i>{{ $ready['weight'] }} {{ __('personal.event_card_kg') }}<i class="bi bi-patch-check-fill verified" style="font-size:10px"></i></span>
                @endif
            </div>
            @if($person['club'] ?? null)
                {{-- ===== THE CLUB, AS A DOOR =====

                     A club with a page on the platform is somewhere to go; a
                     club the organiser wrote down for the day (`event_clubs`,
                     no tenant behind it) is a NAME and nothing more, and most
                     of a real competition is those. So the row is a link only
                     when the HOST hands over `club_href` — it decides what is
                     reachable from the surface it is drawing, exactly as it
                     already does for the person's own `href`.

                     ⚠️ A host inside the sealed event app should pass NOTHING
                     here. There is no sealed club page, so a platform address
                     would walk a reader straight out of the event — the hole
                     that was closed on 2026-09-08, and the reason every other
                     destination on this card is a sealed route or absent.

                     `stopPropagation` so the tap opens the club rather than the
                     card's own sheet, and the row keeps its exact shape either
                     way: the same markup, wrapped. --}}
                @php $clubHref = $person['club_href'] ?? null; @endphp
                <{{ $clubHref ? 'a' : 'div' }} class="club{{ $clubHref ? ' is-door' : '' }}"
                    @if($clubHref)
                        href="{{ $clubHref }}"
                        onclick="event.stopPropagation()"
                        title="{{ $person['club'] }}"
                    @endif>
                    @if($person['club_logo'] ?? null)
                        <img src="{{ $person['club_logo'] }}" alt="">
                    @else
                        <i class="bi bi-people"></i>
                    @endif
                    <span>{{ $person['club'] }}</span>
                    @if($clubHref)
                        <i class="bi bi-chevron-right club-chev"></i>
                    @endif
                </{{ $clubHref ? 'a' : 'div' }}>
            @else
                {{-- The draft's rule for an athlete with no club behind them. --}}
                <div class="club"><i class="bi bi-people"></i><span>{{ __('personal.event_card_unattached') }}</span></div>
            @endif
        </div>
        {{-- Right column: format badge on top, BIG belt symbol (44px) + rank name under it --}}
        @if(($person['activity'] ?? null) || $belt)
            <div class="side">
                @if($person['activity'] ?? null)
                    <span class="fmt"><i class="bi bi-collection" style="font-size:9px"></i>{{ $person['activity'] }}</span>
                @endif
                @if($belt)
                    <svg width="44" height="44" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                        @if($belt['outline'])
                            {{-- The silhouette under a pale belt, so a white one is
                                 visible on a white card. Drawn only when BeltIcon
                                 measured the colour as pale. --}}
                            <rect x="0.6" y="9.8" width="9.4" height="4.4" rx="1.3" fill="{{ $belt['outline'] }}" stroke="{{ $belt['outline'] }}" stroke-width="1.1" stroke-linejoin="round"></rect>
                            <rect x="14" y="9.8" width="9.4" height="4.4" rx="1.3" fill="{{ $belt['outline'] }}" stroke="{{ $belt['outline'] }}" stroke-width="1.1" stroke-linejoin="round"></rect>
                            <rect x="9.1" y="13.2" width="2.6" height="9" rx="1" transform="rotate(15 10.4 13.8)" fill="{{ $belt['outline'] }}" stroke="{{ $belt['outline'] }}" stroke-width="1.1" stroke-linejoin="round"></rect>
                            <rect x="12.4" y="13.2" width="2.6" height="7.2" rx="1" transform="rotate(-7 13.7 13.8)" fill="{{ $belt['outline'] }}" stroke="{{ $belt['outline'] }}" stroke-width="1.1" stroke-linejoin="round"></rect>
                            <rect x="8.6" y="8.8" width="6.8" height="6" rx="2" fill="{{ $belt['outline'] }}" stroke="{{ $belt['outline'] }}" stroke-width="1.1" stroke-linejoin="round"></rect>
                            <rect x="11.1" y="8.8" width="1.8" height="6" rx="0.3" fill="{{ $belt['outline'] }}" stroke="{{ $belt['outline'] }}" stroke-width="1.1" stroke-linejoin="round"></rect>
                        @endif
                        <rect class="belt-fill" x="0.6" y="9.8" width="9.4" height="4.4" rx="1.3" fill="{{ $belt['band'] }}"></rect>
                        <rect class="belt-fill" x="14" y="9.8" width="9.4" height="4.4" rx="1.3" fill="{{ $belt['band'] }}"></rect>
                        <rect class="belt-fill" x="9.1" y="13.2" width="2.6" height="9" rx="1" transform="rotate(15 10.4 13.8)" fill="{{ $belt['band'] }}"></rect>
                        <rect class="belt-fill" x="12.4" y="13.2" width="2.6" height="7.2" rx="1" transform="rotate(-7 13.7 13.8)" fill="{{ $belt['band'] }}"></rect>
                        <rect class="belt-knot" x="8.6" y="8.8" width="6.8" height="6" rx="2" fill="{{ $belt['knot'] }}"></rect>
                        <rect class="belt-fill" x="11.1" y="8.8" width="1.8" height="6" rx="0.3" fill="{{ $belt['band'] }}"></rect>
                    </svg>
                    <span class="belt-name">{{ $belt['label'] }}</span>
                @endif
            </div>
        @endif

        {{-- THE HOST'S OWN CONTROL, on the trailing edge. Empty on every card
             that does not pass one, so the draft's layout is untouched. It is
             where the organiser's roster hangs its selection checkbox: right
             in English, left in Arabic, reading as the row's answer rather
             than its bullet. --}}
        @if(isset($trailing))
            <div class="ptrail">{{ $trailing }}</div>
        @endif
    </div>
    {{-- Divisions: tap navigates to that group's draw — when the organiser has
         let the draw out. When they have not, the chip is not a door and says
         so on tap rather than landing on an empty board; the `closed` class is
         the only thing added to the draft here, and it changes nothing but the
         cursor. --}}
    @if($divisions)
        <div class="divs">
            @foreach($divisions as $d)
                {{-- Three states, not two. A chip with somewhere to go is a
                     door; a chip whose draw is WITHHELD says when it opens; and
                     a chip on a surface that simply does not offer the draw is
                     SILENT — it is a label. The third was toasting "the draw is
                     not out yet" at an organiser looking at a draw that was
                     very much out, which is worse than saying nothing. --}}
                @php $dTap = $drawOpen ? 'onDivisionTap(this)' : ($drawNote ? 'onDrawClosed()' : null); @endphp
                <div class="{{ $drawOpen ? 'div' : 'div closed' }}" data-division-id="{{ $d['id'] }}"
                     @if($dTap) onclick="event.stopPropagation();{{ $dTap }}" @endif><i class="bi bi-rulers"></i><span>{{ $d['name'] }}</span></div>
            @endforeach
        </div>
    @endif
</article>


{{-- ===========================================================================
     THE COMPONENT'S OWN STYLESHEET — drafts/participant-card.html, verbatim.

     Emitted ONCE however many cards a page draws, by the card itself: a
     component that needs the host to remember a stylesheet is not standalone.

     Copied rather than translated into Tailwind, on purpose and on instruction:
     the draft IS the design contract, and the platform's utility bundle is
     PREBUILT (a class nobody used before has no CSS in public/build and renders
     as nothing). Plain rules in a page stylesheet cannot fail that way.

     The draft's page scaffolding — `body{background}` and `.wrap` — is NOT
     here: that was its demo harness, and this card is dropped into a host's
     own shell.
=========================================================================== --}}
@once
@push('styles')
<style>

:root{
  --male:#3b82f6; --male-chip-bg:#eff6ff; --male-chip-fg:#2563eb;
  --female:#ec4899; --female-chip-bg:#fdf2f8; --female-chip-fg:#db2777;
  --ok-fg:#15803d; --ok-bg:#f0fdf4; --ok-border:#bbf7d0;
  --warn-fg:#b45309; --warn-bg:#fffbeb; --warn-border:#fde68a;
  --danger-fg:#dc2626; --danger-bg:#fef2f2; --danger-border:#fecaca;
  --ink:#0f172a; --muted:#64748b; --line:#eef0f3;
}

/* ===== Card shell =====
   data-gender="male|female" on .pcard drives the left stripe AND the
   category chip / gender icon / division icon colors (blue vs pink). */
.pcard{position:relative;background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 1px 2px rgba(15,23,42,.05);overflow:hidden;display:flex;flex-direction:column;cursor:pointer;transition:box-shadow .15s,transform .15s}
.pcard:hover{box-shadow:0 4px 14px rgba(15,23,42,.10);transform:translateY(-1px)}
.pcard .gbar{position:absolute;left:0;top:0;bottom:0;width:6px;background:var(--male)}
.pcard[data-gender="female"] .gbar{background:var(--female)}
.pcard .body{display:flex;gap:12px;padding:10px 14px 8px 18px}

/* ===== Photo =====
   HARD REQUIREMENT — the member photo is ALWAYS 3:4 (width:height), taller
   than wide. Fixed 66x88 box, overflow hidden, object-fit:cover. Never let it
   flex, never let the ratio drift regardless of source image. */
.pcard .photo{position:relative;flex-shrink:0;width:66px;height:88px;border-radius:10px;overflow:hidden;background:#e2e8f0;cursor:zoom-in;transition:opacity .15s}
.pcard .photo:hover{opacity:.85}
.pcard .photo img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center top}

/* ===== Info column =====
   min-width:0 + ellipsis on every text node is what prevents warping. Every
   field except name+category is optional; rows collapse when data is missing. */
.pcard .info{flex:1;min-width:0;display:flex;flex-direction:column;gap:6px;justify-content:center}
.pcard .row{display:flex;align-items:center;gap:6px;min-width:0;flex-wrap:wrap}
.pcard .row.nowrap{flex-wrap:nowrap}
.pcard .name{font-weight:700;font-size:15px;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.pcard .flag{height:13px;width:17px;border-radius:3px;flex-shrink:0;object-fit:cover}

/* ===== Right column: format badge + big belt symbol ===== */
.pcard .side{flex-shrink:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;padding-left:4px}
.pcard .fmt{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:700;background:var(--ink);color:#fff;white-space:nowrap}
.pcard .belt-name{font-size:10px;font-weight:700;color:var(--muted)}

/* ===== Enrollment status pill =====
   readiness = payment AND weigh-in — two facts, each icon next to the word it
   describes. pending -> red; paid -> amber; ready -> green. */
.status{display:inline-flex;align-items:center;gap:6px;padding:2px 9px;border-radius:999px;font-size:10.5px;font-weight:700;border:1px solid;white-space:nowrap}
.status>span{display:inline-flex;align-items:center;gap:4px}
.status .dot{opacity:.45}
.status[data-status="pending"]{color:var(--danger-fg);background:var(--danger-bg);border-color:var(--danger-border)}
.status[data-status="paid"]{color:var(--warn-fg);background:var(--warn-bg);border-color:var(--warn-border)}
.status[data-status="ready"]{color:var(--ok-fg);background:var(--ok-bg);border-color:var(--ok-border)}

.chip{padding:2px 8px;border-radius:999px;font-size:10px;font-weight:700;white-space:nowrap;background:var(--male-chip-bg);color:var(--male-chip-fg)}
.pcard[data-gender="female"] .chip{background:var(--female-chip-bg);color:var(--female-chip-fg)}
.meta{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;color:var(--ink)}
.meta .bi{color:var(--male-chip-fg);font-size:11px}
.pcard[data-gender="female"] .meta .bi{color:var(--female-chip-fg)}

/* ===== Weight =====
   declared but NOT verified -> amber; verified at the official weigh-in ->
   green with bi-patch-check-fill. */
.weight{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;color:#d97706}
.pcard[data-weight-verified="true"] .weight{color:var(--ok-fg)}
.pcard:not([data-weight-verified="true"]) .weight .verified{display:none}

.club{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);min-width:0}
/* A club row that GOES somewhere. Underlined on hover rather than coloured: it
   sits under a name and two badges, and a blue line there would read as the
   most important thing on the card. The chevron is the quiet affordance. */
.club.is-door{text-decoration:none;color:inherit;cursor:pointer}
.club.is-door:hover span{text-decoration:underline}
.club .club-chev{font-size:8px;opacity:.45;flex:none}
.club img{width:16px;height:16px;object-fit:contain;flex-shrink:0}
.club span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* ===== Divisions footer band =====
   one chip per enrolled division, auto-fill grid so chips never warp; text
   ellipsizes, never wraps. The band is absent when there are no divisions. */
.divs{border-top:1px solid #f1f5f9;padding:8px 14px 10px 18px;display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:5px}
.divs .div{display:flex;align-items:center;gap:6px;background:#f8fafc;border:1px solid #f1f5f9;border-radius:8px;padding:4px 8px;font-size:11px;font-weight:600;color:#334155;min-width:0;cursor:pointer;transition:background .15s,border-color .15s}
.divs .div:hover{background:#eff6ff;border-color:#bfdbfe}
/* Not a door: the draw is not out yet. The only rule added to the draft's
   stylesheet, and it changes nothing but the cursor and the hover. */
.divs .div.closed{cursor:default}
.divs .div.closed:hover{background:#f8fafc;border-color:#f1f5f9}
.divs .div .bi{color:var(--male-chip-fg);font-size:10px;flex-shrink:0}
.pcard[data-gender="female"] .divs .bi{color:var(--female-chip-fg)}
.divs .div span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* ===== Photo lightbox =====
   photo tap (both roles) opens the full photo — still 3:4 — over a black tint;
   tapping ANYWHERE closes it. */
.lightbox{position:fixed;inset:0;z-index:60;background:rgba(0,0,0,.78);display:none;align-items:center;justify-content:center;cursor:zoom-out}
.lightbox.open{display:flex}
.lightbox img{width:min(66vh,84vw);aspect-ratio:3/4;object-fit:cover;object-position:center top;border-radius:14px;box-shadow:0 24px 64px rgba(0,0,0,.5)}

/* ===== Organizer bottom sheet =====
   ONLY shown when the viewer runs the event and they tap the card. Everyone
   else goes straight to the member's public profile. */
/* The host's trailing control (the roster's select checkbox). Centred down
   the card's trailing edge and never squeezed. */
.pcard .ptrail{display:flex;align-items:center;flex-shrink:0;padding-inline-start:4px}
/* ⚠️ The sheet's SHELL — backdrop, panel, the rise animation — is no longer
   here. It moved into `x-action-sheet` on 2026-09-17 so the next sheet does not
   have to be typed out again, and the class names went from `.sheet*` to
   `.as-*` with it. What stays below is only what belongs to the ENROL page this
   card puts inside that sheet. */
.sec{font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;padding:6px 0 4px}
/* enroll view — compatible groups solid border, incompatible groups DASHED with
   a reason line. BOTH remain checkable: organizers may override compatibility.
   Already-enrolled groups arrive pre-checked + ENROLLED tag. */
.group{display:flex;align-items:center;gap:10px;padding:10px;border:1px solid #e2e8f0;background:#fff;border-radius:12px;cursor:pointer;margin-bottom:5px;transition:background .15s,border-color .15s}
.group.incompat{border-style:dashed}
.group.checked{border-color:#93c5fd;background:#eff6ff}
.group .box{width:20px;height:20px;border-radius:6px;flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;border:2px solid #cbd5e1;background:#fff;color:#fff;font-size:12px}
.group.checked .box{border-color:#2563eb;background:#2563eb}
.group .lbl{min-width:0;font-size:13px;font-weight:600;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.group .why{font-size:10.5px;color:var(--warn-fg)}
/* What tells two identically-named divisions apart. Quieter than the name and
   quieter than a refusal — it is a label, not a verdict. */
.group .detail{font-size:10.5px;color:var(--muted);font-weight:600}
.group .tag{margin-left:auto;flex-shrink:0;font-size:9px;font-weight:800;letter-spacing:.05em;color:var(--ok-fg);background:var(--ok-bg);border:1px solid var(--ok-border);border-radius:999px;padding:2px 7px;text-transform:uppercase}
</style>
@endpush
@endonce

{{-- ===========================================================================
     THE COMPONENT'S OWN RUNTIME.

     Also once: the photo lightbox, the tap behaviour, the scroll memory and —
     only for a reader who manages the event — the organiser's action sheet.

     ⚠️ `@once` renders on the FIRST card of a page, so the organiser's sheet is
     emitted when the first card is a manageable one. That is the truth on every
     host there is: `manage` is a fact about the READER, not about the row, so
     it is the same for every card on a page. A host that ever varies it per
     card must render a manageable card first, and would be better served by
     asking for this component to take the sheet as an explicit input.
=========================================================================== --}}
@once
@push('scripts')

{{-- Photo lightbox: black tint, tap anywhere to close --}}
<div class="lightbox" id="lightbox" onclick="this.classList.remove('open')"><img id="lightbox-img" src="" alt=""></div>

@if($manage)
    {{-- ===== The organiser's sheet =====

         `x-action-sheet` draws it. It was 90 lines of markup here until
         2026-09-17, when the owner asked for the sheet to be reusable — the
         band, the rows, the open/close and the pages are the component's now,
         and this file supplies only what is true about an ENTRANT: which rows,
         which glyph, and a pencil in the band.

         Rendered ONLY for whoever runs the event: the shape of an organiser's
         tools has no business in a stranger's page source, and every endpoint
         behind it re-authorises anyway.

         ⚠️ `@once` renders on the FIRST card of a page, so the sheet is emitted
         when the first card is a manageable one. That is the truth on every
         host there is: `manage` is a fact about the READER, not about the row.
         The sheet is addressed as `participant`, so a page carrying a second
         `x-action-sheet` of its own does not collide with this one. --}}
    @php
        $cardActions = [
            [
                'icon' => 'bi-clipboard2-check-fill',
                'label' => __('personal.event_card_action_verify'),
                'hint' => __('personal.event_card_action_verify_hint'),
                'onclick' => 'onVerify()',
            ],
            [
                'icon' => 'bi-diagram-3',
                'label' => __('personal.event_card_action_enroll'),
                'hint' => __('personal.event_card_action_enroll_hint'),
                'onclick' => 'showEnroll()',
            ],
            [
                'icon' => 'bi-person-vcard',
                'label' => __('personal.event_card_action_profile'),
                'hint' => __('personal.event_card_action_profile_hint'),
                'onclick' => 'onProfile()',
            ],
        ];

        /* Taking them off the list: last, and in red, because it is the one row
           here that cannot be undone by tapping again. */
        if ($canRemove) {
            $cardActions[] = [
                'icon' => 'bi-person-dash-fill',
                'label' => __('personal.event_people_action_remove'),
                'hint' => __('personal.event_people_action_remove_hint'),
                'onclick' => 'onRemoveEntry()',
                'tone' => 'danger',
            ];
        }
    @endphp

    <x-action-sheet id="participant" :color="$sheetColor"
                    icon="bi-person-arms-up"
                    :subtitle="__('personal.event_card_sheet_subtitle')"
                    :actions="$cardActions">

        @if($canEdit)
            {{-- Correcting them, in the band. A pencil beside the ✕ rather than
                 a fifth row: the rows are places to GO, and fixing a misspelt
                 name is something done TO the person the band is naming. --}}
            <x-slot:controls>
                <button type="button" onclick="onEditEntry()"
                        aria-label="{{ __('events::events.entry_edit_title_theirs') }}"
                        title="{{ __('events::events.entry_edit_title_theirs') }}"
                        class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                    <i class="bi bi-pencil"></i>
                </button>
            </x-slot:controls>
        @endif

        {{-- ===== Enrolling them in groups =====
             The second face of the same sheet. Its rows keep their own `.group`
             styling: they are a CHECKLIST built in JS, not navigation, and
             dressing them as action rows would say they lead somewhere. --}}
        <x-slot:pages>
            {{-- No Back control: removed on 2026-09-18 at the owner's request.
                 Cancel in the footer leaves, and the band's ✕ closes — a third
                 way out of a two-button sheet was one too many.

                 `window.showActions()` is kept: it is part of this sheet's
                 public surface (`ActionSheet.actions`), and nothing in the
                 markup calls it now. --}}
            <div data-sheet-page="enroll" class="px-4 pt-3">
                <div id="groups-state" class="text-[12px] text-muted-foreground pb-2"></div>

                <div class="sec" id="sec-compat" style="color:var(--ok-fg);display:none">{{ __('personal.event_card_groups_compatible') }}</div>
                <div id="groups-compat"></div>
                <div class="sec" id="sec-incompat" style="color:var(--warn-fg);display:none"><i class="bi bi-exclamation-triangle" style="font-size:10px"></i> {{ __('personal.event_card_groups_incompatible') }}</div>
                <div id="groups-incompat"></div>

                <div class="flex items-center gap-2 pt-3"
                     style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">
                    <button type="button" onclick="closeSheet()"
                            class="m-press flex-1 h-11 rounded-xl border border-gray-200 bg-white text-foreground text-xs font-black">{{ __('personal.event_card_cancel') }}</button>
                    {{-- Save is the wider of the two. `flex-[2]` is NOT in the
                         prebuilt Tailwind bundle — it would render as nothing
                         and the two buttons would come out equal — so the ratio
                         is stated inline. --}}
                    <button type="button" id="btn-save" onclick="onSaveEnrollment()"
                            class="m-press h-11 rounded-xl text-white text-xs font-black disabled:opacity-60"
                            style="flex: 2 1 0%; background: {{ $sheetColor }};">{{ __('personal.event_card_save') }}</button>
                </div>
            </div>
        </x-slot:pages>
    </x-action-sheet>
@endif

<script>
/* ============================================================================
   The card's behaviour — drafts/participant-card.html, wired to real routes.

   Behavior matrix, unchanged from the draft:
     PARTICIPANT: card tap -> member public profile (SEALED, inside this event)
     ORGANISER:   card tap -> bottom sheet (verify / enroll / profile)
     BOTH:        photo tap -> lightbox; division tap -> that group's draw

   ⚠️ Every destination is an address this surface may actually reach. The
   public event page is a sealed app (SealEventPage) and `events.public.section`
   is not one of the mirrored routes, so a platform URL here would walk a reader
   straight out of the event. The profile is `sealed.people.show`; the draw is
   this event's own section page; the two organiser endpoints are fetched, never
   navigated to, so they stay on the platform where they are defined.
============================================================================ */
(function () {
    var ROLE = @js($manage ? 'organizer' : 'participant');
    var DRAW_URL = @js($drawUrl);

    /* ---------------- Shared: the photo ---------------- */
    window.openLightbox = function (src) {
        /* `s=full` is not a known rendition, and the file door serves the
           ORIGINAL for a size it does not know — which is exactly what a
           lightbox wants (App\Support\Thumbnails::knows). */
        document.getElementById('lightbox-img').src = String(src).replace('s=card', 's=full');
        document.getElementById('lightbox').classList.add('open');
    };

    /* ---------------- Shared: a division ----------------
       The chip opens the group's own bracket. It is only WIRED to this when the
       draw is out for the public (the organiser's `draw_reveal` plus the
       calendar, answered server-side); otherwise the chip calls onDrawClosed()
       and says when it opens. */
    window.onDivisionTap = function (el) {
        var id = el.dataset.divisionId;
        if (! id) return;
        location.assign(DRAW_URL + '?division=' + encodeURIComponent(id));
    };

    window.onDrawClosed = function () {
        window.showToast('info', @js($drawNote ?: __('events::events.draw_hidden_msg')));
    };

    /* ---------------- The card itself ---------------- */
    window.onCardTap = function (card) {
        if (ROLE === 'organizer') { openSheet(card); return; }

        /* A card with nowhere to go is not a door. The server decides: a
           profile that would 404 for this reader is never linked (see the
           section's $pHref). */
        var href = card.dataset.href;
        if (! href) return;

        /* Remember WHERE THEY WERE before leaving. A competitor scrolling a
           two-hundred-name list, tapping a rival and coming back used to land
           at the top and have to find their place again. The profile's back
           control is an address rather than a gesture — deliberately, and for
           reasons written on that button — so the list restores itself. */
        rememberPlace(card);
        location.assign(href);
    };

    window.onCardKey = function (ev, card) {
        if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); window.onCardTap(card); }
    };

    /* ---------------- Coming back to the same place ----------------

       Kept per EVENT and in sessionStorage: it is about one visit to one list,
       and it must not outlive the tab or follow the reader to another event.

       Restored in two steps because a list is not guaranteed to be identical
       when they return — somebody may have entered while they were reading.
       The saved offset is exact when nothing moved; the card itself is the
       anchor when something did. */
    var PLACE_KEY = @js('tk:participants:'.($placeKey ?: $event ?: 'list'));

    function rememberPlace(card) {
        try {
            sessionStorage.setItem(PLACE_KEY, JSON.stringify({
                y: window.scrollY || document.documentElement.scrollTop || 0,
                id: card.dataset.memberId || card.dataset.href || '',
                at: Date.now(),
            }));
        } catch (e) { /* private mode: the list simply opens at the top */ }
    }

    function restorePlace() {
        var raw = null;
        try { raw = sessionStorage.getItem(PLACE_KEY); sessionStorage.removeItem(PLACE_KEY); }
        catch (e) { return; }
        if (! raw) return;

        var place;
        try { place = JSON.parse(raw); } catch (e) { return; }

        /* Only a return, not a fresh visit an hour later that happens to find
           the key still there. */
        if (! place || (Date.now() - (place.at || 0)) > 30 * 60 * 1000) return;

        window.scrollTo(0, place.y || 0);

        /* If the list moved under them, the card wins over the number. */
        if (! place.id) return;

        var card = Array.from(document.querySelectorAll('.pcard')).find(function (el) {
            return el.dataset.memberId === place.id || el.dataset.href === place.id;
        });
        if (! card) return;

        var box = card.getBoundingClientRect();
        if (box.top < 0 || box.bottom > window.innerHeight) {
            card.scrollIntoView({ block: 'center' });
        }
    }

    /* Before paint where the browser allows it, so the list never appears at
       the top and then jumps. */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', restorePlace);
    } else {
        restorePlace();
    }

    document.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Escape') return;
        document.getElementById('lightbox')?.classList.remove('open');
        /* The sheet closes itself on Escape — `x-action-sheet` owns that now,
           for every sheet on the page. Only the lightbox is ours. */
    });

    if (ROLE !== 'organizer') return;

    /* ======================= ORGANISER ONLY ======================= */

    /* WHICH SHEET. `x-action-sheet` namespaces every id by this, so a host
       page may carry sheets of its own without either reaching into the other. */
    var SHEET = 'participant';

    var currentCard = null;
    var GROUPS = [];
    var ENTRY = null;
    var LOCKED = false;

    var PERSON_DIVISIONS = @js($event ? route('me.events.person.divisions', ['event' => $event, 'uuid' => '__UUID__']) : '');
    var DIVISION_MEMBERS = @js($event ? route('me.events.divisions.members', ['event' => $event, 'division' => 0]) : '');
    /* ⚠️ `?from=participants`, exactly as a participant's own card carries it.
       Without it the profile's back control falls through to its default for
       somebody who manages the event — the organiser's entry list at
       /admin/people — and an organiser who tapped a name on THIS list was
       dropped on a different one (reported 2026-09-16). The key names the page
       they actually came from; it is read by PeopleController::sealedBackUrl,
       and it is a KEY rather than a URL because a destination taken from the
       query string is an open redirect with extra steps. */
    var PROFILE_URL = @js($event ? route('sealed.people.show', ['event' => $event, 'uuid' => '__UUID__']).'?from=participants' : '');

    var csrf = function () { return document.querySelector('meta[name=csrf-token]')?.content || ''; };

    /* ===== Opening the sheet =====
       The band's three facts come off the CARD that was tapped — no round trip,
       and no second source of truth for somebody's name. `x-action-sheet` owns
       the opening itself; this only says who it is about. */
    window.openSheet = function (card) {
        currentCard = card;

        var img = card.querySelector('.photo img');
        var club = card.querySelector('.club span');

        window.ActionSheet.open(SHEET, {
            /* A card with no picture shows the silhouette rather than a broken
               image. The card's own answer is used: it draws the gendered
               fallback avatar when nothing is on file, and that is still an
               image element.

               ⚠️ No `<x-…>` component tag may appear anywhere in this file, not
               even inside a JS comment: Blade parses component tags before PHP
               sees the script, so one written here compiles into a component
               render in the middle of a <script> and the whole view dies with a
               misleading "expecting endif" at end of file. It did, once. */
            photo: (img && img.getAttribute('src')) ? img.src : null,
            title: card.querySelector('.name').textContent.trim(),
            /* Their club under the name. Falls back to the sheet's own
               subtitle, so the line is never empty. */
            subtitle: (club && club.textContent.trim())
                ? club.textContent.trim()
                : @js(__('personal.event_card_sheet_subtitle')),
        });
    };

    /* The three names the sheet's own markup calls, kept as thin wrappers: they
       are what `onclick=` in the enrol page says, and renaming them would be a
       change to that markup for no gain. */
    window.closeSheet = function () { window.ActionSheet.close(SHEET); };
    window.showActions = function () { window.ActionSheet.actions(SHEET); };

    /* THIS PERSON's verification sheet, OVER THIS PAGE.
       It used to navigate to the organiser's entry list with `?entry=` and let
       that page open the sheet — a second list of everybody, opening over the
       list already being read. Cancelled on 2026-09-16: the desk is hosted here
       (entry/public/sections/partials/verification-desk) and `open-verification`
       is the event it already listens for, the same one the member list's own
       action menu dispatches. No navigation, nothing to come back from. */
    window.onVerify = function () {
        var entry = currentCard.dataset.entry;

        if (! entry) {
            window.showToast('error', @js(__('personal.event_card_verify_missing')));
            return;
        }

        closeSheet();
        window.dispatchEvent(new CustomEvent('open-verification', { detail: { entry: Number(entry) } }));
    };
    /* CORRECT THIS ENTRY. Same shape as onVerify: the component closes its own
       sheet and names the entry on the window; whatever editor the host renders
       is the host's. Nothing is fetched here, so there is nothing to fail and
       no second opinion about who may edit — the endpoint behind the host's
       editor re-authorises, as every endpoint does. */
    window.onEditEntry = function () {
        var entry = currentCard.dataset.entry;

        if (! entry) {
            window.showToast('error', @js(__('personal.event_card_verify_missing')));
            return;
        }

        var club = currentCard.querySelector('.club span');

        closeSheet();
        window.dispatchEvent(new CustomEvent('participant-edit', {
            detail: {
                entry: Number(entry),
                uuid: currentCard.dataset.memberId || null,
                name: currentCard.querySelector('.name').textContent.trim(),
                club: club ? club.textContent.trim() : null,
            },
        }));
    };

    /* TAKE THEM OFF THE LIST. The host confirms and calls its own endpoint —
       a removal is its list to patch and its realtime to push, and a component
       that deleted an entry on a host's behalf would be doing the one thing it
       must not (CLAUDE.md → RULE #1, and the security rule that every
       destructive action is authorised where it is performed). */
    window.onRemoveEntry = function () {
        var entry = currentCard.dataset.entry;

        if (! entry) {
            window.showToast('error', @js(__('personal.event_card_verify_missing')));
            return;
        }

        closeSheet();
        window.dispatchEvent(new CustomEvent('participant-remove', {
            detail: {
                entry: Number(entry),
                name: currentCard.querySelector('.name').textContent.trim(),
            },
        }));
    };

    window.onProfile = function () {
        var uuid = currentCard.dataset.memberId;
        /* Remember the place first: the organiser comes back to this list the
           same way a competitor does, and to the same spot. */
        rememberPlace(currentCard);
        closeSheet();
        location.assign(PROFILE_URL.replace('__UUID__', uuid));
    };

    /* ---------------- Enrol in groups ----------------
       The list, its compatibility and its reasons are all computed SERVER-side
       (PersonalEventController::personDivisions, DivisionRange::judge). The
       sheet never re-derives who fits what — that is how two screens start
       disagreeing. */
    window.showEnroll = function () {
        window.ActionSheet.page(SHEET, 'enroll');
        loadGroups();
    };

    function state(msg) { document.getElementById('groups-state').textContent = msg || ''; }

    function loadGroups() {
        GROUPS = [];
        renderGroups();
        state(@js(__('personal.event_card_groups_loading')));

        fetch(PERSON_DIVISIONS.replace('__UUID__', currentCard.dataset.memberId), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
        })
        .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) {
            if (! r.ok || ! d.success) throw new Error(d.message || @js(__('personal.event_card_groups_failed')));
            return d;
        }); })
        .then(function (d) {
            GROUPS = d.groups || [];
            ENTRY = d.entry;
            LOCKED = !! d.locked;
            /* The server says whether this is closed, and since 2026-09-18 it
               never is — an organiser correcting their groups mid-competition
               is the case this sheet exists for. `note` is the advisory that
               replaced the lock: it says where the change LANDS (the bench, not
               the drawn bracket) instead of refusing to make it. */
            state(LOCKED ? @js(__('personal.event_card_groups_locked')) : (d.note || ''));
            document.getElementById('btn-save').disabled = LOCKED;
            renderGroups();
        })
        .catch(function (err) { state(err.message); });
    }

    function renderGroups() {
        var c = document.getElementById('groups-compat'), n = document.getElementById('groups-incompat');
        c.innerHTML = ''; n.innerHTML = '';

        GROUPS.forEach(function (g) {
            var el = document.createElement('div');
            el.className = 'group' + (g.compatible ? '' : ' incompat') + (g.enrolled ? ' checked' : '');
            el.dataset.groupId = g.id;

            var box = document.createElement('span');
            box.className = 'box';
            box.innerHTML = '<i class="bi bi-check-lg"></i>';

            var mid = document.createElement('span');
            mid.style.minWidth = '0';
            var lbl = document.createElement('span');
            lbl.className = 'lbl';
            lbl.textContent = g.label;          /* textContent: an organiser
                                                   named this group, and a name
                                                   is never markup. */
            mid.appendChild(lbl);

            /* WHICH of two divisions with the same name this is — bounds and
               belt, sent only when the name is ambiguous (see
               PersonalEventController::personDivisions). Without it the picker
               showed "Group F (+80) Adult [Gi]" twice, one compatible and one
               refused on belt, with nothing to tell them apart.
               textContent: the organiser named this, and a name is never markup. */
            if (g.detail) {
                var det = document.createElement('div');
                det.className = 'detail';
                det.textContent = g.detail;
                mid.appendChild(det);
            }

            if (g.reason) {
                var why = document.createElement('div');
                why.className = 'why';
                why.textContent = g.reason;
                mid.appendChild(why);
            }

            el.appendChild(box);
            el.appendChild(mid);

            if (g.enrolled) {
                var tag = document.createElement('span');
                tag.className = 'tag';
                tag.textContent = @js(__('personal.event_card_enrolled'));
                el.appendChild(tag);
            }

            el.onclick = function () {
                if (LOCKED) return;
                el.classList.toggle('checked');
                updateSaveCount();
            };

            (g.compatible ? c : n).appendChild(el);
        });

        document.getElementById('sec-compat').style.display = GROUPS.some(function (g) { return g.compatible; }) ? '' : 'none';
        document.getElementById('sec-incompat').style.display = GROUPS.some(function (g) { return ! g.compatible; }) ? '' : 'none';
        updateSaveCount();
    }

    function updateSaveCount() {
        document.getElementById('btn-save').textContent =
            @js(__('personal.event_card_save_n', ['n' => ':n'])).replace(':n', document.querySelectorAll('.group.checked').length);
    }

    /* Saved group by group, through the endpoint that already owns this write
       (me.events.divisions.members): one verb, one authority, one set of rules
       about what being in a group does — a tick makes a second entry rather
       than moving one, an untick only ever removes from THAT division. Only
       what CHANGED is sent. */
    function onSave() {
        if (LOCKED) return;

        var picked = {};
        document.querySelectorAll('.group.checked').forEach(function (g) { picked[g.dataset.groupId] = true; });

        var calls = GROUPS.filter(function (g) {
            return !! picked[g.id] !== !! g.enrolled;
        }).map(function (g) {
            var body = picked[g.id] ? { add: [ENTRY] } : { remove: [g.registration] };

            return fetch(DIVISION_MEMBERS.replace(/\/0\/members$/, '/' + g.id + '/members'), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
                credentials: 'same-origin',
                body: JSON.stringify(body),
            }).then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) {
                if (! r.ok || ! d.success) throw new Error(d.message || @js(__('personal.event_show_action_failed')));
                return d;
            }); });
        });

        if (! calls.length) { closeSheet(); return; }

        document.getElementById('btn-save').disabled = true;

        Promise.all(calls)
            .then(function () {
                window.showToast('success', @js(__('personal.event_card_enrollment_saved')));

                /* The division band is server-rendered, so the card cannot
                   patch itself honestly. The host is told first — a page that
                   knows how to re-read its own list can listen and do that —
                   and the reload is what happens when nobody does. */
                var handled = ! window.dispatchEvent(new CustomEvent('participant-enrolled', {
                    cancelable: true,
                    detail: { uuid: currentCard.dataset.memberId },
                }));

                if (! handled) location.reload();
            })
            .catch(function (err) {
                window.showToast('error', err.message);
                document.getElementById('btn-save').disabled = false;
            });
    }

    window.onSaveEnrollment = onSave;
})();
</script>
@endpush
@endonce
