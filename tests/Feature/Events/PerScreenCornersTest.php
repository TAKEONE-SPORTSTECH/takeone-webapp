<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Scoreboard\Corners\MatSides;
use App\Scoreboard\Fleet;
use Tests\TestCase;

/**
 * Red's side, screen by screen — Karate Combat's Corners tab, now in the shared
 * Settings pop-up for every sport. A flip moves only the screens named; the
 * rest of the mat stays where it was; and the whole-mat swap still works
 * exactly as before.
 */
class PerScreenCornersTest extends TestCase
{
    private const DOORS = [
        'taekwondo' => ['/taekwondo/control/', '/court/'],
        'bjj' => ['/bjj/control/', '/bjj/screen/'],
    ];

    public static function sports(): array
    {
        return ['taekwondo' => ['taekwondo'], 'bjj' => ['bjj']];
    }

    private function scenario(string $sport): array
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);
        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id, 'title' => 'Sides Open', 'event_type' => 'championship',
            'sport' => $sport, 'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeek()->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ]);
        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Male -68 kg', 'sort_order' => 1]);
        EventMatch::create(['event_id' => $event->id, 'category_id' => $cat->id, 'round' => 'Final', 'phase' => 'finals',
            'slot' => 0, 'a_name' => 'A', 'b_name' => 'B', 'court' => 'Mat 1', 'status' => 'upcoming']);

        return [$owner, $event->fresh()];
    }

    /** @dataProvider sports */
    public function test_one_screen_flips_and_the_others_stay(string $sport): void
    {
        [$owner, $event] = $this->scenario($sport);
        $before = MatSides::screens($event, 'Mat 1', $sport);

        $this->actingAs($owner)->postJson(self::DOORS[$sport][0].$event->uuid.'/corners', ['mat' => 'Mat 1', 'screens' => ['board']])
            ->assertOk()->assertJsonPath('success', true)->assertJsonStructure(['screens' => MatSides::SCREENS]);

        $after = MatSides::screens($event->fresh(), 'Mat 1', $sport);
        $this->assertSame(! $before['board'], $after['board'], 'the scoreboard flipped');
        $this->assertSame($before['control'], $after['control'], 'the console stayed');
        $this->assertSame($before['upcoming'], $after['upcoming'], 'the up-next board stayed');

        // The whole-mat swap still works as it always did.
        $this->actingAs($owner)->postJson(self::DOORS[$sport][0].$event->uuid.'/corners', ['mat' => 'Mat 1', 'red_right' => ! $before['control']])
            ->assertOk();
        $this->assertSame(! $before['control'], MatSides::redRight($event->fresh(), 'Mat 1', $sport));
    }

    /** @dataProvider sports */
    public function test_a_paired_table_flips_its_own_mats_screens(string $sport): void
    {
        [$owner, $event] = $this->scenario($sport);
        $model = Fleet::deviceFor($sport);
        ['device' => $device, 'token' => $token] = $model::issue($event, 'Mat 1', $owner->id);
        $device->forceFill(['surface' => 'control'])->save();
        $before = MatSides::screens($event, 'Mat 1', $sport);

        $this->postJson(self::DOORS[$sport][1].$token.'/corners', ['screens' => ['upcoming']])->assertOk();

        $after = MatSides::screens($event->fresh(), 'Mat 1', $sport);
        $this->assertSame(! $before['upcoming'], $after['upcoming']);
        $this->assertSame($before['board'], $after['board']);
    }

    /** @dataProvider sports */
    public function test_the_console_offers_the_screen_rows(string $sport): void
    {
        [$owner, $event] = $this->scenario($sport);

        $this->actingAs($owner)->get(self::DOORS[$sport][0].$event->uuid)
            ->assertOk()->assertSee('id="cornerScreens"', false)
            ->assertSee('data-screen="board"', false)->assertSee('data-screen="upcoming"', false);
    }

    /** @dataProvider sports */
    public function test_a_bad_screen_name_is_refused(string $sport): void
    {
        [$owner, $event] = $this->scenario($sport);

        $this->actingAs($owner)->postJson(self::DOORS[$sport][0].$event->uuid.'/corners', ['mat' => 'Mat 1', 'screens' => ['kitchen']])
            ->assertStatus(422);
    }
}
