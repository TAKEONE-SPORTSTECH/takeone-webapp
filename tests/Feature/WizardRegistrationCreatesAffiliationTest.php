<?php

namespace Tests\Feature;

use App\Models\ClubAffiliation;
use App\Models\ClubPackage;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

/**
 * A club-page (wizard) registration created the subscription, the ledger row and
 * the membership, but never called SubscriptionService::syncAffiliation() the way
 * every other join path does. So the member ended up with NO ClubAffiliation:
 * the club was missing from their profile's Affiliations tab and none of the
 * package's activities were recorded as skills.
 */
class WizardRegistrationCreatesAffiliationTest extends TestCase
{
    private function club(): array
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'club_name' => 'TAKEONE SportsTech']);

        $package = ClubPackage::create([
            'tenant_id' => $club->id,
            'name' => 'Monthly',
            'price' => 45,
            'duration_days' => 30,
        ]);

        return [$owner, $club, $package];
    }

    private function registerViaWizard(Tenant $club, ClubPackage $package): User
    {
        $email = 'wizard'.uniqid().'@example.com';

        // The wizard verifies the email by OTP before submit; the controller
        // consumes that session flag, so mirror it here.
        $this->withSession(['wizard.verified' => $email]);

        $this->postJson('/register/wizard/submit', [
            'full_name' => 'Wizard Member',
            'email' => $email,
            'mobile_code' => '+973',
            'mobile_number' => '3'.rand(1000000, 9999999),
            'nationality' => 'Bahraini',
            'club_slug' => $club->slug,
            'self_gender' => 'Male',
            'self_birthdate' => now()->subYears(25)->toDateString(),
            'self_packages' => [$package->id],
        ])->assertOk();

        return User::where('email', $email)->firstOrFail();
    }

    public function test_a_club_page_registration_creates_the_club_affiliation(): void
    {
        [, $club, $package] = $this->club();

        $member = $this->registerViaWizard($club, $package);

        $affiliation = ClubAffiliation::where('member_id', $member->id)
            ->where('tenant_id', $club->id)
            ->first();

        $this->assertNotNull($affiliation, 'Registering through the club page must create a ClubAffiliation.');
        $this->assertSame('TAKEONE SportsTech', $affiliation->club_name);
    }

    public function test_the_subscription_is_linked_to_that_affiliation(): void
    {
        [, $club, $package] = $this->club();

        $member = $this->registerViaWizard($club, $package);

        $affiliation = ClubAffiliation::where('member_id', $member->id)->where('tenant_id', $club->id)->first();
        $this->assertNotNull($affiliation);

        $this->assertDatabaseHas('club_member_subscriptions', [
            'tenant_id' => $club->id,
            'user_id' => $member->id,
            'club_affiliation_id' => $affiliation->id,
        ]);
    }

    public function test_registering_twice_reuses_one_affiliation_per_club(): void
    {
        [, $club, $package] = $this->club();

        $second = ClubPackage::create([
            'tenant_id' => $club->id,
            'name' => 'Evening',
            'price' => 30,
            'duration_days' => 30,
        ]);

        $member = $this->registerViaWizard($club, $package);

        // Enrol the same member in another package the same way the service would.
        $sub = \App\Models\ClubMemberSubscription::where('user_id', $member->id)->firstOrFail();
        app(\App\Services\SubscriptionService::class)->syncAffiliation($club, $member->id, $sub, $second);

        $this->assertSame(
            1,
            ClubAffiliation::where('member_id', $member->id)->where('tenant_id', $club->id)->count(),
            'One affiliation per member per club.'
        );
    }
}
