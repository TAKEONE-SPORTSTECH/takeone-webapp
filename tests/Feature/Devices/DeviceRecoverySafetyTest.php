<?php

namespace Tests\Feature\Devices;

use App\Scoreboard\Sports\BrazilianJiuJitsu\HallScreen\ScreenDevice as BjjScreenDevice;
use App\Scoreboard\Sports\Karate\HallScreen\CourtDisplayDevice as KarateDevice;
use App\Scoreboard\Sports\Taekwondo\HallScreen\CourtDisplayDevice as TaekwondoDevice;
use App\Events\Support\PendingScreen;
use App\Models\ClubEvent;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use Tests\TestCase;

/**
 * THE SAFETY NET FOR "Unattended Devices Must Always Recover" (CLAUDE.md, Part 4).
 *
 * CLAUDE.md points at `/tmp/takeone-smoke.php` as the verification for that
 * STRICT rule. That file does not exist on disk — so the one documented net
 * under the rule was missing entirely. This is it, rebuilt as a committed test
 * so it cannot go missing again and so a regression fails the build rather than
 * a competition morning.
 *
 * What the rule says, and what is asserted here, for EVERY device surface —
 * Taekwondo, Karate, Brazilian Jiu-Jitsu, and the shared /screen room:
 *
 *  1. A PAGE a device renders never 404s on a dead identity. It redirects, and
 *     the chain ends at the sport-neutral waiting room, which issues a fresh
 *     pairing code — a state somebody in the hall can act on.
 *  2. A JSON door a device POLLS does 404 on a dead identity. That 404 is the
 *     ONLY signal a client can use to tell "this identity is gone" from "the
 *     wifi dropped", and therefore the only thing that makes it discard its
 *     token and re-enrol.
 *  3. Every redirect chain terminates. In particular the documented hand-off
 *     hazard: tokenControl() sends a console it cannot open back to the board,
 *     so the board must NOT send it straight back (the canServeControl guard).
 *     Two pages redirecting to each other is an infinite loop on a screen
 *     nobody can stop — it shipped once in the BJJ package.
 *  4. One answer for every failure. A bad token, a revoked device and an
 *     unpaired one get the SAME response on a page route; differing replies
 *     tell a stranger which tokens are real.
 *
 * Nothing here modifies production code. Where current behaviour diverges from
 * the STRICT rule it is asserted AS IT IS and marked `// DIVERGENCE:` — this
 * test documents the platform, it does not fix it.
 *
 * All data is fake and created per test against the in-memory SQLite database
 * phpunit.xml pins. ⚠️ Run `php artisan config:clear` first — see CLAUDE.md.
 */
class DeviceRecoverySafetyTest extends TestCase
{
    private const MAT = 'Mat 1';

    /** A token that is well-formed (40 alnum, so it passes the route regex) and belongs to nothing. */
    private const DEAD = 'deadtokendeadtokendeadtokendeadtoken0001';

    /**
     * The device surfaces of each sport, by the shape the rule sorts them into.
     *
     * `pages` are rendered on the glass — they must never 404.
     * `json` are polled by the agent — they must 404 on a dead identity.
     *
     * @return array<string, array{device: class-string, pages: array<string,string>, json: array<string,string>}>
     */
    private function fleets(): array
    {
        return [
            'taekwondo' => [
                'device' => TaekwondoDevice::class,
                'pages' => [
                    'board' => '/court/{token}',
                    'console' => '/court/{token}/control',
                ],
                'json' => [
                    'status' => '/court/{token}/status',
                    'payload' => '/court/{token}/payload',
                    'link' => '/court/{token}/link',
                    'state' => '/court/{token}/state',
                ],
            ],
            'karate' => [
                'device' => KarateDevice::class,
                'pages' => [
                    'board' => '/karate/court/{token}',
                    'console' => '/karate/court/{token}/control',
                ],
                'json' => [
                    'status' => '/karate/court/{token}/status',
                    'payload' => '/karate/court/{token}/payload',
                    'link' => '/karate/court/{token}/link',
                    'state' => '/karate/court/{token}/state',
                    'audio' => '/karate/court/{token}/audio/intro',
                ],
            ],
            'bjj' => [
                'device' => BjjScreenDevice::class,
                'pages' => [
                    'board' => '/bjj/screen/{token}',
                    'overlay' => '/bjj/screen/{token}/overlay',
                    'console' => '/bjj/screen/{token}/control',
                ],
                'json' => [
                    'status' => '/bjj/screen/{token}/status',
                    'payload' => '/bjj/screen/{token}/payload',
                    'link' => '/bjj/screen/{token}/link',
                    'state' => '/bjj/screen/{token}/state',
                ],
            ],
        ];
    }

    private function url(string $template, string $token): string
    {
        return str_replace('{token}', $token, $template);
    }

    /** A club whose owner may manage — and therefore score — its events. */
    private function clubFor(User $user): Tenant
    {
        $club = $this->createClub($user, ['country' => 'BH', 'currency' => 'BHD']);
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $club;
    }

    /** A championship of one sport, on one mat, with one bout so the mat exists. */
    private function event(string $sport, User $organiser): ClubEvent
    {
        $event = ClubEvent::create([
            'tenant_id' => $this->clubFor($organiser)->id,
            'created_by' => $organiser->id,
            'title' => 'Recovery Baseline Open',
            'event_type' => 'championship',
            'sport' => $sport,
            'scope' => 'internal',
            'status' => 'active',
            'is_archived' => false,
            'date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $category = EventCategory::create([
            'event_id' => $event->id,
            'name' => 'Adult Men Light',
            'sort_order' => 1,
        ]);

        EventMatch::create([
            'event_id' => $event->id,
            'category_id' => $category->id,
            'round' => 'Final',
            'phase' => 'finals',
            'slot' => 0,
            'match_no' => 1,
            'a_name' => 'Ali',
            'b_name' => 'Bader',
            'court' => self::MAT,
            'status' => 'upcoming',
        ]);

        return $event;
    }

    /**
     * Walk a redirect chain by hand, and prove it ends somewhere that draws.
     *
     * Not `followingRedirects()`: the point of this test is the HOPS, so the
     * chain has to be visible. A URL seen twice is the loop the rule exists to
     * forbid, and a hop budget catches a chain that grows without repeating.
     *
     * @return array{0: int, 1: array<int, string>} the final status, and the path walked
     */
    private function walk(string $url, int $budget = 8): array
    {
        $seen = [];

        for ($hop = 0; $hop < $budget; $hop++) {
            $this->assertNotContains($url, $seen,
                'REDIRECT LOOP: a device with no keyboard would bounce forever between '.implode(' -> ', $seen));

            $seen[] = $url;

            $response = $this->get($url);

            if (! $response->isRedirect()) {
                return [$response->getStatusCode(), $seen];
            }

            $next = (string) $response->headers->get('Location');
            $url = parse_url($next, PHP_URL_PATH).(($q = parse_url($next, PHP_URL_QUERY)) ? '?'.$q : '');
        }

        $this->fail('UNBOUNDED CHAIN: '.$budget.' hops without rendering — '.implode(' -> ', $seen));
    }

    // ---------------------------------------------------------------------
    // 1. A page a device renders NEVER 404s on a dead identity
    // ---------------------------------------------------------------------

    /**
     * The board and the overlay — the addresses a machine remembers and reopens
     * after a power cut — send a dead token straight back to the waiting room.
     */
    public function test_a_dead_token_on_a_board_page_redirects_to_the_pairing_room(): void
    {
        foreach ($this->fleets() as $sport => $fleet) {
            foreach ($fleet['pages'] as $name => $template) {
                if ($name === 'console') {
                    continue; // asserted below — it recovers via its own board.
                }

                $this->get($this->url($template, self::DEAD))
                    ->assertRedirect(route('screen.new'));
            }
        }
    }

    /** The shared waiting room's own page route follows the same rule. */
    public function test_a_dead_token_on_the_shared_screen_page_redirects_to_a_fresh_identity(): void
    {
        $this->get('/screen/'.self::DEAD)->assertRedirect(route('screen.new'));
    }

    /**
     * A console page refuses by handing the screen back to its BOARD, which is
     * the address that knows how to show a pairing code. Never a 404.
     */
    public function test_a_dead_token_on_a_scoring_console_redirects_rather_than_aborting(): void
    {
        $boards = [
            'taekwondo' => '/court/'.self::DEAD,
            'karate' => '/karate/court/'.self::DEAD,
            'bjj' => '/bjj/screen/'.self::DEAD,
        ];

        foreach ($this->fleets() as $sport => $fleet) {
            $this->get($this->url($fleet['pages']['console'], self::DEAD))
                ->assertRedirect($boards[$sport]);
        }
    }

    /** No page route a device can land on may answer 404, for any dead identity. */
    public function test_no_device_page_route_ever_answers_404(): void
    {
        foreach ($this->fleets() as $sport => $fleet) {
            foreach ($fleet['pages'] as $name => $template) {
                $response = $this->get($this->url($template, self::DEAD));

                $this->assertNotSame(404, $response->getStatusCode(),
                    "{$sport}/{$name} 404s a dead token — that is a bricked screen, not an error message");
                $this->assertTrue($response->isRedirect(),
                    "{$sport}/{$name} must redirect a dead token");
            }
        }

        $this->assertNotSame(404, $this->get('/screen/'.self::DEAD)->getStatusCode());
    }

    // ---------------------------------------------------------------------
    // 2. A JSON door a device polls DOES 404 on a dead identity
    // ---------------------------------------------------------------------

    /**
     * The other half of the rule, and the one the client contract depends on:
     * 404 means "discard the stored token and re-enrol", and nothing else may
     * say it.
     */
    public function test_every_json_door_404s_an_unknown_token(): void
    {
        foreach ($this->fleets() as $sport => $fleet) {
            foreach ($fleet['json'] as $name => $template) {
                $this->getJson($this->url($template, self::DEAD))
                    ->assertNotFound();
            }
        }

        $this->getJson('/screen/'.self::DEAD.'/status')->assertNotFound();
    }

    /**
     * The two heartbeat doors RETURN their 404 rather than aborting, because
     * abort() is rewritten into a redirect home for a session-bearing browser —
     * whereupon the agent sees 200 and HTML and polls a dead token forever.
     * Asserted on the body, which is the only way to tell the two apart.
     */
    public function test_the_heartbeat_doors_return_a_json_404_that_no_handler_can_rewrite(): void
    {
        foreach ([
            '/screen/'.self::DEAD.'/status',
            '/court/'.self::DEAD.'/status',
            '/karate/court/'.self::DEAD.'/status',
            '/bjj/screen/'.self::DEAD.'/status',
        ] as $url) {
            $this->getJson($url)->assertNotFound()->assertExactJson(['error' => 'unknown']);
        }
    }

    /**
     * And the same doors answer 404 to a plain browser GET too — no session, no
     * Accept header, exactly as a bare agent asks.
     */
    public function test_a_heartbeat_door_404s_a_plain_get_as_well(): void
    {
        foreach ([
            '/screen/'.self::DEAD.'/status',
            '/court/'.self::DEAD.'/status',
            '/karate/court/'.self::DEAD.'/status',
            '/bjj/screen/'.self::DEAD.'/status',
        ] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    // ---------------------------------------------------------------------
    // 3. Every redirect chain terminates
    // ---------------------------------------------------------------------

    /** From any dead page address, a device reaches something that draws. */
    public function test_every_dead_page_route_walks_to_a_page_that_renders(): void
    {
        foreach ($this->fleets() as $sport => $fleet) {
            foreach ($fleet['pages'] as $name => $template) {
                [$status, $path] = $this->walk($this->url($template, self::DEAD));

                $this->assertSame(200, $status,
                    "{$sport}/{$name} never reaches a page that renders: ".implode(' -> ', $path));
            }
        }

        [$status] = $this->walk('/screen/'.self::DEAD);
        $this->assertSame(200, $status);
    }

    /** The end of the chain is the waiting room, holding a fresh pairing code. */
    public function test_the_end_of_the_chain_is_a_code_somebody_in_the_hall_can_act_on(): void
    {
        $before = PendingScreen::count();

        [$status, $path] = $this->walk('/court/'.self::DEAD);

        $this->assertSame(200, $status);
        $this->assertSame('/screen', $path[1], 'recovery must go to the sport-neutral room, not a package code');
        $this->assertGreaterThan($before, PendingScreen::count(),
            'the waiting room must issue a fresh identity, not park the screen on nothing');
    }

    /**
     * THE DOCUMENTED HAND-OFF HAZARD.
     *
     * A screen paired as a scoring table is sent from its board to the console.
     * The console refuses when the organiser who paired it can no longer score
     * — and hands it back to the board. Without `canServeControl` on the board
     * side, the board sends it straight back to the console: two pages pointing
     * at each other, on a television nobody can stop. It shipped once in the
     * BJJ package because the guard was dropped when the file was copied.
     */
    public function test_a_console_that_will_not_open_hands_back_to_a_board_that_draws(): void
    {
        $consoles = [
            'taekwondo' => '/court/{token}/control',
            'karate' => '/karate/court/{token}/control',
            'bjj' => '/bjj/screen/{token}/control',
        ];

        foreach ($this->fleets() as $sport => $fleet) {
            // Paired as a scoring table by somebody who cannot score this event:
            // the console will refuse, and the board must therefore draw.
            $organiser = $this->createUser();
            $stranger = $this->createUser();
            $event = $this->event($sport, $organiser);

            /** @var class-string $model */
            $model = $fleet['device'];
            ['device' => $device, 'token' => $token] = $model::issue($event, self::MAT, $stranger->id, 'table');
            $device->forceFill(['surface' => 'control'])->save();

            [$status, $path] = $this->walk($this->url($consoles[$sport], $token));

            $this->assertSame(200, $status,
                "{$sport}: a refused console must land on a board that draws — ".implode(' -> ', $path));
            $this->assertContains($this->url($fleet['pages']['board'], $token), $path,
                "{$sport}: the refusal must hand back to the screen's own board");
        }
    }

    /**
     * And the healthy direction of the same hand-off: a board paired as a
     * scoring table, by somebody who CAN score, goes to the console — and the
     * console opens, so the chain still terminates.
     */
    public function test_a_console_that_will_open_is_reached_from_the_board_and_terminates(): void
    {
        foreach ($this->fleets() as $sport => $fleet) {
            $organiser = $this->createUser();
            $event = $this->event($sport, $organiser);

            /** @var class-string $model */
            $model = $fleet['device'];
            ['device' => $device, 'token' => $token] = $model::issue($event, self::MAT, $organiser->id, 'table');
            $device->forceFill(['surface' => 'control'])->save();

            [$status, $path] = $this->walk($this->url($fleet['pages']['board'], $token));

            $this->assertSame(200, $status, "{$sport}: ".implode(' -> ', $path));
            $this->assertContains($this->url($fleet['pages']['console'], $token), $path,
                "{$sport}: a control screen must be handed to its console");
        }
    }

    /** A screen whose event was deleted out from under it recovers, it does not brick. */
    public function test_a_screen_whose_event_was_deleted_recovers_to_the_waiting_room(): void
    {
        foreach ($this->fleets() as $sport => $fleet) {
            $organiser = $this->createUser();
            $event = $this->event($sport, $organiser);

            /** @var class-string $model */
            $model = $fleet['device'];
            ['token' => $token] = $model::issue($event, self::MAT, $organiser->id, 'board');

            $event->delete();

            $this->get($this->url($fleet['pages']['board'], $token))
                ->assertRedirect(route('screen.new'));
        }
    }

    /**
     * ⚠️ DIVERGENCE from the STRICT rule — asserted as it IS, not as it should be.
     *
     * A screen claimed onto an event whose sport is NOT its fleet's cannot draw
     * that event. BJJ recognises this and recovers to the waiting room
     * (HallScreenController::screen — "it can always go back and wait for one").
     * Taekwondo and Karate do NOT check: their boards render 200 and draw the
     * foreign event on their own sport's board.
     *
     * It does not brick a device — nothing 404s, and the rule's letter holds —
     * but it is the copy-paste drift "Shared Stays Shared" warns about: the
     * guard exists in one of three copies. A hall would see a Karate event on a
     * Taekwondo scoreboard rather than a pairing code it could act on.
     *
     * Reachable in practice by changing an event's sport after a screen was
     * paired to it; adopt() itself always picks the matching fleet.
     *
     * DO NOT "fix" this by editing the controllers under this test. The fix is
     * the shared hall-screen base recorded as known debt in CLAUDE.md, and it
     * needs an explicit go-ahead under RULE #1.
     */
    public function test_a_screen_claimed_onto_another_sports_event_diverges_by_package(): void
    {
        $expected = [
            // DIVERGENCE: renders the foreign event instead of recovering.
            'taekwondo' => 200,
            // DIVERGENCE: same.
            'karate' => 200,
            // The rule as written: recover to a code somebody can act on.
            'bjj' => 302,
        ];

        // A sport each fleet does not serve, so the mismatch is unambiguous.
        $foreign = ['taekwondo' => 'karate', 'karate' => 'taekwondo', 'bjj' => 'karate'];

        foreach ($this->fleets() as $sport => $fleet) {
            $organiser = $this->createUser();
            $event = $this->event($foreign[$sport], $organiser);

            /** @var class-string $model */
            $model = $fleet['device'];
            ['token' => $token] = $model::issue($event, self::MAT, $organiser->id, 'board');

            $response = $this->get($this->url($fleet['pages']['board'], $token));

            $this->assertSame($expected[$sport], $response->getStatusCode(),
                "{$sport}: cross-sport board behaviour changed — re-read the DIVERGENCE note above before editing this expectation");

            if ($expected[$sport] === 302) {
                $response->assertRedirect(route('screen.new'));
            }

            // Whichever way it goes, the rule's hard floor holds: never a 404.
            $this->assertNotSame(404, $response->getStatusCode());
        }
    }

    // ---------------------------------------------------------------------
    // 4. One answer for every failure
    // ---------------------------------------------------------------------

    /**
     * A bad token, a revoked device and an unpaired one are indistinguishable
     * on a page route. A wall screen is scanned by whoever walks past it, and a
     * different reply for each case tells them which tokens are real.
     */
    public function test_bad_revoked_and_unpaired_get_one_identical_page_answer(): void
    {
        foreach ($this->fleets() as $sport => $fleet) {
            $organiser = $this->createUser();
            $event = $this->event($sport, $organiser);

            /** @var class-string $model */
            $model = $fleet['device'];

            ['device' => $revoked, 'token' => $revokedToken] = $model::issue($event, self::MAT, $organiser->id, 'gone');
            $revoked->revoke();

            ['token' => $waitingToken] = $model::begin('waiting');

            ['device' => $unpaired, 'token' => $unpairedToken] = $model::issue($event, self::MAT, $organiser->id, 'moved');
            $unpaired->unclaim();

            $answers = [];

            foreach (['dead' => self::DEAD, 'revoked' => $revokedToken, 'never-paired' => $waitingToken, 'unpaired' => $unpairedToken] as $case => $token) {
                $response = $this->get($this->url($fleet['pages']['board'], $token));

                $answers[$case] = $response->getStatusCode().' '.$response->headers->get('Location');
            }

            $this->assertCount(1, array_unique($answers),
                "{$sport}: the board answers differently per failure, which leaks which tokens are real: "
                .json_encode($answers));
        }
    }

    /** The same, for the JSON doors: a bad token and a revoked one match exactly. */
    public function test_a_bad_token_and_a_revoked_device_are_identical_on_every_json_door(): void
    {
        foreach ($this->fleets() as $sport => $fleet) {
            $organiser = $this->createUser();
            $event = $this->event($sport, $organiser);

            /** @var class-string $model */
            $model = $fleet['device'];
            ['device' => $revoked, 'token' => $revokedToken] = $model::issue($event, self::MAT, $organiser->id, 'gone');
            $revoked->revoke();

            foreach ($fleet['json'] as $name => $template) {
                $bad = $this->getJson($this->url($template, self::DEAD));
                $gone = $this->getJson($this->url($template, $revokedToken));

                $this->assertSame($bad->getStatusCode(), $gone->getStatusCode(),
                    "{$sport}/{$name}: a revoked device answers differently from an unknown token");
                $this->assertSame($bad->getContent(), $gone->getContent(),
                    "{$sport}/{$name}: a revoked device's BODY differs from an unknown token's");
            }
        }
    }

    /**
     * An unpaired screen's heartbeat is deliberately NOT the same answer: the
     * identity is alive and the honest reply is `claimed:false`, which is what
     * moves the screen back to its code. This pins that distinction so nobody
     * "fixes" it into a 404 and strands every screen an organiser unpairs.
     */
    public function test_an_unpaired_screen_still_gets_a_living_heartbeat(): void
    {
        foreach ($this->fleets() as $sport => $fleet) {
            /** @var class-string $model */
            $model = $fleet['device'];
            ['token' => $token] = $model::begin('waiting');

            $this->getJson($this->url($fleet['json']['status'], $token))
                ->assertOk()
                ->assertExactJson(['claimed' => false]);
        }
    }

    /**
     * The pairing room's own heartbeat, same contract: alive while waiting,
     * 404 the moment the identity is gone.
     */
    public function test_the_waiting_rooms_heartbeat_says_nothing_until_it_is_told_where_to_go(): void
    {
        ['token' => $token] = PendingScreen::begin();

        $this->getJson('/screen/'.$token.'/status')->assertOk()->assertExactJson(['go' => null]);
    }
}
