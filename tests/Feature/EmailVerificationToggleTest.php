<?php

namespace Tests\Feature;

use App\Mail\WelcomeEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailVerificationToggleTest extends TestCase
{
    private function regData(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'New Member',
            'email' => 'newmember@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'mobile_number' => '33001122',
            'gender' => 'Male',
            'birthdate' => '2000-01-01',
            'country_code' => '+973',
            'nationality' => 'Bahraini',
        ], $overrides);
    }

    /** A club with verification OFF verifies the registrant on the spot, no email. */
    public function test_registration_under_a_club_with_verification_off_auto_verifies(): void
    {
        $this->makeSuperAdmin($this->createUser());   // so the registrant isn't the first-user super-admin
        $club = $this->createClub($this->createUser(), ['require_email_verification' => false]);
        Mail::fake();

        $resp = $this->withSession(['club.context' => ['slug' => $club->slug], 'url.intended' => '/explore'])
            ->post('/register', $this->regData());

        $user = User::where('email', 'newmember@example.com')->first();
        $this->assertNotNull($user, 'user was created');
        $this->assertNotNull($user->email_verified_at, 'auto-verified when club verification is off');
        Mail::assertNotQueued(WelcomeEmail::class);
        $resp->assertRedirect('/explore');
    }

    /** A club with verification ON (the default) still requires it + emails the link. */
    public function test_registration_under_a_club_with_verification_on_still_verifies(): void
    {
        $this->makeSuperAdmin($this->createUser());
        $club = $this->createClub($this->createUser());   // default require_email_verification = true
        $this->assertTrue($club->fresh()->require_email_verification);
        Mail::fake();

        $resp = $this->withSession(['club.context' => ['slug' => $club->slug]])
            ->post('/register', $this->regData(['email' => 'verify@example.com']));

        $user = User::where('email', 'verify@example.com')->first();
        $this->assertNull($user->email_verified_at, 'still unverified');
        Mail::assertQueued(WelcomeEmail::class);
        $resp->assertRedirect(route('verification.notice'));
    }

    /** No club context → platform default: verification required. */
    public function test_plain_registration_requires_verification(): void
    {
        $this->makeSuperAdmin($this->createUser());
        Mail::fake();

        $this->post('/register', $this->regData(['email' => 'plain@example.com']));

        $user = User::where('email', 'plain@example.com')->first();
        $this->assertNull($user->email_verified_at);
        Mail::assertQueued(WelcomeEmail::class);
    }

    /** A club admin can flip the switch off from the details form. */
    public function test_club_admin_can_disable_email_verification(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->makeClubAdmin($owner, $club);
        $this->assertTrue($club->fresh()->require_email_verification);

        $this->actingAs($owner)->put(route('admin.club.update', $club->slug), [
            'club_name' => $club->club_name,
            'require_email_verification' => '0',
        ]);

        $this->assertFalse($club->fresh()->require_email_verification);
    }
}
