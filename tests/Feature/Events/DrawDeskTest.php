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
}
