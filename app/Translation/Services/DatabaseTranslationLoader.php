<?php

namespace App\Translation\Services;

use App\Translation\Models\InterfaceTranslation;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Laravel's file loader, with the database laid over the top.
 *
 * ── An OVERLAY, never a replacement ─────────────────────────────────────────
 *
 * Every `load()` asks the file loader first and then merges any stored rows
 * over the result. Three consequences, and all three are the point:
 *
 *   · An EMPTY table renders exactly what the files render. Installing this
 *     changes nothing until something is imported.
 *   · `lang/en` stays the source of truth for English and needs no rows.
 *   · A database that is unreachable, a table that does not exist yet, a query
 *     that fails — every one of them falls back to the files rather than to a
 *     blank interface. A translation layer may never take a page down
 *     (RULE #1).
 *
 * ── Why it does not cost a query per page ───────────────────────────────────
 *
 * Laravel asks a loader once per (locale, group, namespace) per request and
 * keeps the result. On top of that this caches each file's rows for an hour, so
 * the steady state is a cache read, not a query — and a page that renders
 * strings from six lang files does six cache reads, not six hundred.
 *
 * ⚠️ The cache is keyed by locale AND file. Writing a string must forget its
 * own file's key (`forget()`), or the correction is invisible for an hour and
 * the person who made it concludes the feature is broken.
 */
class DatabaseTranslationLoader implements Loader
{
    /** How long a file's rows stay cached. */
    private const TTL_MINUTES = 60;

    /**
     * Files already read this request, so a repeated ask is free.
     *
     * ⚠️ STATIC on purpose. `forget()` is static — it is called from the store
     * after a write, which has no handle on the loader instance — so a
     * per-instance memo could not be cleared by it. The result was that saving
     * a correction cleared the cache, re-read the file, and still served the
     * old string for the rest of that request: the admin who had just typed the
     * fix would be shown their old text back and conclude nothing had happened.
     */
    private static array $memo = [];

    public function __construct(private Loader $files) {}

    /**
     * @param  string  $locale
     * @param  string  $group
     * @param  string|null  $namespace
     * @return array<string, mixed>
     */
    public function load($locale, $group, $namespace = null)
    {
        $fromFiles = $this->files->load($locale, $group, $namespace);

        if (! config('translation.database_strings', true)) {
            return $fromFiles;
        }

        /*
         * ⚠️ Laravel says `'*'` where this table says NULL.
         *
         * `Translator::parseKey('events.title')` returns the namespace `'*'`,
         * not null — see FileLoader::load(), which treats the two the same. A
         * loader that takes `'*'` literally queries for a namespace called
         * "*", finds nothing, and silently returns the file's value for every
         * root string: the whole overlay does nothing, on exactly the strings
         * that make up most of the interface. Cost an hour on 2026-09-09; the
         * rows were right, the query was asking the wrong question.
         *
         * Group `'*'` with namespace `'*'` is Laravel's JSON-translation path,
         * which this table does not store at all.
         */
        if ($group === '*') {
            return $fromFiles;
        }

        $stored = $this->stored($locale, $group, $namespace === '*' ? null : $namespace);

        if ($stored === []) {
            return $fromFiles;
        }

        /*
         * ⚠️ `array_replace_recursive`, not `array_merge`. A lang file may nest
         * (`['nav' => ['home' => '…']]`) and the stored key is dotted, so the
         * rows are expanded back into that shape before merging — otherwise a
         * stored `nav.home` would appear as a literal top-level key and the
         * real one would still resolve from the file.
         */
        return array_replace_recursive($fromFiles, $stored);
    }

    /**
     * One file's rows for one language, expanded from dotted keys.
     *
     * @return array<string, mixed>
     */
    private function stored(string $locale, string $group, ?string $namespace): array
    {
        $key = $locale.'|'.($namespace ?? '').'|'.$group;

        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }

        try {
            $rows = Cache::remember(
                'itr:'.$key,
                now()->addMinutes(self::TTL_MINUTES),
                fn () => InterfaceTranslation::query()
                    ->where('locale', $locale)
                    ->where('group', $group)
                    ->when($namespace === null,
                        fn ($q) => $q->whereNull('namespace'),
                        fn ($q) => $q->where('namespace', $namespace))
                    ->pluck('value', 'key')
                    ->all(),
            );
        } catch (\Throwable $e) {
            /*
             * No table yet (a deploy that has not migrated), a locked SQLite
             * write, a cache backend having a bad day. The files are a complete
             * answer on their own, so this is a downgrade and never a failure.
             */
            return self::$memo[$key] = [];
        }

        $out = [];

        foreach ($rows as $dotted => $value) {
            data_set($out, $dotted, $value);
        }

        return self::$memo[$key] = $out;
    }

    /** Drop a file's cached rows. Called by anything that writes one. */
    public static function forget(string $locale, ?string $namespace, string $group): void
    {
        $key = $locale.'|'.($namespace ?? '').'|'.$group;

        Cache::forget('itr:'.$key);
        unset(self::$memo[$key]);

        /*
         * ⚠️ And Laravel's OWN copy, which is the one a page actually reads.
         *
         * `Translator` keeps every group it has loaded in `$this->loaded` and
         * never asks the loader again. So clearing the cache and the memo left
         * the third copy untouched: a correction saved in a request that had
         * already rendered that string went on serving the old text for the
         * rest of it — the admin would type the fix, save, and be shown their
         * old words back.
         *
         * `setLoaded([])` is blunt (every group reloads on next use) and that
         * is fine: this runs on a WRITE, which is rare, never on a render.
         */
        try {
            $translator = app('translator');

            if ($translator instanceof \Illuminate\Translation\Translator) {
                $translator->setLoaded([]);
            }
        } catch (\Throwable $e) {
            // No translator resolved yet (a console boot): nothing to clear.
        }
    }

    /**
     * Forget everything this process has read.
     *
     * ⚠️ Needed because the memo is static and therefore has no request
     * boundary of its own. Under php-fpm that is harmless — the process ends
     * with the request — but a test suite runs hundreds of "requests" in one
     * process, and `RefreshDatabase` rolls the rows back without the memo ever
     * hearing about it. One test's correction was then still being served to
     * the next, which is how this was found (2026-09-09).
     *
     * Called from Tests\TestCase::setUp(). A long-lived worker (Octane, a queue
     * process rendering mail) wants it for the same reason.
     */
    public static function flush(): void
    {
        self::$memo = [];
    }

    /** Drop every cached file for a language. */
    public static function forgetLocale(string $locale): void
    {
        try {
            $files = DB::table('interface_translations')
                ->where('locale', $locale)
                ->select('namespace', 'group')
                ->distinct()
                ->get();

            foreach ($files as $file) {
                self::forget($locale, $file->namespace, $file->group);
            }
        } catch (\Throwable $e) {
            // Nothing to forget is not a failure.
        }

        foreach (array_keys(self::$memo) as $key) {
            if (str_starts_with($key, $locale.'|')) {
                unset(self::$memo[$key]);
            }
        }
    }

    // ── The rest of the contract, handed straight to the file loader ────────

    public function addNamespace($namespace, $hint)
    {
        $this->files->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->files->addJsonPath($path);
    }

    public function namespaces()
    {
        return $this->files->namespaces();
    }
}
