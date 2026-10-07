<?php

namespace Tests\Feature;

use App\Clubs\Models\ClubSocialLink;
use App\Clubs\Models\Tenant;
use App\Clubs\Requests\UpdateClubApiRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * The club popup (<x-club-modal>): what its create (platform.clubs.store) and
 * update (platform.clubs.update) endpoints accept, store and refuse.
 */
class ClubModalSaveTest extends TestCase
{
    private const SHORT = 'https://maps.app.goo.gl/AbCdEf123?g_st=iw';

    private function admin()
    {
        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);

        return $admin;
    }

    private function base(Tenant $club, array $extra = []): array
    {
        return $extra + [
            'club_name' => $club->club_name,
            'slug' => $club->slug,
            'owner_user_id' => $club->owner_user_id,
            'country' => 'BH',
            'currency' => 'BHD',
            'timezone' => 'Asia/Bahrain',
            'relations_loaded' => '0',
        ];
    }

    private function update($admin, Tenant $club, array $extra)
    {
        return $this->actingAs($admin)->putJson('/admin/clubs/'.$club->id, $this->base($club, $extra));
    }

    public function test_public_profile_can_be_switched_off(): void
    {
        $admin = $this->admin();
        $club = $this->createClub($this->createUser(), ['public_profile_enabled' => true]);

        $this->update($admin, $club, ['public_profile_enabled' => '0'])->assertOk();
        $this->assertFalse((bool) $club->fresh()->public_profile_enabled);

        $this->update($admin, $club, ['public_profile_enabled' => '1'])->assertOk();
        $this->assertTrue((bool) $club->fresh()->public_profile_enabled);
    }

    public function test_create_ignores_a_logo_that_is_not_a_data_uri(): void
    {
        $admin = $this->admin();
        $this->createRole('club-admin');
        $owner = $this->createUser();
        $slug = 'modal-club-'.uniqid();

        $this->actingAs($admin)->postJson('/admin/clubs', [
            'owner_user_id' => $owner->id,
            'club_name' => 'Modal Club',
            'slug' => $slug,
            'logo' => 'members/someone-else/profile/face.jpg',
            'cover_image' => '../../.env',
            'registration_splash_image' => 'clubs/x/branding/a.png',
        ])->assertOk();

        $club = Tenant::where('slug', $slug)->firstOrFail();
        $this->assertNull($club->logo);
        $this->assertNull($club->cover_image);
        $this->assertNull($club->registration_splash_image);
    }

    public function test_renaming_keeps_a_legacy_slug_but_a_new_slug_must_be_well_formed(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['slug' => 'Legacy_Slug'.uniqid()]);
        $rules = fn (array $input) => Validator::make($input, (function () use ($club, $input) {
            $request = UpdateClubApiRequest::create('/admin/clubs/'.$club->id, 'PUT', $input);
            $request->setRouteResolver(fn () => tap(new \Illuminate\Routing\Route('PUT', 'admin/clubs/{club}', []), fn ($r) => $r->bind($request)->setParameter('club', $club)));

            return $request->rules();
        })());

        // Renamed, slug untouched: passes even though the old slug predates the rule.
        $this->assertFalse($rules(['club_name' => 'New Name', 'slug' => $club->slug, 'owner_user_id' => $owner->id])->fails());
        // A changed slug must be lowercase letters, digits and hyphens, not all digits.
        $this->assertTrue($rules(['club_name' => 'X', 'slug' => 'Bad Slug', 'owner_user_id' => $owner->id])->fails());
        $this->assertTrue($rules(['club_name' => 'X', 'slug' => '12345', 'owner_user_id' => $owner->id])->fails());
        $this->assertFalse($rules(['club_name' => 'X', 'slug' => 'good-slug-'.uniqid(), 'owner_user_id' => $owner->id])->fails());
    }

    public function test_a_maps_link_with_no_pin_fills_the_pin(): void
    {
        Http::fake(['maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://maps.google.com?q=26.1150000,50.5091000&entry=gps'])]);
        $admin = $this->admin();
        $club = $this->createClub($this->createUser());

        $this->update($admin, $club, ['maps_url' => self::SHORT, 'gps_lat' => '', 'gps_long' => ''])->assertOk();

        $club->refresh();
        $this->assertSame(self::SHORT, $club->maps_url);
        $this->assertEqualsWithDelta(26.115, (float) $club->gps_lat, 0.00001);
        $this->assertEqualsWithDelta(50.5091, (float) $club->gps_long, 0.00001);

        $this->actingAs($admin)->getJson('/admin/api/clubs/'.$club->id)->assertJsonPath('maps_url', self::SHORT);
    }

    public function test_a_maps_link_that_is_not_google_is_refused(): void
    {
        Http::fake();
        $admin = $this->admin();
        $club = $this->createClub($this->createUser());

        $this->update($admin, $club, ['maps_url' => 'https://evil.example.com/x'])->assertStatus(422)->assertJsonValidationErrors('maps_url');
        Http::assertNothingSent();
    }

    public function test_a_javascript_social_link_is_refused(): void
    {
        $admin = $this->admin();
        $club = $this->createClub($this->createUser());

        $this->update($admin, $club, [
            'relations_loaded' => '1',
            'social_links' => [['platform' => 'website', 'url' => 'javascript:alert(1)']],
        ])->assertStatus(422)->assertJsonValidationErrors('social_links.0.url');

        $this->update($admin, $club, [
            'relations_loaded' => '1',
            'social_links' => [['platform' => 'not-a-platform', 'url' => 'https://example.com']],
        ])->assertStatus(422)->assertJsonValidationErrors('social_links.0.platform');

        $this->assertSame(0, ClubSocialLink::where('tenant_id', $club->id)->count());
    }

    public function test_contact_source_is_saved_without_losing_other_settings(): void
    {
        $admin = $this->admin();
        $club = $this->createClub($this->createUser(), ['settings' => ['member_code_prefix' => 'TK'], 'email' => 'club@example.com']);

        $this->update($admin, $club, ['settings' => ['contact_source' => 'owner'], 'email' => 'club@example.com'])->assertOk();

        $club->refresh();
        $this->assertSame('owner', $club->settings['contact_source']);
        $this->assertSame('TK', $club->settings['member_code_prefix']);
        $this->assertSame('club@example.com', $club->email);
        $this->assertTrue($club->usesOwnerContact());

        $this->update($admin, $club, ['settings' => ['contact_source' => 'nobody']])->assertStatus(422);
    }

    public function test_registration_text_and_its_arabic_copy_are_saved_and_sanitised(): void
    {
        $admin = $this->admin();
        $club = $this->createClub($this->createUser());

        $this->update($admin, $club, [
            'registration_terms' => '<p>Be kind</p><script>alert(1)</script>',
            'translations' => ['registration_terms' => ['ar' => '<p>كن لطيفاً</p><img src=x onerror=alert(1)>']],
        ])->assertOk();

        $club->refresh();
        $this->assertStringContainsString('Be kind', $club->registration_terms);
        $this->assertStringNotContainsString('<script', $club->registration_terms);
        $this->assertStringContainsString('كن لطيفاً', $club->tr('registration_terms', 'ar'));
        $this->assertStringNotContainsString('onerror', (string) $club->tr('registration_terms', 'ar'));
    }

    public function test_a_bank_account_needs_its_number_and_saves_with_one(): void
    {
        $admin = $this->admin();
        $club = $this->createClub($this->createUser());

        // account_number is NOT NULL: refused cleanly instead of a 500.
        $this->update($admin, $club, [
            'relations_loaded' => '1',
            'bank_accounts' => [['bank_name' => 'NBB', 'account_name' => 'Club WLL', 'account_number' => '']],
        ])->assertStatus(422)->assertJsonValidationErrors('bank_accounts.0.account_number');

        $this->update($admin, $club, [
            'relations_loaded' => '1',
            'bank_accounts' => [['bank_name' => 'NBB', 'account_name' => 'Club WLL', 'account_number' => '0012345']],
        ])->assertOk();

        $this->assertSame('0012345', $club->bankAccounts()->first()->account_number);
    }

    public function test_a_non_super_admin_cannot_use_the_popup_endpoints(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->makeClubAdmin($owner, $club);

        $this->actingAs($owner)->getJson('/admin/api/clubs/'.$club->id)->assertForbidden();
        $this->actingAs($owner)->putJson('/admin/clubs/'.$club->id, $this->base($club, ['club_name' => 'Hijacked']))->assertForbidden();
        $this->actingAs($owner)->getJson('/admin/api/users')->assertForbidden();
        $this->assertSame('Test Club', $club->fresh()->club_name);
    }

    public function test_the_user_list_is_bounded_and_searchable(): void
    {
        $admin = $this->admin();
        $this->createUser(['full_name' => 'Zebra Findme', 'email' => 'zebra-findme@example.com']);

        $all = $this->actingAs($admin)->getJson('/admin/api/users')->assertOk()->json();
        $this->assertLessThanOrEqual(100, count($all));

        $found = $this->actingAs($admin)->getJson('/admin/api/users?q=findme')->assertOk()->json();
        $this->assertSame(['zebra-findme@example.com'], array_column($found, 'email'));
    }

    public function test_the_public_page_offers_the_popup_only_to_a_super_admin(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH']);
        $this->makeClubAdmin($owner, $club);
        $url = route('clubs.show', ['country' => 'bh', 'slug' => $club->slug]);

        $page = $this->actingAs($owner)->get($url)->assertOk()->getContent();
        $this->assertStringContainsString(route('admin.club.details', $club->slug), $page);
        $this->assertStringNotContainsString('clubModalController', $page);

        $admin = $this->admin();
        $page = $this->actingAs($admin)->get($url)->assertOk()->getContent();
        $this->assertStringContainsString("mode: 'edit', clubId: ".$club->id, $page);
        $this->assertStringContainsString('clubModalController', $page);

        if ($dir = getenv('CLUB_MODAL_DUMP')) {
            file_put_contents($dir.'/public-show.html', $page);
            file_put_contents($dir.'/platform-clubs.html', $this->actingAs($admin)->get('/admin/clubs')->getContent());
        }
    }
}
