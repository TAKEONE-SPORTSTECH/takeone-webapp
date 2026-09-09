<?php

namespace App\Translation\Services;

/**
 * Every English string the interface is made of, and where its translation goes.
 *
 * The INTERFACE half of this module. `Translator` handles what an organiser
 * writes; this handles what the product itself says — "About this event", the
 * buttons, the empty states, the chips.
 *
 * ⚠️ The two halves are stored differently ON PURPOSE. An event's words are
 * per-record and unbounded, so they live in a database document. The interface
 * is per-RELEASE and finite — 9,516 strings that change when the code changes —
 * so it lives in `lang/<code>/*.php` files exactly like the hand-written
 * English and Arabic. That buys three things a table would not: Laravel's
 * per-KEY fallback (a half-finished language renders, with English filling the
 * gaps, and nothing errors), review as a git diff, and no query on the render
 * path of the most-hit page on the platform.
 */
class LangFileCatalog
{
    /**
     * The strings a stranger on a public event page actually reads.
     *
     * The whole English tree is 9,516 keys; this tier is a fraction of it, and
     * it is the fraction that answers "the page is still half English". Ordered
     * by how much of that page each file carries.
     */
    public const VISITOR = [
        'events', 'personal', 'shared', 'nav', 'header', 'errors', 'auth',
        'club', 'explore', 'platform', 'currency', 'notifications', 'security',
    ];

    /** The member's own area — their profile, schedule, payments. */
    public const MEMBER = ['member', 'family', 'challenge', 'market', 'messenger', 'trainer', 'business', 'settings'];

    /** Club staff and super-admin. Deliberately last: a known, small audience. */
    public const STAFF = ['admin', 'copilot'];

    /**
     * Every English lang file on the platform: the shared tree plus each
     * module's and event package's own.
     *
     * @return array<string, array{group: string, namespace: ?string, source: string, target: string}>
     *         keyed by a stable id ("events", "scoreboard::messages")
     */
    public function files(?string $locale = null): array
    {
        $out = [];

        foreach (glob(lang_path('en/*.php')) ?: [] as $path) {
            $group = basename($path, '.php');

            $out[$group] = [
                'group' => $group,
                'namespace' => null,
                'source' => $path,
                'target' => $locale ? lang_path($locale.'/'.$group.'.php') : '',
            ];
        }

        /*
         * Module and event-package strings. They are namespaced
         * (`scoreboard::messages`, `event-bjj_tournament::messages`) and bound
         * by their own service providers, so a translated file is simply a
         * sibling of the English one — no provider change, no registration.
         */
        foreach (glob(base_path('app/*/resources/lang/en/*.php')) ?: [] as $path) {
            $this->addNamespaced($out, $path, $locale);
        }

        foreach (glob(base_path('app/Events/*/resources/lang/en/*.php')) ?: [] as $path) {
            $this->addNamespaced($out, $path, $locale);
        }

        foreach (glob(base_path('app/Events/Sports/*/resources/lang/en/*.php')) ?: [] as $path) {
            $this->addNamespaced($out, $path, $locale);
        }

        foreach (glob(base_path('app/Events/Sports/*/*/resources/lang/en/*.php')) ?: [] as $path) {
            $this->addNamespaced($out, $path, $locale);
        }

        ksort($out);

        return $out;
    }

    /**
     * The files and code that make up the EVENT surface.
     *
     * Everything a person sees between opening a shared competition link and
     * entering it: the poster, its four sub-pages, the entry form, the shared
     * partials and components they render, and the PHP that puts words on them.
     *
     * This list is the definition of "the event", and it is deliberately
     * explicit rather than a wildcard over the whole app — the point is to
     * translate a competition for the world, not to translate an admin panel
     * nobody outside the club will ever open.
     */
    private const EVENT_SURFACE_DIRS = [
        'resources/views/entry',
        'resources/views/components/bracket',
        'app/Events',
        'app/Sports/Combat',
    ];

    private const EVENT_SURFACE_FILES = [
        'resources/views/partials/event-*.blade.php',
        'resources/views/partials/toast-host.blade.php',
        'resources/views/components/event-*.blade.php',
        'resources/views/components/entrant-card.blade.php',
        'resources/views/components/tournament-bracket.blade.php',
        'resources/views/components/draw-veil.blade.php',
        'resources/views/components/belt-chip.blade.php',
        'resources/views/components/qr-code.blade.php',
        'resources/views/components/media-lightbox.blade.php',
        'resources/views/components/confirm-dialog.blade.php',
        'resources/views/components/country-dropdown.blade.php',
        'resources/views/components/country-code-dropdown.blade.php',
        'resources/views/components/select-menu.blade.php',
        'resources/views/components/date-picker.blade.php',
        'app/Media/Bout*.php',
        'app/Http/Controllers/PublicEventController.php',
        'app/Translation/Controllers/*.php',
        'app/Support/BoutStage.php',
    ];

    /**
     * Every translation key the event surface actually asks for.
     *
     * ⚠️ This is what keeps the cost honest. The full English tree is ~9,500
     * strings; an event needs a few hundred of them. Translating the whole tree
     * to answer "the poster is half English" would be ~30× the tokens and ~30×
     * the wait for no visible gain, and most of it would be admin chrome no
     * visitor can reach.
     *
     * Read from the SOURCE rather than maintained by hand, so a key added to
     * the poster tomorrow is picked up without anyone remembering to list it.
     *
     * @return array<int, string>  dotted keys, e.g. "events.public_spots"
     */
    public function eventSurfaceKeys(): array
    {
        $keys = [];

        foreach ($this->eventSurfaceFiles() as $file) {
            $code = (string) file_get_contents($file);

                /*
                 * `__('x.y')`, `@lang('x.y')`, `trans_choice('x.y')` and
                 * `trans('x.y')`, single or double quoted. A key built at
                 * runtime — "personal.official_role_".$role — cannot be seen
                 * this way; those are caught by the prefix sweep below.
                 */
            if (preg_match_all('/(?:__|@lang|trans|trans_choice)\(\s*[\x27"]([a-z0-9_:-]+\.[a-zA-Z0-9_.:-]+)[\x27"]/', $code, $m)) {
                foreach ($m[1] as $key) {
                    $keys[$key] = true;
                }
            }

                /*
                 * Keys assembled at runtime from a prefix — the officials'
                 * role labels, an event type's own action names. Take the whole
                 * family: a handful of extra strings costs nothing next to a
                 * label that silently stays English on the page.
                 */
            if (preg_match_all('/[\x27"]([a-z0-9_:-]+\.[a-zA-Z0-9_.:-]*_)[\x27"]\s*\./', $code, $m)) {
                foreach ($m[1] as $prefix) {
                    $keys['prefix:'.$prefix] = true;
                }
            }
        }

        return array_keys($keys);
    }

    /**
     * Every file that makes up the event surface.
     *
     * ⚠️ Directories are walked RECURSIVELY, by hand.
     *
     * This used a `**` glob, which reads like a recursive match and is not
     * one: PHP's `glob()` has no globstar, so `entry/**\/*.blade.php` matched
     * 9 of the 25 blade files under `entry/` and silently skipped the rest.
     * The gallery, the participants list, the entry form and the install
     * prompt were all in the missing sixteen — which is exactly why French and
     * Spanish came back with those screens still in English while every other
     * screen was translated. A silent partial match is the worst kind of bug
     * for a job whose whole purpose is finding every string.
     *
     * @return array<int, string>
     */
    private function eventSurfaceFiles(): array
    {
        $files = [];

        foreach (self::EVENT_SURFACE_DIRS as $dir) {
            $path = base_path($dir);

            if (! is_dir($path)) {
                continue;
            }

            $walker = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($walker as $file) {
                // `resources/` inside a module holds views and lang files; the
                // views are wanted, the lang files are the OUTPUT of this and
                // scanning them would be circular.
                if ($file->isFile() && $file->getExtension() === 'php'
                    && ! str_contains($file->getPathname(), '/resources/lang/')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        foreach (self::EVENT_SURFACE_FILES as $pattern) {
            foreach (glob(base_path($pattern), GLOB_BRACE) ?: [] as $file) {
                if (is_file($file)) {
                    $files[] = $file;
                }
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * One file's strings, narrowed to the keys the event surface uses.
     *
     * @param  array<string,string>  $strings
     * @param  array<int,string>  $wanted  from eventSurfaceKeys()
     * @return array<string,string>
     */
    public function narrowToEvent(array $strings, array $wanted, string $fileId): array
    {
        $group = str_contains($fileId, '::') ? $fileId : $fileId;

        $exact = [];
        $prefixes = [];

        foreach ($wanted as $key) {
            if (str_starts_with($key, 'prefix:')) {
                $prefixes[] = substr($key, 7);

                continue;
            }

            $exact[$key] = true;
        }

        $out = [];

        foreach ($strings as $key => $value) {
            $full = $group.'.'.$key;

            if (isset($exact[$full])) {
                $out[$key] = $value;

                continue;
            }

            foreach ($prefixes as $prefix) {
                if (str_starts_with($full, $prefix)) {
                    $out[$key] = $value;

                    break;
                }
            }
        }

        return $out;
    }

    /** The files in one tier, or all of them. */
    public function filesFor(string $tier, ?string $locale = null): array
    {
        $all = $this->files($locale);

        if ($tier === 'all') {
            return $all;
        }

        $groups = match ($tier) {
            // `event` looks at the same files as `visitor`; what makes it small
            // is the per-KEY narrowing the command applies on top.
            'event', 'visitor' => self::VISITOR,
            'member' => self::MEMBER,
            'staff' => self::STAFF,
            default => [],
        };

        // Module strings ride with the visitor tier: an event package's own
        // labels are on the poster, and there are only a few hundred of them.
        return array_filter(
            $all,
            fn ($f, $id) => in_array($f['group'], $groups, true)
                || (in_array($tier, ['event', 'visitor'], true) && $f['namespace'] !== null),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * A lang file's strings, flattened to dotted keys.
     *
     * Every file on this platform is depth 1 today, but the flatten is
     * recursive anyway: a nested array added later would otherwise be silently
     * skipped, and a silently untranslated string is the thing this whole
     * exercise exists to remove.
     *
     * @return array<string, string>
     */
    public function strings(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $data = require $path;

        return is_array($data) ? $this->flatten($data) : [];
    }

    /** @return array<string, string> */
    private function flatten(array $data, string $prefix = ''): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            $dotted = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $out += $this->flatten($value, $dotted);
            } elseif (is_string($value)) {
                $out[$dotted] = $value;
            }
        }

        return $out;
    }

    private function addNamespaced(array &$out, string $path, ?string $locale): void
    {
        $group = basename($path, '.php');

        // app/Scoreboard/resources/lang/en/messages.php → scoreboard
        // app/Events/Sports/Karate/Tournament/resources/lang/en/… → the package
        $moduleDir = dirname($path, 4);
        $namespace = $this->namespaceFor($moduleDir);

        if ($namespace === null) {
            return;
        }

        $id = $namespace.'::'.$group;

        $out[$id] = [
            'group' => $group,
            'namespace' => $namespace,
            'source' => $path,
            'target' => $locale ? $moduleDir.'/resources/lang/'.$locale.'/'.$group.'.php' : '',
        ];
    }

    /**
     * The view/lang namespace a folder is bound under.
     *
     * Read from the registries rather than guessed from the path, because the
     * namespace is the module's own declaration (`Module::key()`) and an event
     * package's key is whatever `config/event_types.php` says it is.
     */
    private function namespaceFor(string $dir): ?string
    {
        static $map = null;

        if ($map === null) {
            $map = [];

            /*
             * Asked of the SAME registries that bind these folders at boot, not
             * guessed from the path. A module's namespace is its own
             * `Module::key()`, and an event package's is `event-<key>` /
             * `sport-<sport>` decided by EventPackageServiceProvider — mirrored
             * here so a package that moves, or a sport that is renamed, cannot
             * leave its strings quietly untranslatable.
             */
            foreach (app(\App\Support\Modules\ModuleRegistry::class)->all() as $module) {
                $map[dirname((new \ReflectionClass($module))->getFileName())] = $module->key();
            }

            $registry = app(\App\Events\EventTypeRegistry::class);
            $packages = $registry->all();
            $packages[$registry->fallback()->key()] ??= $registry->fallback();

            foreach ($packages as $key => $type) {
                $path = dirname((new \ReflectionClass($type))->getFileName());
                $map[$path] = 'event-'.$key;

                // A sport folder holds what every event type of that sport
                // shares — its weight tables, its belt ladder, its vocabulary.
                if (str_contains($path, '/Events/Sports/')) {
                    $sportDir = dirname($path);
                    $sport = $this->sportKeyFor($sportDir);

                    if ($sport !== null) {
                        $map[$sportDir] = 'sport-'.$sport;
                    }
                }
            }
        }

        return $map[$dir] ?? null;
    }

    /** The `config/event_schema.php` key whose folder this is. */
    /**
     * The sport namespace for a sport folder.
     *
     * ⚠️ Mirrors App\Events\EventPackageServiceProvider::sportOf() EXACTLY —
     * `strtolower(FolderName)` — because that is what actually binds the
     * namespace at boot.
     *
     * Deriving it from `config/event_schema.php` keys instead looked more
     * principled and was wrong: the schema calls the sport `bjj` while the
     * folder is `BrazilianJiuJitsu`, so the live namespace is
     * `sport-brazilianjiujitsu`, nothing was ever catalogued under it, and
     * every Brazilian Jiu-Jitsu sport string stayed English in every language
     * while the catalogue reported everything complete.
     */
    private function sportKeyFor(string $sportDir): ?string
    {
        return strtolower(basename($sportDir)) ?: null;
    }
}
