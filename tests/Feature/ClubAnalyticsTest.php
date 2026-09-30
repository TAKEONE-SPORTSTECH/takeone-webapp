<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClubAnalyticsTest extends TestCase
{
    public function test_analytics_shows_the_clubs_own_numbers(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        foreach (range(1, 3) as $i) {
            DB::table('memberships')->insert([
                'tenant_id' => $club->id, 'user_id' => $this->createUser()->id,
                'status' => $i === 3 ? 'inactive' : 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($owner)->get(route('admin.club.analytics', $club->slug))
            ->assertOk()
            ->assertViewHas('analytics', fn ($a) => $a['new_members'] === 3 && $a['retention_rate'] == 67);
    }

    public function test_another_clubs_admin_cannot_read_it(): void
    {
        $club = $this->createClub($this->createUser());

        $this->actingAs($this->createUser())
            ->getJson(route('admin.club.analytics', $club->slug))
            ->assertForbidden();
    }
}
