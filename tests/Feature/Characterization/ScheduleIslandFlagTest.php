<?php

namespace Tests\Feature\Characterization;

use App\Models\User;
use Tests\Feature\Contracts\ContractTestCase;

/**
 * Phase M1 — the React island fork on /me/schedule.
 *
 * Pins BOTH sides of the feature fork, because the whole safety argument for
 * the island is "the legacy path is still there and still the default". A test
 * that only covers the new path would let the fallback rot unnoticed.
 *
 * The per-request `?react=` override exists so the two implementations can be
 * compared on the same URL without changing what anyone else sees.
 */
class ScheduleIslandFlagTest extends ContractTestCase
{
    private const PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = $this->createUser();
        $this->joinClub($this->member, $this->clubFor($this->createUser()));
    }

    private function getSchedule(string $uri)
    {
        return $this->withHeaders(['User-Agent' => self::PHONE])->get($uri);
    }

    public function test_the_legacy_board_is_what_renders_by_default(): void
    {
        config(['features.react_schedule' => false]);

        $res = $this->actingAs($this->member)->getSchedule('/me/schedule');

        $res->assertOk();
        // The legacy renderer's mount points and its inline script.
        $res->assertSee('id="sched-strip"', false);
        $res->assertSee('id="sched-sessions"', false);
        $res->assertDontSee('id="schedule-island"', false);
    }

    public function test_the_query_override_switches_a_single_request_to_the_island(): void
    {
        config(['features.react_schedule' => false]);

        $res = $this->actingAs($this->member)->getSchedule('/me/schedule?react=1');

        $res->assertOk();
        $res->assertSee('id="schedule-island"', false);
        // Exactly one renderer runs — never both.
        $res->assertDontSee('id="sched-strip"', false);
    }

    public function test_the_override_can_also_force_the_legacy_board_back_on(): void
    {
        config(['features.react_schedule' => true]);

        $res = $this->actingAs($this->member)->getSchedule('/me/schedule?react=0');

        $res->assertOk();
        $res->assertSee('id="sched-strip"', false);
        $res->assertDontSee('id="schedule-island"', false);
    }

    public function test_a_junk_override_value_is_ignored_and_the_default_stands(): void
    {
        config(['features.react_schedule' => false]);

        $res = $this->actingAs($this->member)->getSchedule('/me/schedule?react=banana');

        $res->assertOk();
        $res->assertSee('id="sched-strip"', false);
        $res->assertDontSee('id="schedule-island"', false);
    }

    public function test_the_island_is_seeded_with_first_paint_props(): void
    {
        config(['features.react_schedule' => false]);

        $res = $this->actingAs($this->member)->getSchedule('/me/schedule?react=1');

        $res->assertOk();
        $res->assertSee('data-island-props', false);
    }
}
