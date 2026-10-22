<?php

namespace App\Events\Controllers;

use App\Events\Models\ClubEvent;
use App\Events\Support\BoutNumbers;
use App\Events\Support\EventAccess;
use App\Events\Support\PublicEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * /me/events/{uuid}/draw-desk — the scoring tablet's draw screen, for the
 * organiser at a desk (owner, 2026-10-14: the federation dashboard's Divisions
 * tab opens it on the division that was clicked).
 *
 * The SAME page as /tablet/{token}/draw (events::tablet-draw) with the same
 * tools — move, set / remove a winner, bout numbers, names, late entry — but
 * signed in as the viewer: no device token, no PIN. Every edit goes to the
 * organiser's own endpoints, which authorise exactly as they do for the
 * member board. Only the three bout-number doors are new here, and they call
 * App\Events\Support\BoutNumbers — the one implementation the tablet calls too.
 *
 * Who: whoever may arrange this draw (organiser or appointed official); the
 * number tools and the winner tools appear only for one who manages it.
 * Anybody else is sent to the ordinary draw page, which decides what they see.
 */
class DrawDeskController extends Controller
{
    public function show(Request $request, ClubEvent $event): Response
    {
        $me = Auth::user();
        $access = app(EventAccess::class);
        $manages = $access->canManage($event, $me);

        $division = (int) $request->query('division', 0);
        if ($event->is_archived || (! $manages && ! $access->canArrange($event, $me))) {
            return redirect()->route('me.events.bracket.manage', array_filter(['event' => $event->uuid, 'division' => $division ?: null]));
        }

        $id = '999999999';

        // Back returns to the screen that opened the desk (the federation
        // dashboard's Divisions tab) — but only a federation page on this
        // site, never an address a link could aim anywhere else.
        $back = $this->backTo($request->query('back'));

        return response()->view('events::tablet-draw', [
            'token' => null,
            'event' => $event,
            'title' => (string) $event->title,
            'color' => app(PublicEvent::class)->color($event),
            'consoleUrl' => $back ?? route('me.events.show', ['event' => $event->uuid]),
            'backLabel' => $back ? __('events::events.claim_continue_back') : __('events::events.public_enrol_back_to_event'),
            'editing' => true,
            'editorManages' => $manages,
            'editorName' => $me->full_name,
            'secondsLeft' => 0,
            'hasPin' => false,
            'pinRequired' => false,
            'division' => $division ?: null,
            'oneDivisionOn' => (bool) $event->one_division_per_person,
            'urls' => [
                'data' => route('me.events.bracket.data', ['event' => $event->uuid]),
                // The bracket on show as a PDF (BracketPdf) — the desk's Download
                // button. The route re-authorises (canArrange, which every viewer
                // of this desk has); absent while printing is switched off.
                'pdf' => \App\Events\Support\BracketPdf::available() ? route('me.events.bracket.pdf', ['event' => $event->uuid]) : null,
                // Add / delete a division from the desk (organisers only; the
                // endpoints re-check). Delete is base + '/{id}' with `release`.
                'divisions' => $manages ? route('me.events.divisions.store', ['event' => $event->uuid]) : null,
                // The division editor's drag onto a board (it grows to fit, and a
                // desk move that empties half a board shrinks it), and the
                // event's one-division-per-person switch. Organisers only.
                'pull' => $manages ? route('me.events.bracket.pull', ['event' => $event->uuid]) : null,
                'oneDivision' => $manages ? route('me.events.bracket.one-division', ['event' => $event->uuid]) : null,
                'arrange' => route('me.events.bracket.arrange', ['event' => $event->uuid]),
                'admit' => route('me.events.bracket.admit', ['event' => $event->uuid]),
                'transfer' => route('me.events.bracket.transfer', ['event' => $event->uuid]),
                'outcome' => str_replace($id, '__ID__', route('me.events.outcome', ['event' => $event->uuid, 'unit' => $id])),
                'entrant' => str_replace($id, '__ID__', route('me.events.entrant.update', ['event' => $event->uuid, 'registration' => $id])),
                'candidates' => route('me.events.bracket.candidates', ['event' => $event->uuid]),
                'clubs' => route('me.events.clubs.list', ['event' => $event->uuid]),
                'unnamed' => route('me.events.entries.unnamed', ['event' => $event->uuid]),
                'number' => route('me.events.draw-desk.number', ['event' => $event->uuid, 'match' => $id]),
                'numberRelease' => route('me.events.draw-desk.number.release', ['event' => $event->uuid, 'match' => $id]),
                'renumber' => route('me.events.draw-desk.renumber', ['event' => $event->uuid]),
                'shuffle' => route('me.events.action', ['event' => $event->uuid, 'action' => 'shuffle_draw']),
                'renumberAll' => route('me.events.order.renumber', ['event' => $event->uuid]),
                // Free edit (FreeEdit): write any bout as it happened, even after
                // the event. Organisers only, behind EVENT_FREE_EDIT.
                'freeEdit' => $manages && \App\Events\Support\Tournament\FreeEdit::enabled()
                    ? route('me.events.bracket.free-edit', ['event' => $event->uuid, 'match' => $id]) : null,
                // No PIN on a desk: the viewer IS the organiser.
                'unlock' => null,
                'lock' => null,
            ],
        ]);
    }

    public function numberPlace(ClubEvent $event, int $match): Response
    {
        $this->mustManage($event);

        return app(BoutNumbers::class)->place($event, $match);
    }

    public function renumber(Request $request, ClubEvent $event): Response
    {
        $this->mustManage($event);

        return app(BoutNumbers::class)->renumber($request, $event);
    }

    public function releaseNumber(ClubEvent $event, int $match): Response
    {
        $this->mustManage($event);

        return app(BoutNumbers::class)->release($event, $match);
    }

    /** A same-site /federation/… path (with an optional plain query), or null. */
    private function backTo(mixed $back): ?string
    {
        if (! is_string($back) || strlen($back) > 300
            || ! preg_match('~^/federation(/[A-Za-z0-9_-]+)*/?(\?[A-Za-z0-9_=&%.-]*)?$~', $back)) {
            return null;
        }

        return url($back);
    }

    private function mustManage(ClubEvent $event): void
    {
        abort_unless(app(EventAccess::class)->canManage($event, Auth::user()), 403);
    }
}
