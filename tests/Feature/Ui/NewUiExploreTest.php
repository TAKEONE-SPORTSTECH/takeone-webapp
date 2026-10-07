<?php

namespace Tests\Feature\Ui;

use App\Clubs\Models\ClubActivity;
use App\Platform\Models\ActivityCatalog;
use Tests\TestCase;

/**
 * The new Explore (/explore while NEW_UI is on): the classic page is untouched
 * when the flag is off, and the new one carries only what a card shows.
 */
class NewUiExploreTest extends TestCase
{
    private const PHONE = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36';

    private function scene(): array
    {
        $owner = $this->createUser(['email' => 'owner-secret@example.com']);
        $club = $this->createClub($owner, ['country' => 'BH', 'club_name' => 'Victory Academy', 'gps_lat' => 26.23, 'gps_long' => 50.53,
            'email' => 'club-private@example.com', 'owner_email' => 'owner-secret@example.com', 'address' => 'Building 1, Seef, Manama']);
        ActivityCatalog::create(['name' => 'Karate', 'description' => '<h3>Origins</h3><p>Empty hand.</p>', 'is_active' => true, 'usage_count' => 5,
            'variants' => [['name' => 'Shotokan'], ['name' => 'Goju-ryu']]]);
        ActivityCatalog::create(['name' => 'Padel', 'is_active' => true]);
        ActivityCatalog::create(['name' => 'Hidden sport', 'is_active' => false]);
        ClubActivity::create(['tenant_id' => $club->id, 'name' => 'karate']);

        $member = $this->createUser();
        $member->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return [$member->fresh(), $club];
    }

    private function dump(string $name, string $html): void
    {
        if (getenv('T1_DUMP')) {
            @mkdir(storage_path('framework/testing/ui'), 0775, true);
            file_put_contents(storage_path("framework/testing/ui/{$name}.html"), $html);
        }
    }

    public function test_flag_off_keeps_the_classic_explore(): void
    {
        config(['features.new_ui' => 'off']);
        [$member] = $this->scene();

        $this->actingAs($member)->get('/explore')->assertOk()
            ->assertSee('exploreApp()', false)
            ->assertDontSee('t1Explore()', false);
    }

    public function test_the_new_explore_lists_clubs_and_activities_on_desktop(): void
    {
        config(['features.new_ui' => 'on']);
        [$member, $club] = $this->scene();

        $page = $this->actingAs($member)->get('/explore')->assertOk()
            ->assertSee('t1Explore()', false)
            ->assertSee('Victory Academy')
            ->assertSee('Karate')
            ->assertSee('Shotokan')
            ->assertSee('Seef')
            ->assertDontSee('Hidden sport')
            // only what a card shows leaves the server
            ->assertDontSee('owner-secret@example.com')
            ->assertDontSee('club-private@example.com')
            // the catalogue's HTML description is flattened to text
            ->assertDontSee('<h3>Origins', false);
        $this->dump('explore-desktop', $page->getContent());
    }

    public function test_the_new_explore_renders_inside_the_phone_shell(): void
    {
        config(['features.new_ui' => 'on']);
        [$member] = $this->scene();

        $page = $this->actingAs($member)->withHeaders(['User-Agent' => self::PHONE])->get('/explore')->assertOk()
            ->assertSee('t1Explore()', false)
            ->assertSee('id="shell-content"', false)
            ->assertSee('t1-tabs', false);
        $this->dump('explore-phone', $page->getContent());
    }

    public function test_a_club_matched_to_an_activity_counts_on_the_tile(): void
    {
        config(['features.new_ui' => 'on']);
        [$member] = $this->scene();

        $data = \App\Ui\Explore\ExplorePage::data();
        $karate = collect($data['activities'])->firstWhere('name', 'Karate');
        $padel = collect($data['activities'])->firstWhere('name', 'Padel');

        $this->assertSame(1, $karate['clubs']);
        $this->assertSame(0, $padel['clubs']);
        $this->assertSame(['Karate'], $data['clubs'][0]['activities']);
        $this->assertArrayNotHasKey('owner_email', $data['clubs'][0]);
    }

    public function test_the_shop_tab_lists_published_products_without_private_fields(): void
    {
        config(['features.new_ui' => 'on']);
        [$member, $club] = $this->scene();
        \App\Shop\Models\ClubProduct::create(['tenant_id' => $club->id, 'name' => 'Competition Gi', 'category' => 'uniforms', 'price' => 25, 'old_price' => 40,
            'cost' => 9, 'supplier' => 'Secret Supplier Co', 'status' => 'published']);
        \App\Shop\Models\ClubProduct::create(['tenant_id' => $club->id, 'name' => 'Draft Belt', 'category' => 'belts', 'price' => 5, 'status' => 'draft']);

        $data = \App\Ui\Explore\ExplorePage::data();
        $this->assertSame(['Competition Gi'], collect($data['products'])->pluck('name')->all());
        $gi = $data['products'][0];
        $this->assertSame('BD 25', $gi['price']);
        $this->assertSame('BD 40', $gi['old']);
        $this->assertSame('−38%', $gi['off']);
        $this->assertArrayNotHasKey('cost', $gi);
        $this->assertArrayNotHasKey('supplier', $gi);

        $this->actingAs($member)->get('/explore')->assertOk()
            ->assertSee('Competition Gi')
            ->assertDontSee('Secret Supplier Co')
            ->assertDontSee('Draft Belt');
    }

    public function test_the_coming_soon_tabs_are_on_the_page(): void
    {
        config(['features.new_ui' => 'on']);
        [$member] = $this->scene();

        $this->actingAs($member)->get('/explore')->assertOk()
            ->assertSee('soon_offers_title', false)
            ->assertSee('soon_venues_title', false)
            ->assertSee('soon_clinics_title', false)
            ->assertSee('soon_specialists_title', false)
            ->assertSee('t1-soon', false);
    }
}
