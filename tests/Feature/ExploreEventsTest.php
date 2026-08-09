<?php

namespace Tests\Feature;

use App\Models\ClubEvent;
use App\Models\Tenant;
use App\Models\User;
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

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/explore/events')->assertUnauthorized();
    }

    public function test_it_returns_events_that_have_not_started_and_those_running_now(): void
    {
        $user = $this->createUser();
        $club = $this->clubFor($user);

        $this->event($club, [
            'title' => 'Running now',
            'date' => now()->toDateString(),
            'start_time' => now()->subHours(2)->format('H:i:s'),
            'end_time' => now()->addHours(3)->format('H:i:s'),
        ]);
        $this->event($club, [
            'title' => 'Multi-day, still running',
            'date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'end_time' => '18:00:00',
        ]);
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

        $response = $this->actingAs($user->fresh())->getJson('/explore/events')->assertOk();

        $titles = collect($response->json('events'))->pluck('title')->all();
        $states = collect($response->json('events'))->pluck('state', 'title')->all();

        $this->assertEqualsCanonicalizing(
            ['Running now', 'Multi-day, still running', 'Later today', 'Next month'],
            $titles
        );
        $this->assertSame('live', $states['Running now']);
        $this->assertSame('live', $states['Multi-day, still running']);
        $this->assertSame('upcoming', $states['Later today']);
        $this->assertSame('upcoming', $states['Next month']);
        $this->assertSame(2, $response->json('live'));
        $this->assertSame(2, $response->json('upcoming'));
    }

    public function test_it_excludes_finished_cancelled_and_archived_events(): void
    {
        $user = $this->createUser();
        $club = $this->clubFor($user);

        // Ended earlier today — the end_time has passed, so the day alone is not enough.
        $this->event($club, [
            'title' => 'Finished earlier',
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
}
