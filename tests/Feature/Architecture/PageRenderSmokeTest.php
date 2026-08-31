<?php

namespace Tests\Feature\Architecture;

use App\Clubs\Models\Tenant;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Architecture characterization: every parameter-free authenticated page still
 * ANSWERS.
 *
 * This is a smoke test, not a feature test. It does not check what a page says
 * — the feature suites do that. It checks the one thing a refactor is most
 * likely to break and least likely to notice: that the route still resolves, the
 * controller still constructs, the view still compiles, and nothing fatals on
 * the way. A module migration that moves a controller, renames a view namespace
 * or drops a `use` statement shows up here as a 500 on twenty pages at once.
 *
 * What counts as a pass:
 *   - any status below 500. A 302 is EXPECTED for a page the actor may not see:
 *     `bootstrap/app.php` deliberately reroutes a forbidden BROWSER GET to '/'
 *     with a flash rather than dead-ending on a raw 403 (see CLAUDE.md, "403s
 *     Redirect on Web, Return JSON on API"). Denying access is correct
 *     behaviour; fatalling is not.
 *   - a body carrying no PHP error signature. Laravel renders an uncaught
 *     exception as a 500, but a partially-rendered view or a debug-mode trace
 *     can arrive with a 200, so the body is checked too.
 *
 * NOT covered here on purpose: device/kiosk surfaces (/screen*, /camera,
 * /court*, scoreboards) — they have their own recovery contract (CLAUDE.md,
 * "Unattended Devices Must Always Recover") and their own coverage.
 */
class PageRenderSmokeTest extends TestCase
{
    /** Text that only ever appears when PHP fell over. */
    private const ERROR_SIGNATURES = [
        'Fatal error',
        'Parse error',
        'Undefined variable',
        'Undefined property',
        'Undefined array key',
        'Call to a member function',
        'Call to undefined',
        'ErrorException',
        'ParseError',
        'Whoops\\',
        'Stack trace:',
        'vendor/laravel/framework/src/Illuminate/Foundation/Exceptions',
        'Illuminate\\View\\ViewException',
    ];

    /** Pages that legitimately render log/error text and cannot be signature-screened. */
    private const RENDERS_LOG_TEXT = [
        '/admin/logs',
    ];

    /**
     * Parameter-free GET pages behind auth, as registered today.
     *
     * Derived from `php artisan route:list`, minus: guest auth screens, JSON/file
     * endpoints that download rather than render, and the device/kiosk routes
     * called out in the class docblock. Keep it in step when a page is added —
     * a new page that is never smoke-tested is a page nobody notices breaking.
     *
     * @return array<string, array{string}>
     */
    public static function authenticatedPages(): array
    {
        $uris = [
            // Member ("/me") shell
            '/me',
            '/me/affiliations',
            '/me/app',
            '/me/challenge',
            '/me/challenge/create',
            '/me/challenge/history',
            '/me/events',
            '/me/events/create',
            '/me/family',
            '/me/family/data',
            '/me/market',
            '/me/orders',
            '/me/packages',
            '/me/payments',
            '/me/people',
            '/me/people/search',
            '/me/profile',
            '/me/progress',
            '/me/schedule',
            '/me/schedule/data',
            '/me/settings',
            '/me/videos',
            '/me/videos/data',

            // Platform browsing
            '/explore',
            '/explore/events',
            '/clubs/all',
            '/clubs/nearby',

            // Family / members
            '/family',
            '/family/create',
            '/family/dashboard',
            '/family/members',
            '/family/members/create',
            '/family/search-existing',
            '/members',
            '/profile',

            // Billing
            '/bills',
            '/bills/pay-all',

            // Messaging
            '/messages',
            '/messages/conversations',
            '/messages/search-users',
            '/messages/unread-count',

            // Business / chain
            '/business/create',
            '/business/dashboard',

            // Account security
            '/security',
            '/security/two-factor/setup',

            // Misc member surfaces
            '/market/forms-preview',
            '/screen/paired',

            // Super-admin platform panel
            '/admin',
            '/admin/activities',
            '/admin/ai',
            '/admin/api/users',
            '/admin/audit-log',
            '/admin/backup',
            '/admin/businesses',
            '/admin/clubs',
            '/admin/clubs/create',
            '/admin/logs',
            '/admin/members',
            '/admin/plugins/realtime',
            '/admin/settings',
            '/admin/storage',
        ];

        $cases = [];

        foreach ($uris as $uri) {
            $cases[$uri] = [$uri];
        }

        return $cases;
    }

    /** A super-admin who also owns a club — reaches every surface in the list. */
    private function superAdminOwningAClub(): User
    {
        $this->seedRoles();

        $user = $this->createUser([
            'gender' => 'Male',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);

        $this->makeSuperAdmin($user);

        $club = $this->createClub($user, ['country' => 'BH', 'currency' => 'BHD']);
        $this->makeClubAdmin($user, $club);
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $user->fresh();
    }

    private function assertAnswered(string $uri, \Illuminate\Testing\TestResponse $response): void
    {
        $status = $response->getStatusCode();

        $this->assertLessThan(
            500,
            $status,
            "GET {$uri} returned {$status}. A 2xx or a 3xx is fine (a forbidden browser GET "
            .'is rerouted to / on purpose); a 5xx means the page fatalled.'
        );

        $body = $response->getContent();

        // Only HTML can carry a rendered error page; a redirect body is empty.
        if ($body === false || $body === '' || $status >= 300) {
            return;
        }

        // Pages whose JOB is to display error text cannot be screened for error
        // signatures — /admin/logs renders the tail of laravel.log, so any
        // historical exception scrolling into its window would fail this test
        // while the page itself is working perfectly. Status is still asserted.
        if (in_array($uri, self::RENDERS_LOG_TEXT, true)) {
            return;
        }

        foreach (self::ERROR_SIGNATURES as $signature) {
            $this->assertStringNotContainsString(
                $signature,
                $body,
                "GET {$uri} answered {$status} but its body contains the PHP error "
                ."signature \"{$signature}\" — something fatalled mid-render."
            );
        }
    }

    #[DataProvider('authenticatedPages')]
    public function test_page_answers_without_a_server_error(string $uri): void
    {
        $response = $this->actingAs($this->superAdminOwningAClub())->get($uri);

        $this->assertAnswered($uri, $response);
    }

    /**
     * The member shell again as an ORDINARY member with no club and no roles —
     * the emptiest possible account. Empty states are where a view most often
     * dereferences something that isn't there.
     *
     * @return array<string, array{string}>
     */
    public static function memberPages(): array
    {
        $cases = [];

        foreach (self::authenticatedPages() as $uri => $case) {
            if (str_starts_with($uri, '/me') || str_starts_with($uri, '/family') || $uri === '/explore') {
                $cases[$uri] = $case;
            }
        }

        return $cases;
    }

    #[DataProvider('memberPages')]
    public function test_member_page_answers_for_a_brand_new_account(string $uri): void
    {
        $this->seedRoles();

        $user = $this->createUser([
            'gender' => 'Female',
            'birthdate' => now()->subYears(22)->toDateString(),
        ]);
        $user->assignRole('member');

        $this->assertAnswered($uri, $this->actingAs($user->fresh())->get($uri));
    }

    /**
     * The same member pages through a phone user-agent, so DetectDevice picks
     * the mobile Blade file. Mobile and desktop are separate views (CLAUDE.md,
     * "Mobile / Desktop Separation") — a desktop-only smoke test proves nothing
     * about the half of the codebase that ships in the Android app.
     */
    #[DataProvider('memberPages')]
    public function test_member_page_answers_on_a_phone(string $uri): void
    {
        $this->seedRoles();

        $user = $this->createUser([
            'gender' => 'Male',
            'birthdate' => now()->subYears(19)->toDateString(),
        ]);
        $user->assignRole('member');

        $response = $this->actingAs($user->fresh())
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 '
                    .'(KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36',
            ])
            ->get($uri);

        $this->assertAnswered($uri, $response);
    }

    /** Guard the guard: the list must not silently shrink to nothing. */
    public function test_the_smoke_list_still_covers_the_pages_it_did(): void
    {
        $this->assertCount(
            61,
            self::authenticatedPages(),
            'The smoke list changed size — confirm the page was really added or removed.'
        );

        // Nothing device/kiosk may creep in; those have their own contract.
        foreach (array_keys(self::authenticatedPages()) as $uri) {
            $this->assertDoesNotMatchRegularExpression(
                '#^/(camera|court|karate/court|bjj/screen|openmat)#',
                $uri,
                "{$uri} is a device/kiosk surface and belongs in the device recovery suite."
            );
        }
    }
}
