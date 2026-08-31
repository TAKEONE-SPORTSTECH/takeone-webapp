<?php

namespace App\Events\OpenMat;

use App\Events\Support\EntryService;
use App\Events\Support\EventAccess;
use App\Http\Controllers\Controller;
use App\Models\ClubEvent;
use App\Clubs\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The front door to an open mat, and the two endpoints that are not actions.
 *
 * Everything that CHANGES a mat goes through the package's performAction (one
 * shared, throttled, authorised route for every event type in the product).
 * What lives here is only what that route cannot be:
 *
 *   · the launcher — a mat that does not exist yet has no event to post to
 *   · the opponent search — a read, and the one endpoint here that has to be
 *     hard about abuse, because a searchable endpoint is one somebody will
 *     hammer
 *   · the join page — reached by somebody who is NOT the operator, holding
 *     nothing but six characters
 *   · the state re-read — what a console fetches after a realtime nudge
 */
class OpenMatController extends Controller
{
    public function __construct(private OpenMatSession $session) {}

    /* ==================== The front door ==================== */

    /**
     * `/openmat` — and you are on a mat.
     *
     * There is no launcher and no form. Somebody says "let's go", the other
     * person is already on the floor, and every question asked before the two
     * corner cards appear is a question asked at the worst possible moment.
     * So this resolves everything it can by itself and redirects:
     *
     *   · the CLUB — the one they belong to (their first, alphabetically). An
     *     open mat has to hang on a club because `club_events.tenant_id` is NOT
     *     NULL, but that is the schema's business, not the user's. It is never
     *     asked for and never shown as a choice.
     *   · the SPORT — whatever their last open mat used, else karate. Changed
     *     on the mat itself, in one tap, while it still has no bouts.
     *   · one mat, three minutes — both adjustable afterwards, neither worth
     *     stopping for.
     *
     * A mat already open today is RESUMED rather than joined by a second one,
     * so hitting /openmat twice is idempotent and never strands a paired screen.
     *
     * The only thing that can stop this is having no club at all, which is the
     * one screen this method can render instead of redirecting.
     */
    public function index(Request $request)
    {
        $me = Auth::user();

        $clubs = $this->clubsICanOpenFor($me);

        if (empty($clubs)) {
            return view('event-open_mat::no-club', [
                'isMobile' => (bool) $request->attributes->get('is_mobile'),
            ]);
        }

        $club = Tenant::findOrFail($clubs[0]['id']);

        $event = $this->session->openFor($me, $club, $this->preferredSport($me));

        return redirect()->route('me.events.manage', $event->uuid);
    }

    /**
     * The sport to open on: whatever they used last, else the first available.
     *
     * A club that runs karate opens karate every time without ever being asked,
     * which is the whole point — the question is answered by what they did
     * yesterday rather than by a picker they have to dismiss.
     */
    private function preferredSport(User $me): string
    {
        $last = ClubEvent::where('event_type', OpenMat::TYPE)
            ->where('created_by', $me->id)
            ->latest('id')
            ->value('sport');

        return in_array($last, OpenMat::sports(), true)
            ? $last
            : (OpenMat::sports()[0] ?? 'karate');
    }

    /* ==================== Finding an opponent ==================== */

    /**
     * Search for somebody to put on the mat.
     *
     * The pool is deliberately narrow and the reasoning lives on
     * OpenMatSession::searchOpponents: club-mates by name, anyone discoverable
     * by EXACT email or phone, never a browsable directory. The response
     * carries a name, a club and a published photo — nothing a public profile
     * would not already show.
     */
    public function search(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();

        abort_unless($this->mayOperate($event, $me), 403);

        $people = $this->session->searchOpponents($me, (string) $request->query('q', ''));

        return response()->json(['success' => true, 'people' => $people->all()]);
    }

    /* ==================== The join code ==================== */

    /**
     * Somebody scanned the mat, or typed its six characters.
     *
     * This is how a person who is NOT holding the console gets onto it — a
     * visitor from another club, somebody the operator could never have
     * searched for. They have to be signed in, because taking a corner puts
     * their name and face on a wall screen and files a bout on their record;
     * an anonymous "join" would be a way of impersonating a member.
     *
     * A wrong code says the code is wrong and nothing else. It never
     * distinguishes "no such mat" from "that mat has closed", because the
     * difference tells a stranger whether a code was ever real.
     */
    public function join(Request $request, string $code)
    {
        $me = Auth::user();
        $hit = $this->session->resolveCode($code);

        $isMobile = (bool) $request->attributes->get('is_mobile');

        if (! $hit) {
            return view('event-open_mat::join', [
                'ok' => false,
                'message' => __('event-open_mat::messages.code_unknown'),
            ] + $this->joinBlank());
        }

        /** @var ClubEvent $event */
        $event = $hit['event'];
        $court = $hit['court'];
        $corners = $this->session->corners($event, $court);

        return view('event-open_mat::join', [
            'ok' => true,
            'message' => null,
            'code' => strtoupper($code),
            'event' => $event,
            'court' => $court,
            'corners' => $corners,
            'sport' => (string) $event->sport,
            'me' => ['name' => $me->full_name ?: $me->name],
            'takeUrl' => route('openmat.take', ['code' => strtoupper($code)]),
            'isMobile' => $isMobile,
        ]);
    }

    /** Take a corner. */
    public function take(Request $request, string $code)
    {
        $me = Auth::user();

        $data = $request->validate([
            'side' => ['required', 'string', 'in:'.implode(',', OpenMatCorner::SIDES)],
        ]);

        $hit = $this->session->resolveCode($code);

        if (! $hit) {
            return $this->joinFailure($request, __('event-open_mat::messages.code_unknown'));
        }

        /** @var ClubEvent $event */
        $event = $hit['event'];

        if ($event->is_archived || $event->status === 'completed') {
            return $this->joinFailure($request, __('event-open_mat::messages.mat_closed'));
        }

        try {
            $corners = $this->session->takeCorner($event, $hit['court'], $data['side'], $me);
        } catch (\RuntimeException $e) {
            return $this->joinFailure($request, $e->getMessage());
        }

        // The console is holding this mat open on somebody else's phone. Tell
        // it, or the operator watches an empty corner while the opponent stands
        // there having already joined.
        $this->nudgeConsole($event, $hit['court']);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('event-open_mat::messages.corner_taken'),
                'corners' => $corners,
            ]);
        }

        return redirect()->route('openmat.join', ['code' => strtoupper($code)]);
    }

    /**
     * The mat's join QR, rendered server-side.
     *
     * Takes a MAT, never a URL: the address encoded is built here from the
     * mat's current code, so this can never be turned into a QR generator for
     * arbitrary content pointing anywhere. Re-fetched (cache-busted on the code
     * itself) whenever the operator burns the code and prints a new one.
     */
    public function qr(Request $request, ClubEvent $event)
    {
        $me = Auth::user();

        abort_unless($this->mayOperate($event, $me), 403);

        $court = (string) $request->query('mat', '');

        try {
            $code = $this->session->joinCode($event, $court);
        } catch (\RuntimeException $e) {
            abort(404);
        }

        $svg = \App\Support\Qr::svg(route('openmat.join', ['code' => $code]), 220);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            // A code is short-lived and mat-specific; never let a proxy keep it.
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /* ==================== State ==================== */

    /**
     * The mat as JSON, for a console re-reading after a realtime nudge.
     *
     * A refresh signal rather than a payload is what the nudge carries (what
     * each holder may see differs), so this is where they come to find out what
     * changed.
     */
    public function state(ClubEvent $event): JsonResponse
    {
        $me = Auth::user();

        abort_unless($this->mayOperate($event, $me), 403);

        $type = app(\App\Events\EventTypeRegistry::class)->for($event);

        abort_unless($type instanceof OpenMat, 404);

        return response()->json([
            'success' => true,
            'open_mat' => $type->viewData($event, $me)['openMat'] ?? [],
        ]);
    }

    /* ==================== Helpers ==================== */

    /**
     * Who may drive this mat: whoever the event already trusts.
     *
     * EventAccess is the single authority on that across the product — the
     * creator, an appointed organiser, an official. This package invents no
     * rule of its own, so a mat can never be operable by somebody an event of
     * any other type would refuse.
     */
    private function mayOperate(ClubEvent $event, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $access = app(EventAccess::class);

        return $access->canManage($event, $user) || $access->canOfficiate($event, $user);
    }

    /** The clubs this member may open a mat for. */
    private function clubsICanOpenFor(User $me): array
    {
        $ids = $me->memberClubs()->pluck('tenants.id')
            ->merge(app(EntryService::class)->administeredClubIds($me))
            ->unique();

        return Tenant::whereIn('id', $ids)
            ->orderBy('club_name')
            ->get(['id', 'club_name', 'logo', 'country'])
            ->map(fn (Tenant $c) => [
                'id' => $c->id,
                'name' => $c->club_name,
                'logo' => $c->logo ? file_url($c->logo) : null,
                'country' => $c->country,
            ])->values()->all();
    }

    public static function sportOptions(): array
    {
        $catalog = config('event_schema.sports', []);

        return collect(OpenMat::sports())
            ->map(fn (string $key) => [
                'key' => $key,
                'label' => $catalog[$key]['label'] ?? ucfirst($key),
                'icon' => $catalog[$key]['icon'] ?? 'bi-person-arms-up',
            ])->all();
    }

    private function joinBlank(): array
    {
        return [
            'code' => null, 'event' => null, 'court' => null, 'corners' => ['aka' => null, 'ao' => null],
            'sport' => null, 'me' => ['name' => ''], 'takeUrl' => null, 'isMobile' => true,
        ];
    }

    private function joinFailure(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }

        return back()->with('error', $message);
    }

    /** Nudge every console holding this mat. Best-effort, like every push. */
    private function nudgeConsole(ClubEvent $event, ?string $court = null): void
    {
        rescue(function () use ($event, $court) {
            $type = app(\App\Events\EventTypeRegistry::class)->for($event);

            if ($type instanceof OpenMat) {
                $type->notifyConsoles($event, $court);
            }
        }, null, false);
    }
}
