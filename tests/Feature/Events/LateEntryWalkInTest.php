<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventClub;
use Tests\Feature\Contracts\ContractTestCase;

/**
 * A walk-in written down from the draw's late-entry window
 * (me.events.entries.unnamed — the organiser's board and the scoring tablet).
 *
 * Owner, 2026-10-07: name, date of birth OR age, weight, club (the event's own
 * or a new one with a logo), nationality, gender and a photograph. Only the
 * name is required; an age is kept as an age and never becomes a birthdate.
 */
class LateEntryWalkInTest extends ContractTestCase
{
    /** A real 1×1 PNG, as the cropper would send it. */
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private function setUpEvent(): array
    {
        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        [$division] = $this->drawnDivision($event, $club);

        return [$event, $organiser->fresh(), $division];
    }

    public function test_every_fact_the_desk_knows_is_recorded(): void
    {
        [$event, $organiser, $division] = $this->setUpEvent();

        $res = $this->actingAs($organiser)->postJson(route('me.events.entries.unnamed', $event->uuid), [
            'full_name' => 'Walk In Fighter',
            'category_id' => $division->id,
            'gender' => 'Male',
            'birthdate' => now()->subYears(12)->toDateString(),
            'weight' => 41.5,
            'nationality' => 'bh',
            'photo' => self::PNG,
            'club_name' => 'Brand New Dojo',
            'club_logo' => self::PNG,
        ])->assertOk()->assertJsonPath('success', true);
        $this->assertNull($res->json('warning'));

        $entry = ClubEventRegistration::find($res->json('claim.competitor_id'));
        $this->assertEquals(41.5, $entry->weight);
        $this->assertNotNull($entry->photo, 'the photograph is on the entry');
        $this->assertSame('BH', $entry->user->nationality);
        $this->assertSame('Male', $entry->user->gender);

        // The new club exists, with its logo, and the entry fights for it.
        $club = EventClub::where('event_id', $event->id)->where('name', 'Brand New Dojo')->first();
        $this->assertNotNull($club);
        $this->assertNotNull($club->logo);

        // Typing the same club again reuses it rather than making a second.
        $this->actingAs($organiser)->postJson(route('me.events.entries.unnamed', $event->uuid), [
            'full_name' => 'Second Walk In', 'category_id' => $division->id, 'club_name' => 'brand new dojo',
        ])->assertOk();
        $this->assertSame(1, EventClub::where('event_id', $event->id)->whereRaw('lower(name) = ?', ['brand new dojo'])->count());
    }

    public function test_an_age_stays_an_age_and_the_name_is_all_that_is_required(): void
    {
        [$event, $organiser, $division] = $this->setUpEvent();

        $res = $this->actingAs($organiser)->postJson(route('me.events.entries.unnamed', $event->uuid), [
            'full_name' => 'Age Only', 'category_id' => $division->id, 'age' => 9,
        ])->assertOk();

        $entry = ClubEventRegistration::find($res->json('claim.competitor_id'));
        $this->assertSame(9, (int) $entry->declared_age);
        $this->assertNull($entry->user->birthdate, 'an age never becomes an invented birthdate');

        // A bad photograph enters the person anyway and says so.
        $res = $this->actingAs($organiser)->postJson(route('me.events.entries.unnamed', $event->uuid), [
            'full_name' => 'Bad Photo', 'category_id' => $division->id, 'photo' => 'data:image/png;base64,bm90IGFuIGltYWdl',
        ])->assertOk();
        $this->assertNotNull($res->json('warning'));

        // Out-of-range values are refused.
        $this->actingAs($organiser)->postJson(route('me.events.entries.unnamed', $event->uuid), [
            'full_name' => 'X Y', 'age' => 200,
        ])->assertStatus(422);
    }
}
