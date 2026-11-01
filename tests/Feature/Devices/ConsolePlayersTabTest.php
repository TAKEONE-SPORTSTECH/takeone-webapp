<?php

namespace Tests\Feature\Devices;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Events\Models\EventOfficial;
use App\Scoreboard\Fleet;
use App\Scoreboard\Models\PhotoVsFrame;
use Tests\TestCase;

/**
 * The console Players tab for Taekwondo and BJJ — the same tab the Karate
 * consoles have (App\Scoreboard\Support\ConsolePlayers): an ORGANISER finds
 * an entrant and corrects them, changes the photo and frames it for the VS
 * screen, through the signed-in console AND a paired table; an official who
 * may only score cannot; another event's entry is never reachable; and a
 * table of one sport cannot be used on another sport's door.
 */
class ConsolePlayersTabTest extends TestCase
{
    /** Each sport: its signed-in console base, and its paired-table base. */
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
            'tenant_id' => $club->id, 'created_by' => $owner->id,
            'title' => 'Players Open', 'event_type' => 'championship', 'sport' => $sport,
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeeks(2)->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ]);

        $athlete = $this->createUser(['full_name' => 'Ali Hassan']);
        $entry = ClubEventRegistration::create([
            'event_id' => $event->id, 'user_id' => $athlete->id,
            'role' => 'participant', 'status' => 'registered',
        ]);

        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Male -68 kg', 'sort_order' => 1]);
        EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id, 'round' => 'Final', 'phase' => 'finals',
            'slot' => 0, 'a_competitor_id' => $entry->id, 'a_name' => 'Ali Hassan', 'b_name' => 'Bader',
            'court' => 'Mat 1', 'status' => 'upcoming',
        ]);

        return [$owner, $event->fresh(), $entry];
    }

    /** A scoring table paired at Mat 1 by `$by`. */
    private function table(ClubEvent $event, int $by): string
    {
        $model = Fleet::deviceFor($event->sport);
        ['device' => $device, 'token' => $token] = $model::issue($event, 'Mat 1', $by);
        $device->forceFill(['surface' => 'control'])->save();

        return $token;
    }

    /** @dataProvider sports */
    public function test_the_organisers_console_has_the_players_tab_and_a_scorer_does_not(string $sport): void
    {
        [$owner, $event] = $this->scenario($sport);
        [$control] = self::DOORS[$sport];

        $this->actingAs($owner)->get($control.$event->uuid)
            ->assertOk()->assertSee('id="cpList"', false)->assertSee('data-players-open', false);

        $jury = $this->createUser();
        EventOfficial::create(['event_id' => $event->id, 'user_id' => $jury->id, 'role' => EventOfficial::ROLE_JURY]);

        $this->actingAs($jury)->get($control.$event->uuid)
            ->assertOk()->assertDontSee('id="cpList"', false);
    }

    /** @dataProvider sports */
    public function test_an_organiser_lists_reads_and_corrects_an_entrant(string $sport): void
    {
        [$owner, $event, $entry] = $this->scenario($sport);
        $base = self::DOORS[$sport][0].$event->uuid.'/players';

        $this->actingAs($owner)->getJson($base)->assertOk()->assertJsonPath('players.0.name', 'Ali Hassan');
        $this->actingAs($owner)->getJson("{$base}/{$entry->id}")
            ->assertOk()->assertJsonPath('player.name', 'Ali Hassan')->assertJsonPath('fields.weight.editable', true);

        $this->actingAs($owner)->putJson("{$base}/{$entry->id}", ['weight' => 61.5])
            ->assertOk()->assertJsonPath('player.weight', 61.5);

        $this->assertEquals(61.5, (float) $entry->fresh()->weight);
    }

    /** @dataProvider sports */
    public function test_an_organiser_frames_the_photo_for_the_vs_screen(string $sport): void
    {
        [$owner, $event, $entry] = $this->scenario($sport);
        $entry->forceFill(['photo' => 'members/test/photos/face.jpg'])->save();
        $base = self::DOORS[$sport][0].$event->uuid.'/players';

        $this->actingAs($owner)->postJson("{$base}/{$entry->id}/vs-frame", ['zoom' => 1.4, 'x' => 40, 'y' => 20])
            ->assertOk()->assertJsonPath('success', true);

        $frame = PhotoVsFrame::where('path', 'members/test/photos/face.jpg')->first();
        $this->assertNotNull($frame);
        $this->assertEquals(1.4, (float) $frame->zoom);

        // …and the preview it is framed against is THE shared VS screen.
        $this->actingAs($owner)->get(self::DOORS[$sport][0].$event->uuid.'/players-vs-preview?reg='.$entry->id)
            ->assertOk()->assertSee('id="vsRedName"', false)->assertSee('Ali Hassan', false);

        $this->actingAs($owner)->postJson("{$base}/{$entry->id}/vs-frame", ['reset' => true])->assertOk();
        $this->assertNull(PhotoVsFrame::where('path', 'members/test/photos/face.jpg')->first());
    }

    /** @dataProvider sports */
    public function test_an_official_who_may_only_score_is_refused(string $sport): void
    {
        [, $event, $entry] = $this->scenario($sport);
        $base = self::DOORS[$sport][0].$event->uuid.'/players';

        $jury = $this->createUser();
        EventOfficial::create(['event_id' => $event->id, 'user_id' => $jury->id, 'role' => EventOfficial::ROLE_JURY]);

        $this->actingAs($jury)->getJson($base)->assertForbidden();
        $this->actingAs($jury)->putJson("{$base}/{$entry->id}", ['weight' => 70])->assertForbidden();
        $this->actingAs($jury)->postJson("{$base}/{$entry->id}/vs-frame", ['zoom' => 2, 'x' => 50, 'y' => 50])->assertForbidden();

        $this->assertNull($entry->fresh()->weight);
    }

    /** @dataProvider sports */
    public function test_another_events_entry_is_not_reachable(string $sport): void
    {
        [$owner, $event] = $this->scenario($sport);
        [, , $foreign] = $this->scenario($sport);
        $base = self::DOORS[$sport][0].$event->uuid.'/players';

        $this->actingAs($owner)->getJson("{$base}/{$foreign->id}")->assertNotFound();
        $this->actingAs($owner)->putJson("{$base}/{$foreign->id}", ['weight' => 70])->assertNotFound();

        $this->assertNull($foreign->fresh()->weight);
    }

    /** @dataProvider sports */
    public function test_a_paired_table_reaches_the_tab_as_its_organiser(string $sport): void
    {
        [$owner, $event, $entry] = $this->scenario($sport);
        $token = $this->table($event, $owner->id);
        $base = self::DOORS[$sport][1].$token.'/players';

        $this->getJson($base)->assertOk()->assertJsonPath('players.0.name', 'Ali Hassan');
        $this->putJson("{$base}/{$entry->id}", ['weight' => 63])->assertOk();
        $this->assertEquals(63, (float) $entry->fresh()->weight);

        // The console page itself carries the tab.
        $page = $sport === 'bjj' ? "/bjj/screen/{$token}/control" : "/court/{$token}/control";
        $this->get($page)->assertOk()->assertSee('id="cpList"', false);
    }

    /** @dataProvider sports */
    public function test_a_table_paired_by_a_scorer_gets_no_tab_and_no_door(string $sport): void
    {
        [, $event, $entry] = $this->scenario($sport);
        $jury = $this->createUser();
        EventOfficial::create(['event_id' => $event->id, 'user_id' => $jury->id, 'role' => EventOfficial::ROLE_JURY]);
        $token = $this->table($event, $jury->id);

        $this->getJson(self::DOORS[$sport][1].$token.'/players')->assertForbidden();
        $this->putJson(self::DOORS[$sport][1].$token."/players/{$entry->id}", ['weight' => 70])->assertForbidden();
        $this->assertNull($entry->fresh()->weight);
    }

    /** @dataProvider sports */
    public function test_change_photo_stores_a_real_picture_and_refuses_a_fake_one(string $sport): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::fake('local');

        [$owner, $event, $entry] = $this->scenario($sport);
        $token = $this->table($event, $owner->id);
        $base = self::DOORS[$sport][1].$token."/players/{$entry->id}";

        // A real 3x4 JPEG, as the tab's cropper sends it.
        $img = imagecreatetruecolor(3, 4);
        ob_start();
        imagejpeg($img);
        $jpeg = 'data:image/jpeg;base64,'.base64_encode(ob_get_clean());

        $this->putJson($base, ['photo' => $jpeg])->assertOk()->assertJsonPath('success', true);
        $this->assertNotNull($entry->fresh()->photo, 'the photo landed');

        // Script dressed as a picture is refused by the bytes, not the label.
        $before = $entry->fresh()->photo;
        $this->putJson($base, ['photo' => 'data:image/png;base64,'.base64_encode('<?php echo 1; ?>')])
            ->assertStatus(422);
        $this->assertSame($before, $entry->fresh()->photo);
    }

    public function test_a_table_of_one_sport_cannot_use_another_sports_door(): void
    {
        [$owner, $event] = $this->scenario('bjj');
        $token = $this->table($event, $owner->id);

        // A BJJ table's token on the Taekwondo door: the same answer as a bad token.
        $this->getJson("/court/{$token}/players")->assertNotFound();
        $this->getJson('/court/'.str_repeat('a', 40).'/players')->assertNotFound();
    }
}
