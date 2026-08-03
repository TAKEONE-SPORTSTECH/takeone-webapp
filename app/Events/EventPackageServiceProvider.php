<?php

namespace App\Events;

use App\Events\Contracts\EventType;
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
