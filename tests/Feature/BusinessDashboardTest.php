<?php

namespace Tests\Feature;

use App\Models\Business;
use Tests\TestCase;

/**
 * Regression coverage for ChainDashboardService::build() — every branch must
 * return the same array shape the controller/view expect (previously the
 * empty-clubs early return omitted 'revenue_chart', causing an "Undefined
 * array key" 500 for any approved business with zero linked clubs).
 */
class BusinessDashboardTest extends TestCase
{
    private function createApprovedBusiness($owner): Business
    {
        return Business::create([
            'owner_user_id' => $owner->id,
            'name' => 'Test Chain',
            'slug' => 'test-chain-'.uniqid(),
            'status' => Business::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function test_dashboard_renders_for_a_business_with_no_clubs(): void
    {
        $owner = $this->createUser();
        $this->createApprovedBusiness($owner);

        $this->actingAs($owner)
            ->get('/business/dashboard')
            ->assertOk()
            ->assertSee('Create Club');
    }

    public function test_mobile_dashboard_renders_for_a_business_with_no_clubs(): void
    {
        $owner = $this->createUser();
        $this->createApprovedBusiness($owner);

        $this->actingAs($owner)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36'])
            ->get('/business/dashboard')
            ->assertOk()
            ->assertSee('Create Club');
    }

    public function test_dashboard_renders_for_a_business_with_clubs(): void
    {
        $owner = $this->createUser();
        $business = $this->createApprovedBusiness($owner);
        $club = $this->createClub($owner, ['business_id' => $business->id]);

        $this->actingAs($owner)
            ->get('/business/dashboard')
            ->assertOk()
            ->assertSee($club->club_name);
    }
}
