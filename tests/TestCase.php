<?php

namespace Tests;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Role helpers
    // -------------------------------------------------------------------------

    protected function createRole(string $slug, string $name = ''): Role
    {
        return Role::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name ?: ucfirst(str_replace('-', ' ', $slug)), 'description' => '']
        );
    }

    protected function seedRoles(): void
    {
        $this->createRole('super-admin', 'Super Admin');
        $this->createRole('club-admin',  'Club Admin');
        $this->createRole('instructor',  'Instructor');
        $this->createRole('member',      'Member');
    }

    // -------------------------------------------------------------------------
    // User helpers
    // -------------------------------------------------------------------------

    protected function createUser(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    protected function createUnverifiedUser(array $attrs = []): User
    {
        return User::factory()->unverified()->create($attrs);
    }

    protected function makeSuperAdmin(User $user): void
    {
        $this->createRole('super-admin');
        $user->assignRole('super-admin');
    }

    protected function makeClubAdmin(User $user, Tenant $club): void
    {
        $this->createRole('club-admin');
        $user->assignRole('club-admin', $club->id);
    }

    // -------------------------------------------------------------------------
    // Club (Tenant) helpers
    // -------------------------------------------------------------------------

    protected function createClub(User $owner, array $attrs = []): Tenant
    {
        return Tenant::create(array_merge([
            'owner_user_id' => $owner->id,
            'club_name'     => 'Test Club',
            'slug'          => 'test-club-' . uniqid(),
            'status'        => 'active',
        ], $attrs));
    }
}
