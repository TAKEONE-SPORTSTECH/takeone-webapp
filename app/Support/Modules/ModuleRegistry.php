<?php

namespace App\Support\Modules;

use App\Clubs\Models\Tenant;
use App\Support\Modules\Surfaces\ContributesClubAdminNav;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * The list of the platform's modules, read from config/modules.php.
 *
 * This is the only thing that knows which verticals exist. Shells ask it what
 * to show; nothing asks it "which module is this" — a club is not one of several
 * kinds of club, it is a club that several modules have something to say about.
 */
class ModuleRegistry
{
    /** @var array<string, Module>|null */
    private ?array $modules = null;

    /** Every registered module, keyed by its slug, in config order. */
    public function all(): array
    {
        if ($this->modules !== null) {
            return $this->modules;
        }

        $this->modules = [];

        foreach (config('modules.modules', []) as $class) {
            if (! class_exists($class)) {
                continue;
            }

            $module = app($class);

            if ($module instanceof Module) {
                $this->modules[$module->key()] = $module;
            }
        }

        return $this->modules;
    }

    public function find(string $key): ?Module
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * The modules that contribute to a given shell.
     *
     * @param  class-string  $surface  the surface interface
     */
    public function contributingTo(string $surface): Collection
    {
        return collect($this->all())->filter(fn (Module $m) => $m instanceof $surface);
    }

    /**
     * The club admin sidebar, composed from the modules rather than hardcoded in
     * the layouts.
     *
     * Groups and their order come from config('modules.club_admin_groups.<surface>');
     * each module drops its entries into a group and says where in it they sit.
     * The result is exactly the shape the club admin layouts already render —
     * ['label' => string, 'items' => [...]] — so composing it changes what
     * DECIDES the nav without changing a pixel of how it looks.
     *
     * A group nobody contributes to disappears rather than rendering an empty
     * heading, which is what makes deleting a module a one-directory operation.
     *
     * @return array<int, array{label: string, items: array}>
     */
    public function clubAdminNavGroups(Tenant $club, string $surface = 'desktop'): array
    {
        $items = $this->contributingTo(ContributesClubAdminNav::class)
            ->flatMap(fn (ContributesClubAdminNav $m) => $m->clubAdminNav($club, $surface))
            ->groupBy(fn (array $item) => $item['group'] ?? 'other');

        $groups = [];

        foreach (config('modules.club_admin_groups.'.$surface, []) as $key => $label) {
            $inGroup = $items->get($key);

            if (! $inGroup || $inGroup->isEmpty()) {
                continue;
            }

            $groups[] = [
                'label' => __($label),
                'items' => $inGroup
                    ->sortBy(fn (array $item) => $item['order'] ?? 999)
                    ->map(fn (array $item) => Arr::except($item, ['group', 'order']))
                    ->values()
                    ->all(),
            ];
        }

        return $groups;
    }
}
