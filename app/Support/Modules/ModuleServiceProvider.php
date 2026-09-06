<?php

namespace App\Support\Modules;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

/**
 * Makes each module's folder self-contained.
 *
 *   app/Shop/
 *   ├── Shop.php                    the Module implementation
 *   ├── Models/                     the tables only the shop owns
 *   ├── Controllers/                its screens, admin and member-facing
 *   ├── Services/                   its domain logic
 *   ├── routes.php                  its URLs           → loaded here
 *   └── resources/
 *       ├── views/                  → shop::<view>
 *       └── lang/{en,ar}/           → shop::messages.…
 *
 * Discovered from config/modules.php and bound automatically, so no module path
 * is ever written by hand and a folder stays portable.
 *
 * Mirrors App\Events\EventPackageServiceProvider deliberately: one mechanism for
 * "a folder is a module", not two. That provider stays as it is — it binds the
 * SPORT and EVENT-TYPE packages nested inside the events module, which is a
 * level below this one.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class);
    }

    public function boot(): void
    {
        foreach ($this->app->make(ModuleRegistry::class)->all() as $module) {
            $path = dirname((new ReflectionClass($module))->getFileName());

            $this->bindResources($module->key(), $path);
            $this->registerRoutes($path);
            $this->registerCommands($path);
        }
    }

    /**
     * Load the module's own route files.
     *
     * Declaring them in routes/web.php is the one thing that would stop a module
     * being deletable by removing its directory — the shared file would keep a
     * block of dead controller references that 500 the entire route table on the
     * next boot.
     *
     * Three files, because a module's URLs are not all alike, and the group a
     * route belongs to decides its middleware:
     *
     *   routes.php            plain `web` — public pages, guest-reachable screens.
     *   routes-member.php     grouped under /me with the `me.` name prefix, for a
     *                         signed-in member acting on their own behalf.
     *   routes-club-admin.php grouped under admin/club/{club} with the `admin.club.`
     *                         name prefix, for someone administering a club.
     *
     * Each is optional. All are registered inside `web`, because bare is not
     * a smaller version of `web`: no session means `auth` reads nothing and
     * every admin is bounced to login, and no CSRF means a write would accept a
     * cross-site POST. A module declaring its own routes must not quietly opt out
     * of the protections every other route on the platform has.
     */
    private function registerRoutes(string $path): void
    {
        $web = $path.'/routes.php';
        $member = $path.'/routes-member.php';
        $clubAdmin = $path.'/routes-club-admin.php';

        if (! is_file($web) && ! is_file($member) && ! is_file($clubAdmin)) {
            return;
        }

        $this->app->booted(function () use ($web, $member, $clubAdmin) {
            if (is_file($web)) {
                Route::middleware('web')->group(fn () => require $web);
            }

            if (is_file($member)) {
                Route::middleware(['web', 'auth', 'verified', 'two-factor'])
                    ->prefix('me')
                    ->name('me.')
                    ->group(fn () => require $member);
            }

            if (is_file($clubAdmin)) {
                Route::middleware(['web', 'auth', 'verified', 'two-factor', 'tenant', 'throttle:admin-write'])
                    ->prefix('admin/club/{club}')
                    ->name('admin.club.')
                    ->group(fn () => require $clubAdmin);
            }
        });
    }

    /** Artisan commands shipped beside the domain code they serve. */
    private function registerCommands(string $path): void
    {
        if (! $this->app->runningInConsole() || ! is_dir($path)) {
            return;
        }

        $commands = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            // resources/ holds views and translations, never PHP classes.
            if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $class = $this->classFor($file->getPathname());

            if ($class && is_subclass_of($class, \Illuminate\Console\Command::class)) {
                $commands[] = $class;
            }
        }

        if ($commands) {
            $this->commands($commands);
        }
    }

    /** The PSR-4 class name for a file under app/, or null if it has none. */
    private function classFor(string $file): ?string
    {
        $relative = str_replace([app_path().DIRECTORY_SEPARATOR, '.php'], '', $file);
        $class = 'App\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

        return class_exists($class) ? $class : null;
    }

    /** Bind a folder's views and translations under the given namespace. */
    private function bindResources(string $namespace, string $path): void
    {
        if (is_dir($views = $path.'/resources/views')) {
            $this->loadViewsFrom($views, $namespace);
        }

        if (is_dir($lang = $path.'/resources/lang')) {
            $this->loadTranslationsFrom($lang, $namespace);
        }
    }
}
