<?php

namespace Tests\Feature\Ui;

use App\Members\Models\User;
use Tests\TestCase;

/**
 * The new UI shell (app/Ui) behind NEW_UI. Off must be exactly the old design;
 * preview is a super-admin's own opt-in; on is everyone.
 */
class NewUiShellTest extends TestCase
{
    private const PHONE = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36';

    private function member(): User
    {
        $u = $this->createUser();
        $club = $this->createClub($u, ['country' => 'BH']);
        $u->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $u->fresh();
    }

    /** Writes the page to disk when T1_DUMP is set, so it can be looked at in a browser. */
    private function dump(string $name, string $html): void
    {
        if (getenv('T1_DUMP')) {
            @mkdir(storage_path('framework/testing/ui'), 0775, true);
            file_put_contents(storage_path("framework/testing/ui/{$name}.html"), $html);
        }
    }

    public function test_off_is_the_classic_design(): void
    {
        config(['features.new_ui' => 'off']);

        $this->actingAs($this->member())->get('/explore')->assertOk()
            ->assertSee('class="to-bar"', false)
            ->assertDontSee('t1-top', false)
            ->assertDontSee('takeone-v2.css', false);
    }

    public function test_preview_doors_are_closed_unless_preview_is_set(): void
    {
        config(['features.new_ui' => 'off']);
        $admin = $this->member();
        $this->makeSuperAdmin($admin);

        $this->actingAs($admin)->post('/me/new-ui/on')->assertSessionMissing(\App\Ui\NewUi::SESSION_KEY);
        $this->get('/explore')->assertSee('class="to-bar"', false);
    }

    public function test_a_member_cannot_switch_the_preview_on(): void
    {
        config(['features.new_ui' => 'preview']);

        $this->actingAs($this->member())->post('/me/new-ui/on')->assertSessionMissing(\App\Ui\NewUi::SESSION_KEY);
        $this->get('/explore')->assertSee('class="to-bar"', false);
    }

    public function test_preview_shows_the_new_design_to_a_super_admin_who_can_step_back(): void
    {
        config(['features.new_ui' => 'preview']);
        $admin = $this->member();
        $this->makeSuperAdmin($admin);

        $page = $this->actingAs($admin)->get('/explore')->assertOk()
            ->assertSee('t1-top', false)->assertSee('takeone-v2.css', false)
            ->assertDontSee('class="to-bar"', false);
        $this->dump('desktop-explore', $page->getContent());

        $this->post('/me/new-ui/off');
        $this->get('/explore')->assertOk()->assertSee('class="to-bar"', false);

        $this->post('/me/new-ui/on')->assertRedirect(route('clubs.explore'));
        $this->get('/explore')->assertOk()->assertSee('t1-top', false);
    }

    public function test_preview_keeps_the_classic_design_for_everyone_else(): void
    {
        config(['features.new_ui' => 'preview']);

        $this->actingAs($this->member())->get('/explore')->assertOk()->assertSee('class="to-bar"', false);
    }

    public function test_the_preview_page_is_only_for_a_super_admin_in_preview(): void
    {
        config(['features.new_ui' => 'preview']);
        $admin = $this->member();
        $this->makeSuperAdmin($admin);

        $this->actingAs($admin)->get('/me/new-ui')->assertOk()->assertSee('Turn on');
        $this->actingAs($this->member())->get('/me/new-ui')->assertDontSee('Turn on');

        config(['features.new_ui' => 'off']);
        $this->actingAs($admin)->get('/me/new-ui')->assertDontSee('Turn on');
    }

    public function test_on_gives_the_new_bar_to_everyone_on_desktop(): void
    {
        config(['features.new_ui' => 'on']);

        $this->actingAs($this->member())->get('/me/challenge')->assertOk()
            ->assertSee('t1-top', false)
            ->assertSee('t1-idsw', false);
    }

    public function test_on_gives_the_new_header_and_tabs_on_a_phone(): void
    {
        config(['features.new_ui' => 'on']);

        $page = $this->actingAs($this->member())->withHeaders(['User-Agent' => self::PHONE])
            ->get('/me')->assertOk()
            ->assertSee('t1-tabs', false)->assertSee('t1-idsw', false)
            ->assertSee('data-shell-link', false);
        $this->dump('phone-feed', $page->getContent());
    }

    public function test_the_theme_follows_the_cookie_and_ignores_junk(): void
    {
        config(['features.new_ui' => 'on']);
        $m = $this->member();

        $page = $this->actingAs($m)->withUnencryptedCookie('t1_theme', 'dark')->get('/explore')->assertOk()
            ->assertSee('data-t1-theme="dark"', false)->assertSee('t1-theme', false);
        $this->dump('explore-dark', $page->getContent());
        $this->dump('schedule-dark', $this->actingAs($m)->withUnencryptedCookie('t1_theme', 'dark')->get('/me/schedule')->assertOk()->getContent());
        $this->actingAs($m)->withUnencryptedCookie('t1_theme', '"><script>')->get('/explore')->assertOk()
            ->assertSee('data-t1-theme="light"', false)->assertDontSee('data-t1-theme="">', false);

        config(['features.new_ui' => 'off']);
        $this->actingAs($m)->withUnencryptedCookie('t1_theme', 'dark')->get('/explore')->assertOk()->assertDontSee('data-t1-theme', false);
    }

    public function test_manage_appears_only_for_someone_who_manages_something(): void
    {
        config(['features.new_ui' => 'on']);

        // A plain member of someone else's club: no Manage.
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH']);
        $plain = $this->createUser();
        $plain->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        $this->actingAs($plain->fresh())->get('/me/challenge')->assertOk()->assertDontSee('t1-manage', false);

        // The club's owner: Manage opens the Manage shell.
        $this->actingAs($owner->fresh())->get('/me/challenge')->assertOk()
            ->assertSee('t1-manage', false)->assertSee(route('me.manage'), false);
    }
}
