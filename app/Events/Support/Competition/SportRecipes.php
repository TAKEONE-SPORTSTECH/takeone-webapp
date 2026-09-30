<?php

namespace App\Events\Support\Competition;

use Illuminate\Support\Str;

/**
 * Every sport the generic competition engine can run — one `recipe.php` in
 * each sport's own folder (app/Events/Sports/<Sport>/recipe.php).
 *
 * The sport key is the platform's own (event_schema.sports: `table_tennis`);
 * its folder is the StudlyCase of it (TableTennis). A sport with a combat
 * package of its own (BJJ, Karate, Taekwondo) has no recipe: its package runs
 * it. Adding a sport = one folder with one data file; removing it = deleting
 * that folder.
 */
final class SportRecipes
{
    /** @var array<string, SportRecipe|null> */
    private array $loaded = [];

    public function get(?string $sport): ?SportRecipe
    {
        if (! $sport || ! preg_match('/^[a-z][a-z_]{1,30}$/', $sport)) {
            return null;
        }

        if (array_key_exists($sport, $this->loaded)) {
            return $this->loaded[$sport];
        }

        $file = app_path('Events/Sports/'.Str::studly($sport).'/recipe.php');

        $data = is_file($file) ? (static fn () => require $file)() : null;

        return $this->loaded[$sport] = is_array($data) ? new SportRecipe($sport, $data) : null;
    }

    public function has(?string $sport): bool
    {
        return $this->get($sport) !== null;
    }

    /** @return array<string, SportRecipe> every sport of the platform that has a recipe */
    public function all(): array
    {
        $out = [];

        foreach (array_keys((array) config('event_schema.sports', [])) as $key) {
            if ($recipe = $this->get($key)) {
                $out[$key] = $recipe;
            }
        }

        return $out;
    }
}
