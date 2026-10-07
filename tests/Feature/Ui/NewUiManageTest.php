<?php

namespace Tests\Feature\Ui;

use App\Clubs\Models\ClubTransaction;
use Tests\TestCase;

/** /me/manage — the Manage side of Play/Manage (new UI). */
class NewUiManageTest extends TestCase
{
    private const PHONE = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36';

    private function dump(string $name, string $html): void
    {
        if (getenv('T1_DUMP')) {
            @mkdir(storage_path('framework/testing/ui'), 0775, true);
            file_put_contents(storage_path("framework/testing/ui/{$name}.html"), $html);
        }
    }

    public function test_it_does_not_exist_with_the_flag_off(): void
    {
        config(['features.new_ui' => 'off']);
        $owner = $this->createUser();
        $this->createClub($owner, ['country' => 'BH']);

        $this->actingAs($owner)->getJson('/me/manage')->assertNotFound();
    }

    public function test_a_member_who_manages_nothing_has_no_manage(): void
    {
        config(['features.new_ui' => 'on']);

        $this->actingAs($this->createUser())->getJson('/me/manage')->assertNotFound();
    }

    public function test_an_owner_sees_their_club_and_its_figures(): void
    {
        config(['features.new_ui' => 'on']);
        $owner = $this->createUser(['full_name' => 'Ahmed Saleh']);
        $club = $this->createClub($owner, ['country' => 'BH', 'club_name' => 'Victory Academy', 'currency' => 'BHD']);
        ClubTransaction::create(['tenant_id' => $club->id, 'type' => 'income', 'amount' => 125, 'description' => 'Fees', 'category' => 'membership',
            'payment_method' => 'cash', 'transaction_date' => now()->toDateString()]);

        $page = $this->actingAs($owner->fresh())->get('/me/manage')->assertOk()
            ->assertSee('t1-msb', false)
            ->assertSee('Victory Academy')
            ->assertSee('BD 125')
            ->assertSee(route('admin.club.members', $club->slug), false);
        $this->dump('manage-desktop', $page->getContent());

        $page = $this->withHeaders(['User-Agent' => self::PHONE])->get('/me/manage')->assertOk()->assertSee('t1-scope', false);
        $this->dump('manage-phone', $page->getContent());
    }

    public function test_someone_elses_club_cannot_be_opened_by_its_key(): void
    {
        config(['features.new_ui' => 'on']);
        $mine = $this->createUser();
        $this->createClub($mine, ['country' => 'BH', 'club_name' => 'My Club']);
        $other = $this->createUser();
        $theirs = $this->createClub($other, ['country' => 'BH', 'club_name' => 'Their Secret Club']);

        $this->actingAs($mine->fresh())->get('/me/manage?org=c-'.$theirs->slug)->assertOk()
            ->assertSee('My Club')
            ->assertDontSee('Their Secret Club');
    }

    public function test_a_club_admin_sees_the_club_under_staff(): void
    {
        config(['features.new_ui' => 'on']);
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'club_name' => 'Lotus Yoga']);
        $admin = $this->createUser();
        $this->makeClubAdmin($admin, $club);

        $this->actingAs($admin->fresh())->get('/me/manage')->assertOk()->assertSee('Lotus Yoga');
    }
}
