<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Events\Models\EventOfficial;
use Tests\TestCase;

/**
 * The scoring console's Players tab: an ORGANISER finds an entrant and
 * corrects them; an official who may only score cannot; and no entry of
 * another event can be reached through this event's address.
 */
class KaratePlayersTabTest extends TestCase
{
    private function scenario(): array
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);

        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id,
            'title' => 'Players Open', 'event_type' => 'championship', 'sport' => 'karate',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeeks(2)->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ]);

        $athlete = $this->createUser(['full_name' => 'Ali Hassan']);
        $entry = ClubEventRegistration::create([
            'event_id' => $event->id, 'user_id' => $athlete->id,
            'role' => 'participant', 'status' => 'registered',
        ]);

        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men', 'sort_order' => 1]);
        EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id, 'round' => 'Final', 'phase' => 'finals',
            'slot' => 0, 'a_competitor_id' => $entry->id, 'a_name' => 'Ali Hassan', 'b_name' => 'Bader',
            'court' => 'Mat 1', 'status' => 'upcoming',
        ]);

        return [$owner, $event->fresh(), $entry];
    }

    public function test_an_organiser_lists_and_corrects_an_entrant(): void
    {
        [$owner, $event, $entry] = $this->scenario();

        $this->actingAs($owner)->getJson("/karate/control/{$event->uuid}/players")
            ->assertOk()->assertJsonPath('players.0.name', 'Ali Hassan');

        $this->actingAs($owner)->putJson("/karate/control/{$event->uuid}/players/{$entry->id}", [
            'weight' => 61.5, 'mat' => 'Mat 1',
        ])->assertOk()->assertJsonPath('player.weight', 61.5);

        $this->assertEquals(61.5, (float) $entry->fresh()->weight);
    }

    public function test_an_official_who_may_only_score_is_refused(): void
    {
        [, $event, $entry] = $this->scenario();

        $jury = $this->createUser();
        EventOfficial::create(['event_id' => $event->id, 'user_id' => $jury->id, 'role' => EventOfficial::ROLE_JURY]);

        $this->actingAs($jury)->getJson("/karate/control/{$event->uuid}/players")->assertForbidden();
        $this->actingAs($jury)->putJson("/karate/control/{$event->uuid}/players/{$entry->id}", ['weight' => 70])
            ->assertForbidden();

        $this->assertNull($entry->fresh()->weight);
    }

    public function test_another_events_entry_is_not_reachable(): void
    {
        [$owner, $event] = $this->scenario();
        [, , $foreign] = $this->scenario();

        $this->actingAs($owner)->getJson("/karate/control/{$event->uuid}/players/{$foreign->id}")->assertNotFound();
        $this->actingAs($owner)->putJson("/karate/control/{$event->uuid}/players/{$foreign->id}", ['weight' => 70])
            ->assertNotFound();

        $this->assertNull($foreign->fresh()->weight);
    }

    public function test_switching_from_a_date_of_birth_to_an_age_and_setting_nationality(): void
    {
        [$owner, $event, $entry] = $this->scenario();
        $entry->user->forceFill(['birthdate' => '2012-01-01', 'is_unclaimed' => true])->save();

        $this->actingAs($owner)->putJson("/karate/control/{$event->uuid}/players/{$entry->id}", [
            'birthdate' => null, 'age' => 13, 'nationality' => 'bh',
        ])->assertOk()->assertJsonPath('player.age', 13);

        $entry->refresh();
        $this->assertNull($entry->user->birthdate);
        $this->assertSame(13, (int) $entry->declared_age);
        $this->assertSame('BH', $entry->user->nationality);

        // …and a real date of birth supersedes the stated age again.
        $this->actingAs($owner)->putJson("/karate/control/{$event->uuid}/players/{$entry->id}", [
            'birthdate' => '2011-03-04',
        ])->assertOk();
        $this->assertNull($entry->fresh()->declared_age);
    }

    public function test_an_age_is_refused_when_a_date_of_birth_exists(): void
    {
        [$owner, $event, $entry] = $this->scenario();
        $entry->user->forceFill(['birthdate' => '2012-01-01'])->save();

        $this->actingAs($owner)->putJson("/karate/control/{$event->uuid}/players/{$entry->id}", ['age' => 9])
            ->assertStatus(422)->assertJsonPath('field', 'age');
    }
}
