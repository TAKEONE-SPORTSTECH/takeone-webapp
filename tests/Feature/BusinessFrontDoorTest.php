<?php

namespace Tests\Feature;

use App\Clubs\Models\Business;
use Tests\TestCase;

/**
 * /business — the chain owner's front door — and the super-admin's read-only
 * look into any approved business (?b={slug} on the dashboard).
 */
class BusinessFrontDoorTest extends TestCase
{
    private function business($owner, string $name = 'Test Chain', string $status = Business::STATUS_APPROVED): Business
    {
        return Business::create([
            'owner_user_id' => $owner->id,
            'name' => $name,
            'slug' => 'chain-'.uniqid(),
            'status' => $status,
            'approved_at' => now(),
        ]);
    }

    public function test_owner_goes_to_their_dashboard_and_others_to_setup(): void
    {
        $owner = $this->createUser();
        $this->business($owner);

        $this->actingAs($owner)->get('/business')->assertRedirect(route('business.dashboard'));
        $this->actingAs($this->createUser())->get('/business')->assertRedirect(route('business.setup'));
        $this->actingAs($owner)->get('/busniess')->assertRedirect(route('business.home'));
    }

    public function test_super_admin_picks_a_business_and_sees_it_read_only(): void
    {
        $a = $this->business($this->createUser(), 'Alpha Chain');
        $b = $this->business($this->createUser(), 'Beta Chain');
        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);

        $this->actingAs($admin)->get('/business')->assertOk()->assertSee('Alpha Chain')->assertSee('Beta Chain');

        $this->actingAs($admin)->get('/business/dashboard?b='.$b->slug)
            ->assertOk()
            ->assertSee('Beta Chain')
            ->assertDontSee('open-club-modal');

        // An unknown slug goes back to the picker rather than a 404.
        $this->actingAs($admin)->get('/business/dashboard?b=nope')->assertRedirect(route('business.home'));
    }

    public function test_an_owner_cannot_look_into_another_business(): void
    {
        $mine = $this->business($owner = $this->createUser(), 'Mine Chain');
        $theirs = $this->business($this->createUser(), 'Theirs Chain');

        $this->actingAs($owner)->get('/business/dashboard?b='.$theirs->slug)
            ->assertOk()
            ->assertSee('Mine Chain')
            ->assertDontSee('Theirs Chain');
    }

    public function test_a_pending_business_is_not_offered_to_a_super_admin(): void
    {
        $pending = $this->business($this->createUser(), 'Pending Chain', Business::STATUS_PENDING);
        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);

        $this->actingAs($admin)->get('/business/dashboard?b='.$pending->slug)->assertRedirect(route('business.home'));
    }
}
