<?php

namespace App\Events;

use App\Events\Contracts\EventType;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

/**
 * Makes each sport's folder — and each event type inside it — self-contained.
 *
 * A sport owns a folder; every event type that sport runs is a sub-folder of it,
 * because one sport has many kinds of event that share its tables and vocabulary:
 *
 *   app/Events/Sports/Taekwondo/
 *   ├── Taekwondo.php                      the sport: weight tables, classification, bronze rule
 *   ├── resources/lang/{en,ar}/messages.php   strings EVERY Taekwondo event shares
 *   │                                         → sport-taekwondo::messages.…
 *   ├── Tournament/                        an event type
 *   │   ├── Tournament.php                 the EventType implementation
 *   │   ├── …                              its collaborators (gate, engine, roster)
 *   │   └── resources/
 *   │       ├── views/                     → event-<key>::<view>
 *   │       └── lang/{en,ar}/messages.php  → event-<key>::messages.…
 *   └── BeltTest/                          another event type, same sport
 *
 * Both levels are discovered from the registry and bound automatically, so no
 * package path is ever written by hand and a sport folder stays portable.
 */
class EventPackageServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $registry = $this->app->make(EventTypeRegistry::class);

        $packages = $registry->all();
        $packages[$registry->fallback()->key()] ??= $registry->fallback();

        foreach ($packages as $key => $type) {
            $typePath = $this->packagePath($type);

            // The event type's own resources.
            $this->bindResources('event-'.$key, $typePath);
            $this->registerRoutes($typePath);
            $this->registerCommands($typePath);

            // Resources shared by every event type of the same sport, when the
            // package sits inside a sport folder.
            if ($sport = $this->sportOf($type)) {
                $this->bindResources('sport-'.$sport, dirname($typePath));
            }
        }
    }

    /** The directory the package's implementation lives in. */
    private function packagePath(EventType $type): string
    {
        return dirname((new ReflectionClass($type))->getFileName());
    }

    /**
     * The sport folder this package sits in, lower-cased — e.g. a package at
     * App\Events\Sports\Taekwondo\Tournament belongs to "taekwondo". Null for
     * packages that are not sport-scoped (the generic fallback).
     */
    private function sportOf(EventType $type): ?string
    {
        $namespace = (new ReflectionClass($type))->getNamespaceName();

        return preg_match('/^App\\\\Events\\\\Sports\\\\([^\\\\]+)\\\\/', $namespace, $m)
            ? strtolower($m[1])
            : null;
    }

    /**
     * Load the package's own routes, if it ships any.
     *
     * A package that serves its own screens needs URLs, and writing them into
     * routes/web.php is the one thing that would stop a sport being deletable by
     * removing its directory: the file would keep a block of dead references
     * that 500 the whole route table. A routes.php beside the package keeps the
     * rule — add a directory and a registry line, remove the same two.
     *
     * Purely additive: a package with no routes.php is untouched, which is every
     * package that existed before this. Loading in boot() is the documented
     * pattern for exactly this and survives `route:cache`, because caching walks
     * the registered routes rather than the files that declared them.
     */
    private function registerRoutes(string $path): void
    {
        if (! is_file($routes = $path.'/routes.php')) {
            return;
        }

        // Inside the `web` group, exactly as routes/web.php is.
        //
        // loadRoutesFrom() on its own registers them bare, and bare is not a
        // smaller version of `web` — it is a different thing. No session, so
        // `auth` has nothing to read and every organiser is bounced to login;
        // and no CSRF, so the scoring endpoints would accept a cross-site POST.
        // A package declaring its own routes must not quietly opt out of the
        // protections every other route on the platform has.
        $this->app->booted(function () use ($routes) {
            Route::middleware('web')->group(function () use ($routes) {
                require $routes;
            });
        });
    }

    /**
     * Register any artisan commands the package ships, wherever they sit in it.
     *
     * Console classes live beside the domain code they serve rather than in the
     * app's shared Commands folder, so Laravel's auto-discovery never sees them.
     * Discovering them here keeps the package rule intact: shipping a command
     * with a new event type stays a matter of adding one file to its directory,
     * with no shared registration to edit.
     */
    private function registerCommands(string $path): void
    {
        if (! $this->app->runningInConsole() || ! is_dir($path)) {
            return;
        }

        $commands = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path)) as $file) {
            if ($file->getExtension() !== 'php' || ! str_contains($file->getPathname(), DIRECTORY_SEPARATOR)) {
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
