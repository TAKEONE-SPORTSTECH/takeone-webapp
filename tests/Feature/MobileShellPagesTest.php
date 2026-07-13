<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Mobile /explore used to render a bespoke top bar (back + chat only) outside
 * the personal shell, so there was no user menu and no bottom nav — the only
 * way off the page was history.back(). It now lives in the shell.
 */
class MobileShellPagesTest extends TestCase
{
    private function mobileGet(string $uri)
    {
        // DetectDevice sets the is_mobile request attribute from the UA.
        return $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 '
                .'(KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36',
        ])->get($uri);
    }

    public function test_mobile_explore_renders_inside_the_personal_shell(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->mobileGet('/explore');

        $response->assertOk()
            // The shell's <main> — proves we're in the personal shell.
            ->assertSee('data-shell-id="personal"', false)
            // The drawer (user menu) that the bespoke top bar never had.
            ->assertSee('x-data="{ drawer:false, switcher:false }"', false)
            // Bottom tab bar.
            ->assertSee(__('nav.tab_challenge'), false);
    }

    public function test_mobile_explore_includes_the_chat_panel_exactly_once(): void
    {
        $user = $this->createUser();

        $html = $this->actingAs($user)->mobileGet('/explore')->assertOk()->getContent();

        // mobile-header pulls in partials.mobile-chat; explore must not also
        // include it, or the panel is rendered twice with duplicate ids.
        $this->assertSame(1, substr_count($html, 'x-data="mobileChatHeads()"'));
    }

    public function test_explore_runtime_still_boots(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->mobileGet('/explore')
            ->assertOk()
            ->assertSee('exploreApp()', false);
    }

    public function test_settings_renders_inside_the_personal_shell(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->mobileGet('/me/settings')
            ->assertOk()
            ->assertSee('data-shell-id="personal"', false)
            ->assertSee('x-data="{ drawer:false, switcher:false }"', false)
            // Header title comes from the shell's own nav lookup for me.settings.
            ->assertSee(__('nav.account_settings'), false);
    }

    public function test_settings_keeps_the_locale_switcher(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->mobileGet('/me/settings')
            ->assertOk()
            ->assertSee('switchLocale', false)
            ->assertSee('takeone:locale', false);
    }

    public function test_settings_no_longer_has_the_dead_end_back_button(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->mobileGet('/me/settings')
            ->assertOk()
            ->assertDontSee("history.length > 1 ? history.back()", false);
    }

    public static function shellPages(): array
    {
        return [
            'explore'      => ['/explore'],
            'settings'     => ['/me/settings'],
            'packages'     => ['/me/packages'],
            'progress'     => ['/me/progress'],
            'payments'     => ['/me/payments'],
            'affiliations' => ['/me/affiliations'],
            'people'       => ['/me/people'],
            'family'       => ['/family/members'],
        ];
    }

    /**
     * Every converted page must render, sit in the shell (so the avatar/drawer
     * user menu is reachable), and no longer strand the user on history.back().
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('shellPages')]
    public function test_page_is_in_the_shell_with_a_user_menu(string $uri): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->mobileGet($uri)
            ->assertOk()
            ->assertSee('data-shell-id="personal"', false)
            ->assertSee('x-data="{ drawer:false, switcher:false }"', false)
            ->assertDontSee('history.length > 1 ? history.back()', false);
    }

    /** The shell header must never fall back to "Home" on these pages. */
    public function test_pages_outside_the_nav_list_still_get_a_real_title(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->mobileGet('/me/affiliations')->assertSee(__('nav.affiliations'), false);
        $this->actingAs($user)->mobileGet('/me/people')->assertSee(__('personal.find_people'), false);
        $this->actingAs($user)->mobileGet('/family/members')->assertSee(__('family.title'), false);
    }

    /** Grab the markup of the header's page-action slot. */
    private function headerActions(string $html): string
    {
        $start = strpos($html, 'id="shell-actions"');
        $this->assertNotFalse($start, 'header has no #shell-actions slot');

        // Up to the slot's own closing tag. Anchored on markup, not on a
        // translated aria-label, so this survives a non-English test locale.
        $end = strpos($html, '</div>', $start);
        $this->assertNotFalse($end, '#shell-actions slot is unterminated');

        return substr($html, $start, $end - $start);
    }

    /** Actions from the old top bar must now live in the shell header itself. */
    public function test_page_actions_are_hoisted_into_the_header(): void
    {
        $user = $this->createUser();

        $affiliations = $this->headerActions(
            $this->actingAs($user)->mobileGet('/me/affiliations')->getContent()
        );
        $this->assertStringContainsString('bi-plus-lg', $affiliations);

        $family = $this->headerActions(
            $this->actingAs($user)->mobileGet('/family/members')->getContent()
        );
        $this->assertStringContainsString('bi-diagram-3', $family);
        $this->assertStringContainsString('bi-person-plus', $family);
        // Must dispatch on window: the button no longer sits in the page's x-data.
        $this->assertStringContainsString('window.dispatchEvent', $family);
    }

    /** A page with no actions must leave the slot empty, not inherit another's. */
    public function test_pages_without_actions_have_an_empty_header_slot(): void
    {
        $user = $this->createUser();

        $actions = $this->headerActions(
            $this->actingAs($user)->mobileGet('/me/progress')->getContent()
        );

        $this->assertStringNotContainsString('bi-plus-lg', $actions);
        $this->assertStringNotContainsString('bi-person-plus', $actions);
    }

    /** The search input stays in the page: it's bound to peopleSearch()'s x-data. */
    public function test_people_search_box_stays_in_the_content(): void
    {
        $user = $this->createUser();

        $html = $this->actingAs($user)->mobileGet('/me/people')->assertOk()->getContent();

        $this->assertStringContainsString('peopleSearch(', $html);
        $this->assertStringNotContainsString('x-model="q"', $this->headerActions($html));
    }

    /** The shell navigator must refresh the header slot on in-place navigation. */
    public function test_shell_navigator_swaps_the_header_actions(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->mobileGet('/me/progress')
            ->assertSee("getElementById('shell-actions')", false);
    }

    public function test_mobile_explore_header_is_titled_explore(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->mobileGet('/explore')
            ->assertOk()
            ->assertSee(__('explore.explore'), false);
    }

    public function test_mobile_explore_no_longer_has_the_dead_end_back_button(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->mobileGet('/explore')
            ->assertOk()
            ->assertDontSee("history.length > 1 ? history.back()", false);
    }
}
