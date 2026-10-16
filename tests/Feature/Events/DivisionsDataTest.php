<?php

namespace Tests\Feature\Events;

use Tests\Feature\Contracts\ContractTestCase;

/**
 * me.events.divisions.data — the divisions editor's data as JSON, for the
 * federation dashboard's Divisions tab. Organiser only, like the page.
 */
class DivisionsDataTest extends ContractTestCase
{
    public function test_the_organiser_gets_every_division_and_the_editor_settings(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        [$division] = $this->drawnDivision($event, $club);

        $res = $this->actingAs($organiser)->getJson(route('me.events.divisions.data', $event->uuid))
            ->assertOk()->assertJsonPath('success', true);

        $this->assertSame([$division->id], array_column($res->json('rows'), 'id'));
        $this->assertSame(4, $res->json('rows.0.entrants'));
        $this->assertCount(3, $res->json('phases'));
        $this->assertSame(1, $res->json('days'));
        $this->assertNotEmpty($res->json('belts'), 'the sport\'s belt ladder');
        $this->assertIsArray($res->json('presets'));
        $this->assertArrayHasKey('weight', $res->json('asks'));
    }

    public function test_nobody_else_reads_it(): void
    {
        $organiser = $this->createUser();
        $event = $this->championship($this->clubFor($organiser), $organiser);

        $this->actingAs($this->createUser())->getJson(route('me.events.divisions.data', $event->uuid))->assertForbidden();
    }
}
