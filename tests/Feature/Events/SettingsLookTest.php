<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Scoreboard\Support\VsLayout;
use Tests\TestCase;

/**
 * The shared Settings pop-up's platform-wide tabs — Photo guide and VS layout —
 * on every sport's console (App\Scoreboard\Support\LookController). A
 * super-admin sees and saves them; anybody else sees neither and is refused.
 */
class SettingsLookTest extends TestCase
{
    public static function sports(): array
    {
        return ['taekwondo' => ['taekwondo', '/taekwondo/control/'], 'bjj' => ['bjj', '/bjj/control/']];
    }

    private function event(string $sport, $owner): ClubEvent
    {
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);
        $event = ClubEvent::create(['tenant_id' => $club->id, 'created_by' => $owner->id, 'title' => 'Look Open', 'event_type' => 'championship',
            'sport' => $sport, 'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeek()->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00']);
        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Male -68 kg', 'sort_order' => 1]);
        EventMatch::create(['event_id' => $event->id, 'category_id' => $cat->id, 'round' => 'Final', 'phase' => 'finals',
            'slot' => 0, 'a_name' => 'A', 'b_name' => 'B', 'court' => 'Mat 1', 'status' => 'upcoming']);

        return $event;
    }

    /** @dataProvider sports */
    public function test_a_super_admin_has_both_tabs_and_saves_them(string $sport, string $door): void
    {
        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);
        $event = $this->event($sport, $admin);

        $this->actingAs($admin)->get($door.$event->uuid)->assertOk()
            ->assertSee('data-tab="guide"', false)->assertSee('data-tab="vs"', false);

        $this->actingAs($admin)->postJson("/scoreboard/{$event->uuid}/vs-layout", [
            'layout' => ['info_bottom' => 120, 'info_scale' => 0.9, 'show_stats' => true],
        ])->assertOk()->assertJsonPath('success', true);
        $this->assertEquals(120, VsLayout::get()['info_bottom']);

        $this->actingAs($admin)->postJson("/scoreboard/{$event->uuid}/photo-guide", ['reset' => true])
            ->assertOk()->assertJsonPath('success', true);
    }

    /** @dataProvider sports */
    public function test_an_organiser_sees_neither_and_is_refused(string $sport, string $door): void
    {
        $owner = $this->createUser();
        $event = $this->event($sport, $owner);

        $this->actingAs($owner)->get($door.$event->uuid)->assertOk()
            ->assertDontSee('data-tab="guide"', false)->assertDontSee('data-tab="vs"', false);

        $this->actingAs($owner)->postJson("/scoreboard/{$event->uuid}/vs-layout", [
            'layout' => ['info_bottom' => 300, 'info_scale' => 1],
        ])->assertForbidden();
        $this->actingAs($owner)->postJson("/scoreboard/{$event->uuid}/photo-guide", ['reset' => true])->assertForbidden();
    }
}
