<?php

namespace Tests\Feature\Events;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Members\Models\HealthRecord;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use Tests\TestCase;

/**
 * "Who's joined" — the reading-only roster.
 *
 * Two tabs over one entrant list: the athletes, and the clubs they came from.
 * The rules this file protects:
 *
 *   - it is READING only, for everyone, including the organiser
 *   - spectators are not on it; they did not enter a competition
 *   - a club is listed because one of ITS athletes entered, so the two tabs can
 *     never disagree about who is here
 *   - every row leads somewhere already public — a profile, a club page — and
 *     the athlete link uses the uuid, never the numeric id
 */
class EventPeoplePageTest extends TestCase
{
    private Tenant $club;

    private User $organiser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organiser = $this->createUser(['full_name' => 'Master Kim']);
        $this->club = $this->createClub($this->organiser, [
            'club_name' => 'Emperor Taekwondo', 'country' => 'BH', 'currency' => 'BHD',
        ]);
        $this->organiser->memberClubs()->syncWithoutDetaching([$this->club->id => ['status' => 'active']]);
    }

    private function event(array $attrs = []): ClubEvent
    {
        return ClubEvent::create(array_merge([
            'tenant_id' => $this->club->id,
            'created_by' => $this->organiser->id,
            'title' => 'Spring Open',
            'event_type' => 'championship',
            'sport' => 'taekwondo',
            'scope' => 'internal',
            'date' => now()->addWeeks(2)->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'active',
            'is_archived' => false,
        ], $attrs));
    }

    private function division(ClubEvent $event): EventCategory
    {
        return EventCategory::create([
            'event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1,
            'status' => 'enrolling',
        ]);
    }

    private function member(string $name, ?Tenant $club = null): User
    {
        $user = $this->createUser([
            'full_name' => $name,
            'gender' => 'Male',
            'birthdate' => now()->subYears(25)->toDateString(),
        ]);
        $user->memberClubs()->syncWithoutDetaching([($club ?? $this->club)->id => ['status' => 'active']]);
        HealthRecord::create(['user_id' => $user->id, 'weight' => 57, 'recorded_at' => now()]);

        return $user->fresh();
    }

    private function entry(ClubEvent $event, EventCategory $category, User $athlete, array $attrs = []): ClubEventRegistration
    {
        return ClubEventRegistration::create(array_merge([
            'event_id' => $event->id,
            'user_id' => $athlete->id,
            'category_id' => $category->id,
            'role' => 'participant',
        ], $attrs));
    }

    public function test_it_lists_the_athletes_and_links_each_one_to_their_public_profile(): void
    {
        $event = $this->event();
        $category = $this->division($event);
        $athlete = $this->member('Athlete One');
        $this->entry($event, $category, $athlete);

        $this->actingAs($this->organiser)
            ->get("/me/events/{$event->uuid}/people")
            ->assertOk()
            ->assertSee('Athlete One')
            // The public key, never the numeric id.
            ->assertSee(route('people.show', $athlete->uuid))
            ->assertDontSee(route('people.show', $athlete->id));
    }

    public function test_it_lists_the_clubs_behind_the_athletes_and_links_to_the_club_page(): void
    {
        $event = $this->event();
        $category = $this->division($event);
        $this->entry($event, $category, $this->member('Athlete One'));
        $this->entry($event, $category, $this->member('Athlete Two'));

        $this->actingAs($this->organiser)
            ->get("/me/events/{$event->uuid}/people")
            ->assertOk()
            ->assertSee('Emperor Taekwondo')
            ->assertSee(route('clubs.show', ['country' => 'bh', 'slug' => $this->club->slug]));
    }

    /**
     * A club with nobody entered is not on the list. The clubs tab is built FROM
     * the athletes, so it can only ever name a club that has someone here.
     */
    public function test_a_club_with_nobody_in_the_event_is_not_listed(): void
    {
        $bystander = $this->createUser(['full_name' => 'Other Owner']);
        $otherClub = $this->createClub($bystander, ['club_name' => 'Absent Dojang', 'country' => 'BH']);

        $event = $this->event();
        $category = $this->division($event);
        $this->entry($event, $category, $this->member('Athlete One'));

        $this->actingAs($this->organiser)
            ->get("/me/events/{$event->uuid}/people")
            ->assertOk()
            ->assertSee('Emperor Taekwondo')
            ->assertDontSee('Absent Dojang');
    }

    public function test_spectators_are_not_on_the_page(): void
    {
        $event = $this->event(['spectator_enabled' => true]);
        $category = $this->division($event);
        $this->entry($event, $category, $this->member('Athlete One'));

        $watcher = $this->member('Ticket Holder');
        ClubEventRegistration::create([
            'event_id' => $event->id, 'user_id' => $watcher->id, 'role' => 'spectator',
        ]);

        $this->actingAs($this->organiser)
            ->get("/me/events/{$event->uuid}/people")
            ->assertOk()
            ->assertSee('Athlete One')
            ->assertDontSee('Ticket Holder');
    }

    /**
     * A member who has not published their picture gets the silhouette. Putting
     * someone on a roster everyone entered can read is a publication.
     */
    public function test_an_unpublished_profile_picture_is_not_shown(): void
    {
        $event = $this->event();
        $category = $this->division($event);

        $shy = $this->member('Private Athlete');
        $shy->update(['profile_picture' => 'people/x/secret.jpg', 'profile_picture_is_public' => false]);
        $this->entry($event, $category, $shy);

        $this->actingAs($this->organiser)
            ->get("/me/events/{$event->uuid}/people")
            ->assertOk()
            ->assertSee('Private Athlete')
            ->assertDontSee('secret.jpg');
    }

    /** An outsider cannot read the roster of an event they cannot see. */
    public function test_someone_who_cannot_see_the_event_cannot_read_its_roster(): void
    {
        $event = $this->event(['scope' => 'internal']);
        $category = $this->division($event);
        $this->entry($event, $category, $this->member('Athlete One'));

        $outsiderOwner = $this->createUser(['full_name' => 'Outsider Owner']);
        $otherClub = $this->createClub($outsiderOwner, ['club_name' => 'Far Away Club', 'country' => 'BH']);
        $outsider = $this->member('Complete Stranger', $otherClub);

        $this->actingAs($outsider)
            ->get("/me/events/{$event->uuid}/people")
            ->assertRedirect('/');
    }
}
