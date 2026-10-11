<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Mcp\Tools\SetCornerLookTool;
use App\Scoreboard\Sports\Karate\Mat\CornerLook;
use App\Scoreboard\Sports\Karate\Mat\MatState;
use Tests\TestCase;

/**
 * "Corner names & colours" — one name and one colour per corner for a whole
 * Karate event (Mat\CornerLook). Only the look changes; the style's own is the
 * default; nothing typed reaches a style attribute unvalidated.
 */
class KarateCornerLookTest extends TestCase
{
    private function scenario(?string $belts = null): array
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);

        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id,
            'title' => 'Look Open', 'event_type' => 'championship', 'sport' => 'karate',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeeks(2)->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ]);
        if ($belts) {
            $event->forceFill(['belt_system' => $belts])->save();
        }

        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men', 'sort_order' => 1]);
        EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id, 'round' => 'Final', 'phase' => 'finals',
            'slot' => 0, 'a_name' => 'Ali', 'b_name' => 'Bader', 'court' => 'Mat 1', 'status' => 'upcoming',
        ]);

        return [$owner, $event->fresh()];
    }

    public function test_defaults_follow_the_style(): void
    {
        config(['features.karate_style_boards' => true]);
        [, $plain] = $this->scenario();
        [, $kyo] = $this->scenario('kyokushin');

        $this->assertSame(['AKA', 'AO'], [CornerLook::for($plain)['aka']['label'], CornerLook::for($plain)['ao']['label']]);
        $this->assertSame('#0d55b8', CornerLook::for($plain)['ao']['colour']);
        $this->assertSame('SHIRO', CornerLook::for($kyo)['ao']['label']);
        $this->assertTrue(CornerLook::for($kyo)['ao']['light']);
        $this->assertSame('#14161c', CornerLook::for($kyo)['ao']['ink']);
    }

    public function test_the_console_saves_a_custom_look_and_blanks_return_to_the_default(): void
    {
        [$owner, $event] = $this->scenario();

        $this->actingAs($owner)->postJson("/karate/control/{$event->uuid}/corner-look", [
            'aka' => ['label' => 'Hong', 'colour' => '#1F8A3B'],
            'ao' => ['label' => null, 'colour' => '#f2c200'],
        ])->assertOk()->assertJson(['success' => true]);

        $look = CornerLook::for($event->fresh());
        $this->assertSame('HONG', $look['aka']['label']);
        $this->assertSame('#1f8a3b', $look['aka']['colour']);
        $this->assertSame('AO', $look['ao']['label']);
        $this->assertSame('#f2c200', $look['ao']['colour']);
        $this->assertSame('#14161c', $look['ao']['ink'], 'a light colour gets dark type');

        // The console draws it.
        $this->actingAs($owner)->get("/karate/control/{$event->uuid}?mat=".urlencode('Mat 1'))
            ->assertOk()->assertSee('HONG')->assertSee('#1f8a3b', false);

        // Saving a mat rule keeps it.
        $state = MatState::load($event->fresh(), 'Mat 1');
        $state->gap = 4;
        $state->persistSettings($event->fresh());
        $this->assertSame('HONG', CornerLook::for($event->fresh())['aka']['label']);

        // Blanks clear it.
        $this->actingAs($owner)->postJson("/karate/control/{$event->uuid}/corner-look", [
            'aka' => ['label' => '', 'colour' => null], 'ao' => ['label' => null, 'colour' => null],
        ])->assertOk();
        $this->assertArrayNotHasKey(CornerLook::KEY, (array) $event->fresh()->scoreboard_settings);
    }

    public function test_bad_input_is_refused_or_cleaned(): void
    {
        [$owner, $event] = $this->scenario();

        $this->actingAs($owner)->postJson("/karate/control/{$event->uuid}/corner-look", [
            'aka' => ['label' => 'X', 'colour' => 'red;background:url(x)'], 'ao' => ['label' => null],
        ])->assertStatus(422);

        $this->actingAs($owner)->postJson("/karate/control/{$event->uuid}/corner-look", [
            'aka' => ['label' => '<b>Red</b>', 'colour' => null], 'ao' => ['label' => null],
        ])->assertOk();
        $this->assertSame('RED', CornerLook::for($event->fresh())['aka']['label']);
    }

    public function test_someone_who_may_not_score_cannot_change_it(): void
    {
        [, $event] = $this->scenario();

        $this->actingAs($this->createUser())->postJson("/karate/control/{$event->uuid}/corner-look", [
            'aka' => ['label' => 'NOPE'], 'ao' => ['label' => null],
        ])->assertForbidden();

        $this->assertArrayNotHasKey(CornerLook::KEY, (array) $event->fresh()->scoreboard_settings);
    }

    public function test_the_mcp_tool_sets_it_and_refuses_an_outsider(): void
    {
        [$owner, $event] = $this->scenario();

        $this->actingAs($this->createUser());
        $this->assertArrayHasKey('error', $this->callMcp(['event' => $event->uuid, 'corners' => ['ao' => ['label' => 'Shiro']]]));

        $this->actingAs($owner);
        $out = $this->callMcp(['event' => $event->uuid, 'corners' => ['ao' => ['label' => 'Shiro', 'colour' => '#ffffff']]]);
        $this->assertSame('SHIRO', $out['ao']['label']);
        $this->assertFalse($out['ao']['is_default']);
        $this->assertTrue($out['aka']['is_default']);
    }

    private function callMcp(array $args): array
    {
        $text = (string) (new SetCornerLookTool)->handle(new \Laravel\Mcp\Request($args))->content();
        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : ['error' => $text];
    }
}
