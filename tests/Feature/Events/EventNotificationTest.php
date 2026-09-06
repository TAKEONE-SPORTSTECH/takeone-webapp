<?php

namespace Tests\Feature\Events;

use App\Events\Support\AudienceResolver;
use App\Events\Support\EventNotifier;
use App\Jobs\DeliverEventNotification;
use App\Clubs\Models\ClubActivity;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventNotificationSent;
use App\Clubs\Models\Tenant;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Who gets told about an event, and when.
 *
 * The audience widens with the event's scope; the milestones are declared by
 * the type package; delivery is queued, capped and exactly-once.
 */
class EventNotificationTest extends TestCase
{
    private function club(array $attrs = []): Tenant
    {
        return $this->createClub($this->createUser(), array_merge([
            'country' => 'BH', 'currency' => 'BHD',
            'gps_lat' => 26.2285, 'gps_long' => 50.5860,   // Manama
        ], $attrs));
    }

    /** A club that runs Taekwondo, plus one active member who practises it. */
    private function taekwondoClub(array $attrs = []): array
    {
        $club = $this->club($attrs);
        ClubActivity::create(['tenant_id' => $club->id, 'name' => 'Taekwondo']);

        $member = $this->createUser();
        $member->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return [$club, $member->fresh()];
    }

    private function event(Tenant $club, array $attrs = []): ClubEvent
    {
        return ClubEvent::create(array_merge([
            'tenant_id' => $club->id,
            'created_by' => $club->owner_user_id,
            'title' => 'Spring Open',
            'event_type' => 'championship',
            'sport' => 'taekwondo',
            'scope' => 'internal',
            'date' => now()->addWeeks(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'active',
            'is_archived' => false,
        ], $attrs));
    }

    private function notifier(): EventNotifier
    {
        return app(EventNotifier::class);
    }

    /* ---------------- Audience by scope ---------------- */

    public function test_an_internal_event_reaches_only_the_host_club(): void
    {
        [$host, $mine] = $this->taekwondoClub();
        [, $theirs] = $this->taekwondoClub(['slug' => 'other-'.uniqid()]);

        $audience = app(AudienceResolver::class)->forEvent($this->event($host));

        $this->assertContains($mine->id, $audience);
        $this->assertNotContains($theirs->id, $audience, 'another club must not be told about an internal event');
    }

    public function test_an_open_event_reaches_clubs_within_the_radius_but_not_beyond_it(): void
    {
        [$host, $near] = $this->taekwondoClub();

        // ~7 km away — inside the 50 km radius.
        [, $alsoNear] = $this->taekwondoClub(['slug' => 'near-'.uniqid(), 'gps_lat' => 26.29, 'gps_long' => 50.60]);
        // Dubai — same region, far outside 50 km.
        [, $far] = $this->taekwondoClub(['slug' => 'far-'.uniqid(), 'country' => 'AE', 'gps_lat' => 25.20, 'gps_long' => 55.27]);

        $audience = app(AudienceResolver::class)->forEvent($this->event($host, ['scope' => 'inter_club']));

        $this->assertContains($near->id, $audience);
        $this->assertContains($alsoNear->id, $audience);
        $this->assertNotContains($far->id, $audience, '50 km must actually mean 50 km');
    }

    public function test_a_nationwide_event_reaches_the_country_and_stops_at_the_border(): void
    {
        [$host, $home] = $this->taekwondoClub();
        [, $abroad] = $this->taekwondoClub(['slug' => 'abroad-'.uniqid(), 'country' => 'AE', 'gps_lat' => 25.2, 'gps_long' => 55.27]);

        $audience = app(AudienceResolver::class)->forEvent($this->event($host, ['scope' => 'nationwide']));

        $this->assertContains($home->id, $audience);
        $this->assertNotContains($abroad->id, $audience);
    }

    public function test_a_regional_event_reaches_the_configured_neighbours(): void
    {
        [$host, $home] = $this->taekwondoClub();
        [, $neighbour] = $this->taekwondoClub(['slug' => 'ksa-'.uniqid(), 'country' => 'SA', 'gps_lat' => 24.7, 'gps_long' => 46.7]);
        [, $distant] = $this->taekwondoClub(['slug' => 'jp-'.uniqid(), 'country' => 'JP', 'gps_lat' => 35.6, 'gps_long' => 139.7]);

        $audience = app(AudienceResolver::class)->forEvent($this->event($host, ['scope' => 'regional']));

        $this->assertContains($home->id, $audience);
        $this->assertContains($neighbour->id, $audience, 'SA neighbours BH in config');
        $this->assertNotContains($distant->id, $audience);
    }

    public function test_a_worldwide_event_can_be_narrowed_to_chosen_countries(): void
    {
        [$host, $home] = $this->taekwondoClub();
        [, $chosen] = $this->taekwondoClub(['slug' => 'jp-'.uniqid(), 'country' => 'JP', 'gps_lat' => 35.6, 'gps_long' => 139.7]);
        [, $notChosen] = $this->taekwondoClub(['slug' => 'uk-'.uniqid(), 'country' => 'GB', 'gps_lat' => 51.5, 'gps_long' => -0.12]);

        $audience = app(AudienceResolver::class)->forEvent(
            $this->event($host, ['scope' => 'worldwide', 'notify_countries' => ['bh', 'jp']]),
        );

        $this->assertContains($home->id, $audience);
        $this->assertContains($chosen->id, $audience);
        $this->assertNotContains($notChosen->id, $audience);
    }

    /* ---------------- The activity filter ---------------- */

    public function test_only_people_practising_the_activity_are_told(): void
    {
        [$host, $taekwondoka] = $this->taekwondoClub();

        // A swimming club in the same country — same scope, wrong sport.
        $swimClub = $this->club(['slug' => 'swim-'.uniqid()]);
        ClubActivity::create(['tenant_id' => $swimClub->id, 'name' => 'Swimming']);
        $swimmer = $this->createUser();
        $swimmer->memberClubs()->syncWithoutDetaching([$swimClub->id => ['status' => 'active']]);

        $audience = app(AudienceResolver::class)->forEvent($this->event($host, ['scope' => 'nationwide']));

        $this->assertContains($taekwondoka->id, $audience);
        $this->assertNotContains($swimmer->id, $audience, 'a swimmer must not hear about a taekwondo championship');
    }

    public function test_a_broadcast_whose_activity_cannot_be_identified_reaches_nobody(): void
    {
        [$host] = $this->taekwondoClub();

        // Deny by default: failing to identify the sport must never mean
        // notifying an entire country.
        $audience = app(AudienceResolver::class)->forEvent(
            $this->event($host, ['scope' => 'nationwide', 'sport' => null, 'event_type' => 'race']),
        );

        $this->assertSame([], $audience);
    }

    public function test_a_member_who_opted_out_of_announcements_is_dropped(): void
    {
        [$host, $member] = $this->taekwondoClub();
        $member->update(['notify_event_announcements' => false]);

        $this->assertNotContains(
            $member->id,
            app(AudienceResolver::class)->forEvent($this->event($host), announcement: true),
        );
    }

    /* ---------------- Milestones ---------------- */

    public function test_the_schedule_covers_the_events_whole_life(): void
    {
        [$host] = $this->taekwondoClub();
        $event = $this->event($host, [
            'enrollment_starts_at' => now()->addDay()->toDateString(),
            'enrollment_ends_at' => now()->addWeeks(2)->toDateString(),
            'weigh_in_at' => now()->addWeeks(3)->subDay()->toDateTimeString(),
        ]);

        $keys = collect(app(\App\Events\EventTypeRegistry::class)->for($event)->notificationSchedule($event))
            ->pluck('key')->all();

        $this->assertSame([
            'created', 'enrolment_opens', 'enrolment_closing', 'enrolment_closed', 'event_day', 'weigh_in',
        ], $keys);
    }

    public function test_a_milestone_that_is_not_yet_due_does_not_fire(): void
    {
        Queue::fake();
        [$host] = $this->taekwondoClub();
        $event = $this->event($host, ['enrollment_starts_at' => now()->addWeek()->toDateString()]);

        $fired = $this->notifier()->fireDue($event);

        $this->assertArrayHasKey('created', $fired, 'creation fires immediately');
        $this->assertArrayNotHasKey('enrolment_opens', $fired, 'a future milestone waits');
    }

    public function test_a_milestone_fires_exactly_once_however_often_the_runner_repeats(): void
    {
        Queue::fake();
        [$host] = $this->taekwondoClub();
        $event = $this->event($host);

        $first = $this->notifier()->fireOnce($event, 'created');
        $second = $this->notifier()->fireOnce($event, 'created');
        $third = $this->notifier()->fireDue($event);

        $this->assertNotNull($first);
        $this->assertNull($second, 'a second attempt must be refused by the ledger');
        $this->assertArrayNotHasKey('created', $third);
        $this->assertSame(1, EventNotificationSent::where('event_id', $event->id)->where('milestone', 'created')->count());
    }

    public function test_delivery_is_queued_and_chunked_never_inline(): void
    {
        Queue::fake();
        [$host] = $this->taekwondoClub();
        $event = $this->event($host);

        $this->notifier()->fireOnce($event, 'created');

        Queue::assertPushed(DeliverEventNotification::class);
    }

    public function test_a_cancelled_event_stops_notifying(): void
    {
        Queue::fake();
        [$host] = $this->taekwondoClub();
        $event = $this->event($host, ['status' => 'cancelled']);

        $this->assertNull($this->notifier()->fireOnce($event, 'created'));
        Queue::assertNothingPushed();
    }

    public function test_the_fan_out_cap_truncates_and_records_what_it_dropped(): void
    {
        Queue::fake();
        config(['event_notifications.max_recipients' => 2]);

        [$host] = $this->taekwondoClub();
        for ($i = 0; $i < 4; $i++) {
            $u = $this->createUser();
            $u->memberClubs()->syncWithoutDetaching([$host->id => ['status' => 'active']]);
        }

        $sent = $this->notifier()->fireOnce($this->event($host), 'created');

        $this->assertSame(2, $sent);
        $ledger = EventNotificationSent::where('milestone', 'created')->first();
        $this->assertSame(2, $ledger->recipients);
        $this->assertGreaterThan(0, $ledger->skipped, 'what was dropped must be recorded, never silent');
    }

    /* ---------------- Reminders reach the right people ---------------- */

    public function test_the_event_day_reminder_goes_to_registrants_not_the_whole_country(): void
    {
        Queue::fake();
        [$host, $bystander] = $this->taekwondoClub();
        $event = $this->event($host, ['date' => now()->toDateString(), 'scope' => 'nationwide']);

        $entrant = $this->createUser();
        $entrant->memberClubs()->syncWithoutDetaching([$host->id => ['status' => 'active']]);
        ClubEventRegistration::create([
            'event_id' => $event->id, 'user_id' => $entrant->id,
            'role' => 'participant', 'status' => 'joined', 'paid' => true, 'registered_at' => now(),
        ]);

        $audience = app(AudienceResolver::class)->registrants($event->fresh());

        $this->assertContains($entrant->id, $audience);
        $this->assertNotContains($bystander->id, $audience);
    }

    public function test_the_scheduler_command_runs_clean(): void
    {
        Queue::fake();
        [$host] = $this->taekwondoClub();
        $this->event($host);

        $this->artisan('events:send-notifications')->assertSuccessful();
    }

    /* ---------------- Broadcast is a privilege ---------------- */

    public function test_an_ordinary_member_cannot_create_a_country_wide_event(): void
    {
        [$club, $member] = $this->taekwondoClub();

        $this->actingAs($member)->postJson('/me/events', [
            'tenant_id' => $club->id,
            'title' => 'Mass Mail',
            'event_type' => 'race',
            'scope' => 'nationwide',
            'date' => now()->addWeek()->toDateString(),
            'start_time' => '09:00',
            'participant_free' => true,
            'spectator_enabled' => false,
        ])->assertForbidden();

        $this->assertDatabaseMissing('club_events', ['title' => 'Mass Mail']);
    }

    public function test_the_club_owner_may_create_a_country_wide_event(): void
    {
        Queue::fake();
        [$club] = $this->taekwondoClub();
        $owner = \App\Members\Models\User::find($club->owner_user_id);
        $owner->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $this->actingAs($owner->fresh())->postJson('/me/events', [
            'tenant_id' => $club->id,
            'title' => 'Open Nationals',
            'event_type' => 'race',
            'scope' => 'nationwide',
            'date' => now()->addWeek()->toDateString(),
            'start_time' => '09:00',
            'participant_free' => true,
            'spectator_enabled' => false,
        ])->assertOk();

        $this->assertDatabaseHas('club_events', ['title' => 'Open Nationals', 'scope' => 'nationwide']);
    }

    public function test_creating_an_event_announces_it_once(): void
    {
        Queue::fake();
        [$club] = $this->taekwondoClub();
        $owner = \App\Members\Models\User::find($club->owner_user_id);
        $owner->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $this->actingAs($owner->fresh())->postJson('/me/events', [
            'tenant_id' => $club->id,
            'title' => 'Club Night',
            'event_type' => 'class',
            'scope' => 'internal',
            'date' => now()->addWeek()->toDateString(),
            'start_time' => '18:00',
            'participant_free' => true,
            'spectator_enabled' => false,
        ])->assertOk();

        $event = ClubEvent::where('title', 'Club Night')->firstOrFail();
        $this->assertSame(1, EventNotificationSent::where('event_id', $event->id)->where('milestone', 'created')->count());
    }
}
