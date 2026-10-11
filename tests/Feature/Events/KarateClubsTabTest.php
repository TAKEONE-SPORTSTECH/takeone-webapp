<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventClub;
use App\Events\Models\EventOfficial;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use Tests\TestCase;

/**
 * The scoring console's Clubs tab, and the event Clubs page whose save logic
 * it now shares (App\Events\Support\Clubs\EventClubEditor): an organiser
 * corrects a club written down for the event; a club with its own account is
 * never edited; an official who may only score is refused; another event's
 * club is unreachable.
 */
class KarateClubsTabTest extends TestCase
{
    private function scenario(): array
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);

        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id,
            'title' => 'Clubs Open', 'event_type' => 'championship', 'sport' => 'karate',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeeks(2)->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ]);

        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men', 'sort_order' => 1]);
        EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id, 'round' => 'Final', 'phase' => 'finals',
            'slot' => 0, 'a_name' => 'Ali', 'b_name' => 'Bader', 'court' => 'Mat 1', 'status' => 'upcoming',
        ]);

        $written = EventClub::create(['event_id' => $event->id, 'name' => 'Okami', 'country' => 'BH', 'state' => 'listed']);
        $other = EventClub::create(['event_id' => $event->id, 'name' => 'Shogun', 'country' => 'BH', 'state' => 'listed']);
        $platform = EventClub::create(['event_id' => $event->id, 'tenant_id' => $club->id, 'name' => $club->club_name, 'state' => 'accepted']);

        return [$owner, $event->fresh(), $written, $other, $platform];
    }

    public function test_an_organiser_lists_and_renames_a_written_down_club(): void
    {
        [$owner, $event, $written] = $this->scenario();

        $this->actingAs($owner)->getJson("/karate/control/{$event->uuid}/clubs")
            ->assertOk()->assertJsonCount(3, 'clubs');

        $this->actingAs($owner)->putJson("/karate/control/{$event->uuid}/clubs/{$written->uuid}", [
            'name' => 'Okami Dojo', 'instagram' => '@okami', 'country' => 'sa',
        ])->assertOk()->assertJsonPath('club.name', 'Okami Dojo');

        $written->refresh();
        $this->assertSame('Okami Dojo', $written->name);
        $this->assertSame('https://instagram.com/okami', $written->instagram);
        $this->assertSame('SA', $written->country);
    }

    public function test_a_club_with_its_own_account_and_a_clashing_name_are_refused(): void
    {
        [$owner, $event, $written, $other, $platform] = $this->scenario();

        $this->actingAs($owner)->putJson("/karate/control/{$event->uuid}/clubs/{$platform->uuid}", ['name' => 'Taken Over'])
            ->assertStatus(422);
        $this->actingAs($owner)->putJson("/karate/control/{$event->uuid}/clubs/{$written->uuid}", ['name' => 'shogun'])
            ->assertStatus(422)->assertJsonPath('field', 'name');
        $this->actingAs($owner)->putJson("/karate/control/{$event->uuid}/clubs/{$written->uuid}", [
            'name' => 'Okami', 'instagram' => 'javascript:alert(1)',
        ])->assertStatus(422)->assertJsonPath('field', 'instagram');

        $this->assertSame('Okami', $written->fresh()->name);
    }

    public function test_an_official_who_may_only_score_is_refused(): void
    {
        [, $event, $written] = $this->scenario();

        $jury = $this->createUser();
        EventOfficial::create(['event_id' => $event->id, 'user_id' => $jury->id, 'role' => EventOfficial::ROLE_JURY]);

        $this->actingAs($jury)->getJson("/karate/control/{$event->uuid}/clubs")->assertForbidden();
        $this->actingAs($jury)->putJson("/karate/control/{$event->uuid}/clubs/{$written->uuid}", ['name' => 'X Club'])
            ->assertForbidden();
        $this->assertSame('Okami', $written->fresh()->name);
    }

    public function test_another_events_club_is_not_reachable(): void
    {
        [$owner, $event] = $this->scenario();
        [, , $foreign] = $this->scenario();

        $this->actingAs($owner)->putJson("/karate/control/{$event->uuid}/clubs/{$foreign->uuid}", ['name' => 'Hijacked'])
            ->assertNotFound();
        $this->assertSame('Okami', $foreign->fresh()->name);
    }

    public function test_the_event_clubs_page_still_saves_through_the_shared_editor(): void
    {
        [$owner, $event, $written, , $platform] = $this->scenario();

        $this->actingAs($owner)->putJson("/me/events/{$event->uuid}/clubs/{$written->uuid}", [
            'name' => 'Okami Renamed', 'instagram' => 'instagram.com/okami.bh',
        ])->assertOk()->assertJsonPath('club.name', 'Okami Renamed');
        $this->assertSame('https://instagram.com/okami.bh', $written->fresh()->instagram);

        $this->actingAs($owner)->putJson("/me/events/{$event->uuid}/clubs/{$written->uuid}", ['name' => 'Shogun'])
            ->assertStatus(422)->assertJsonPath('field', 'name');
        $this->actingAs($owner)->putJson("/me/events/{$event->uuid}/clubs/{$platform->uuid}", ['name' => 'Nope'])
            ->assertStatus(422);
    }
}
