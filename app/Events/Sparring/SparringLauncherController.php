<?php

namespace App\Events\Sparring;

use App\Http\Controllers\Controller;
use App\Models\ClubEvent;
use App\Clubs\Models\Tenant;
use App\Traits\HandlesClubAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The one tap: a coach at the club decides there will be matches.
 *
 * This is the whole difference between sparring and a competition. A
 * championship is planned for months and created through a form that asks about
 * divisions, fees and a weigh-in. A session is decided in the middle of
 * training, so it is asked three questions — which sport, how many mats, how
 * long is a bout — and then it exists and the scoreboard works.
 *
 * It lives in the club-admin console rather than under /me/events because that
 * is where a coach already is when they think of it, and because the club is
 * what a session belongs to. Authorisation is the club's own: whoever may
 * administer this club may open its scoreboard, enforced by authorizeClub() on
 * every call and never inferred from the button being visible.
 */
class SparringLauncherController extends Controller
{
    use HandlesClubAuthorization;

    public function __construct(private SparringSession $session) {}

    /** The launcher: today's session if there is one, and the form if not. */
    public function index(Request $request, Tenant $club): View
    {
        $this->authorizeClub($club);

        $today = $this->session->todaysFor($club);

        return view($this->view($request, 'launch'), [
            'club' => $club,
            'today' => $today ? $this->present($today) : null,
            'sports' => $this->sportsFor($club),
            'past' => $this->past($club),
        ]);
    }

    /**
     * Open one (or walk back into the one already running).
     *
     * The sport is validated against the packages that actually have a mat
     * scoreboard — never against whatever the form posted — because a session
     * for a sport with no scoreboard is an event that can do nothing.
     */
    public function store(Request $request, Tenant $club): RedirectResponse
    {
        $this->authorizeClub($club);

        $data = $request->validate([
            'sport' => ['required', 'string', 'in:'.implode(',', Sparring::sports())],
            'mats' => ['nullable', 'integer', 'min:1', 'max:'.SparringSession::MAX_MATS],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:15'],
        ]);

        $event = $this->session->openFor(
            $club,
            Auth::user(),
            $data['sport'],
            (int) ($data['mats'] ?? 1),
            (int) ($data['minutes'] ?? 3),
        );

        // Straight to the console — the coach is standing on the mat, not
        // looking for a link.
        return redirect()->route('me.events.manage', $event->uuid);
    }

    /**
     * The sports this club can run a session for.
     *
     * Ordered by what the club has actually run before, so the common case is
     * first and the coach taps once. A club with no history is offered every
     * sport that has a scoreboard.
     */
    private function sportsFor(Tenant $club): array
    {
        $seen = ClubEvent::where('tenant_id', $club->id)
            ->whereNotNull('sport')
            ->orderByDesc('id')
            ->limit(50)
            ->pluck('sport')
            ->unique()
            ->values()
            ->all();

        $supported = Sparring::sports();

        return collect($seen)->intersect($supported)
            ->merge($supported)
            ->unique()
            ->values()
            ->all();
    }

    /** The club's recent sessions, so an accidental one can be found and closed. */
    private function past(Tenant $club): array
    {
        return ClubEvent::where('tenant_id', $club->id)
            ->where('event_type', Sparring::TYPE)
            ->orderByDesc('date')->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (ClubEvent $e) => $this->present($e))
            ->all();
    }

    /** @return array<string, mixed> */
    private function present(ClubEvent $event): array
    {
        return [
            'uuid' => $event->uuid,
            'title' => $event->title,
            'sport' => $event->sport,
            'date' => $event->date?->toDateString(),
            'started_at' => $event->started_at?->format('H:i'),
            'mats' => (int) ($event->courts ?: 1),
            'closed' => (bool) $event->is_archived,
            'bouts' => $event->matches()->whereNotNull('winner')->count(),
            'url' => route('me.events.manage', $event->uuid),
        ];
    }

    /**
     * The session as JSON, for a console that has been nudged.
     *
     * Its own endpoint rather than a page reload: a coach holding this screen
     * while somebody else queues a bout should see the queue move, not the page
     * blink. Authorised per request against the event — being able to score
     * this session is enough to read it, and nothing here is personal beyond
     * the names already on the mat.
     */
    public function state(Request $request, ClubEvent $event)
    {
        $user = Auth::user();
        $access = app(\App\Events\Support\EventAccess::class);

        abort_unless($event->event_type === Sparring::TYPE, 404);
        abort_unless($access->canManage($event, $user) || $access->canScore($event, $user), 403);

        return response()->json([
            'success' => true,
            'mats' => $this->session->mats($event),
            'entrants' => $this->session->entrants($event)->all(),
            'bouts' => $this->session->bouts($event)->all(),
            'ready_mats' => $this->session->readyMats($event),
            'closed' => (bool) $event->is_archived,
        ]);
    }

    /** This package's own screen for the device in front of us. */
    private function view(Request $request, string $slot): string
    {
        return 'event-sparring::'.$slot.'.'.($request->attributes->get('is_mobile') ? 'mobile' : 'desktop');
    }
}
