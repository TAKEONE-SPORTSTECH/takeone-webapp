<?php

namespace Tests\Feature\Events;

use Tests\Feature\Contracts\ContractTestCase;

/**
 * The draw menu's person search (App\Events\Controllers\DrawPersonController):
 * an organiser finds which division an entrant is in by name, phone, email or
 * CPR. Nobody else may ask, an entrant of another event is never found, and
 * the keys matched on never come back in the answer.
 */
class DrawPersonSearchTest extends ContractTestCase
{
    public function test_the_organiser_finds_an_entrant_by_name_phone_email_or_cpr(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        [$division, $athletes] = $this->drawnDivision($event, $club);

        $athletes[1]->forceFill([
            'email' => 'fighter.two@example.test',
            'mobile' => ['code' => '+973', 'number' => '33445566'],
            'documents' => [['type' => 'CPR', 'number' => '990112345']],
        ])->save();

        $url = "/me/events/{$event->uuid}/brackets/find-person?q=";
        foreach (['Athlete 2', '33445566', '+973 3344 5566', 'FIGHTER.two@example.test', '990112345'] as $q) {
            $res = $this->actingAs($organiser)->getJson($url.urlencode($q))->assertOk();
            $this->assertSame(['Athlete 2'], array_column($res->json('people'), 'name'), "query: $q");
            $this->assertSame([$division->id], $res->json('people.0.divisions'));

            // The keys are searched on, never handed back.
            $body = $res->getContent();
            $this->assertStringNotContainsString('33445566', $body);
            $this->assertStringNotContainsString('fighter.two', $body);
            $this->assertStringNotContainsString('990112345', $body);
        }

        // An email is matched whole, never by fragment.
        $this->actingAs($organiser)->getJson($url.urlencode('fighter.two@'))->assertOk()->assertJsonPath('people', []);
    }

    public function test_the_switch_is_drawn_only_when_the_host_hands_it_a_search_door(): void
    {
        // The draw stage passes a door only to the organiser ($dsPersonUrl);
        // the split view's picker and every other reader pass none.
        $with = view('components.bracket.division-filter', ['count' => "'1 / 1'", 'personUrl' => 'https://example.test/find-person'])->render();
        $this->assertStringContainsString('bi-person-standing', $with);
        $this->assertStringContainsString('find-person', $with);

        $without = view('components.bracket.division-filter', ['count' => "'1 / 1'"])->render();
        $this->assertStringNotContainsString('bi-person-standing', $without);
        $this->assertStringNotContainsString('DrawPersonSearch', $without);
    }

    public function test_nobody_but_the_organiser_may_search_and_other_events_stay_out(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);

        $stranger = $this->createUser();
        $this->actingAs($stranger)->getJson("/me/events/{$event->uuid}/brackets/find-person?q=Athlete")->assertForbidden();

        // Another organiser's event: its entrants are not this event's.
        $other = $this->createUser();
        $otherClub = $this->clubFor($other);
        $otherEvent = $this->championship($otherClub, $other);
        $this->actingAs($other)->getJson("/me/events/{$otherEvent->uuid}/brackets/find-person?q=Athlete")
            ->assertOk()->assertJsonPath('people', []);
    }
}
