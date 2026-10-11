<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Scoreboard\Corners\CornerLook as SharedLook;
use App\Scoreboard\Corners\MatSides;
use App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\CornerLook;
use App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\MatState;
use App\Sports\Combat\CornerSide;
use Tests\TestCase;

/**
 * The Corners tab on the BJJ scoreboard (App\Scoreboard\Corners): which side of
 * a mat the red corner (side A, `blue` in the data) stands on, and each
 * corner's name and colour for the event. Only the look changes.
 */
class BjjCornersTest extends TestCase
{
    private function scenario(): array
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);

        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id,
            'title' => 'Corner Open', 'event_type' => 'championship', 'sport' => 'bjj',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeeks(2)->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ]);

        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Adult Male', 'sort_order' => 1]);
        foreach (['Mat 1', 'Mat 2'] as $i => $mat) {
            EventMatch::create([
                'event_id' => $event->id, 'category_id' => $cat->id, 'round' => 'Final', 'phase' => 'finals',
                'slot' => $i, 'a_name' => 'Ali', 'b_name' => 'Bader', 'court' => $mat, 'status' => 'upcoming',
            ]);
        }

        return [$owner, $event->fresh()];
    }

    public function test_defaults_are_bjjs_own_red_and_blue(): void
    {
        [, $event] = $this->scenario();
        $look = CornerLook::for($event);

        $this->assertSame(['blue', 'white'], array_keys($look));
        $this->assertSame('#c1121f', $look['blue']['colour']);
        $this->assertSame('#1362d1', $look['white']['colour']);
        $this->assertSame(__('sport-brazilianjiujitsu::messages.corner_blue'), $look['blue']['label']);
        $this->assertSame('#ff9aa0', $look['blue']['accent'], 'the tuned console ink');
        $this->assertNull($look['blue']['customColour']);
    }

    public function test_the_console_saves_a_look_and_blanks_clear_it(): void
    {
        [$owner, $event] = $this->scenario();

        $this->actingAs($owner)->postJson("/bjj/control/{$event->uuid}/corner-look", [
            'blue' => ['label' => 'Hong', 'colour' => '#1F8A3B'],
            'white' => ['label' => null, 'colour' => '#f2c200'],
        ])->assertOk()->assertJson(['success' => true]);

        $look = CornerLook::for($event->fresh());
        $this->assertSame('HONG', $look['blue']['label']);
        $this->assertSame('#1f8a3b', $look['blue']['colour']);
        $this->assertSame('#f2c200', $look['white']['colour']);
        $this->assertSame('#14161c', $look['white']['ink'], 'a light colour gets dark type');

        $this->actingAs($owner)->get("/bjj/control/{$event->uuid}?mat=".urlencode('Mat 1'))
            ->assertOk()->assertSee('HONG')->assertSee('data-panel="corners"', false);

        $this->actingAs($owner)->postJson("/bjj/control/{$event->uuid}/corner-look", [
            'blue' => ['label' => '', 'colour' => null], 'white' => ['label' => null, 'colour' => null],
        ])->assertOk();
        $this->assertArrayNotHasKey(SharedLook::KEY, (array) $event->fresh()->scoreboard_settings);
    }

    public function test_the_side_is_per_mat(): void
    {
        [$owner, $event] = $this->scenario();
        $default = CornerSide::redRight('bjj');

        $this->actingAs($owner)
            ->postJson("/bjj/control/{$event->uuid}/corners", ['mat' => 'Mat 1', 'red_right' => ! $default])
            ->assertOk()->assertJson(['success' => true, 'red_right' => ! $default]);

        $event->refresh();
        $this->assertSame(! $default, MatSides::redRight($event, 'Mat 1', 'bjj'));
        $this->assertSame($default, MatSides::redRight($event, 'Mat 2', 'bjj'));

        $this->actingAs($owner)->get("/bjj/control/{$event->uuid}?mat=".urlencode('Mat 1'))
            ->assertOk()->assertViewHas('redRight', ! $default);
    }

    public function test_saving_the_mat_rules_keeps_both(): void
    {
        [, $event] = $this->scenario();

        MatSides::set($event, 'Mat 1', ! CornerSide::redRight('bjj'), 'bjj');
        CornerLook::set($event->fresh(), ['blue' => ['label' => 'Hong'], 'white' => []]);
        $event->refresh();

        $state = MatState::forMat($event, 'Mat 1');
        $state->persistSettings($event);

        $settings = (array) $event->fresh()->scoreboard_settings;
        $this->assertArrayHasKey(MatSides::KEY, $settings);
        $this->assertArrayHasKey(SharedLook::KEY, $settings);
        $this->assertSame('HONG', CornerLook::for($event->fresh())['blue']['label']);
    }

    public function test_a_non_scorer_is_refused_and_an_unknown_mat_is_not_found(): void
    {
        [$owner, $event] = $this->scenario();

        $this->actingAs($this->createUser())
            ->postJson("/bjj/control/{$event->uuid}/corners", ['mat' => 'Mat 1', 'red_right' => true])
            ->assertForbidden();
        $this->actingAs($this->createUser())
            ->postJson("/bjj/control/{$event->uuid}/corner-look", ['blue' => ['label' => 'X'], 'white' => ['label' => null]])
            ->assertForbidden();

        $this->actingAs($owner)
            ->postJson("/bjj/control/{$event->uuid}/corners", ['mat' => 'Mat 9', 'red_right' => true])
            ->assertNotFound();

        $this->actingAs($owner)
            ->postJson("/bjj/control/{$event->uuid}/corner-look", ['blue' => ['colour' => 'red;x'], 'white' => ['label' => null]])
            ->assertStatus(422);

        $settings = (array) $event->fresh()->scoreboard_settings;
        $this->assertArrayNotHasKey(MatSides::KEY, $settings);
        $this->assertArrayNotHasKey(SharedLook::KEY, $settings);
    }

    public function test_the_mcp_tools_use_bjjs_own_corner_keys_and_refuse_an_outsider(): void
    {
        [$owner, $event] = $this->scenario();
        $mat = $event->matches()->value('court');

        $this->actingAs($this->createUser());
        $this->assertArrayHasKey('error', $this->mcp(\App\Mcp\Tools\SetCornerLookTool::class, ['event' => $event->uuid, 'corners' => ['white' => ['label' => 'X']]]));

        $this->actingAs($owner);
        $out = $this->mcp(\App\Mcp\Tools\SetCornerLookTool::class, ['event' => $event->uuid, 'corners' => ['white' => ['label' => 'Gold', 'colour' => '#f2c200']]]);
        $this->assertSame('GOLD', $out['white']['label']);
        $this->assertSame('#f2c200', $out['white']['colour']);
        $this->assertTrue($out['blue']['is_default']);

        $side = $this->mcp(\App\Mcp\Tools\SetMatCornerSideTool::class, ['event' => $event->uuid, 'mat' => $mat, 'red_side' => 'left']);
        $this->assertSame('left', $side['red_side']);
        $this->assertFalse(\App\Scoreboard\Corners\MatSides::redRight($event->fresh(), $mat, 'bjj'));
    }

    private function mcp(string $tool, array $args): array
    {
        $text = (string) (new $tool)->handle(new \Laravel\Mcp\Request($args))->content();
        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : ['error' => $text];
    }
}
