<?php

namespace Tests\Feature\Federations;

use App\Federations\Models\Federation;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationClubApplication;
use App\Members\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The event Clubs tab's club editor (federations.club-edit, api.clubs.logo).
 *
 * What this protects: the federation's managers open a club in a window and
 * change what the federation may change — a pending application, or a club it
 * created itself — including its logo, stored only after its real bytes are
 * checked. A club run by its owner stays the owner's, and nobody outside the
 * federation reaches either door.
 */
class ClubEditTest extends TestCase
{
    private User $owner;

    private Federation $fed;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        config(['federations.enabled' => true, 'federations.club_management' => true]);
        Storage::fake('public');
        $this->owner = $this->createUser();
        $this->fed = (new Federation)->forceFill([
            'owner_user_id' => $this->owner->id, 'name' => 'Test Karate Federation', 'short_name' => 'TKF',
            'country' => 'BH', 'currency' => 'BHD', 'sport' => 'Karate', 'color' => '#1e2c4f',
            'status' => Federation::STATUS_APPROVED, 'approved_at' => now(),
        ]);
        $this->fed->save();
    }

    private function application(): FederationClubApplication
    {
        return FederationClubApplication::forceCreate([
            'federation_id' => $this->fed->id, 'name' => 'Camel Combat', 'coach' => 'Ali',
            'status' => FederationClubApplication::STATUS_PENDING, 'invite_token' => Str::random(48),
        ]);
    }

    public function test_a_manager_opens_the_window_and_changes_a_pending_clubs_logo(): void
    {
        $app = $this->application();
        $key = 'fa-'.$app->uuid;

        $this->actingAs($this->owner)->get(route('federations.club-edit', ['club' => $key]))
            ->assertOk()->assertSee('fedClubEdit', false)->assertHeader('X-Frame-Options', 'SAMEORIGIN');

        $this->actingAs($this->owner)->postJson(route('federations.api.clubs.logo'), ['club' => $key, 'logo' => self::PNG])
            ->assertOk()->assertJsonPath('success', true);

        $path = $app->fresh()->logo;
        $this->assertNotNull($path);
        $this->assertStringEndsWith('.png', $path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($this->owner)->getJson(route('federations.api.clubs.detail', ['club' => $key]))
            ->assertOk()->assertJsonPath('club.logo', route('federations.invite.club-logo', $app->uuid));
    }

    public function test_a_file_that_is_not_an_image_is_refused(): void
    {
        $app = $this->application();

        $this->actingAs($this->owner)->postJson(route('federations.api.clubs.logo'),
            ['club' => 'fa-'.$app->uuid, 'logo' => 'data:image/png;base64,'.base64_encode('<?php system($_GET["c"]); ?>')])
            ->assertStatus(422);

        $this->assertNull($app->fresh()->logo);
    }

    public function test_a_club_run_by_its_owner_keeps_its_logo(): void
    {
        $tenant = $this->createClub($this->createUser());
        $fc = FederationClub::forceCreate(['federation_id' => $this->fed->id, 'tenant_id' => $tenant->id, 'status' => 'approved', 'uuid' => (string) Str::uuid()]);

        $this->actingAs($this->owner)->postJson(route('federations.api.clubs.logo'), ['club' => 'fc-'.$fc->uuid, 'logo' => self::PNG])
            ->assertStatus(422);

        $this->assertSame($tenant->logo, $tenant->fresh()->logo);
    }

    public function test_someone_outside_the_federation_reaches_neither_door(): void
    {
        $app = $this->application();
        $stranger = $this->createUser();

        $this->actingAs($stranger)->get(route('federations.club-edit', ['club' => 'fa-'.$app->uuid]))->assertNotFound();
        $this->actingAs($stranger)->postJson(route('federations.api.clubs.logo'), ['club' => 'fa-'.$app->uuid, 'logo' => self::PNG])
            ->assertNotFound();

        $this->assertNull($app->fresh()->logo);
    }
}
