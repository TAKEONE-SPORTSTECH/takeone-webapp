<?php

namespace Tests\Feature;

use App\Clubs\Models\ClubSocialLink;
use App\Clubs\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The club details save (admin.club.update), as the desktop and mobile pages
 * use it: pictures never lost, slugs that can't break the club's address,
 * cleared money fields, social links only replaced by a form that showed them,
 * settings limited to the form's own keys, and the in-place JSON answer.
 */
class ClubDetailsUpdateTest extends TestCase
{
    private $owner;

    private Tenant $club;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Http::fake();
        $this->owner = $this->createUser();
        $this->club = $this->createClub($this->owner, ['club_name' => 'Falcons', 'slug' => 'falcons']);
        $this->makeClubAdmin($this->owner, $this->club);
    }

    private function save(array $fields, bool $json = true)
    {
        $payload = ['club_name' => $this->club->club_name] + $fields;
        $this->actingAs($this->owner);

        return $json
            ? $this->putJson(route('admin.club.update', $this->club->slug), $payload)
            : $this->put(route('admin.club.update', $this->club->slug), $payload);
    }

    public function test_a_rejected_picture_never_deletes_the_current_one(): void
    {
        Storage::disk('public')->put('clubs/'.$this->club->id.'/branding/old-logo.png', 'png');
        $this->club->update(['logo' => 'clubs/'.$this->club->id.'/branding/old-logo.png']);

        $this->save([
            'logo' => UploadedFile::fake()->image('new.png'),
            'registration_splash_image' => UploadedFile::fake()->create('not-an-image.pdf', 10, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('registration_splash_image');

        $this->assertSame('clubs/'.$this->club->id.'/branding/old-logo.png', $this->club->fresh()->logo);
        Storage::disk('public')->assertExists('clubs/'.$this->club->id.'/branding/old-logo.png');
        $this->assertCount(1, Storage::disk('public')->allFiles('clubs/'.$this->club->id.'/branding'));
    }

    public function test_a_new_picture_replaces_the_old_one_after_the_save(): void
    {
        Storage::disk('public')->put('clubs/'.$this->club->id.'/branding/old-logo.png', 'png');
        $this->club->update(['logo' => 'clubs/'.$this->club->id.'/branding/old-logo.png']);

        $this->save(['logo' => UploadedFile::fake()->image('new.png')])->assertOk()->assertJsonPath('success', true);

        $new = $this->club->fresh()->logo;
        $this->assertNotSame('clubs/'.$this->club->id.'/branding/old-logo.png', $new);
        Storage::disk('public')->assertExists($new);
        Storage::disk('public')->assertMissing('clubs/'.$this->club->id.'/branding/old-logo.png');
    }

    public function test_remove_clears_the_picture(): void
    {
        Storage::disk('public')->put('clubs/'.$this->club->id.'/branding/cover.png', 'png');
        $this->club->update(['cover_image' => 'clubs/'.$this->club->id.'/branding/cover.png']);

        $this->save(['cover_image' => '', 'remove_cover_image' => '1'])->assertOk();

        $this->assertNull($this->club->fresh()->cover_image);
        Storage::disk('public')->assertMissing('clubs/'.$this->club->id.'/branding/cover.png');
    }

    public function test_bad_slugs_are_refused_with_a_field_error(): void
    {
        $this->createClub($this->createUser(), ['slug' => 'taken-club']);

        foreach (['', '42', 'taken-club', 'Has Spaces', 'trailing-'] as $slug) {
            $this->save(['slug' => $slug])->assertStatus(422)->assertJsonValidationErrors('slug');
        }
        $this->assertSame('falcons', $this->club->fresh()->slug);
    }

    public function test_a_new_slug_answers_with_the_new_address(): void
    {
        $this->save(['slug' => 'Falcons-Academy'])->assertOk()
            ->assertJsonPath('redirect', route('admin.club.details', 'falcons-academy'))
            ->assertJsonPath('club.slug', 'falcons-academy');

        $this->assertSame('falcons-academy', $this->club->fresh()->slug);
    }

    public function test_a_classic_post_with_a_new_slug_redirects_to_the_new_page(): void
    {
        $this->save(['slug' => 'falcons-two'], json: false)
            ->assertRedirect(route('admin.club.details', 'falcons-two'));
    }

    public function test_cleared_money_fields_save_as_zero(): void
    {
        $this->club->update(['enrollment_fee' => 20, 'registration_fee' => 5, 'vat_percentage' => 10]);

        $this->save(['enrollment_fee' => '', 'registration_fee' => '', 'vat_percentage' => ''])->assertOk();

        $club = $this->club->fresh();
        $this->assertEquals(0, $club->enrollment_fee);
        $this->assertEquals(0, $club->registration_fee);
        $this->assertEquals(0, $club->vat_percentage);
    }

    public function test_country_currency_and_timezone_cannot_be_blanked(): void
    {
        $this->save(['country' => '', 'currency' => '', 'timezone' => ''])
            ->assertStatus(422)->assertJsonValidationErrors(['country', 'currency', 'timezone']);
    }

    public function test_a_legacy_country_name_is_saved_as_its_code(): void
    {
        $this->save(['country' => 'Bahrain'])->assertOk();

        $this->assertSame('BH', $this->club->fresh()->country);
    }

    public function test_social_links_are_left_alone_unless_the_form_showed_them(): void
    {
        ClubSocialLink::create(['tenant_id' => $this->club->id, 'platform' => 'instagram', 'url' => 'https://instagram.com/falcons']);

        $this->save([])->assertOk();
        $this->assertSame(1, ClubSocialLink::where('tenant_id', $this->club->id)->count());

        $this->save(['social_links_present' => '1', 'social_links' => [['platform' => 'facebook', 'url' => 'https://facebook.com/falcons']]])->assertOk();
        $this->assertSame(['facebook'], ClubSocialLink::where('tenant_id', $this->club->id)->pluck('platform')->all());

        $this->save(['social_links_present' => '1'])->assertOk();
        $this->assertSame(0, ClubSocialLink::where('tenant_id', $this->club->id)->count());
    }

    public function test_a_javascript_social_link_is_refused(): void
    {
        $this->save(['social_links_present' => '1', 'social_links' => [['platform' => 'facebook', 'url' => 'javascript:alert(1)']]])
            ->assertStatus(422)->assertJsonValidationErrors('social_links.0.url');
    }

    public function test_settings_only_take_the_forms_own_keys(): void
    {
        $this->save(['settings' => ['whatsapp' => ['session_name' => 'someone-elses']]])
            ->assertStatus(422)->assertJsonValidationErrors('settings');

        $this->save(['settings' => ['member_code_prefix' => 'FAL', 'block_explore' => '1']])->assertOk();
        $this->assertSame('FAL', data_get($this->club->fresh()->settings, 'member_code_prefix'));
    }

    public function test_owner_name_and_email_are_not_editable_here(): void
    {
        $this->club->update(['owner_email' => 'real@owner.test']);

        $this->save(['owner_email' => 'attacker@example.com', 'owner_name' => 'Attacker'])->assertOk();

        $this->assertSame('real@owner.test', $this->club->fresh()->owner_email);
    }

    public function test_rich_text_keeps_headings_colour_and_alignment_but_not_script(): void
    {
        $this->save(['registration_terms' => '<h1>Rules</h1><hr><p style="text-align: center; position: fixed">ok</p><font color="#ff0000">red</font><script>alert(1)</script>'])->assertOk();

        $html = $this->club->fresh()->registration_terms;
        $this->assertStringContainsString('<h1>Rules</h1>', $html);
        $this->assertStringContainsString('<hr>', $html);
        $this->assertStringContainsString('style="text-align: center"', $html);
        $this->assertStringContainsString('style="color: #ff0000"', $html);
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('position', $html);
    }

    public function test_deleting_needs_the_typed_name_and_sends_the_owner_home(): void
    {
        $this->actingAs($this->owner)->delete(route('admin.club.destroy', $this->club->slug), ['confirm_name' => 'wrong'])
            ->assertRedirect();
        $this->assertNotNull(Tenant::find($this->club->id));

        $this->actingAs($this->owner)->delete(route('admin.club.destroy', $this->club->slug), ['confirm_name' => 'Falcons'])
            ->assertRedirect(route('me.home'));
        $this->assertNull(Tenant::find($this->club->id));
    }

    public function test_a_whatsapp_session_another_club_uses_cannot_be_claimed(): void
    {
        $other = $this->createClub($this->createUser());
        $other->update(['settings' => ['whatsapp' => ['enabled' => true, 'session_name' => 'their-phone']]]);

        $this->actingAs($this->owner)->putJson(route('admin.club.settings.whatsapp.update', $this->club->slug), ['enabled' => true, 'session_name' => 'Their-Phone'])
            ->assertStatus(422)->assertJsonValidationErrors('session_name');

        $this->actingAs($this->owner)->putJson(route('admin.club.settings.whatsapp.update', $this->club->slug), ['enabled' => true, 'session_name' => 'falcons-phone'])
            ->assertOk();
    }

    public function test_create_owner_never_revives_a_deleted_account(): void
    {
        $gone = $this->createUser(['email' => 'gone@example.com']);
        $gone->delete();

        $this->actingAs($this->owner)->postJson(route('admin.club.create-owner', $this->club->slug), [
            'full_name' => 'Taker', 'email' => 'gone@example.com', 'password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123',
            'gender' => 'Male', 'birthdate' => '1990-01-01', 'nationality' => 'BH',
        ])->assertStatus(422);

        $this->assertTrue($gone->fresh()->trashed());
        $this->assertSame($this->owner->id, $this->club->fresh()->owner_user_id);
    }
}
