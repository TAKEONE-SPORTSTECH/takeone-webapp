<?php

namespace Tests\Feature\Characterization;

use PHPUnit\Framework\Attributes\DataProvider;
use App\Clubs\Models\ClubAffiliation;
use App\Models\ClubMemberSubscription;
use App\Clubs\Models\ClubPackage;
use App\Clubs\Models\ClubPackageActivity;
use App\Clubs\Models\ClubActivity;
use App\Clubs\Models\ClubInstructor;
use App\Models\Goal;
use App\Clubs\Models\Tenant;
use App\Models\User;
use App\Models\UserScheduleSession;
use App\Shop\Models\ClubProduct;
use App\Shop\Models\ClubProductCategory;
use App\Support\SyncedClassToken;
use Tests\Feature\Contracts\ContractTestCase;

/**
 * CHARACTERIZATION — the READ (GET) surface of
 * app/Http/Controllers/PersonalMobileController.php.
 *
 * These tests pin CURRENT behaviour so that a later refactor which splits this
 * 3,700-line controller cannot silently change what a route returns. They make
 * no claim that the behaviour is CORRECT — only that it is what ships today.
 *
 * What is pinned, per route:
 *   - the HTTP status for the owner, for a guest, and (where a parameter names
 *     someone else's resource) for a non-owner;
 *   - for HTML: the Blade view that actually rendered, on BOTH device branches
 *     (the controller reads `is_mobile`, set by the DetectDevice middleware from
 *     the User-Agent), plus the view-data keys the Blade depends on;
 *   - for JSON: the top-level response structure.
 *
 * A `// DIVERGENCE:` comment marks behaviour that looks wrong but is pinned as-is.
 *
 * Re-run: sudo -u www-data php artisan config:clear \
 *      && sudo -u www-data vendor/bin/phpunit tests/Feature/Characterization
 */
class PersonalMobileReadRoutesTest extends ContractTestCase
{
    /** A plain laptop Chrome — DetectDevice leaves is_mobile false. */
    private const DESKTOP_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /** An iPhone — DetectDevice sets is_mobile true (and NOT for an iPad). */
    private const PHONE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    private function desktop(User $user): self
    {
        $this->actingAs($user)->withHeader('User-Agent', self::DESKTOP_UA);

        return $this;
    }

    private function phone(User $user): self
    {
        $this->actingAs($user)->withHeader('User-Agent', self::PHONE_UA);

        return $this;
    }

    /** A verified member who belongs to one active club. */
    private function member(array $attrs = []): array
    {
        $user = $this->createUser(array_merge(['full_name' => 'Sara Ahmed'], $attrs));
        $club = $this->clubFor($user);

        return [$user, $club];
    }

    // ---------------------------------------------------------------------
    // Guests
    // ---------------------------------------------------------------------

    /**
     * Every read route sits behind ['auth','verified','two-factor']. A guest is
     * bounced to the login page — never shown a 403 or a partial page.
     */
    public static function guestReadRoutes(): array
    {
        return [
            'home' => ['/me'],
            'affiliations' => ['/me/affiliations'],
            'market' => ['/me/market'],
            'market show' => ['/me/market/1'],
            'packages' => ['/me/packages'],
            'payments' => ['/me/payments'],
            'profile' => ['/me/profile'],
            'progress' => ['/me/progress'],
            'schedule' => ['/me/schedule'],
            'schedule data' => ['/me/schedule/data'],
            'schedule show' => ['/me/schedule/1'],
            'schedule synced' => ['/me/schedule/synced/abc'],
            'substitute search' => ['/me/schedule/synced/abc/substitutes'],
            'settings' => ['/me/settings'],
            'videos' => ['/me/videos'],
            'videos data' => ['/me/videos/data'],
        ];
    }

    #[DataProvider('guestReadRoutes')]
    public function test_a_guest_is_redirected_to_login(string $uri): void
    {
        $this->get($uri)->assertRedirect('/login');
    }

    /** An unverified member is bounced to the verification notice, not the page. */
    public function test_an_unverified_member_is_sent_to_email_verification(): void
    {
        $user = $this->createUnverifiedUser();

        $this->actingAs($user)->get('/me')->assertRedirect(route('verification.notice'));
    }

    // ---------------------------------------------------------------------
    // GET /me — me.home
    // ---------------------------------------------------------------------

    public function test_home_renders_the_desktop_feed(): void
    {
        [$user] = $this->member();

        $this->desktop($user)->get('/me')
            ->assertOk()
            ->assertViewIs('personal.desktop.home')
            ->assertViewHas('user')
            ->assertViewHas('posts')
            ->assertViewHas('personalPosts')
            ->assertViewHas('followingPosts')
            ->assertViewHas('suggestions')
            ->assertViewHas('allPosts')
            ->assertViewHas('feedTabDots');
    }

    public function test_home_renders_the_mobile_feed(): void
    {
        [$user] = $this->member();

        $this->phone($user)->get('/me')
            ->assertOk()
            ->assertViewIs('personal.mobile.home')
            ->assertViewHas('user')
            ->assertViewHas('feedTabDots');
    }

    /** A member with no club at all still gets a page, not an error. */
    public function test_home_renders_for_a_member_with_no_club(): void
    {
        $user = $this->createUser();

        $this->desktop($user)->get('/me')->assertOk()->assertViewIs('personal.desktop.home');
    }

    // ---------------------------------------------------------------------
    // GET /me/schedule + /me/schedule/data
    // ---------------------------------------------------------------------

    public function test_schedule_renders_on_both_device_branches(): void
    {
        [$user] = $this->member();

        $keys = ['weekDays', 'sessions', 'members', 'todayKey', 'todayShort',
            'subjectsList', 'iconChoices', 'colorChoices'];

        $desktop = $this->desktop($user)->get('/me/schedule')->assertOk()
            ->assertViewIs('personal.desktop.schedule');
        foreach ($keys as $key) {
            $desktop->assertViewHas($key);
        }

        $mobile = $this->phone($user)->get('/me/schedule')->assertOk()
            ->assertViewIs('personal.mobile.schedule');
        foreach ($keys as $key) {
            $mobile->assertViewHas($key);
        }
    }

    public function test_schedule_data_returns_the_four_top_level_keys(): void
    {
        [$user] = $this->member();

        $response = $this->actingAs($user)->getJson('/me/schedule/data')->assertOk();

        $response->assertJsonStructure(['weekDays', 'sessions', 'members', 'todayKey']);

        // The page-only key `todayShort` is deliberately NOT in the JSON.
        $this->assertSame(['weekDays', 'sessions', 'members', 'todayKey'], array_keys($response->json()));
    }

    // ---------------------------------------------------------------------
    // GET /me/schedule/{session} — me.schedule.show (a PERSONAL session)
    // ---------------------------------------------------------------------

    private function personalSession(User $owner, ?array $workout = null): UserScheduleSession
    {
        return UserScheduleSession::create([
            'user_id' => $owner->id,
            'subject_user_id' => $owner->id,
            'day' => 'monday',
            'start_time' => '06:30',
            'end_time' => '07:30',
            'title' => 'Morning Strength',
            'discipline' => 'Strength',
            'icon' => 'bi-lightning',
            'color' => '#7c3aed',
            'coach' => 'Self',
            'location' => 'Home gym',
            'location_meta' => ['type' => 'text'],
            'intensity' => 'medium',
            'focus' => ['legs'],
            'notes' => 'Deload week',
            // The detail Blade renders each `main` item as a STRUCTURED exercise
            // (name / sets / reps / note), while `warmup` and `cooldown` are
            // plain strings.
            //
            // FIXED 2026-08-31 (was a DIVERGENCE): /me/schedule/data
            // (scheduleData) happily returned a `main` array of plain strings
            // while /me/schedule/{session} (scheduleShow) rendered $ex['name']
            // with no guard and 500'd on that very same row. scheduleShow now
            // normalises `main` to the structured shape the write paths store,
            // so both read endpoints serve the same row — see
            // test_schedule_show_renders_a_session_whose_main_workout_is_plain_strings.
            'workout' => $workout ?: [
                'warmup' => ['Row 5 min'],
                'main' => [['name' => 'Back Squat', 'sets' => 5, 'reps' => '5', 'note' => 'Belt on']],
                'cooldown' => ['Stretch'],
            ],
        ]);
    }

    /**
     * REGRESSION (defect fixed 2026-08-31): a session whose `main` holds plain
     * strings — the shape /me/schedule/data already tolerates — renders its own
     * detail page instead of crashing. Blank/absent entries are dropped, exactly
     * as the write paths do when they clean a submitted workout.
     */
    public function test_schedule_show_renders_a_session_whose_main_workout_is_plain_strings(): void
    {
        [$user] = $this->member();
        $session = $this->personalSession($user, [
            'warmup' => ['Row 5 min'],
            'main' => ['Back Squat', 'Bench Press', '', ['note' => 'no name at all']],
            'cooldown' => ['Stretch'],
        ]);

        // The same row is still served by the JSON list endpoint, unchanged.
        $this->actingAs($user)->getJson('/me/schedule/data')->assertOk();

        foreach ([$this->desktop($user), $this->phone($user)] as $client) {
            $res = $client->get('/me/schedule/'.$session->id)->assertOk();
            $s = $res->viewData('s');

            $this->assertSame([
                ['name' => 'Back Squat', 'sets' => '', 'reps' => '', 'note' => ''],
                ['name' => 'Bench Press', 'sets' => '', 'reps' => '', 'note' => ''],
            ], $s['workout']['main']);
        }
    }

    public function test_schedule_show_renders_the_owners_personal_session_on_both_branches(): void
    {
        [$user] = $this->member();
        $session = $this->personalSession($user);

        $keys = ['s', 'member', 'subjectsList', 'isOwner', 'synced', 'location',
            'clubFacilities', 'clubInstructors', 'coachLink', 'coachAvatar', 'canEngage'];

        $desktop = $this->desktop($user)->get('/me/schedule/'.$session->id)->assertOk()
            ->assertViewIs('personal.desktop.schedule-show');
        foreach ($keys as $key) {
            $desktop->assertViewHas($key);
        }
        $desktop->assertViewHas('isOwner', true)->assertViewHas('synced', false);

        $this->phone($user)->get('/me/schedule/'.$session->id)->assertOk()
            ->assertViewIs('personal.mobile.schedule-show');
    }

    /**
     * A stranger's personal session is not reachable. The controller scopes the
     * lookup to the viewer's own family subjects and then `firstOrFail()`s — so
     * it never reveals that the row exists.
     *
     * The 404 does NOT reach the browser: bootstrap/app.php turns a 404 on a
     * signed-in WEB navigation into redirect()->back(fallback: '/'), so a test
     * with no referer lands on '/'. An AJAX/JSON caller still gets a real 404
     * (asserted separately below).
     */
    public function test_schedule_show_404s_for_another_members_session(): void
    {
        [$owner] = $this->member();
        $session = $this->personalSession($owner);

        $stranger = $this->createUser(['full_name' => 'Omar Ali']);

        $this->desktop($stranger)->get('/me/schedule/'.$session->id)->assertRedirect('/');
    }

    public function test_schedule_show_404s_for_a_session_that_does_not_exist(): void
    {
        [$user] = $this->member();

        $this->desktop($user)->get('/me/schedule/999999')->assertRedirect('/');
    }

    // ---------------------------------------------------------------------
    // GET /me/schedule/synced/{token} — me.schedule.synced (a CLUB class)
    // ---------------------------------------------------------------------

    /**
     * A club class the member is enrolled in: package + activity + a weekly slot,
     * with an active subscription to the package.
     *
     * @return array{0: User, 1: Tenant, 2: ClubPackageActivity, 3: string}
     */
    private function enrolledClass(): array
    {
        $owner = $this->createUser(['full_name' => 'Club Owner']);
        $club = Tenant::factory()->create(['owner_user_id' => $owner->id]);

        $member = $this->createUser(['full_name' => 'Sara Ahmed']);
        $member->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $package = ClubPackage::factory()->create(['tenant_id' => $club->id, 'name' => 'Evening Squad']);
        $activity = ClubActivity::factory()->create(['tenant_id' => $club->id, 'name' => 'Taekwondo']);

        $coach = $this->createUser(['full_name' => 'Coach Kim']);
        $instructor = ClubInstructor::factory()->create(['tenant_id' => $club->id, 'user_id' => $coach->id]);

        $pa = ClubPackageActivity::create([
            'package_id' => $package->id,
            'activity_id' => $activity->id,
            'instructor_id' => $instructor->id,
        ]);
        $pa->forceFill(['schedule' => [[
            'day' => 'monday',
            'start_time' => '18:00',
            'end_time' => '19:00',
            'facility_name' => 'Main Dojang',
            'location_type' => 'text',
        ]]])->save();

        ClubMemberSubscription::factory()->create([
            'tenant_id' => $club->id,
            'user_id' => $member->id,
            'package_id' => $package->id,
            'status' => 'active',
        ]);

        $token = SyncedClassToken::encode($pa->id, 'monday', '18:00');

        return [$member->fresh(), $club, $pa, $token];
    }

    public function test_synced_class_detail_renders_for_an_enrolled_member_on_both_branches(): void
    {
        [$member, , , $token] = $this->enrolledClass();

        $keys = ['s', 'member', 'isOwner', 'synced', 'canEditClub', 'editSlot',
            'updateUrl', 'occurrenceDate', 'occurrenceLabel', 'substitute',
            'substituteSearchUrl', 'substituteAssignUrl', 'substituteRemoveUrl',
            'roster', 'canMarkAttendance', 'classEnded', 'classStarted',
            'slotStart', 'slotEnd', 'attendanceUrl'];

        $desktop = $this->desktop($member)->get('/me/schedule/synced/'.$token)->assertOk()
            ->assertViewIs('personal.desktop.schedule-show');
        foreach ($keys as $key) {
            $desktop->assertViewHas($key);
        }
        // A club class is never "owned" by the member and is always flagged synced.
        $desktop->assertViewHas('isOwner', false)->assertViewHas('synced', true)
            ->assertViewHas('canEditClub', false);

        $this->phone($member)->get('/me/schedule/synced/'.$token)->assertOk()
            ->assertViewIs('personal.mobile.schedule-show');
    }

    /**
     * NON-OWNER. Someone who is neither enrolled, the coach, a club manager nor
     * an assigned substitute is refused with abort(404) — deliberately "no such
     * class" rather than "forbidden", so a token cannot be used to probe. On a
     * browser navigation that 404 is rewritten to a redirect home by the global
     * handler in bootstrap/app.php.
     */
    public function test_synced_class_detail_404s_for_an_unrelated_member(): void
    {
        [, , , $token] = $this->enrolledClass();

        $stranger = $this->createUser(['full_name' => 'Omar Ali']);

        $this->desktop($stranger)->get('/me/schedule/synced/'.$token)->assertRedirect('/');
    }

    public function test_synced_class_detail_404s_on_a_malformed_token(): void
    {
        [$member] = $this->member();

        $this->desktop($member)->get('/me/schedule/synced/not-a-real-token')->assertRedirect('/');
    }

    // ---------------------------------------------------------------------
    // GET /me/schedule/synced/{token}/substitutes — me.schedule.substitute.search
    // ---------------------------------------------------------------------

    /**
     * The coach of the class may search for a substitute. Note the endpoint
     * answers with an empty result set — status 200 — when the query is shorter
     * than one character, rather than a validation error.
     */
    public function test_substitute_search_returns_results_for_the_club_owner(): void
    {
        [, $club, , $token] = $this->enrolledClass();
        $owner = User::find($club->owner_user_id);
        $this->createUser(['full_name' => 'Zaid Substitute']);

        $response = $this->actingAs($owner)->getJson('/me/schedule/synced/'.$token.'/substitutes?q=Zaid')
            ->assertOk();

        $response->assertJsonStructure([
            'results' => ['*' => ['id', 'name', 'avatar', 'initials', 'rating', 'rating_count', 'busy']],
        ]);
        $this->assertSame(['results'], array_keys($response->json()));
    }

    public function test_substitute_search_returns_an_empty_list_for_a_blank_query(): void
    {
        [, $club, , $token] = $this->enrolledClass();
        $owner = User::find($club->owner_user_id);

        $this->actingAs($owner)->getJson('/me/schedule/synced/'.$token.'/substitutes')
            ->assertOk()
            ->assertExactJson(['results' => []]);
    }

    /**
     * NON-OWNER. A member who merely attends the class may not search for a
     * substitute: 403, with an empty `results` key still present so the caller's
     * parser does not break.
     */
    public function test_substitute_search_is_forbidden_for_a_plain_member(): void
    {
        [$member, , , $token] = $this->enrolledClass();

        $this->actingAs($member)->getJson('/me/schedule/synced/'.$token.'/substitutes?q=Zaid')
            ->assertForbidden()
            ->assertExactJson(['results' => []]);
    }

    // ---------------------------------------------------------------------
    // GET /me/affiliations
    // ---------------------------------------------------------------------

    /**
     * Affiliations is a SINGLE view — it does not branch on is_mobile. Both a
     * phone and a laptop get `personal.affiliations`.
     */
    public function test_affiliations_renders_one_shared_view_on_both_branches(): void
    {
        [$user, $club] = $this->member();

        ClubAffiliation::create([
            'member_id' => $user->id,
            'tenant_id' => $club->id,
            'club_name' => $club->club_name,
            'start_date' => now()->subYear()->toDateString(),
        ]);
        ClubAffiliation::create([
            'member_id' => $user->id,
            'club_name' => 'Old Dojo',
            'start_date' => now()->subYears(5)->toDateString(),
            'end_date' => now()->subYears(2)->toDateString(),
        ]);

        foreach ([self::DESKTOP_UA, self::PHONE_UA] as $ua) {
            $response = $this->actingAs($user)->withHeader('User-Agent', $ua)
                ->get('/me/affiliations')->assertOk()
                ->assertViewIs('personal.affiliations')
                ->assertViewHas('active')
                ->assertViewHas('left')
                ->assertViewHas('shellTitle');

            $this->assertCount(1, $response->viewData('active'));
            $this->assertCount(1, $response->viewData('left'));
        }
    }

    // ---------------------------------------------------------------------
    // GET /me/profile — a permanent redirect to the real profile
    // ---------------------------------------------------------------------

    public function test_profile_redirects_to_the_members_own_member_show_page(): void
    {
        [$user] = $this->member();

        $this->desktop($user)->get('/me/profile')
            ->assertRedirect(route('member.show', $user->uuid));
    }

    // ---------------------------------------------------------------------
    // GET /me/packages
    // ---------------------------------------------------------------------

    public function test_packages_lists_only_the_viewers_own_subscriptions(): void
    {
        [$user, $club] = $this->member();
        $stranger = $this->createUser(['full_name' => 'Omar Ali']);

        ClubMemberSubscription::factory()->create(['tenant_id' => $club->id, 'user_id' => $user->id]);
        ClubMemberSubscription::factory()->create(['tenant_id' => $club->id, 'user_id' => $stranger->id]);

        $desktop = $this->desktop($user)->get('/me/packages')->assertOk()
            ->assertViewIs('personal.desktop.packages')
            ->assertViewHas('subscriptions');

        $this->assertCount(1, $desktop->viewData('subscriptions'));
        $this->assertSame($user->id, $desktop->viewData('subscriptions')->first()->user_id);

        $this->phone($user)->get('/me/packages')->assertOk()
            ->assertViewIs('personal.mobile.packages');
    }

    // ---------------------------------------------------------------------
    // GET /me/progress
    // ---------------------------------------------------------------------

    /**
     * Progress is a SINGLE view (no is_mobile branch) with a computed goal tally.
     */
    public function test_progress_renders_one_shared_view_with_goal_stats(): void
    {
        [$user] = $this->member();

        // DIVERGENCE: goals.status is an ENUM of ('active','completed') — the
        // DB refuses anything else. The controller nevertheless buckets goals
        // into completed / in_progress / pending, so `in_progress` is a bucket
        // that can never be filled and every open goal lands in `pending`.
        foreach (['completed', 'active', 'active'] as $status) {
            Goal::create([
                'user_id' => $user->id,
                'title' => ucfirst($status).' goal '.uniqid(),
                'status' => $status,
                // goals.target_value is NOT NULL in the schema.
                'target_value' => 10,
                'current_progress_value' => 0,
                'unit' => 'sessions',
                'start_date' => now()->toDateString(),
                'target_date' => now()->addMonth()->toDateString(),
            ]);
        }

        foreach ([self::DESKTOP_UA, self::PHONE_UA] as $ua) {
            $response = $this->actingAs($user)->withHeader('User-Agent', $ua)
                ->get('/me/progress')->assertOk()
                ->assertViewIs('personal.progress')
                ->assertViewHas('goals')
                ->assertViewHas('goalStats');

            // The third bucket is "everything that is neither" — not a named status.
            $this->assertSame(
                ['completed' => 1, 'in_progress' => 0, 'pending' => 2],
                $response->viewData('goalStats')
            );
        }
    }

    // ---------------------------------------------------------------------
    // GET /me/payments
    // ---------------------------------------------------------------------

    /**
     * Payments is a SINGLE view. `totalDue` sums only unpaid + pending_approval
     * rows; `totalPaid` sums amount_paid across every subscription regardless of
     * status.
     */
    public function test_payments_renders_one_shared_view_with_the_running_totals(): void
    {
        [$user, $club] = $this->member();

        ClubMemberSubscription::factory()->create([
            'tenant_id' => $club->id, 'user_id' => $user->id,
            'payment_status' => 'unpaid', 'amount_due' => 30, 'amount_paid' => 0,
        ]);
        ClubMemberSubscription::factory()->create([
            'tenant_id' => $club->id, 'user_id' => $user->id,
            'payment_status' => 'paid', 'amount_due' => 0, 'amount_paid' => 50,
        ]);

        foreach ([self::DESKTOP_UA, self::PHONE_UA] as $ua) {
            $response = $this->actingAs($user)->withHeader('User-Agent', $ua)
                ->get('/me/payments')->assertOk()
                ->assertViewIs('personal.payments')
                ->assertViewHas('subscriptions')
                ->assertViewHas('totalPaid')
                ->assertViewHas('totalDue');

            $this->assertSame(50.0, $response->viewData('totalPaid'));
            $this->assertSame(30.0, $response->viewData('totalDue'));
            $this->assertCount(2, $response->viewData('subscriptions'));
        }
    }

    public function test_payments_never_shows_another_members_bills(): void
    {
        [$user, $club] = $this->member();
        $stranger = $this->createUser(['full_name' => 'Omar Ali']);

        ClubMemberSubscription::factory()->create([
            'tenant_id' => $club->id, 'user_id' => $stranger->id, 'amount_due' => 999,
        ]);

        $response = $this->desktop($user)->get('/me/payments')->assertOk();

        $this->assertCount(0, $response->viewData('subscriptions'));
        $this->assertSame(0.0, $response->viewData('totalDue'));
    }

    // ---------------------------------------------------------------------
    // GET /me/videos + /me/videos/data
    // ---------------------------------------------------------------------

    public function test_videos_renders_on_both_device_branches(): void
    {
        [$user] = $this->member();

        $this->desktop($user)->get('/me/videos')->assertOk()
            ->assertViewIs('personal.desktop.videos')
            ->assertViewHas('shelves')
            ->assertViewHas('total')
            ->assertViewHas('shellTitle');

        $this->phone($user)->get('/me/videos')->assertOk()
            ->assertViewIs('personal.mobile.videos')
            ->assertViewHas('shelves')
            ->assertViewHas('total');
    }

    public function test_videos_data_returns_shelves_and_total(): void
    {
        [$user] = $this->member();

        $response = $this->actingAs($user)->getJson('/me/videos/data')->assertOk();

        $response->assertJsonStructure(['shelves', 'total']);
        $this->assertSame(['shelves', 'total'], array_keys($response->json()));
        $this->assertSame(0, $response->json('total'));
    }

    // ---------------------------------------------------------------------
    // GET /me/market + /me/market/{product}
    // ---------------------------------------------------------------------

    private function product(Tenant $club, array $attrs = []): ClubProduct
    {
        return ClubProduct::create(array_merge([
            'tenant_id' => $club->id,
            'name' => 'Pro Sparring Gloves',
            'brand' => 'TAKEONE Sport',
            'category' => 'gear',
            'price' => 28.0,
            'availability' => 'in_stock',
            'featured' => true,
            'color' => '#7c3aed',
            'icon' => 'bi-trophy',
            'description' => 'Twelve-ounce gloves.',
            'fulfillment' => 'stock',
            'quantity' => 10,
            'status' => 'published',
            'rating_count' => 0,
            'rating_sum' => 0,
        ], $attrs));
    }

    public function test_market_lists_published_products_from_the_members_clubs(): void
    {
        [$user, $club] = $this->member();
        $this->product($club);
        ClubProductCategory::create([
            'tenant_id' => $club->id, 'key' => 'gear', 'label' => 'Gear', 'icon' => 'bi-bag', 'sort' => 1,
        ]);

        $desktop = $this->desktop($user)->get('/me/market')->assertOk()
            ->assertViewIs('personal.desktop.market')
            ->assertViewHas('products')
            ->assertViewHas('categories');

        $this->assertCount(1, $desktop->viewData('products'));

        // "All" is always prepended, then only the categories that have products.
        $categories = $desktop->viewData('categories');
        $this->assertSame('all', $categories[0]['key']);
        $this->assertSame('gear', $categories[1]['key']);

        $this->phone($user)->get('/me/market')->assertOk()
            ->assertViewIs('personal.mobile.market');
    }

    /** A product belonging to a club the member has not joined is not listed. */
    public function test_market_hides_products_from_clubs_the_member_has_not_joined(): void
    {
        [$user] = $this->member();
        $otherClub = Tenant::factory()->create();
        $this->product($otherClub);

        $response = $this->desktop($user)->get('/me/market')->assertOk();

        $this->assertCount(0, $response->viewData('products'));
    }

    public function test_market_show_renders_on_both_device_branches(): void
    {
        [$user, $club] = $this->member();
        $product = $this->product($club);
        // A sibling in the same category, so `related` is exercised.
        $this->product($club, ['name' => 'Head Guard']);

        $desktop = $this->desktop($user)->get('/me/market/'.$product->id)->assertOk()
            ->assertViewIs('personal.desktop.market-show')
            ->assertViewHas('p')
            ->assertViewHas('related')
            ->assertViewHas('reviews')
            ->assertViewHas('breakdown');

        $this->assertCount(1, $desktop->viewData('related'));
        $this->assertSame([], $desktop->viewData('reviews'));
        // Always five buckets, 5 down to 1, as whole percentages.
        $this->assertSame([5, 4, 3, 2, 1], array_keys($desktop->viewData('breakdown')));

        $this->phone($user)->get('/me/market/'.$product->id)->assertOk()
            ->assertViewIs('personal.mobile.market-show');
    }

    /**
     * DIVERGENCE: the product DETAIL page is NOT scoped to the member's clubs the
     * way the market LIST is. Any signed-in member can open any published
     * product by id, including one sold by a club they have never joined, and
     * the ids are sequential. Pinned as current behaviour — not fixed here.
     */
    public function test_market_show_serves_a_product_from_a_club_the_member_has_not_joined(): void
    {
        [$user] = $this->member();
        $otherClub = Tenant::factory()->create();
        $product = $this->product($otherClub, ['name' => 'Someone Elses Gloves']);

        $this->desktop($user)->get('/me/market/'.$product->id)->assertOk()
            ->assertViewIs('personal.desktop.market-show');
    }

    /** An unpublished (draft) product is a 404 for everyone (redirected home on web). */
    public function test_market_show_404s_for_an_unpublished_product(): void
    {
        [$user, $club] = $this->member();
        $draft = $this->product($club, ['status' => 'draft']);

        $this->desktop($user)->get('/me/market/'.$draft->id)->assertRedirect('/');
    }

    public function test_market_show_404s_for_a_product_that_does_not_exist(): void
    {
        [$user] = $this->member();

        $this->desktop($user)->get('/me/market/999999')->assertRedirect('/');
    }

    // ---------------------------------------------------------------------
    // GET /me/settings
    // ---------------------------------------------------------------------

    public function test_settings_renders_on_both_device_branches_with_the_viewer(): void
    {
        [$user] = $this->member();

        $desktop = $this->desktop($user)->get('/me/settings')->assertOk()
            ->assertViewIs('personal.desktop.settings')
            ->assertViewHas('user');

        $this->assertSame($user->id, $desktop->viewData('user')->id);

        $this->phone($user)->get('/me/settings')->assertOk()
            ->assertViewIs('personal.mobile.settings')
            ->assertViewHas('user');
    }
    // ---------------------------------------------------------------------
    // The 404 handler, pinned from the JSON side
    // ---------------------------------------------------------------------

    /**
     * The web redirect above is a presentation choice, not a relaxation. An
     * AJAX/JSON caller still gets a real 404 with a bare message body, for
     * every one of these routes. Pinned so the refactor cannot swap the shape.
     */
    public function test_json_callers_still_get_a_real_404(): void
    {
        [$user, $club] = $this->member();

        $owner = $this->createUser(['full_name' => 'Omar Ali']);
        $strangersSession = $this->personalSession($owner);
        $draft = $this->product($club, ['status' => 'draft']);

        foreach ([
            '/me/schedule/'.$strangersSession->id,
            '/me/schedule/999999',
            '/me/schedule/synced/not-a-real-token',
            '/me/market/'.$draft->id,
            '/me/market/999999',
        ] as $uri) {
            $this->actingAs($user)->getJson($uri)
                ->assertNotFound()
                ->assertExactJson(['message' => 'Not found.']);
        }
    }
}