<?php

namespace Tests\Feature;

use App\Clubs\Models\ClubBankAccount;
use App\Clubs\Models\ClubSocialLink;
use Tests\TestCase;

/**
 * The club popup's update (platform.clubs.update) replaces social links and
 * bank accounts only when the form loaded the club's own rows. Editing from a
 * list where the rows were never shown must leave them alone.
 */
class ClubModalUpdateKeepsRelationsTest extends TestCase
{
    private function scene(): array
    {
        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);
        $club = $this->createClub($this->createUser(), ['registration_fee' => 15]);
        ClubSocialLink::create(['tenant_id' => $club->id, 'platform' => 'instagram', 'url' => 'https://instagram.com/club', 'display_order' => 0]);
        ClubBankAccount::create(['tenant_id' => $club->id, 'bank_name' => 'NBB', 'account_name' => 'Club WLL', 'account_number' => '0012345']);

        return [$admin, $club];
    }

    private function save($admin, $club, array $extra)
    {
        return $this->actingAs($admin)->putJson('/admin/clubs/'.$club->id, [
            'club_name' => 'Renamed Club',
            'slug' => $club->slug,
            'owner_user_id' => $club->owner_user_id,
            'country' => 'BH',
            'currency' => 'BHD',
            'timezone' => 'Asia/Bahrain',
        ] + $extra);
    }

    public function test_a_save_that_never_loaded_the_rows_keeps_them(): void
    {
        [$admin, $club] = $this->scene();

        $this->save($admin, $club, ['relations_loaded' => '0'])->assertOk();

        $this->assertSame('Renamed Club', $club->fresh()->club_name);
        $this->assertSame(1, ClubSocialLink::where('tenant_id', $club->id)->count());
        $this->assertSame(1, ClubBankAccount::where('tenant_id', $club->id)->count());
    }

    public function test_a_save_with_the_rows_loaded_replaces_them(): void
    {
        [$admin, $club] = $this->scene();

        $this->save($admin, $club, [
            'relations_loaded' => '1',
            'social_links' => [['platform' => 'facebook', 'url' => 'https://facebook.com/club']],
        ])->assertOk();

        $this->assertSame(['facebook'], ClubSocialLink::where('tenant_id', $club->id)->pluck('platform')->all());
        $this->assertSame(0, ClubBankAccount::where('tenant_id', $club->id)->count());
    }

    public function test_the_edit_payload_carries_the_registration_fee(): void
    {
        [$admin, $club] = $this->scene();

        $this->actingAs($admin)->getJson('/admin/api/clubs/'.$club->id)->assertOk()
            ->assertJsonPath('registration_fee', fn ($v) => (float) $v === 15.0)
            ->assertJsonCount(1, 'social_links');
    }
}
