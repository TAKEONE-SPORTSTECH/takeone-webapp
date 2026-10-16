<?php

namespace Tests\Feature\Events;

use Tests\Feature\Contracts\ContractTestCase;

/**
 * The tablet's draw screen at the organiser's desk
 * (App\Events\Controllers\DrawDeskController): the same page as
 * /tablet/{token}/draw, signed in as the viewer, opened on a division.
 */
class DrawDeskTest extends ContractTestCase
{
    public function test_the_organiser_gets_the_tablet_screen_on_their_own_doors(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        [$division] = $this->drawnDivision($event, $club);

        $this->actingAs($organiser)
            ->get("/me/events/{$event->uuid}/draw-desk?division={$division->id}")
            ->assertOk()
            ->assertSee("setMode('winner')", false)
            ->assertSee("setMode('numbers')", false)
            // Never a device token: every door is the organiser's own.
            ->assertDontSee('/tablet/', false)
            ->assertSee('draw-desk\\/numbers', false);
    }

    public function test_someone_who_may_not_arrange_is_sent_to_the_ordinary_draw_and_refused_the_number_doors(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        [$division] = $this->drawnDivision($event, $club);
        $stranger = $this->createUser();

        $this->actingAs($stranger)
            ->get("/me/events/{$event->uuid}/draw-desk?division={$division->id}")
            ->assertRedirect(route('me.events.bracket.manage', ['event' => $event->uuid, 'division' => $division->id]));

        $this->actingAs($stranger)->getJson("/me/events/{$event->uuid}/draw-desk/numbers/1")->assertForbidden();
        $this->actingAs($stranger)->putJson("/me/events/{$event->uuid}/draw-desk/numbers", ['match' => 1, 'court' => 'Mat 1', 'no' => 1])->assertForbidden();
        $this->actingAs($stranger)->deleteJson("/me/events/{$event->uuid}/draw-desk/numbers/1")->assertForbidden();
    }

    public function test_back_returns_to_the_federation_screen_and_nowhere_else(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $desk = "/me/events/{$event->uuid}/draw-desk?back=";

        $this->actingAs($organiser)->get($desk.urlencode('/federation/events/abc-123/divisions'))
            ->assertOk()->assertSee(url('/federation/events/abc-123/divisions'), false);

        foreach (['https://evil.example/federation', '//evil.example/federation', '/federation/../me', '/me/settings', 'javascript:alert(1)'] as $bad) {
            $this->actingAs($organiser)->get($desk.urlencode($bad))
                ->assertOk()->assertDontSee('evil.example', false)
                ->assertSee(route('me.events.show', ['event' => $event->uuid]), false);
        }
    }

    /**
     * Add and delete a division from the desk (owner, 2026-10-16): offered to
     * the organiser in edit mode; a new one with no name is named by its group
     * letter and range; deleting releases its athletes and is refused once a
     * bout has been fought; nobody else may do either.
     */
    public function test_the_organiser_adds_and_deletes_divisions_from_the_desk(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        [$division] = $this->drawnDivision($event, $club);

        $this->actingAs($organiser)->get("/me/events/{$event->uuid}/draw-desk?division={$division->id}")
            ->assertOk()->assertSee('openDivMenu()', false)->assertSee('openDelDiv()', false);

        $made = $this->actingAs($organiser)->postJson(route('me.events.divisions.store', $event->uuid), [
            'name' => '', 'auto_name' => true, 'is_heading' => false, 'format' => 'knockout',
            'gender' => 'Male', 'min_age' => 5, 'max_age' => 6, 'min_weight' => 17, 'max_weight' => 23,
        ])->assertOk()->json('division');
        $this->assertSame('Group B : 5-6y, 17-23kg', $made['name']);

        // The drawn one: its athletes are released, not lost.
        $entrants = \App\Events\Models\ClubEventRegistration::where('category_id', $division->id)->pluck('id');
        $this->assertNotEmpty($entrants);
        $this->actingAs($organiser)->deleteJson(route('me.events.divisions.destroy', [$event->uuid, $division->id]), ['release' => true])->assertOk();
        $this->assertNull(\App\Events\Models\EventCategory::find($division->id));
        $this->assertSame(0, \App\Events\Models\ClubEventRegistration::whereIn('id', $entrants)->whereNotNull('category_id')->count(), 'athletes stay, in no division');
        $this->assertSame($entrants->count(), \App\Events\Models\ClubEventRegistration::whereIn('id', $entrants)->count());

        $stranger = $this->createUser();
        $this->actingAs($stranger)->postJson(route('me.events.divisions.store', $event->uuid), ['name' => 'X', 'auto_name' => true])->assertForbidden();
        $this->actingAs($stranger)->deleteJson(route('me.events.divisions.destroy', [$event->uuid, $made['id']]), ['release' => true])->assertForbidden();
        $this->assertNotNull(\App\Events\Models\EventCategory::find($made['id']));
    }

    public function test_a_division_with_a_fought_bout_cannot_be_deleted(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        [$division] = $this->drawnDivision($event, $club);
        $bout = $division->matches()->whereNotNull('a_name')->whereNotNull('b_name')->first();
        $bout->forceFill(['winner' => 'a', 'status' => 'done'])->save();

        $this->actingAs($organiser)->deleteJson(route('me.events.divisions.destroy', [$event->uuid, $division->id]), ['release' => true])->assertStatus(422);
        $this->assertNotNull(\App\Events\Models\EventCategory::find($division->id));
    }
}
