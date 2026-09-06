<?php

namespace App\EventLab\Controllers;

use App\EventLab\Models\LabEntrant;
use App\EventLab\Models\LabEvent;
use App\EventLab\Services\EventCloner;
use App\EventLab\Services\LabFee;
use App\EventLab\Services\LabSeeder;
use App\Http\Controllers\Controller;
use App\Members\Models\User;
use App\Models\ClubEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The sandbox's only door.
 *
 * Every method re-checks super-admin rather than trusting the route group to
 * have done it: this is a workbench that reads real events and creates real
 * rows, and "the middleware surely covered it" is how an internal tool becomes
 * an attack surface. Being temporary is not a reason to skip it.
 */
class LabController extends Controller
{
    /** The workbench: what has been copied in so far, and what could be. */
    public function index(Request $request): View
    {
        $this->guard($request);

        $events = LabEvent::withCount(['entrants', 'entries', 'variants', 'clubs'])
            ->latest('id')
            ->get();

        // Candidates to copy in. Read-only: listing a real event here does not
        // touch it in any way.
        $sources = ClubEvent::query()
            ->where('is_archived', false)
            ->latest('date')
            ->limit(25)
            ->get(['id', 'uuid', 'title', 'date', 'sport']);

        // Sandbox twins: real `club_events` rows made by EventCloner, which is
        // what lets every existing event screen be tried without touching one.
        $twins = ClubEvent::query()
            ->where('title', 'like', EventCloner::TITLE_PREFIX.'%')
            ->withCount('registrations')
            ->latest('id')
            ->get(['id', 'uuid', 'title', 'date', 'sport']);

        return view('eventlab::index', compact('events', 'sources', 'twins'));
    }

    /** One sandbox event: its variants, its entrants, what each of them owes. */
    public function show(Request $request, LabEvent $event): View
    {
        $this->guard($request);

        $event->load('variants');

        $entrants = $event->entrants()
            ->with(['club', 'entry.selections.variant'])
            ->orderBy('full_name')
            ->get();

        return view('eventlab::event', compact('event', 'entrants'));
    }

    /**
     * Copy a real event in.
     *
     * The source is resolved by uuid from the database, never by anything the
     * form claims about it beyond which one to read.
     */
    public function seed(Request $request): JsonResponse
    {
        $user = $this->guard($request);

        $data = $request->validate([
            'source' => ['required', 'uuid'],
        ]);

        $source = ClubEvent::where('uuid', $data['source'])->firstOrFail();

        $event = app(LabSeeder::class)->fromRealEvent($source, $user);

        return response()->json([
            'success' => true,
            'message' => 'Copied "'.$source->title.'" into the sandbox.',
            'event' => [
                'uuid' => $event->uuid,
                'title' => $event->title,
                'url' => route('testcode.event', $event->uuid),
            ],
        ]);
    }

    /**
     * What would this selection cost?
     *
     * Priced on the server from the database, so a browser that posts its own
     * idea of the total gets the real one back.
     */
    public function quote(Request $request, LabEvent $event): JsonResponse
    {
        $this->guard($request);

        $data = $request->validate([
            'variants' => ['array'],
            'variants.*' => ['integer'],
        ]);

        $fee = app(LabFee::class);
        $owned = $fee->ownedBy($event, $data['variants'] ?? []);

        return response()->json(['success' => true, 'quote' => $fee->quote($event, $owned)]);
    }

    /**
     * Mark an entrant as a duplicate of another.
     *
     * The row is kept: what somebody typed at the desk is the record of what
     * happened, and a competition that hid it would be harder to audit, not
     * tidier. Both must belong to the same sandbox event, so an id from
     * elsewhere cannot be pointed at this one.
     */
    public function markDuplicate(Request $request, LabEvent $event, LabEntrant $entrant): JsonResponse
    {
        $this->guard($request);

        abort_unless($entrant->lab_event_id === $event->id, 404);

        $data = $request->validate([
            'duplicate_of' => ['nullable', 'integer'],
        ]);

        $target = null;

        if (! empty($data['duplicate_of'])) {
            $target = LabEntrant::where('lab_event_id', $event->id)
                ->where('id', (int) $data['duplicate_of'])
                ->firstOrFail();

            abort_if($target->id === $entrant->id, 422, 'An entrant cannot duplicate themselves.');
        }

        $entrant->update(['duplicate_of_id' => $target?->id]);

        return response()->json([
            'success' => true,
            'message' => $target ? 'Marked as a duplicate.' : 'Duplicate mark cleared.',
            'entrant' => ['id' => $entrant->id, 'duplicate_of_id' => $entrant->duplicate_of_id],
        ]);
    }

    /**
     * Make a sandbox twin of a real event.
     *
     * Additive only: the source is read, and a second event is created beside
     * it with competitors who are nobody. Nothing about the source changes.
     */
    public function twin(Request $request): JsonResponse
    {
        $user = $this->guard($request);

        $data = $request->validate([
            'source' => ['required', 'uuid'],
        ]);

        $source = ClubEvent::where('uuid', $data['source'])->firstOrFail();

        abort_if(str_starts_with($source->title, EventCloner::TITLE_PREFIX), 422, 'That is already a sandbox twin.');

        $clone = app(EventCloner::class)->clone($source, $user);

        return response()->json([
            'success' => true,
            'message' => 'Twinned "'.$source->title.'".',
            'twin' => [
                'uuid' => $clone->uuid,
                'title' => $clone->title,
                'url' => route('testcode.me.events.show', $clone->uuid),
            ],
        ]);
    }

    /**
     * Delete a twin and the people it invented.
     *
     * The title prefix is checked on the SERVER before anything is removed: a
     * uuid arriving here names an event, and the only events this door may ever
     * delete are the ones the sandbox made.
     */
    public function untwin(Request $request, ClubEvent $clone): JsonResponse
    {
        $this->guard($request);

        abort_unless(str_starts_with($clone->title, EventCloner::TITLE_PREFIX), 403);

        $result = app(EventCloner::class)->purge($clone);

        return response()->json([
            'success' => true,
            'message' => 'Removed the twin, '.$result['people'].' sandbox people and '.$result['matches'].' bouts.',
        ]);
    }

    /** Super-admin only. Returns the actor so callers can attribute writes. */
    private function guard(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isSuperAdmin(), 403);

        return $user;
    }
}
