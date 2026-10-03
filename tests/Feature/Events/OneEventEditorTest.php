<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use Tests\TestCase;

/**
 * ONE event editor (owner, 2026-10-03; PLAN.md Phase 6): with events.one_form
 * on, the club admin panel's Add / Edit event open the shared full editor with
 * the club already chosen. Off, the panel keeps its own form exactly as before.
 */
class OneEventEditorTest extends TestCase
{
    private const DESKTOP = 'Mozilla/5.0 (X11; Linux x86_64) Chrome/120';

    private const PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148';

    private function setUpClub(): array
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $owner->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id, 'title' => 'Cup', 'event_type' => 'championship',
            'sport' => 'taekwondo', 'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeek()->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ]);

        return [$owner->fresh(), $club, $event];
    }

    public function test_switched_off_the_panel_keeps_its_own_form(): void
    {
        [$owner, $club] = $this->setUpClub();
        config(['events.one_form' => false]);

        foreach ([self::DESKTOP, self::PHONE] as $ua) {
            $this->actingAs($owner)->withHeaders(['User-Agent' => $ua])
                ->get(route('admin.club.events', $club->slug))->assertOk()
                ->assertViewHas('oneForm', null);
        }
    }

    public function test_switched_on_add_and_edit_open_the_shared_editor_on_both_devices(): void
    {
        [$owner, $club, $event] = $this->setUpClub();
        config(['events.one_form' => true]);
        $create = str_replace('/', '\\/', route('me.events.create', ['club' => $club->slug]));
        $edit = str_replace('/', '\\/', route('me.events.edit', $event->uuid));

        foreach ([self::DESKTOP, self::PHONE] as $ua) {
            $this->actingAs($owner)->withHeaders(['User-Agent' => $ua])
                ->get(route('admin.club.events', $club->slug))->assertOk()
                ->assertSee($create, false)
                ->assertSee($edit, false);
        }
    }

    public function test_the_editor_arrives_with_the_club_chosen_but_only_one_you_may_use(): void
    {
        [$owner, $club] = $this->setUpClub();

        $this->actingAs($owner)->get(route('me.events.create', ['club' => $club->slug]))
            ->assertOk()->assertViewHas('preselectClub', $club->id);

        // Somebody else's club in the address is simply ignored.
        $other = $this->createClub($this->createUser());
        $this->actingAs($owner)->get(route('me.events.create', ['club' => $other->slug]))
            ->assertOk()->assertViewHas('preselectClub', null);
    }

    public function test_a_club_admin_may_open_the_shared_editor_for_their_clubs_event(): void
    {
        [$owner, $club, $event] = $this->setUpClub();
        $admin = $this->createUser();
        $this->makeClubAdmin($admin, $club);

        $this->actingAs($admin)->get(route('me.events.edit', $event->uuid))->assertOk();
    }
}
