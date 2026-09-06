<?php

namespace Tests;

use App\Members\Models\Role;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
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

        $this->assertRealtimeIsSilenced();
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

    /**
     * SAFETY: never let the suite reach the LIVE message broker.
     *
     * A realtime topic is keyed on the numeric user id — `takeone/user/{id}/…`
     * — and a fresh in-memory database numbers its users from 1. So a test that
     * notifies "user 1" publishes onto the topic the REAL user 1's browser is
     * sitting on, and the fake order, entry, draw or podium it invented arrives
     * as a notification popup, with the sound, on somebody's actual screen.
     *
     * That is not hypothetical: phpunit.xml did not set REALTIME_ENABLED, so it
     * fell through to .env — where it is true and points at the production
     * broker — and every run of this suite spammed the account with id 1. Proven
     * on 2026-09-02 by subscribing to that topic with the credential the browser
     * is issued and watching the traffic arrive.
     *
     * phpunit.xml now sets it false. This is the second lock, for the same
     * reason the database has one: `config:cache` freezes env() into
     * bootstrap/cache/config.php and silently overrides phpunit's env, so the
     * setting alone is not a guarantee.
     *
     * If this fires: `php artisan config:clear`, then run the tests again.
     */
    private function assertRealtimeIsSilenced(): void
    {
        if (! function_exists('Realtime')) {
            return;
        }

        if (\Realtime()->enabled()) {
            $this->fail(
                "REFUSING TO RUN: realtime is ENABLED for the test suite.\n"
                ."Topics are keyed on the numeric user id, and test users start at 1, so this\n"
                ."would publish invented notifications onto real accounts' live topics.\n\n"
                .'Fix: php artisan config:clear (phpunit.xml sets REALTIME_ENABLED=false)'
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
