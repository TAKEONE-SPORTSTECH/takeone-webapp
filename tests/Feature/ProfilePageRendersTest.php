<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\MemberEvent;
use Tests\TestCase;

/**
 * Smoke tests: the member & family profile pages must render (HTTP 200) with
 * the new Goals / Attendance / Event-log sections present — catches runtime
 * Blade errors that a compile check cannot.
 */
class ProfilePageRendersTest extends TestCase
{
    public function test_member_profile_page_renders_for_self(): void
    {
        $user = $this->createUser();
        Goal::create([
            'user_id' => $user->id, 'title' => 'Run 5k', 'target_value' => 5, 'unit' => 'km',
            'current_progress_value' => 1, 'status' => 'active', 'priority_level' => 'medium',
            'start_date' => now()->toDateString(), 'target_date' => now()->addMonth()->toDateString(),
        ]);
        MemberEvent::create([
            'user_id' => $user->id, 'title' => 'Charity Walk', 'event_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get("/member/{$user->uuid}")
            ->assertOk()
            ->assertSee('Goal Tracking')
            ->assertSee('Personal Event Log');
    }

    public function test_mobile_member_profile_renders_previous_clubs_link(): void
    {
        // The mobile Clubs tab now shows a single active card + a collapsible
        // "Previous clubs" history link (was two stacked empty cards).
        $user = $this->createUser();

        $this->actingAs($user)
            ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148')
            ->get("/member/{$user->uuid}")
            ->assertOk()
            ->assertSee('Previous clubs')
            ->assertDontSee('No club affiliations.');
    }

    public function test_admin_member_view_renders_with_new_sections(): void
    {
        // family/show.blade.php is the super-admin member view (/admin/members/{id}).
        $super = $this->createUser();
        $this->makeSuperAdmin($super);
        $member = $this->createUser();
        MemberEvent::create([
            'user_id' => $member->id, 'title' => 'Open Day', 'event_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($super)
            ->get("/admin/members/{$member->id}")
            ->assertOk()
            ->assertSee('Personal Event Log')
            ->assertSee('Goal Tracking');
    }
}
