<?php

namespace Tests\Feature\Members;

use App\Members\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The public sign-up form never makes anybody a super-admin (security fix,
 * 2026-10-03). It used to, whenever no super-admin existed — so losing the one
 * super-admin account handed the platform to the next stranger who registered.
 */
class RegistrationNeverGrantsSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_with_no_super_admin_on_the_platform_grants_nothing(): void
    {
        $this->seedRoles();
        Mail::fake();
        $this->assertFalse(User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->exists());

        $this->post('/register', [
            'full_name' => 'Stranger', 'email' => 'stranger@example.com',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            'mobile_number' => '33001122', 'country_code' => '+973',
            'gender' => 'Male', 'birthdate' => '2000-01-01', 'nationality' => 'Bahraini',
        ]);

        $user = User::where('email', 'stranger@example.com')->first();
        $this->assertNotNull($user, 'the registration itself still works');
        $this->assertFalse($user->fresh()->isSuperAdmin());
    }
}
