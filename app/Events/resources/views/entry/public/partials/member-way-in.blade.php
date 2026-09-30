{{--
    The way in, for a MEMBER — the member page's own flow, on the poster.

    Rendered by entry/public/mobile only when `$asMember` (config
    `events.unified_page`, /me/events/{uuid}). Carried over from
    personal/mobile/event-show: the participant and spectator rows, the
    "taken off at the deadline" notice and the payment-proof and join sheets —
    everything that page offered a member and the poster did not. (Results
    are the shared results SECTION for every reader, so the member page's
    results sheet did not come across.) It runs inside the member Alpine
    scope the poster opens (partials/event-show-script), so startJoin() and
    explainIneligible() answer.

    Where the public door is the better answer it hands over to it
    (partials/way-in): "my entry" for somebody who entered through the public
    form or holds a participant place on a published event, and "enter" for a
    member the member join cannot take (not eligible here, but the organiser
    accepts requests from anybody).

    Expects from the poster: `$e` (poster payload), `$mine`, `$posterPublic`,
    `$memberEvent`, `$memberScope`, `$mCanJoin`, `$mLocked`, `$mByQual`,
    `$mPaid`, `$mHasTicket`, `$mTicketPaid`; from the controller the member
    page's own keys (`$canCompete`, `$excludedAt`, `$eligReason`, …).
--}}
@php
    $poster = $e;
    // Below this line `$e` is the MEMBER payload, as every member partial expects.
    $e = $memberEvent;

    $handOver = $posterPublic && (
        $mine === 'pending'
        || ($mine === 'entered' && ($memberEvent['joined'] ?? false))
        || (! $mCanJoin && ! $mLocked && ! ($memberEvent['joined'] ?? false) && ($poster['enrol']['open'] ?? false))
    );
@endphp

{{-- ===== Taken off at the deadline =====
     Said on arrival rather than behind a "Why not?" tap: somebody who expected
     to compete this morning should not have to go looking for the reason. --}}
@if($excludedAt ?? null)
    <div class="px-4 mt-4">
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 flex items-start gap-3">
            <span class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 grid place-items-center flex-shrink-0">
                <i class="bi bi-clock-history text-lg"></i>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-amber-900">{{ __('personal.event_show_deadline_removed') }}</p>
                <p class="text-[12px] text-amber-800 mt-1 leading-snug">{{ $eligReason }}</p>
                <p class="text-[11px] text-amber-700/80 mt-1.5">{{ \App\Support\Cldr::shortDate($excludedAt) }}</p>
            </div>
        </div>
    </div>
@endif

@if($handOver)
    @include('events::entry.public.partials.way-in', ['e' => $poster])
@else
    <div class="px-4 mt-4">
        {{-- Participant. The row IS the way in: it names the thing, its price
             and who it is for. Green once the place is held, amber while the
             fee is due, slate with "Why not?" when it cannot be taken. --}}
        {{-- A host that takes its athletes from CLUBS (a federation's event):
             nobody enters themselves. A club's coach or manager is handed the
             host's own invitation; everybody else is told who enters them. --}}
        @if($hostEntry ?? null)
            @php $hostDoorEnters = (bool) $hostEntry['enters']; @endphp
            <{{ $hostDoorEnters ? 'a' : 'div' }} x-show="!registered" x-cloak
                @if($hostDoorEnters) href="{{ $hostEntry['url'] }}" @endif
                class="{{ $hostDoorEnters ? 'm-press active:scale-[.98] ' : '' }}w-full block rounded-2xl p-4 text-white text-start relative overflow-hidden shadow-lg transition-all"
                style="background: linear-gradient(135deg, {{ $e['color'] }}, #1f2937)">
                <div class="absolute -right-6 -top-6 w-28 h-28 rounded-full bg-white/10"></div>
                <div class="relative flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                        <i class="bi {{ $hostDoorEnters ? 'bi-people-fill' : 'bi-shield-check' }} text-2xl"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-black text-base leading-tight">{{ $hostDoorEnters ? __('events::events.host_entry_manager') : __('events::events.host_entry_title') }}</h3>
                        <p class="text-xs text-white/85 mt-0.5 leading-snug">{{ $hostDoorEnters
                            ? __('events::events.host_entry_manager_body', ['host' => $hostEntry['host']])
                            : __('events::events.host_entry_body', ['host' => $hostEntry['host']]) }}</p>
                    </div>
                    @if($hostDoorEnters)
                        <i class="bi bi-chevron-right rtl:rotate-180 text-white/80 flex-shrink-0"></i>
                    @endif
                </div>
            </{{ $hostDoorEnters ? 'a' : 'div' }}>
        @endif

        <button type="button" id="join-participate"
                x-show="{{ ($hostEntry ?? null) ? 'registered' : 'entriesOpen || registered' }}" x-cloak
                @click="{{ $mCanJoin ? "startJoin('participant')" : 'explainIneligible()' }}"
                :disabled="registered && !feeDue"
                class="m-press w-full block rounded-2xl p-4 text-white text-start relative overflow-hidden
                       shadow-lg transition-all active:scale-[.98] disabled:cursor-not-allowed"
                :style="(going && !feeDue)
                    ? 'background: linear-gradient(135deg, #16a34a, #1f2937)'
                    : going
                    ? 'background: linear-gradient(135deg, #d97706, #1f2937)'
                    : 'background: linear-gradient(135deg, {{ $mCanJoin ? $e['color'] : '#64748b' }}, #1f2937)'">
            <div class="absolute -right-6 -top-6 w-28 h-28 rounded-full bg-white/10"></div>
            <div class="relative flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                    <i class="bi text-2xl" :class="going ? 'bi-check2-circle' : @js($mCanJoin ? 'bi-person-check' : 'bi-info-circle')"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="font-black text-base leading-tight"
                        x-text="!going ? @js($mByQual ? __('personal.event_show_take_part') : __('personal.event_show_join_participant')) : (feeDue ? @js(__('personal.event_show_place_held_fee_due')) : @js(__('personal.event_show_youre_participant')))">{{ $mByQual ? __('personal.event_show_take_part') : __('personal.event_show_join_participant') }}</h3>
                    <p class="text-xs text-white/85 mt-0.5">
                        @if(!($canCompete ?? true)) {{ __('personal.event_show_not_eligible_spectators') }}
                        @elseif($mByQual) {{ __('personal.event_show_reserved_finalists') }}
                        @elseif($mPaid) {{ __('personal.event_show_fee_paid_club') }}
                        @else {{ __('personal.event_show_free_members') }} @endif
                    </p>
                </div>
                <span class="text-sm font-black flex-shrink-0"
                      x-text="!going ? @js($mCanJoin ? $e['participant_fee'] : __('personal.event_show_cta_why')) : (feeDue ? @js(__('personal.event_show_cta_fee_due')) : @js(__('personal.event_show_cta_joined')))">{{ $mCanJoin ? $e['participant_fee'] : __('personal.event_show_cta_why') }}</span>
                <i class="bi bi-chevron-right text-white/80 flex-shrink-0"></i>
            </div>
        </button>

        {{-- Entries closed and no place held: the poster's own closed card, so
             the way in never simply vanishes. --}}
        <div x-show="{{ ($hostEntry ?? null) ? 'false' : '!entriesOpen && !registered' }}" x-cloak
             class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 text-center">
            <i class="bi bi-lock-fill text-xl" style="color: {{ $poster['color'] }}"></i>
            <p class="text-sm font-bold text-foreground mt-2">{{ __('events::events.public_entries_closed') }}</p>
            <p class="text-[12px] text-muted-foreground mt-1 leading-relaxed">{{ ($entriesNote ?? null) ?: (($poster['enrol']['note'] ?? null) ?: __('events::events.public_how_to_enter_body', ['club' => $poster['host']])) }}</p>
        </div>
    </div>
@endif

{{-- Spectator ticket --}}
@if($mHasTicket)
    <div class="px-4">
        <button type="button" @click="startJoin('spectator')"
                :disabled="(registered && !feeDue) || {{ $mLocked ? 'true' : 'false' }}"
                class="m-press mt-2.5 w-full block rounded-2xl p-4 text-white text-start relative overflow-hidden
                       shadow-lg transition-all active:scale-[.98]
                       disabled:cursor-not-allowed disabled:opacity-60"
                :style="(watching && !feeDue)
                    ? 'background: linear-gradient(135deg, #16a34a, #1f2937)'
                    : watching
                    ? 'background: linear-gradient(135deg, #d97706, #1f2937)'
                    : 'background: linear-gradient(135deg, {{ $mTicketPaid ? '#7c3aed' : '#0284c7' }}, #1f2937)'">
            <div class="absolute -right-6 -top-6 w-28 h-28 rounded-full bg-white/10"></div>
            <div class="relative flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                    <i class="bi text-2xl" :class="watching ? 'bi-check2-circle' : 'bi-ticket-perforated'"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="font-black text-base leading-tight"
                        x-text="!watching ? @js(__('personal.event_show_spectator_ticket')) : (feeDue ? @js(__('personal.event_show_place_held_fee_due')) : @js(__('personal.event_show_ticket_booked')))">{{ __('personal.event_show_spectator_ticket') }}</h3>
                    <p class="text-xs text-white/85 mt-0.5"><span x-text="spectators">{{ $e['spectator']['count'] }}</span> {{ __('personal.event_show_watching') }}{{ $mTicketPaid ? ' · '.__('personal.event_show_entry_watch_matches') : ' · '.__('personal.event_show_free_to_watch') }}</p>
                </div>
                <span class="text-sm font-black flex-shrink-0"
                      x-text="!watching ? @js($e['spectator']['fee']) : (feeDue ? @js(__('personal.event_show_cta_fee_due')) : @js(__('personal.event_show_cta_booked')))">{{ $e['spectator']['fee'] }}</span>
                <i class="bi bi-chevron-right text-white/80 flex-shrink-0"></i>
            </div>
        </button>
    </div>
@endif

{{-- In-place division confirmation, after a join --}}
<div x-show="joinedDivision" x-cloak class="px-4 mt-2">
    <div class="m-card rounded-2xl p-3 flex items-center gap-2 text-[12px]">
        <i class="bi bi-diagram-3 bracket-icon text-primary"></i>
        <span class="text-muted-foreground">{{ __('personal.event_show_placed_in') }} <span class="font-bold text-foreground" x-text="joinedDivision"></span></span>
    </div>
</div>

{{-- Teleported sheets only — no visible card. The join sheet is opened by
     startJoin(), the proof sheet by openProof() from finishJoin(). --}}
<div class="px-4">
    @include('events::partials.event-payment-proof', $memberScope)
    @include('events::partials.event-join-sheet', $memberScope)
</div>
