<?php

namespace Tests\Feature;

use App\Clubs\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The MOBILE club settings page (details/mobile.blade.php): it renders on a
 * phone with the attributes its in-place save relies on, and the save door
 * answers that page in JSON — success, and a 422 it can pin to a field.
 */
class ClubDetailsMobileTest extends TestCase
{
    private const PHONE = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36';

    private $owner;

    private Tenant $club;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Http::fake();
        $this->owner = $this->createUser();
        $this->club = $this->createClub($this->owner, ['club_name' => 'Falcons', 'slug' => 'falcons-m']);
        $this->makeClubAdmin($this->owner, $this->club);
    }

    public function test_mobile_details_page_renders_with_the_save_contract(): void
    {
        $res = $this->actingAs($this->owner)
            ->withHeaders(['User-Agent' => self::PHONE])
            ->get(route('admin.club.details', $this->club->slug));

        $res->assertOk();
        $res->assertSee('id="clubStudio"', false);
        $res->assertSee('novalidate', false);
        $res->assertSee('name="confirm_name"', false);
        $res->assertSee('name="social_links_present"', false);
    }

    public function test_a_legacy_string_phone_is_shown_split_so_saving_keeps_it(): void
    {
        $this->club->forceFill(['phone' => '+973 3344 5566'])->save();

        $res = $this->actingAs($this->owner)
            ->withHeaders(['User-Agent' => self::PHONE])
            ->get(route('admin.club.details', $this->club->slug));

        $res->assertOk();
        $res->assertSee('name="phone_number" value="33445566"', false);
    }

    public function test_json_update_returns_the_saved_club(): void
    {
        $this->actingAs($this->owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->putJson(route('admin.club.update', $this->club->slug), [
                'club_name' => 'Falcons United',
                'social_links_present' => 1,
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'redirect' => null, 'club' => ['club_name' => 'Falcons United', 'slug' => 'falcons-m']]);

        $this->assertSame('Falcons United', $this->club->fresh()->club_name);
    }

    public function test_json_update_with_a_bad_slug_is_a_422_naming_the_field(): void
    {
        $this->actingAs($this->owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->putJson(route('admin.club.update', $this->club->slug), [
                'club_name' => 'Falcons',
                'slug' => 'Not A Slug!',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');

        $this->assertSame('falcons-m', $this->club->fresh()->slug);
    }
}
