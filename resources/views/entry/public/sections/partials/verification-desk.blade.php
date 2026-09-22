{{--
    The verification desk, hosted on the PUBLIC participants page.

    ── One desk, two pages ────────────────────────────────────────────────────

    This includes `personal.partials.event-people` in its sheet-only shape —
    the same sheet the organiser's own entry list opens, markup for markup. It
    is not a copy and must never become one: the sheet decides which official
    may hold a body weight and which may see a bank receipt, and a rule about
    disclosure that exists twice is a rule that will eventually disagree with
    itself.

    Until 2026-09-16 the participant card reached that sheet by NAVIGATING to
    the organiser's entry list with a deep link — a second list of everybody,
    opening over the list they were already reading. Now the sheet opens over
    the card that was tapped, and the desk moved to the reader rather than the
    reader to the desk.

    ── What this partial is for ───────────────────────────────────────────────

    The sheet reads a member-shaped payload and this page has the public one, so
    `$e` here is the desk payload assembled by PublicEventController::deskFor()
    and handed in as `e`. Shadowing the page's own `$e` is the whole point of
    doing it in a partial: the host script and the sheet then read one payload,
    the right one, and the page around them keeps its own.

    Expects: e · canWeigh · canPay · canManage · payment · feeTypes · feeSingle
             · feeCurrency  (all from deskFor()).
--}}
@php
    /* `partials.event-show-script` is the shared event-show Alpine state, and it
       is the scope the sheet's actions live in. It is written for the event
       DETAIL page, so the handful of scalars that page passes it are defaulted
       here — the same list `personal/event-people` defaults for the same
       reason. This page needs the sheet, not the join flow. */
    $hasTicket = false;
    $byQual = false;
    $banned = false;
    $eligReason = null;
    $finance = null;
    $entriesOpen = false;
    $entriesNote = null;
    $manual_results = false;
    $stage = null;
    $representing = null;
    $pPaid = false;
    $ticketPaid = false;
    $whyNot = null;
@endphp

<div @include('partials.event-show-script')>
    @include('personal.partials.event-people', [
        'sheetOnly' => true,
        'hasTicket' => false,
        'byQual' => [],
    ])
</div>
