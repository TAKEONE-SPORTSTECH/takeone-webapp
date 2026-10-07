<?php

namespace Tests\Feature;

use App\Events\Models\ClubEvent;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use Tests\TestCase;

/**
 * The explore "Events" tab lists open events only — those that have not started
 * yet, plus those running right now — and never an event whose detail page the
 * viewer would be refused.
 */
class ExploreEventsTest extends TestCase
{
    /** A club the given user is an active member of. */
    private function clubFor(User $user, array $attrs = []): Tenant
    {
        $club = $this->createClub($user, array_merge(['country' => 'BH'], $attrs));
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $club;
    }

    private function event(Tenant $club, array $attrs): ClubEvent
    {
        return ClubEvent::create(array_merge([
            'tenant_id' => $club->id,
            'title' => 'Event',
            'event_type' => 'championship',
            'sport' => 'taekwondo',
            'scope' => 'internal',
            'status' => 'active',
            'is_archived' => false,
            'start_time' => '09:00:00',
        ], $attrs));
    }

    /** Begin an event for real — `started_at` is guarded, so it is force-filled. */
    private function start(ClubEvent $event): ClubEvent
    {
        $event->forceFill(['started_at' => now()->subHours(2)])->save();

        return $event;
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/explore/events')->assertUnauthorized();
    }

    public function test_it_returns_events_that_have_not_started_and_those_running_now(): void
    {
        // Frozen at midday: the relative times below (+8h, +5h) would otherwise
        // roll past midnight when the suite runs in the evening, and an
        // end_time earlier than its start_time reads as already finished.
        $this->travelTo(\Carbon\Carbon::parse('2026-06-15 12:00:00'));

        $user = $this->createUser();
        $club = $this->clubFor($user);

        // `live` follows ClubEvent::hasStarted(), which is `started_at !== null`
        // — the organiser's decision to begin, deliberately NOT the clock (see
        // the method's docblock). An event whose start time has passed but which
        // nobody started is late, not running. These two were started for real.
        $this->start($this->event($club, [
            'title' => 'Running now',
            'date' => now()->toDateString(),
            'start_time' => now()->subHours(2)->format('H:i:s'),
            'end_time' => now()->addHours(3)->format('H:i:s'),
        ]));
        $this->start($this->event($club, [
            'title' => 'Multi-day, still running',
            'date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'end_time' => '18:00:00',
        ]));
        $this->event($club, [
            'title' => 'Later today',
            'date' => now()->toDateString(),
            'start_time' => now()->addHours(4)->format('H:i:s'),
            'end_time' => now()->addHours(8)->format('H:i:s'),
        ]);
        $this->event($club, [
            'title' => 'Next month',
            'date' => now()->addMonth()->toDateString(),
            'end_time' => '17:00:00',
        ]);
        // Its scheduled start passed an hour ago and nobody pressed start: it is
        // still listed, and it is still `upcoming`.
        $this->event($club, [
            'title' => 'Overdue, never started',
            'date' => now()->toDateString(),
            'start_time' => now()->subHour()->format('H:i:s'),
            'end_time' => now()->addHours(5)->format('H:i:s'),
        ]);

        $response = $this->actingAs($user->fresh())->getJson('/explore/events')->assertOk();

        $titles = collect($response->json('events'))->pluck('title')->all();
        $states = collect($response->json('events'))->pluck('state', 'title')->all();

        $this->assertEqualsCanonicalizing(
            ['Running now', 'Multi-day, still running', 'Later today', 'Next month', 'Overdue, never started'],
            $titles
        );
        $this->assertSame('live', $states['Running now']);
        $this->assertSame('live', $states['Multi-day, still running']);
        $this->assertSame('upcoming', $states['Later today']);
        $this->assertSame('upcoming', $states['Next month']);
        $this->assertSame('upcoming', $states['Overdue, never started']);
        $this->assertSame(2, $response->json('live'));
        $this->assertSame(3, $response->json('upcoming'));
    }

    public function test_it_excludes_finished_cancelled_and_archived_events(): void
    {
        /*
         * ⚠️ MIDDAY, FROZEN — the fixtures below are built from the wall clock
         * and only make sense in the middle of a day.
         *
         * "Ended earlier today" is `now()->subHours(6)` for the start and
         * `now()->subHour()` for the end, each formatted as a TIME and pinned
         * to TODAY's date. Run at 01:30, the start becomes 19:30 — yesterday
         * evening's clock reading stamped on today — so the row says an event
         * that starts tonight and ended an hour ago, and the query is asked a
         * question with no right answer. It failed every night between midnight
         * and 06:00 and passed every working hour, which is the worst kind of
         * red: nobody sees it, and when somebody finally does it looks like
         * whatever they happened to be touching.
         */
        $this->travelTo(now()->startOfDay()->addHours(12));

        $user = $this->createUser();
        $club = $this->clubFor($user);

        // Completed earlier today — the organiser closed it, so its results are out.
        // (An end_time passing on its own is NOT enough: see
        // test_a_same_day_event_is_not_over_until_the_day_ends_or_every_result_is_in.)
        $this->event($club, [
            'title' => 'Finished earlier',
            'status' => 'completed',
            'date' => now()->toDateString(),
            'start_time' => now()->subHours(6)->format('H:i:s'),
            'end_time' => now()->subHour()->format('H:i:s'),
        ]);
        $this->event($club, [
            'title' => 'Finished last week',
            'date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->subDays(6)->toDateString(),
            'end_time' => '18:00:00',
        ]);
        $this->event($club, [
            'title' => 'Cancelled',
            'date' => now()->addWeek()->toDateString(),
            'end_time' => '17:00:00',
            'status' => 'cancelled',
        ]);
        $this->event($club, [
            'title' => 'Archived',
            'date' => now()->addWeek()->toDateString(),
            'end_time' => '17:00:00',
            'is_archived' => true,
        ]);

        $response = $this->actingAs($user->fresh())->getJson('/explore/events')->assertOk();

        $this->assertSame(0, $response->json('total'));
    }

    public function test_it_hides_another_clubs_internal_event(): void
    {
        $user = $this->createUser();
        $this->clubFor($user);

        $stranger = $this->createUser();
        $otherClub = $this->clubFor($stranger, ['country' => 'BH']);
        $this->event($otherClub, [
            'title' => 'Their internal event',
            'date' => now()->addWeek()->toDateString(),
            'end_time' => '17:00:00',
            'scope' => 'internal',
        ]);

        $response = $this->actingAs($user->fresh())->getJson('/explore/events')->assertOk();

        $this->assertSame(0, $response->json('total'));
    }

    public function test_it_shows_another_clubs_event_when_the_scope_reaches_the_member(): void
    {
        $user = $this->createUser();
        $this->clubFor($user, ['country' => 'BH']);

        $stranger = $this->createUser();
        $otherClub = $this->clubFor($stranger, ['country' => 'BH']);

        $this->event($otherClub, [
            'title' => 'Open to everyone',
            'date' => now()->addWeek()->toDateString(),
            'end_time' => '17:00:00',
            'scope' => 'worldwide',
        ]);
        $this->event($otherClub, [
            'title' => 'Same country',
            'date' => now()->addWeek()->toDateString(),
            'end_time' => '17:00:00',
            'scope' => 'nationwide',
        ]);

        $response = $this->actingAs($user->fresh())->getJson('/explore/events')->assertOk();

        $this->assertEqualsCanonicalizing(
            ['Open to everyone', 'Same country'],
            collect($response->json('events'))->pluck('title')->all()
        );
    }

    public function test_it_exposes_the_uuid_not_the_database_id(): void
    {
        $user = $this->createUser();
        $club = $this->clubFor($user);
        $event = $this->event($club, [
            'title' => 'Keyed by uuid',
            'date' => now()->addWeek()->toDateString(),
            'end_time' => '17:00:00',
        ]);

        $row = $this->actingAs($user->fresh())->getJson('/explore/events')->json('events.0');

        $this->assertSame($event->uuid, $row['key']);
        $this->assertArrayNotHasKey('id', $row);
        $this->assertArrayNotHasKey('tenant_id', $row);
        $this->assertStringContainsString($event->uuid, $row['url']);
    }

    public function test_finished_events_are_listed_separately_newest_first_and_scoped(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-06-15 12:00:00'));

        $user = $this->createUser();
        $club = $this->clubFor($user);
        $this->event($club, ['title' => 'Last week', 'date' => '2026-06-08']);
        $this->event($club, ['title' => 'Yesterday', 'date' => '2026-06-14']);
        $this->event($club, ['title' => 'Ended this morning', 'date' => '2026-06-15', 'start_time' => '07:00:00', 'end_time' => '09:00:00', 'status' => 'completed']);
        $this->event($club, ['title' => 'Old but cancelled', 'date' => '2026-06-10', 'status' => 'cancelled']);
        $this->event($club, ['title' => 'Next week', 'date' => '2026-06-22']);

        // Another club's internal event must never leak into the finished list.
        $other = $this->createClub($this->createUser(), ['country' => 'BH']);
        $this->event($other, ['title' => 'Not yours', 'date' => '2026-06-12']);

        $res = $this->actingAs($user)->getJson('/explore/events')->assertOk();

        $this->assertSame(['Next week'], collect($res->json('events'))->pluck('title')->all());
        $this->assertSame(
            ['Ended this morning', 'Yesterday', 'Last week'],
            collect($res->json('finished'))->pluck('title')->all()
        );
        $this->assertSame(['ended'], collect($res->json('finished'))->pluck('state')->unique()->values()->all());
        $this->assertFalse(collect($res->json('finished'))->contains('featured', true));
    }

    public function test_going_counts_people_and_prices_list_every_option(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-06-15 12:00:00'));

        $user = $this->createUser();
        $club = $this->clubFor($user);
        $event = $this->event($club, ['title' => 'Open', 'date' => '2026-06-20', 'participant_fee_amount' => 0]);
        foreach ([['Gi', 10], ['No-Gi', 10], ['Gi + No-Gi', 15]] as $i => [$label, $amount]) {
            \App\Events\Models\EventFeeOption::create(['event_id' => $event->id, 'role' => 'participant', 'label' => $label, 'amount' => $amount, 'is_active' => true, 'sort' => $i]);
        }
        // One person in two divisions is two entries but ONE competitor.
        $athlete = $this->createUser();
        foreach ([1, 2] as $n) {
            \App\Events\Models\ClubEventRegistration::create(['event_id' => $event->id, 'user_id' => $athlete->id, 'role' => 'participant', 'status' => 'registered']);
        }

        $card = $this->actingAs($user)->getJson('/explore/events')->assertOk()->json('events.0');

        $this->assertSame(1, $card['going']);
        $this->assertSame(['Gi', 'No-Gi', 'Gi + No-Gi'], array_column($card['prices'], 'label'));
        $this->assertTrue($card['price_varies']);
        $this->assertStringContainsString('10', $card['price_from']);
    }

    public function test_a_same_day_event_is_not_over_until_the_day_ends_or_every_result_is_in(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-06-15 20:00:00'));

        $user = $this->createUser();
        $club = $this->clubFor($user);
        // Planned 09:00–12:00, but the day overran: its end TIME has passed.
        $event = $this->event($club, ['title' => 'Overran', 'date' => '2026-06-15', 'start_time' => '09:00:00', 'end_time' => '12:00:00']);
        $a = \App\Events\Models\EventCategory::create(['event_id' => $event->id, 'name' => 'A', 'status' => 'completed']);
        $b = \App\Events\Models\EventCategory::create(['event_id' => $event->id, 'name' => 'B', 'status' => 'running']);
        foreach ([$a, $b] as $cat) {
            \App\Events\Models\ClubEventRegistration::create(['event_id' => $event->id, 'user_id' => $this->createUser()->id, 'role' => 'participant', 'status' => 'registered', 'category_id' => $cat->id]);
        }

        $res = $this->actingAs($user)->getJson('/explore/events')->assertOk();
        $this->assertSame(['Overran'], collect($res->json('events'))->pluck('title')->all());
        $this->assertSame([], $res->json('finished'));

        // The last division's podium is settled → every result is out → over.
        $b->update(['status' => 'completed']);
        $res = $this->actingAs($user)->getJson('/explore/events')->assertOk();
        $this->assertSame([], $res->json('events'));
        $this->assertSame(['Overran'], collect($res->json('finished'))->pluck('title')->all());
    }

    /** The new Explore asks for ?scope=public: published events only, never a finished one. */
    public function test_public_scope_lists_only_published_events_that_have_not_ended(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-06-15 12:00:00'));
        $user = $this->createUser();
        $club = $this->clubFor($user);

        $this->event($club, ['title' => 'Open to all', 'entry_mode' => 'public', 'date' => now()->addDays(3)->toDateString()]);
        $this->event($club, ['title' => 'Members only', 'entry_mode' => 'members', 'date' => now()->addDays(3)->toDateString()]);
        $this->event($club, ['title' => 'Public but over', 'entry_mode' => 'public', 'date' => now()->subDays(3)->toDateString()]);

        $res = $this->actingAs($user)->getJson('/explore/events?scope=public')->assertOk();
        $this->assertSame(['Open to all'], collect($res->json('events'))->pluck('title')->all());
        $this->assertSame([], $res->json('finished'));

        // The classic page still gets everything it always did.
        $classic = $this->actingAs($user)->getJson('/explore/events')->assertOk();
        $this->assertContains('Members only', collect($classic->json('events'))->pluck('title')->all());
    }
}
