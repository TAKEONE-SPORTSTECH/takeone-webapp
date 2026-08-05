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

    /**
     * SAFETY: never let the suite touch a real database.
     *
     * `RefreshDatabase` runs `migrate:fresh`, which DROPS EVERY TABLE. phpunit.xml
     * points the suite at an in-memory SQLite database — but `php artisan
     * config:cache` freezes env() into bootstrap/cache/config.php, and a cached
     * config silently overrides those phpunit env vars. Run the suite while a
     * config cache exists and RefreshDatabase wipes the REAL database instead.
     *
     * That has now happened once (2026-08-02: the stage database was dropped by
     * a test run that followed a `config:cache`). This guard makes it impossible
     * to repeat: the run aborts before a single table is touched.
     *
     * If this fires: `php artisan config:clear`, then run the tests again.
     */
    protected function setUp(): void
    {
        $this->assertSafeTestDatabase();

        parent::setUp();
    }

    private function assertSafeTestDatabase(): void
    {
        $cachedConfig = __DIR__.'/../bootstrap/cache/config.php';

        if (file_exists($cachedConfig)) {
            $this->fail(
                "REFUSING TO RUN: a cached config exists (bootstrap/cache/config.php).\n"
                ."It overrides phpunit.xml's DB_DATABASE=:memory:, so RefreshDatabase would\n"
                ."run migrate:fresh against the REAL database and drop every table.\n\n"
                .'Fix: php artisan config:clear'
            );
        }

        // Belt and braces — the connection must be in-memory whatever the env says.
        $connection = env('DB_CONNECTION', 'sqlite');
        $database = env('DB_DATABASE');

        if ($connection === 'sqlite' && $database !== ':memory:') {
            $this->fail(
                'REFUSING TO RUN: the test suite is pointed at a FILE database ('.var_export($database, true).").\n"
                ."RefreshDatabase would drop every table in it. Expected ':memory:' from phpunit.xml."
            );
        }
    }

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
