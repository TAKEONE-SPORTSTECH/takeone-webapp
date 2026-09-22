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
                                    'a' => ['competitor_id', 'name', 'seed', 'country', 'score', 'provisional', 'photo', 'club', 'club_ab'],
                                    'b' => ['competitor_id', 'name', 'seed', 'country', 'score', 'provisional', 'photo', 'club', 'club_ab'],
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
            'can_manage',
        ]);

        $this->assertSame(
            ['divisions', 'can_arrange', 'locked', 'can_manage'],
            array_keys($response->json()),
        );
        $this->assertIsBool($response->json('can_arrange'));
        $this->assertIsBool($response->json('locked'));
        /* `can_manage` joined the payload on 2026-09-20 with the redesigned
           board: it decides whether a winner, a score and a name are OFFERED
           on the card itself. It is not a boundary — me.events.outcome and
           me.events.entrant.update each ask for the same authority again —
           and the organiser here holds it. */
        $this->assertIsBool($response->json('can_manage'));
        $this->assertTrue($response->json('can_manage'));

        $division = $response->json('divisions.0');
        $this->assertSame('Senior Men -58 kg', $division['name']);
        $this->assertSame(4, $division['entrants']);
        $this->assertIsArray($division['rounds']);
        $this->assertIsArray($division['bench']);
        $this->assertIsArray($division['podium']);

        $side = $division['rounds'][0]['matches'][0]['a'];
        $this->assertIsBool($side['provisional']);
        /* The club under the name. Present as a key on every corner even when
           the competitor competes for nobody — the board draws a second line
           only when there is one, and an absent KEY would make that a
           different question from an absent value. */
        $this->assertArrayHasKey('club', $side);
        $this->assertArrayHasKey('club_ab', $side);

        // The organiser may arrange a draw that has not started.
        $this->assertTrue($response->json('can_arrange'));
        $this->assertFalse($response->json('locked'));
    }

    /**
     * THE BOARD'S OWN WRITE ADDRESSES.
     *
     * The draw stage runs the design file `drafts/Combat Sports Bracket.html`
     * as itself, and `public/bracket/takeone-bridge.js` is what makes it real:
     * it reads this payload and posts changes back through the two addresses
     * the page hands it in `window.TAKEONE_BRACKET`.
     *
     * CLAUDE.md → Navigation Integrity: "JS-built URLs must match a real route
     * pattern". `outcomeUrl` is a template whose `__ID__` the bridge replaces
     * with a bout id, so a route rename would otherwise ship a board whose
     * every result 404s — and nothing would fail until somebody tapped a
     * corner on an event day.
     */
    public function test_the_board_hands_an_organiser_working_write_addresses(): void
    {
        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);

        $html = $this->actingAs($organiser->fresh())
            ->get("/me/events/{$event->uuid}/brackets/manage")
            ->assertOk()
            ->getContent();

        foreach (['outcomeUrl', 'arrangeUrl'] as $key) {
            $this->assertMatchesRegularExpression(
                '/'.$key.':\s*"([^"]+)"/', $html, $key.' is not on the draw stage.',
            );
            preg_match('/'.$key.':\s*"([^"]+)"/', $html, $m);
            $url = str_replace('\/', '/', $m[1]);

            // Resolves to a registered route once the sentinel is a real id —
            // which is exactly what the bridge does before it posts.
            $path = parse_url(str_replace('__ID__', '1', $url), PHP_URL_PATH);

            $matched = collect(app('router')->getRoutes()->getRoutes())
                ->first(fn ($route) => preg_match(
                    '#^'.preg_replace('#\{[^}]+\}#', '[^/]+', $route->uri()).'$#',
                    ltrim((string) $path, '/'),
                ) === 1);

            $this->assertNotNull($matched, $key.' points at no route: '.$path);
        }
    }

    /**
     * ...and hands NEITHER of them to a reader who may not manage the event.
     *
     * Both endpoints re-authorise for themselves, so this is defence in depth
     * rather than the defence — but an address nobody may use has no business
     * in a stranger's page source.
     */
    public function test_a_reader_is_handed_no_write_addresses(): void
    {
        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);

        $stranger = $this->createUser(['full_name' => 'Visiting Coach']);

        $html = $this->actingAs($stranger->fresh())
            ->get("/me/events/{$event->uuid}/brackets")
            ->getContent();

        foreach (['outcomeUrl: "', 'arrangeUrl: "'] as $key) {
            $this->assertStringNotContainsString($key, $html, $key.' leaked to a reader.');
        }
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
