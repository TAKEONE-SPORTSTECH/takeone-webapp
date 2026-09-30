<?php

namespace Tests\Feature;

use App\Clubs\Models\Business;
use Tests\TestCase;

/**
 * /club — the manager's front door (ClubEntryController). It lists exactly the
 * clubs the viewer may manage, and never anyone else's.
 */
class ClubEntryTest extends TestCase
{
    private const DESKTOP_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    private const PHONE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/club')->assertRedirect(route('login'));
    }

    public function test_owner_of_one_club_goes_straight_to_its_dashboard(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);

        $this->actingAs($owner)->get('/club')
            ->assertRedirect(route('admin.club.dashboard', $club->slug));
    }

    public function test_user_managing_nothing_is_sent_home(): void
    {
        $this->actingAs($this->createUser())->get('/club')->assertRedirect('/');
    }

    public function test_plain_membership_does_not_count_as_managing(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $member = $this->createUser();
        $club->members()->attach($member->id, ['status' => 'active']);

        $this->actingAs($member)->get('/club')->assertRedirect('/');
    }

    public function test_manager_of_several_clubs_gets_a_picker_of_only_theirs(): void
    {
        $user = $this->createUser();
        $owned = $this->createClub($user, ['club_name' => 'Owned Club']);

        $stranger = $this->createUser();
        $administered = $this->createClub($stranger, ['club_name' => 'Administered Club']);
        $this->makeClubAdmin($user, $administered);
        $notMine = $this->createClub($stranger, ['club_name' => 'Somebody Elses Club']);

        foreach ([self::DESKTOP_UA, self::PHONE_UA] as $ua) {
            $this->actingAs($user)->withHeader('User-Agent', $ua)->get('/club')
                ->assertOk()
                ->assertSee('Owned Club')
                ->assertSee('Administered Club')
                ->assertSee(route('admin.club.dashboard', $owned->slug), false)
                ->assertDontSee('Somebody Elses Club')
                ->assertDontSee(route('admin.club.dashboard', $notMine->slug), false);
        }
    }

    public function test_chain_owner_sees_their_chain_clubs(): void
    {
        $chainOwner = $this->createUser();
        $business = Business::create([
            'owner_user_id' => $chainOwner->id,
            'name' => 'Chain',
            'slug' => 'chain-'.uniqid(),
            'status' => Business::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
        $other = $this->createUser();
        $a = $this->createClub($other, ['club_name' => 'Branch A']);
        $b = $this->createClub($other, ['club_name' => 'Branch B']);
        foreach ([$a, $b] as $c) {
            $c->forceFill(['business_id' => $business->id])->save();
        }

        $this->actingAs($chainOwner)->get('/club')
            ->assertOk()->assertSee('Branch A')->assertSee('Branch B');
    }

    public function test_super_admin_with_no_club_gets_the_platform_link(): void
    {
        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);
        $this->createClub($this->createUser(), ['club_name' => 'Random Club']);

        $this->actingAs($admin)->get('/club')
            ->assertOk()
            ->assertSee(route('admin.platform.clubs'), false)
            ->assertDontSee('Random Club');
    }

    // ---- Club mode: /club locks the session into the manager's clubs ----

    public function test_entering_through_club_locks_the_session_to_the_club(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $dashboard = route('admin.club.dashboard', $club->slug);

        $this->actingAs($owner)->get('/club')->assertRedirect($dashboard);

        // Any other full page bounces back to the club …
        $this->get(route('clubs.explore'))->assertRedirect($dashboard);
        $this->get(route('me.home'))->assertRedirect($dashboard);

        // … while the club panel itself renders, with the club bar and no platform nav.
        $this->get($dashboard)->assertOk()
            ->assertSee(__('clubs::club.lock_mode'))
            ->assertDontSee(route('clubs.explore'), false);
    }

    public function test_another_clubs_panel_is_out_of_bounds_while_locked(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $other = $this->createClub($this->createUser());
        $this->makeClubAdmin($owner, $other);

        // Locked to the clubs managed at the moment /club was opened — both here.
        $this->actingAs($owner)->get('/club')->assertOk();
        $this->get(route('admin.club.dashboard', $other->slug))->assertOk();

        $third = $this->createClub($this->createUser());
        $this->get(route('club.entry'))->assertOk();
        $this->get(route('admin.club.dashboard', $third->slug))->assertRedirect();
    }

    public function test_a_member_of_the_club_opens_sealed_but_a_stranger_does_not(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->makeClubAdmin($owner, $club); // what lets a club admin open a member's profile at all
        $member = $this->createUser();
        $club->members()->attach($member->id, ['status' => 'active']);
        $stranger = $this->createUser();

        $this->actingAs($owner)->get('/club');

        $this->get(route('member.show', $member->uuid))->assertOk()
            ->assertSee(__('clubs::club.lock_mode'));
        // Never rendered — refused by the profile's own access check before the lock is asked.
        $this->get(route('member.show', $stranger->uuid))->assertRedirect();
    }

    public function test_json_is_never_re_aimed(): void
    {
        $owner = $this->createUser();
        $this->createClub($owner);
        $this->actingAs($owner)->get('/club');

        $response = (new \App\Clubs\Middleware\KeepInClub)->handle(
            \Illuminate\Http\Request::create('/anything', 'GET'),
            fn () => response()->json(['ok' => true]),
        );
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_signing_out_ends_club_mode(): void
    {
        $owner = $this->createUser();
        $this->createClub($owner);

        $this->actingAs($owner)->get('/club');
        $this->post(route('logout'));

        $this->actingAs($owner)->get(route('clubs.explore'))->assertOk();
    }

    public function test_super_admin_is_never_locked(): void
    {
        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);
        $this->createClub($admin);

        $this->actingAs($admin)->get('/club')->assertOk();
        $this->get(route('clubs.explore'))->assertOk();
    }

    public function test_without_club_mode_nothing_changes(): void
    {
        $owner = $this->createUser();
        $this->createClub($owner);

        $this->actingAs($owner)->get(route('clubs.explore'))->assertOk()
            ->assertDontSee(__('clubs::club.lock_mode'));
    }
}
