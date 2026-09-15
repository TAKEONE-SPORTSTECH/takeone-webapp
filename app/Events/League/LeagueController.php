<?php

namespace App\Events\League;

use App\Events\League\Models\LeagueFixture;
use App\Events\League\Models\LeagueTeam;
use App\Events\Support\EventAccess;
use App\Models\ClubEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * The league's own doors: its teams, its fixture list, its results.
 *
 * PRIVATE to this package — nothing outside `app/Events/League/` may call it
 * (CLAUDE.md → module boundaries). Every method re-checks authority through the
 * shared `EventAccess`, so a league grants nobody anything the rest of the
 * platform would not.
 */
class LeagueController
{
    public function __construct(private League $league, private Table $table) {}

    /* ---------------- Teams ---------------- */

    public function storeTeam(Request $request, ClubEvent $event): JsonResponse
    {
        $this->assertManage($event);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'short_name' => ['nullable', 'string', 'max:12'],
            // A side may BE one of our clubs; it does not have to be.
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        if (LeagueTeam::where('event_id', $event->id)->where('name', trim($data['name']))->exists()) {
            return response()->json(['success' => false, 'message' => __('event-league::messages.team_exists')], 422);
        }

        $team = LeagueTeam::create([
            'event_id' => $event->id,
            'name' => trim($data['name']),
            'short_name' => $data['short_name'] ?? null,
            'tenant_id' => $data['tenant_id'] ?? null,
            // `country` is nullable, so the KEY may not be here at all — reading
            // it unguarded made "add a team" a 500 for every team that was not
            // given one, which is the ordinary case.
            'country' => ($data['country'] ?? null) ? strtoupper($data['country']) : null,
            'sort_order' => (int) LeagueTeam::where('event_id', $event->id)->max('sort_order') + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('event-league::messages.team_added', ['name' => $team->name]),
            'team' => collect($this->league->teamRows($event))->firstWhere('id', $team->id),
            'teams' => $this->league->teamRows($event),
        ]);
    }

    /**
     * Take a side out.
     *
     * A team with a PLAYED fixture is withdrawn, never deleted: those matches
     * happened, the tables of the weeks they were in were right at the time,
     * and deleting them would rewrite everybody else's season. A team that has
     * played nothing is simply removed.
     */
    public function destroyTeam(Request $request, ClubEvent $event, LeagueTeam $team): JsonResponse
    {
        $this->assertManage($event);
        abort_unless($team->event_id === $event->id, 404);

        $hasPlayed = LeagueFixture::where('event_id', $event->id)
            ->where('status', LeagueFixture::PLAYED)
            ->where(fn ($q) => $q->where('home_team_id', $team->id)->orWhere('away_team_id', $team->id))
            ->exists();

        if ($hasPlayed) {
            $team->update(['withdrawn_at' => now()]);

            LeagueFixture::where('event_id', $event->id)
                ->where('status', '!=', LeagueFixture::PLAYED)
                ->where(fn ($q) => $q->where('home_team_id', $team->id)->orWhere('away_team_id', $team->id))
                ->update(['status' => LeagueFixture::CANCELLED]);
        } else {
            LeagueFixture::where('event_id', $event->id)
                ->where(fn ($q) => $q->where('home_team_id', $team->id)->orWhere('away_team_id', $team->id))
                ->delete();

            $team->delete();
        }

        return response()->json([
            'success' => true,
            'message' => __($hasPlayed ? 'event-league::messages.team_withdrawn' : 'event-league::messages.team_removed', ['name' => $team->name]),
            'teams' => $this->league->teamRows($event),
            'matches' => $this->league->fixtureRows($event),
            'table' => $this->table->build($event, $this->league->settings($event)['points']),
        ]);
    }

    /* ---------------- The season ---------------- */

    public function generate(Request $request, ClubEvent $event): JsonResponse
    {
        $this->assertManage($event);

        $data = $request->validate(['rounds' => ['nullable', 'integer', 'min:1', 'max:4']]);

        $result = $this->league->generateFixtures($event, $data);

        return response()->json($result + [
            'matches' => $this->league->fixtureRows($event),
            'table' => $this->table->build($event, $this->league->settings($event)['points']),
        ], $result['success'] ? 200 : 422);
    }

    /* ---------------- One fixture ---------------- */

    public function updateFixture(Request $request, ClubEvent $event, LeagueFixture $fixture): JsonResponse
    {
        abort_unless($fixture->event_id === $event->id, 404);

        $data = $request->validate([
            'home_score' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'away_score' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'status' => ['nullable', Rule::in(LeagueFixture::STATUSES)],
            'kickoff_at' => ['nullable', 'date'],
            'venue' => ['nullable', 'string', 'max:160'],
        ]);

        $scoring = array_key_exists('home_score', $data) || array_key_exists('away_score', $data);
        $moving = array_key_exists('kickoff_at', $data) || array_key_exists('venue', $data) || array_key_exists('status', $data);

        /*
         * ⚠️ AUTHORIZE FIRST, THEN DECIDE WHAT MAY BE WRITTEN.
         *
         * The gate used to be CHOSEN by the payload — manage if a moving key
         * was present, score if a scoring key was — which meant a body that
         * named neither was checked by nothing at all. `PUT …/fixtures/{id}`
         * with `{}` answered 200 and handed the whole fixture row to any
         * signed-in account, on an event they may not be able to see. That is
         * authorization derived from client input, which is the one thing it
         * must never be derived from.
         *
         * So the floor is the same for every call — you must be able to score
         * this event to address this endpoint at all — and moving a fixture,
         * which is running the competition rather than officiating it, takes
         * the organiser on top.
         */
        $this->assertScore($event);

        if ($moving) {
            $this->assertManage($event);
        }

        // ...and a call that asks for nothing is a mistake, not a read.
        if (! $scoring && ! $moving) {
            return response()->json([
                'success' => false,
                'message' => __('event-league::messages.fixture_nothing_to_change'),
            ], 422);
        }

        if ($moving) {
            $fixture->update(array_filter([
                'kickoff_at' => array_key_exists('kickoff_at', $data)
                    ? ($data['kickoff_at'] ? Carbon::parse($data['kickoff_at']) : null)
                    : $fixture->kickoff_at,
                'venue' => $data['venue'] ?? $fixture->venue,
                'status' => $data['status'] ?? $fixture->status,
            ], fn ($v) => $v !== null) + (array_key_exists('kickoff_at', $data) && $data['kickoff_at'] === null ? ['kickoff_at' => null] : []));
        }

        if ($scoring) {
            $outcome = $this->league->recordOutcome($event, $fixture->id, $data);

            return response()->json($outcome + ['fixtures' => $this->league->fixtureRows($event)]);
        }

        return response()->json([
            'success' => true,
            'message' => __('event-league::messages.fixture_saved'),
            'fixture' => $this->league->fixtureRow($fixture->fresh()),
        ]);
    }

    /** The whole league as JSON — what the screen re-reads after a change. */
    public function data(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();
        abort_unless($me && app(EventAccess::class)->visible($event, $me), 403);

        return response()->json(['success' => true] + $this->league->viewData($event, $me)['league']);
    }

    private function assertManage(ClubEvent $event): void
    {
        abort_unless(Auth::check() && app(EventAccess::class)->canManage($event, Auth::user()), 403);
    }

    private function assertScore(ClubEvent $event): void
    {
        abort_unless(Auth::check() && app(EventAccess::class)->canScore($event, Auth::user()), 403);
    }
}
