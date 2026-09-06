<?php

namespace Tests\Feature\Contracts;

/**
 * RESPONSE-SHAPE CONTRACT — GET /me/events/{event:uuid}/brackets/data
 * (route `me.events.bracket.data`, PersonalEventController@bracketData).
 *
 * CONSUMER: <x-tournament-bracket> and the React island that will replace its
 * runtime. The renderer is sport-agnostic — it knows ONLY this payload — so
 * the shape here is the whole contract between every event package and every
 * bracket screen.
 *
 * ANY CHANGE TO THIS SHAPE IS A BREAKING CHANGE for that island. Bracket
 * geometry is implicit in the ordering of `rounds` and `matches` (bout i feeds
 * bout floor(i/2) of the next round); re-ordering them silently draws the
 * wrong connectors rather than failing.
 */
class BracketDataContractTest extends ContractTestCase
{
    public function test_bracket_data_returns_the_documented_shape(): void
    {
        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);

        $response = $this->actingAs($organiser->fresh())
            ->getJson("/me/events/{$event->uuid}/brackets/data")
            ->assertOk();

        $response->assertJsonStructure([
            'divisions' => [
                '*' => [
                    'id', 'name', 'weight_class', 'status', 'draw_state',
                    'entrants',
                    'rounds' => [
                        '*' => [
                            'name',
                            'matches' => [
                                '*' => [
                                    'id', 'no', 'court', 'time', 'status', 'winner',
                                    'a' => ['competitor_id', 'name', 'seed', 'country', 'score', 'provisional', 'photo'],
                                    'b' => ['competitor_id', 'name', 'seed', 'country', 'score', 'provisional', 'photo'],
                                ],
                            ],
                        ],
                    ],
                    'bench',
                    'podium',
                ],
            ],
            'can_arrange',
            'locked',
        ]);

        $this->assertSame(['divisions', 'can_arrange', 'locked'], array_keys($response->json()));
        $this->assertIsBool($response->json('can_arrange'));
        $this->assertIsBool($response->json('locked'));

        $division = $response->json('divisions.0');
        $this->assertSame('Senior Men -58 kg', $division['name']);
        $this->assertSame(4, $division['entrants']);
        $this->assertIsArray($division['rounds']);
        $this->assertIsArray($division['bench']);
        $this->assertIsArray($division['podium']);

        $side = $division['rounds'][0]['matches'][0]['a'];
        $this->assertIsBool($side['provisional']);

        // The organiser may arrange a draw that has not started.
        $this->assertTrue($response->json('can_arrange'));
        $this->assertFalse($response->json('locked'));
    }

    /**
     * The bracket payload is a reading surface: it carries who is fighting and
     * where, and nothing about money, weight, contact details or membership.
     */
    public function test_bracket_data_exposes_nothing_sensitive(): void
    {
        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);

        $body = $this->actingAs($organiser->fresh())
            ->getJson("/me/events/{$event->uuid}/brackets/data")
            ->assertOk()
            ->getContent();

        $this->assertLeaksNothingSensitive($body, [
            '"email"', '"mobile"', '"weight"', '"paid"', '"fee"', '"user_id"', '"birthdate"',
        ]);
    }

    /**
     * AUTHORIZATION — an outsider with no relationship to the event or its
     * club is denied. A JSON request gets a real 403 (a browser GET on the
     * same page would be redirected to '/' by the global handler).
     */
    public function test_an_outsider_is_denied(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);

        $outsider = $this->createUser();
        $this->clubFor($outsider, ['slug' => 'other-club-'.uniqid()]);

        $this->actingAs($outsider->fresh())
            ->getJson("/me/events/{$event->uuid}/brackets/data")
            ->assertForbidden();
    }

    /** A JSON request from a guest is refused (401), never served. */
    public function test_a_guest_is_refused(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);

        $this->getJson("/me/events/{$event->uuid}/brackets/data")->assertUnauthorized();
    }
}
