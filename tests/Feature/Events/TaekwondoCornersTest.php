<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Scoreboard\Corners\CornerLook as SharedLook;
use App\Scoreboard\Corners\MatSides;
use App\Scoreboard\Sports\Taekwondo\HallScreen\CourtDisplayDevice;
use App\Scoreboard\Sports\Taekwondo\Mat\CornerLook;
use App\Sports\Combat\CornerSide;
use Tests\TestCase;

/**
 * Taekwondo's Corners: which side of each mat red stands on (MatSides) and the
 * event's corner names and colours (CornerLook) — the shared core with
 * Taekwondo's spec. Only the look changes; the default draws as designed.
 */
class TaekwondoCornersTest extends TestCase
{
    private function scenario(): array
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);

        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id,
            'title' => 'Corner Open', 'event_type' => 'championship', 'sport' => 'taekwondo',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeeks(2)->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ]);

        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);
        foreach (['Mat 1', 'Mat 2'] as $i => $mat) {
            EventMatch::create([
                'event_id' => $event->id, 'category_id' => $cat->id, 'round' => 'Final', 'phase' => 'finals',
                'slot' => $i, 'a_name' => 'Ali', 'b_name' => 'Bader', 'court' => $mat, 'status' => 'upcoming',
            ]);
        }

        return [$owner, $event->fresh()];
    }

    public function test_defaults_are_hong_red_and_chung_blue_drawn_as_designed(): void
    {
        [, $event] = $this->scenario();
        $look = CornerLook::for($event);

        $this->assertSame(__('scoreboard::taekwondo_messages.sb_hong'), $look['aka']['label']);
        $this->assertSame(__('scoreboard::taekwondo_messages.sb_chung'), $look['ao']['label']);
        $this->assertFalse(CornerLook::customised($look));

        // The designed oklch shades, not generated ones.
        $skin = CornerLook::skin($look);
        $this->assertSame('oklch(0.55 0.21 25)', $skin['aka']['cardFrom']);
        $this->assertSame('oklch(0.5 0.18 255)', $skin['ao']['cardFrom']);
        $this->assertNull($skin['aka']['celebrate']);
        $this->assertSame(CornerSide::redRight('taekwondo'), CornerLook::redRight($event, 'Mat 1'));
    }

    public function test_the_console_saves_a_look_and_blanks_clear_it(): void
    {
        [$owner, $event] = $this->scenario();

        $this->actingAs($owner)->postJson("/taekwondo/control/{$event->uuid}/corner-look", [
            'aka' => ['label' => 'Gold', 'colour' => '#F2C200'],
            'ao' => ['label' => null, 'colour' => null],
        ])->assertOk()->assertJson(['success' => true]);

        $look = CornerLook::for($event->fresh());
        $this->assertSame('GOLD', $look['aka']['label']);
        $this->assertSame('#f2c200', $look['aka']['colour']);
        $this->assertTrue($look['aka']['light']);
        $skin = CornerLook::skin($look);
        $this->assertSame('#14161c', $skin['aka']['scoreInk'], 'a light corner takes dark type');
        $this->assertNotNull($skin['aka']['celebrate']);
        $this->assertSame('oklch(0.5 0.18 255)', $skin['ao']['cardFrom'], 'the untouched corner keeps its design');

        // The console draws it, and has the Corners sheet.
        $this->actingAs($owner)->get("/taekwondo/control/{$event->uuid}?mat=".urlencode('Mat 1'))
            ->assertOk()->assertSee('GOLD')->assertSee('id="cornersModal"', false);

        $this->actingAs($owner)->postJson("/taekwondo/control/{$event->uuid}/corner-look", [
            'aka' => ['label' => '', 'colour' => null], 'ao' => ['label' => null, 'colour' => null],
        ])->assertOk();
        $this->assertArrayNotHasKey(SharedLook::KEY, (array) $event->fresh()->scoreboard_settings);
    }

    public function test_the_side_is_per_mat_and_back_to_default_clears_it(): void
    {
        [$owner, $event] = $this->scenario();
        $default = CornerSide::redRight('taekwondo');

        $this->actingAs($owner)
            ->postJson("/taekwondo/control/{$event->uuid}/corners", ['mat' => 'Mat 1', 'red_right' => ! $default])
            ->assertOk()->assertJson(['success' => true, 'red_right' => ! $default]);

        $event->refresh();
        $this->assertSame(! $default, CornerLook::redRight($event, 'Mat 1'));
        $this->assertSame($default, CornerLook::redRight($event, 'Mat 2'));

        $this->actingAs($owner)
            ->postJson("/taekwondo/control/{$event->uuid}/corners", ['mat' => 'Mat 1', 'red_right' => $default])
            ->assertOk();
        $this->assertArrayNotHasKey(MatSides::KEY, (array) $event->fresh()->scoreboard_settings);
    }

    public function test_a_paired_table_sets_its_own_mat_only(): void
    {
        [$owner, $event] = $this->scenario();
        $default = CornerSide::redRight('taekwondo');

        ['device' => $device, 'token' => $token] = CourtDisplayDevice::issue($event, 'Mat 2', $owner->id, 'table');
        $device->forceFill(['surface' => 'control'])->save();

        $this->postJson("/court/{$token}/corners", ['mat' => 'Mat 1', 'red_right' => ! $default])->assertOk();

        $event->refresh();
        $this->assertSame(! $default, CornerLook::redRight($event, 'Mat 2'), 'the device\'s mat, not the request\'s');
        $this->assertSame($default, CornerLook::redRight($event, 'Mat 1'));

        $this->postJson("/court/{$token}/corner-look", ['aka' => ['label' => 'Red'], 'ao' => ['label' => null]])->assertOk();
        $this->assertSame('RED', CornerLook::for($event->fresh())['aka']['label']);
    }

    public function test_a_non_scorer_is_refused_and_an_unknown_mat_is_not_found(): void
    {
        [$owner, $event] = $this->scenario();
        $stranger = $this->createUser();

        $this->actingAs($stranger)
            ->postJson("/taekwondo/control/{$event->uuid}/corners", ['mat' => 'Mat 1', 'red_right' => true])
            ->assertForbidden();
        $this->actingAs($stranger)
            ->postJson("/taekwondo/control/{$event->uuid}/corner-look", ['aka' => ['label' => 'NOPE'], 'ao' => ['label' => null]])
            ->assertForbidden();

        $this->actingAs($owner)
            ->postJson("/taekwondo/control/{$event->uuid}/corners", ['mat' => 'Mat 9', 'red_right' => true])
            ->assertNotFound();

        $this->actingAs($owner)
            ->postJson("/taekwondo/control/{$event->uuid}/corner-look", ['aka' => ['colour' => 'red;x:y'], 'ao' => ['label' => null]])
            ->assertStatus(422);

        $settings = (array) $event->fresh()->scoreboard_settings;
        $this->assertArrayNotHasKey(MatSides::KEY, $settings);
        $this->assertArrayNotHasKey(SharedLook::KEY, $settings);
    }
}
