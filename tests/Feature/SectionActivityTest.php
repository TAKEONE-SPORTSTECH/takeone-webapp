<?php

namespace Tests\Feature;

use App\Models\Duel;
use App\Support\SectionActivity;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SectionActivityTest extends TestCase
{
    private function joinClub($user, $club): void
    {
        DB::table('memberships')->insert([
            'tenant_id' => $club->id, 'user_id' => $user->id, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_pending_challenge_lights_the_challenge_dot_then_clears_when_seen(): void
    {
        $me = $this->createUser();
        $rival = $this->createUser();
        Duel::create([
            'challenger_id' => $rival->id, 'opponent_id' => $me->id, 'status' => 'pending', 'discipline' => 'boxing',
        ]);

        $this->assertTrue((new SectionActivity)->dots($me)['challenge'], 'pending challenge should light the dot');

        (new SectionActivity)->markSeen($me, 'challenge');

        $this->assertFalse((new SectionActivity)->dots($me)['challenge'], 'dot clears after seen');
    }

    public function test_new_club_event_lights_the_events_dot(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $me = $this->createUser();
        $this->joinClub($me, $club);

        \App\Models\ClubEvent::create([
            'tenant_id' => $club->id, 'title' => 'Open Mat', 'date' => now()->addWeek()->toDateString(),
            'start_time' => '18:00', 'end_time' => '19:00', 'status' => 'published', 'is_archived' => false,
        ]);

        $this->assertTrue((new SectionActivity)->dots($me)['events']);
    }

    public function test_mark_section_seen_endpoint_persists(): void
    {
        $me = $this->createUser();

        $this->actingAs($me)->postJson('/me/seen', ['section' => 'events'])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('user_section_views', ['user_id' => $me->id, 'section' => 'events']);
    }

    public function test_mark_section_seen_ignores_unknown_section(): void
    {
        $me = $this->createUser();

        $this->actingAs($me)->postJson('/me/seen', ['section' => 'not-a-real-section'])
            ->assertOk();

        $this->assertDatabaseMissing('user_section_views', ['user_id' => $me->id, 'section' => 'not-a-real-section']);
    }

    public function test_feed_page_renders_with_indicators(): void
    {
        $me = $this->createUser();

        $this->actingAs($me)
            ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148')
            ->get('/me')->assertOk();
    }
}
