<?php

namespace App\Events\Controllers;

use App\Events\Models\ClubEvent;
use App\Events\Support\ClubEntry\ClubEntryDesks;
use App\Events\Support\EventAccess;
use App\Events\Support\PublicEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Register your club" — one address per screen (owner, 2026-10-04: "make a
 * link for every page within the event"):
 *
 *   /me/events/{uuid}/rules          1  rules & consent
 *   /me/events/{uuid}/club           2  find or register your club
 *   /me/events/{uuid}/club/athletes  3  add athletes
 *   /me/events/{uuid}/club/review    4  review & pay
 *   /me/events/{uuid}/club/done      5  confirmation
 *   /me/events/{uuid}/club/entries   6  your entries
 *
 * Every address draws the SAME page (events::entry.club.page) opened at its
 * step; moving between steps changes the address in place (history), and a
 * refresh on a step whose earlier steps are not done settles on the first
 * unfinished one — in the browser, with a replace, never a server redirect.
 *
 * The screens are shared by every host; what stands behind them is the host's
 * ClubEntryDesk. An event with no desk has no such screens (404), and nothing
 * links to them.
 *
 * Who may READ: exactly who may read the event page — the EventPageReader
 * middleware lets a stranger in only while the event is published; a member
 * must be able to see the event. Every write goes through the host's own
 * doors, which re-check everything.
 */
class ClubEntryController extends Controller
{
    public const STEPS = [
        1 => 'rules',
        2 => 'club',
        3 => 'club/athletes',
        4 => 'club/review',
        5 => 'club/done',
        6 => 'club/entries',
    ];

    /**
     * Record that this reader accepted the rules (the rules screen's Continue).
     * The statements and the fingerprint are the server's own — from the page
     * the reader was shown — never from the request.
     */
    public function consent(Request $request, ClubEvent $event): \Illuminate\Http\JsonResponse
    {
        $desk = $this->readableDesk($request, $event);
        $page = $desk->page($request);
        $consent = \App\Events\Support\ClubEntry\EntryConsent::record(
            $request, $event, 'club',
            \App\Events\Support\ClubEntry\EntryConsent::statements((array) ($page['strings'] ?? [])),
            \App\Events\Support\ClubEntry\EntryConsent::rulesHash((array) ($page['e'] ?? [])),
        );

        return response()->json(['success' => true, 'consent' => ['uuid' => $consent->uuid, 'accepted_at' => $consent->accepted_at?->toIso8601String()]]);
    }

    /** Stamp the club this reader proved (the host's own session) on their acceptance — after a registration. */
    public function consentClub(Request $request, ClubEvent $event): \Illuminate\Http\JsonResponse
    {
        $desk = $this->readableDesk($request, $event);
        $subject = $desk->subject($request);
        $consent = $subject ? \App\Events\Support\ClubEntry\EntryConsent::attach($request, $event, $subject) : null;

        return response()->json(['success' => (bool) $consent]);
    }

    /** The same reading gate as the screens, for the JSON doors (one answer for every refusal). */
    private function readableDesk(Request $request, ClubEvent $event): \App\Events\Support\ClubEntry\ClubEntryDesk
    {
        abort_unless(config('events.club_entry', false) && ! $event->is_archived, 404);
        $me = $request->user();
        abort_unless(app(PublicEvent::class)->isPublic($event)
            || \App\Events\Support\EventInvitePass::has($request, $event)
            || ($me && app(EventAccess::class)->visible($event, $me)), 404);
        $desk = ClubEntryDesks::for($event);
        abort_if($desk === null, 404);

        return $desk;
    }

    public function page(Request $request, ClubEvent $event, int $step): Response
    {
        abort_unless(config('events.club_entry', false) && isset(self::STEPS[$step]), 404);
        abort_if($event->is_archived, 404);

        if ($request->attributes->get('event_reader') !== 'visitor') {
            $me = $request->user();
            abort_unless($me && (app(EventAccess::class)->visible($event, $me)
                || app(PublicEvent::class)->isPublic($event)
                || \App\Events\Support\EventInvitePass::has($request, $event)), 404);
        }

        $desk = ClubEntryDesks::for($event);
        abort_if($desk === null, 404);

        $desk->prepare($request);

        $page = $desk->page($request);
        $consented = \App\Events\Support\ClubEntry\EntryConsent::current(
            $request, $event, \App\Events\Support\ClubEntry\EntryConsent::rulesHash((array) ($page['e'] ?? []))) !== null;

        $nav = [
            'initial' => $step,
            // Acceptance of THESE rules is on record for this reader (EntryConsent).
            'consented' => $consented,
            'consentUrl' => route('events.club-entry.consent', ['event' => $event->uuid]),
            'consentClubUrl' => route('events.club-entry.consent-club', ['event' => $event->uuid]),
            'words' => ['consentFailed' => __('events::events.club_entry_consent_failed')],
            'home' => route('me.events.show', ['event' => $event->uuid]),
            'urls' => collect(self::STEPS)->map(fn ($path) => url('/me/events/'.$event->uuid.'/'.$path))->all(),
        ];

        return response()->view('events::entry.club.page', $page + ['nav' => $nav, 'shareUrl' => \App\Events\Support\EventShareLink::for($event)])
            ->withHeaders([
                'Cache-Control' => 'no-store, private',
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy' => 'same-origin',
            ]);
    }
}
