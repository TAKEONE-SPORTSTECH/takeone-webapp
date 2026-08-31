<?php

namespace Tests\Feature\Modules;

use App\Support\Modules\Module;
use App\Support\Modules\ModuleRegistry;
use App\Support\Modules\Surfaces\ContributesClubAdminNav;
use App\Clubs\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * The club's capability modules and the shell they compose.
 *
 * The club admin sidebar used to be two hardcoded lists inside two layout
 * files, so adding a capability meant editing the shell — the exact coupling
 * the event-package rule exists to prevent. These tests hold the replacement
 * honest: the composed nav is what it was, every entry goes somewhere real, and
 * removing a module's line removes its entries and nothing else.
 */
class ModuleRegistryTest extends TestCase
{
    use RefreshDatabase;

    private function club(): Tenant
    {
        return $this->createClub(User::factory()->create(), ['country' => 'BH', 'currency' => 'BHD']);
    }

    private function registry(): ModuleRegistry
    {
        return app(ModuleRegistry::class);
    }

    public function test_every_registered_module_implements_the_contract_with_a_unique_key(): void
    {
        $modules = $this->registry()->all();

        $this->assertNotEmpty($modules, 'No modules are registered.');

        foreach ($modules as $key => $module) {
            $this->assertInstanceOf(Module::class, $module);
            $this->assertSame($key, $module->key(), 'A module is keyed by something other than its own key().');
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9-]*$/', $key, "Module key '{$key}' is not a slug.");
        }
    }

    /**
     * The order a club owner reads every day. Composing the sidebar must not
     * quietly reshuffle it.
     */
    public function test_the_desktop_sidebar_is_composed_in_the_order_it_always_had(): void
    {
        $groups = $this->registry()->clubAdminNavGroups($this->club(), 'desktop');

        $routes = collect($groups)->map(fn ($g) => collect($g['items'])->pluck('route')->all())->all();

        $this->assertSame([
            ['admin.club.dashboard', 'admin.club.analytics', 'admin.club.financials'],
            ['admin.club.members', 'admin.club.instructors', 'admin.club.roles', 'admin.club.achievements.verifications'],
            ['admin.club.activities', 'admin.club.packages', 'admin.club.events', 'admin.club.sparring', 'admin.club.facilities'],
            ['admin.club.shop', 'admin.club.orders', 'admin.club.perks'],
            ['admin.club.timeline', 'admin.club.gallery', 'admin.club.achievements'],
        ], $routes);
    }

    /**
     * The mobile drawer is its OWN navigation, not the desktop rail at a
     * narrower width — different groups, different order, and entries the
     * desktop shell keeps elsewhere.
     */
    public function test_the_mobile_drawer_is_composed_in_the_order_it_always_had(): void
    {
        $groups = $this->registry()->clubAdminNavGroups($this->club(), 'mobile');

        $routes = collect($groups)->map(fn ($g) => collect($g['items'])->pluck('route')->all())->all();

        $this->assertSame([
            ['admin.club.dashboard', 'admin.club.analytics'],
            ['admin.club.members', 'admin.club.instructors', 'admin.club.roles', 'admin.club.achievements.verifications', 'admin.club.messages', 'admin.club.notifications'],
            ['admin.club.packages', 'admin.club.activities', 'admin.club.events', 'admin.club.sparring', 'admin.club.facilities'],
            ['admin.club.shop', 'admin.club.orders'],
            ['admin.club.gallery', 'admin.club.timeline', 'admin.club.perks', 'admin.club.achievements'],
            ['admin.club.financials'],
            ['admin.club.details'],
        ], $routes);
    }

    /**
     * Navigation Integrity: a composed sidebar makes it easy to ship an entry
     * pointing at a route that does not exist, which is a 500 on the shell of
     * every club-admin page rather than a broken link on one.
     */
    public function test_every_nav_entry_on_every_surface_points_at_a_real_route(): void
    {
        $club = $this->club();
        $named = collect(RouteFacade::getRoutes()->getRoutesByName())->keys();

        foreach (['desktop', 'mobile'] as $surface) {
            foreach ($this->registry()->clubAdminNavGroups($club, $surface) as $group) {
                foreach ($group['items'] as $item) {
                    $this->assertContains(
                        $item['route'],
                        $named->all(),
                        "The {$surface} sidebar links to '{$item['route']}', which is not a registered route."
                    );
                }
            }
        }
    }

    /** Every group heading rendered is a real translation, not a raw key. */
    public function test_group_headings_are_translated(): void
    {
        $club = $this->club();

        foreach (['desktop', 'mobile'] as $surface) {
            foreach ($this->registry()->clubAdminNavGroups($club, $surface) as $group) {
                $this->assertNotEmpty($group['label']);
                $this->assertStringNotContainsString('.', $group['label'], "Group heading '{$group['label']}' looks like an untranslated key.");
            }
        }
    }

    /**
     * The whole point of the boundary: a capability is removed by deleting its
     * directory and its one registry line. Nothing else may notice.
     */
    public function test_removing_a_module_from_the_registry_removes_only_its_own_entries(): void
    {
        $club = $this->club();

        $before = collect($this->registry()->clubAdminNavGroups($club, 'desktop'))
            ->flatMap(fn ($g) => collect($g['items'])->pluck('route'))->all();

        $this->assertContains('admin.club.shop', $before);

        config(['modules.modules' => collect(config('modules.modules'))
            ->reject(fn ($class) => $class === \App\Shop\Shop::class)
            ->values()->all()]);

        // The registry memoises, so ask a fresh one.
        $after = collect((new ModuleRegistry)->clubAdminNavGroups($club, 'desktop'))
            ->flatMap(fn ($g) => collect($g['items'])->pluck('route'))->all();

        $this->assertNotContains('admin.club.shop', $after);
        $this->assertNotContains('admin.club.orders', $after);
        $this->assertNotContains('admin.club.perks', $after);

        // Everything else survived, and the now-empty group is gone rather than
        // rendering a heading with nothing under it.
        $this->assertContains('admin.club.dashboard', $after);
        $this->assertContains('admin.club.members', $after);
        $this->assertSame(
            collect($before)->reject(fn ($r) => in_array($r, ['admin.club.shop', 'admin.club.orders', 'admin.club.perks'], true))->values()->all(),
            $after
        );
    }
}
