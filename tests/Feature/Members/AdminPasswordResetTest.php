<?php

namespace Tests\Feature\Members;

use App\Clubs\Models\Tenant;
use App\Federations\Models\Federation;
use App\Members\Mail\PasswordChangedByAdminEmail;
use App\Members\Models\Membership;
use App\Members\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Setting somebody else's password (security fix, 2026-10-31).
 *
 * A club admin could enroll ANY account into their club and then set its
 * password — a super-admin's included. Now the desk can still help an ordinary
 * member who forgot theirs, but never a privileged account, and every change
 * made by somebody else is logged and emailed. See docs/account-recovery.md.
 */
class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Tenant $club;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->seedRoles();

        $this->admin = $this->createUser();
        $this->club = $this->createClub($this->createUser());
        $this->makeClubAdmin($this->admin, $this->club);
    }

    private function memberOfClub(): User
    {
        $member = $this->createUser();
        Membership::create(['tenant_id' => $this->club->id, 'user_id' => $member->id, 'status' => 'active']);

        return $member;
    }

    private function reset(User $actor, User $member)
    {
        return $this->actingAs($actor)->postJson(route('member.reset-password', $member->id), [
            'password' => 'NewPass#2026',
            'password_confirmation' => 'NewPass#2026',
        ]);
    }

    public function test_the_desk_can_reset_an_ordinary_member_and_it_is_logged_and_emailed(): void
    {
        $member = $this->memberOfClub();

        $this->reset($this->admin, $member)->assertOk();

        $this->assertTrue(Hash::check('NewPass#2026', $member->fresh()->password));
        Mail::assertSent(PasswordChangedByAdminEmail::class, fn ($mail) => $mail->hasTo($member->email)
            && $mail->clubName === $this->club->club_name);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'member.password_set_by_admin',
            'causer_id' => $this->admin->id,
            'subject_id' => $member->id,
        ]);
    }

    public function test_a_club_admin_cannot_reset_a_super_admin_enrolled_into_their_club(): void
    {
        $target = $this->memberOfClub();
        $this->makeSuperAdmin($target);
        $before = $target->fresh()->password;

        $this->reset($this->admin, $target)
            ->assertForbidden()
            ->assertJson(['message' => __('members::member.reset_password_not_allowed')]);

        $this->assertSame($before, $target->fresh()->password);
        Mail::assertNothingSent();
    }

    public function test_a_club_admin_cannot_reset_the_owner_of_another_club(): void
    {
        $target = $this->memberOfClub();
        $this->createClub($target);

        $this->reset($this->admin, $target)->assertForbidden();
    }

    public function test_a_club_admin_cannot_reset_an_admin_of_another_club(): void
    {
        $target = $this->memberOfClub();
        $this->makeClubAdmin($target, $this->createClub($this->createUser()));

        $this->reset($this->admin, $target)->assertForbidden();
    }

    public function test_a_club_admin_cannot_reset_federation_staff(): void
    {
        $target = $this->memberOfClub();
        $federation = Federation::create([
            'uuid' => (string) Str::uuid(), 'owner_user_id' => $this->createUser()->id,
            'name' => 'Test Federation', 'country' => 'BH', 'sport' => 'karate', 'status' => 'approved',
        ]);
        DB::table('federation_staff')->insert([
            'federation_id' => $federation->id, 'user_id' => $target->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->reset($this->admin, $target)->assertForbidden();
    }

    public function test_a_club_admin_cannot_reset_somebody_outside_their_club(): void
    {
        $this->reset($this->admin, $this->createUser())->assertForbidden();
    }

    public function test_a_super_admin_can_reset_anyone_and_it_is_emailed(): void
    {
        $super = $this->createUser();
        $this->makeSuperAdmin($super);
        $target = $this->createUser();
        $this->makeClubAdmin($target, $this->club);

        $this->reset($super, $target)->assertOk();

        Mail::assertSent(PasswordChangedByAdminEmail::class, fn ($mail) => $mail->clubName === null);
    }

    public function test_a_member_changing_their_own_password_gets_no_email(): void
    {
        $member = $this->memberOfClub();

        $this->reset($member, $member)->assertOk();

        Mail::assertNothingSent();
    }

    public function test_the_profile_hides_the_reset_button_for_a_privileged_member(): void
    {
        $target = $this->memberOfClub();
        $this->makeClubAdmin($target, $this->createClub($this->createUser()));

        $this->actingAs($this->admin)->get(route('member.show', $target->uuid))
            ->assertOk()
            ->assertViewHas('canResetPassword', false);

        $ordinary = $this->memberOfClub();
        $this->actingAs($this->admin)->get(route('member.show', $ordinary->uuid))
            ->assertOk()
            ->assertViewHas('canResetPassword', true);
    }

    public function test_the_notice_renders_without_the_password(): void
    {
        $member = $this->createUser();

        $html = (new PasswordChangedByAdminEmail($member, 'Falcon Karate Club'))->render();

        $this->assertStringContainsString('Falcon Karate Club', $html);
        $this->assertStringContainsString(route('password.request'), $html);
        $this->assertStringNotContainsString('NewPass', $html);
    }
}
