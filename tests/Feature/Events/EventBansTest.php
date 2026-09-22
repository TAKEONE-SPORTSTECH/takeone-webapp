<?php

namespace Tests\Feature\Events;

use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use App\Events\Models\ClubEvent;
use App\Events\Models\EventOfficial;
use App\Events\Models\EventParticipantBan;
use Tests\TestCase;

/**
 * Who is barred — the moderation list, and the door it is read through.
 *
 * ⚠️ This exists because the feature DISAPPEARED once. `me.events.ban.lift` and
 * the ban rows outlived the verification desk that was removed on 2026-09-03;
 * its blocked tab was the only screen that ever drew them, so for twelve days
 * nobody — organiser included — could see who was barred or let anybody back
 * in, and nothing failed to say so. The list went on being serialised into the
 * roster's client-side search index for a tab that page does not render, which
 * is the same names reaching more browsers than before, drawn on none of them.
 *
 * So the tests here are about both halves: the organiser can READ it and ACT on
 * it, and nobody else is handed it.
 */
class EventBansTest extends TestCase
{
    private function clubFor(User $user): Tenant
    {
        $club = $this->createClub($user, ['country' => 'BH', 'club_name' => 'Emperor Taekwondo']);
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $club;
    }

    private function event(Tenant $club, User $organiser): ClubEvent
    {
        return ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $organiser->id,
            'title' => 'Spring Open', 'event_type' => 'championship', 'sport' => 'taekwondo',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00', 'end_time' => '17:00',
        ]);
    }

    private function bar(ClubEvent $event, User $who, string $scope, User $by, ?string $reason = null): EventParticipantBan
    {
        return EventParticipantBan::create([
            'scope' => $scope,
            'event_id' => $scope === 'event' ? $event->id : null,
            'tenant_id' => $event->tenant_id,
            'user_id' => $who->id,
            'reason' => $reason,
            'created_by' => $by->id,
        ]);
    }

    public function test_the_organiser_reads_who_is_barred_and_why(): void
    {
        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $blocked = $this->createUser(['full_name' => 'Blocked Person']);
        $this->bar($event, $blocked, 'event', $organiser, 'withdrew twice after the draw was cut');

        $response = $this->actingAs($organiser->fresh())
            ->getJson("/me/events/{$event->uuid}/bans")
            ->assertOk()
            ->assertJson(['success' => true]);

        $row = $response->json('people.0');

        $this->assertSame('Blocked Person', $row['name']);
        $this->assertSame('event', $row['scope']);
        // The whole point of the screen: the reason has been recorded since the
        // beginning and no surface ever printed it.
        $this->assertSame('withdrew twice after the draw was cut', $row['reason']);
        $this->assertSame('Master Kim', $row['by']);
        $this->assertNotNull($row['at']);
    }

    /** A club-wide blacklist keeps somebody out of THIS event, so it is listed here. */
    public function test_a_club_wide_blacklist_is_listed_with_its_scope(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $banned = $this->createUser(['full_name' => 'Barred Everywhere']);
        $this->bar($event, $banned, 'club', $organiser);

        $this->actingAs($organiser->fresh())
            ->getJson("/me/events/{$event->uuid}/bans")
            ->assertOk()
            ->assertJsonPath('people.0.scope', 'club')
            ->assertJsonPath('people.0.reason', null)
            ->assertJsonPath('club', 'Emperor Taekwondo');
    }

    /**
     * One card per PERSON. Barred from the event AND blacklisted club-wide is
     * two rows and one human, and the card has to carry the WIDER of the two —
     * that is the bar that decides what happens to them.
     */
    public function test_someone_barred_twice_is_one_row_carrying_the_wider_scope(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $both = $this->createUser(['full_name' => 'Twice Barred']);
        $this->bar($event, $both, 'event', $organiser);
        $this->bar($event, $both, 'club', $organiser);

        $people = $this->actingAs($organiser->fresh())
            ->getJson("/me/events/{$event->uuid}/bans")
            ->assertOk()
            ->json('people');

        $this->assertCount(1, $people, 'one card per person');
        $this->assertSame('club', $people[0]['scope'], 'the wider bar is the one that decides');
        $this->assertTrue($people[0]['both']);
    }

    /** Their own choice about their own face is not suspended by being barred. */
    public function test_an_unpublished_profile_picture_is_not_handed_out(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $shy = $this->createUser(['full_name' => 'Private Person']);
        $shy->update(['profile_picture' => 'people/x/secret.jpg', 'profile_picture_is_public' => false]);
        $this->bar($event, $shy, 'event', $organiser);

        $response = $this->actingAs($organiser->fresh())
            ->getJson("/me/events/{$event->uuid}/bans")
            ->assertOk();

        $this->assertNull($response->json('people.0.photo'));
        $this->assertStringNotContainsString('secret.jpg', $response->getContent());
    }

    /**
     * Nobody but whoever runs the event. An appointed official may open the
     * console — this list is about people's conduct, not their competing, and
     * it is not part of the job they were appointed to.
     */
    public function test_nobody_but_the_organiser_may_read_it(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $blocked = $this->createUser(['full_name' => 'Blocked Person']);
        $this->bar($event, $blocked, 'event', $organiser, 'a reason nobody else may read');

        $member = $this->createUser();
        $member->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $this->actingAs($member->fresh())
            ->getJson("/me/events/{$event->uuid}/bans")
            ->assertForbidden();

        $official = $this->createUser();
        $official->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        EventOfficial::create([
            'event_id' => $event->id, 'user_id' => $official->id,
            'role' => EventOfficial::ROLE_WEIGH_IN, 'compensation' => 'volunteer',
        ]);

        $this->actingAs($official->fresh())
            ->getJson("/me/events/{$event->uuid}/bans")
            ->assertForbidden();
    }

    /** The console offers the row, and only to whoever may open what it opens. */
    public function test_the_console_carries_the_row_for_the_organiser_and_not_for_an_official(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        /*
         * Two decodings before the question is asked, both load-bearing:
         *
         *  · the component hands the URL to Alpine through @js(), which escapes
         *    every slash — so the page is decoded rather than the assertion
         *    loosened until it matches something;
         *  · the URL is asked for with its closing quote. `partials/
         *    event-show-script` carries an `unblock()` helper that builds
         *    `…/bans/' + id`, which CONTAINS `…/bans` — so the bare URL is a
         *    prefix match against a string every console has had all along, and
         *    the negative below would have passed for the wrong reason.
         */
        $html = $this->actingAs($organiser->fresh())
            ->get("/me/events/{$event->uuid}/manage")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            route('me.events.bans', $event->uuid)."'",
            str_replace('\\/', '/', $html),
        );

        $official = $this->createUser();
        $official->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        EventOfficial::create([
            'event_id' => $event->id, 'user_id' => $official->id,
            'role' => EventOfficial::ROLE_PAYMENTS, 'compensation' => 'volunteer',
        ]);

        // A control whose endpoint would refuse them is worse than no control.
        $theirs = $this->actingAs($official->fresh())
            ->get("/me/events/{$event->uuid}/manage")
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(
            route('me.events.bans', $event->uuid)."'",
            str_replace('\\/', '/', $theirs),
        );
    }

    /*
     * The x-data truncation guard that was here has moved to
     * Tests\Feature\Architecture\AlpineStateIntegrityTest, which runs it over
     * every organiser screen rather than this one console. It was written here
     * when this component shipped the bug; the sweep that followed found a
     * second, older instance on the event create/edit form, so the check
     * belongs to the technique rather than to this feature.
     */

    /** And the lift still works from it — the endpoint that had no door. */
    public function test_the_organiser_lets_someone_back_in(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $blocked = $this->createUser(['full_name' => 'Blocked Person']);
        $this->bar($event, $blocked, 'event', $organiser);

        $this->actingAs($organiser->fresh())
            ->deleteJson("/me/events/{$event->uuid}/bans/{$blocked->id}")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('event_participant_bans', ['user_id' => $blocked->id]);

        $this->actingAs($organiser->fresh())
            ->getJson("/me/events/{$event->uuid}/bans")
            ->assertOk()
            ->assertJsonCount(0, 'people');
    }
}
