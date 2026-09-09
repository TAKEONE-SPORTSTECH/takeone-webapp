<?php

namespace App\Translation;

use App\Translation\Contracts\TranslatableContent;
use App\Translation\Models\TranslationDocument;
use App\Translation\Services\ContentLocales;
use App\Translation\Services\ProviderChain;
use App\Translation\Services\Translator;
use Illuminate\Database\Eloquent\Model;

/**
 * The module's front door — the ONE class the rest of the platform calls.
 *
 * Everything real lives in `Services/`, which is private to this module and
 * enforced as such by tests/Feature/Modules/ModuleBoundaryTest.php. That test
 * is not bureaucracy: it is what stops "the event page needs one translated
 * string" from becoming twelve files across five verticals reaching into a
 * translator's internals, which is exactly how a boundary quietly stops
 * existing. `App\Scoreboard\Fleet` is the precedent.
 *
 * So this is deliberately thin, and deliberately small. It exposes the four
 * things another vertical can legitimately want:
 *
 *   of()        read a record's words in a language   — the page render path
 *   ensure()    make sure a language exists           — costs money, guarded
 *   status()    where a language stands               — starts nothing
 *   document()  what counts as this record's words    — for review screens
 *
 * Anything else — the agent, the prompt, the job, the hashing, how a refusal is
 * decided — is this module's business and stays behind the door.
 */
class Translations
{
    /**
     * Depth of the current source-reading scope — see `source()`.
     *
     * Lives here, on the front door, rather than on App\Traits\
     * TranslatesAttributes, because a static property declared inside a TRAIT
     * gets one copy per using class. A scope opened while rendering a
     * ClubEvent's edit form would then not have covered the EventFeeOption and
     * EventCategory rows read inside it — which is precisely the case the
     * scope exists for.
     */
    private static int $sourceDepth = 0;

    /**
     * Read the ORGANISER'S OWN WORDS inside this callback, whatever language
     * the reader picked.
     *
     * ⚠️ The one thing this protects against is an edit form destroying a
     * source. An organiser who switches their poster to Chinese to check it,
     * then opens the edit screen, must not find the machine's Chinese sitting
     * in the input — because pressing Save would write that Chinese into the
     * column their Arabic used to occupy, and the Arabic is not recoverable.
     * Every form, every API write path and everything that hashes, matches or
     * de-duplicates on the text runs inside this.
     *
     * Re-entrant, and restored on a throw.
     *
     *     $title = Translations::source(fn () => $event->title);
     */
    public static function source(callable $callback): mixed
    {
        self::$sourceDepth++;

        try {
            return $callback();
        } finally {
            self::$sourceDepth = max(0, self::$sourceDepth - 1);
        }
    }

    /** Are we inside a `source()` scope? Asked by the model trait, per read. */
    public static function readingSource(): bool
    {
        return self::$sourceDepth > 0;
    }

    /**
     * A record's words in the reader's language.
     *
     * The whole render path. Never throws, never blocks, never calls a network:
     * with nothing translated it hands back a document whose every accessor
     * returns the original text, so a page that asks for a language nobody has
     * ever requested renders exactly as it did before this module existed.
     *
     *   $tr = Translations::of($event);
     *   'title' => $tr->get('title', $event->title),
     */
    public static function of(TranslatableContent&Model $record, ?string $locale = null): TranslatedDocument
    {
        return app(Translator::class)->for($record, $locale);
    }

    /**
     * Make sure this language exists, starting the work if it does not.
     *
     * ⚠️ THE EXPENSIVE DOOR. Each first call can start a generation at a paid
     * provider, so a page render must never call it — only an explicit,
     * rate-limited act by a person or an organiser's own tooling. Returns
     * 'source' | 'ready' | 'preparing' | 'failed' | 'unavailable'.
     */
    public static function ensure(TranslatableContent&Model $record, string $locale): string
    {
        return app(Translator::class)->ensure($record, $locale);
    }

    /** Where a language stands. Reads only; starts nothing. */
    public static function status(TranslatableContent&Model $record, string $locale): string
    {
        return app(Translator::class)->status($record, $locale);
    }

    /**
     * What counts as this record's words — field key => source text.
     *
     * The single definition, so a review screen, a validator and the translator
     * itself can never disagree about which fields exist.
     *
     * @return array<string, string>
     */
    public static function document(TranslatableContent $record): array
    {
        return Translator::document($record);
    }

    /** The hash that decides whether a stored translation still matches. */
    public static function hash(string $text): string
    {
        return Translator::hash($text);
    }

    /**
     * Record a translation written by a PERSON.
     *
     * Exposed because it is the one write another vertical may legitimately
     * make, and because routing it through here keeps the invariant in one
     * place: a human row is never overwritten by a machine run, and an empty
     * value REMOVES the correction rather than blanking it (a blank human row
     * would out-rank the machine's and leave the field empty forever).
     *
     * Returns false when the field is not one the record publishes — callers
     * must not be able to write arbitrary rows into the store.
     */
    public static function correct(TranslatableContent&Model $record, string $locale, string $field, ?string $value): bool
    {
        $locale = app(ContentLocales::class)->normalise($locale);
        $document = Translator::document($record);

        if ($locale === null || ! isset($document[$field])) {
            return false;
        }

        TranslationDocument::mutate(
            $record->getMorphClass(),
            (int) $record->getKey(),
            fn (array $stored) => TranslationDocument::setHuman(
                $stored,
                $locale,
                $field,
                $value,
                // Stamped with the source as it stands NOW: the person
                // corrected a translation of text they could see.
                Translator::hash($document[$field]),
            ),
        );

        app(Translator::class)->forget($record, $locale);

        return true;
    }

    /**
     * Throw away the MACHINE translation of a language so it will be written
     * again — after switching provider or model, say.
     *
     * A person's corrections survive it, exactly as they survive everything
     * else in this module.
     */
    public static function dropMachine(TranslatableContent&Model $record, string $locale): void
    {
        TranslationDocument::mutate(
            $record->getMorphClass(),
            (int) $record->getKey(),
            function (array $stored) use ($locale) {
                $fields = (array) ($stored[$locale]['fields'] ?? []);

                $stored[$locale]['fields'] = array_filter(
                    $fields,
                    fn ($entry) => ($entry['o'] ?? '') === TranslationDocument::HUMAN,
                );

                return $stored;
            },
        );

        app(Translator::class)->forget($record, $locale);
    }

    /**
     * Remove a language entirely — the machine's words AND the organiser's own.
     *
     * Destructive and deliberate, which is why it is its own method and its own
     * confirmation in the UI rather than a mode of `correct()`.
     */
    public static function remove(TranslatableContent&Model $record, string $locale): void
    {
        TranslationDocument::mutate(
            $record->getMorphClass(),
            (int) $record->getKey(),
            function (array $stored) use ($locale) {
                unset($stored[$locale]);

                return $stored;
            },
        );

        app(Translator::class)->forget($record, $locale);
    }

    /**
     * Everything stored for this record, keyed by locale — status, provenance
     * and the words themselves. What the organiser's review screen reads.
     *
     * @return array<string, mixed>
     */
    public static function stored(TranslatableContent&Model $record): array
    {
        return app(Translator::class)->documentFor($record);
    }

    /**
     * The fields still waiting on a machine translation — missing, or written
     * from source text that has since changed.
     *
     * Public because a review screen has to show "not translated yet" and
     * "needs review" per field, and because it is the honest answer to "would
     * running this again do anything?". A field a PERSON wrote is never in
     * here, whatever happens to the source.
     *
     * @return array<string, string> field => source text
     */
    public static function pending(TranslatableContent&Model $record, string $locale): array
    {
        return app(Translator::class)->pending($record, $locale);
    }

    /**
     * Has the source text moved under an existing machine translation?
     *
     * The question that separates "as done as it will get" from "the organiser
     * edited the event and the translation now describes something else".
     */
    public static function hasStale(TranslatableContent&Model $record, string $locale): bool
    {
        return app(Translator::class)->hasStale($record, $locale);
    }

    /**
     * Drop the cached read for a record's language.
     *
     * Needed by anything that changes what a translation SHOULD say without
     * going through `correct()` — deleting a batch of machine rows before a
     * re-run, for instance. Cheap, and safe to call from anywhere: it only ever
     * removes a cache entry.
     */
    public static function forget(Model $record, ?string $locale = null): void
    {
        app(Translator::class)->forget($record, $locale);
    }

    /**
     * Lay the database over the lang files, so the product's own words are
     * DATA rather than PHP on disk.
     *
     * Called once, from AppServiceProvider::register(), before anything
     * resolves the translator. An overlay: with an empty table every page
     * renders exactly what the files render, and an unreachable database
     * degrades to the files rather than to a blank interface — see
     * Services\DatabaseTranslationLoader for why that matters and what it costs
     * (a cache read per lang file, not a query per string).
     */
    public static function useDatabaseStrings(\Illuminate\Contracts\Foundation\Application $app): void
    {
        if (! config('translation.database_strings', true)) {
            return;
        }

        $app->extend('translation.loader', function ($loader) {
            try {
                return $loader instanceof \Illuminate\Contracts\Translation\Loader
                    ? new \App\Translation\Services\DatabaseTranslationLoader($loader)
                    : $loader;
            } catch (\Throwable $e) {
                return $loader;
            }
        });
    }

    /**
     * Store one interface string, as a PERSON's words.
     *
     * The one write another vertical may make. Human origin, so no machine run
     * will overwrite it — the same invariant the content translations carry,
     * and the reason correcting a word is worth doing.
     */
    public static function correctInterface(string $locale, string $fileId, string $key, ?string $value): bool
    {
        return app(\App\Translation\Services\InterfaceStore::class)
            ->put($locale, $fileId, $key, $value, \App\Translation\Models\InterfaceTranslation::HUMAN);
    }

    /**
     * Where every language stands on the INTERFACE — coverage, and what is
     * still English. Plain data, for a screen.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function interfaceCoverage(?string $tier = null): array
    {
        return app(\App\Translation\Services\InterfaceStore::class)->coverage($tier);
    }

    /**
     * Record an interface string as a MACHINE's work.
     *
     * Returns false when a person's words are already there — the caller has
     * not failed, it has been refused, and that distinction belongs to it.
     * `correctInterface()` is the human-side counterpart.
     */
    public static function recordInterface(
        string $locale,
        string $fileId,
        string $key,
        ?string $value,
        ?string $model = null,
    ): bool {
        return app(\App\Translation\Services\InterfaceStore::class)->put(
            $locale, $fileId, $key, $value,
            \App\Translation\Models\InterfaceTranslation::MACHINE, $model,
        );
    }

    /**
     * Forget every interface string this process has read.
     *
     * Needed wherever a process outlives a request and the rows can change
     * underneath it — a test suite between cases, a long-lived worker. See the
     * note on the loader's static memo.
     */
    public static function flushInterfaceCache(): void
    {
        \App\Translation\Services\DatabaseTranslationLoader::flush();
    }

    /**
     * One language's interface strings, beside the English they came from.
     *
     * Paged: a tier is ~5,500 strings, and neither a screen nor a JSON payload
     * wants all of them at once.
     *
     * @return array<string, mixed>
     */
    public static function interfaceStrings(
        string $locale,
        string $tier = 'event',
        ?string $search = null,
        string $only = 'all',
        int $page = 1,
    ): array {
        return app(\App\Translation\Services\InterfaceStore::class)
            ->strings($locale, $tier, $search, $only, $page);
    }

    /**
     * Start watching for interface strings that fall back to English.
     *
     * Called once, from AppServiceProvider::register(). It lives behind this
     * door rather than in the provider because the machinery — the counter and
     * the translator subclass — is this module's `Services/`, which nothing
     * outside the module may name (tests/Feature/Modules/ModuleBoundaryTest).
     *
     * Wraps the framework's translator rather than replacing it, changes what
     * NO string resolves to, and returns the original untouched if anything at
     * all goes wrong: a diagnostic must never be able to take a page's text
     * away.
     */
    public static function watchInterface(\Illuminate\Contracts\Foundation\Application $app): void
    {
        $app->singleton(\App\Translation\Services\MissingStrings::class);

        $app->extend('translator', function ($translator, $app) {
            try {
                if (! $translator instanceof \Illuminate\Translation\Translator) {
                    return $translator;
                }

                $audit = new \App\Translation\Services\AuditingTranslator(
                    $translator->getLoader(),
                    $translator->getLocale(),
                );

                $audit->setFallback($translator->getFallback());

                /*
                 * The MARKER is a different decision from the COUNT. Counting
                 * is free and always on; wrapping every unresolved string in
                 * ⟦…⟧ is an auditing tool and would be alarming nonsense to a
                 * visitor, so it is limited to a developer machine or an
                 * explicit config switch — never a query parameter a stranger
                 * could add to a public poster.
                 */
                $audit->watch(
                    $app->make(\App\Translation\Services\MissingStrings::class),
                    (bool) config('translation.mark_untranslated', false) || $app->environment('local'),
                );

                return $audit;
            } catch (\Throwable $e) {
                return $translator;
            }
        });
    }

    /**
     * What this request had to resolve from English, as plain data.
     *
     * @return array{total: int, locales: array<string, array{strings: int, worst: array<int, string>}>}
     */
    public static function untranslated(): array
    {
        $log = app(\App\Translation\Services\MissingStrings::class);
        $locales = [];

        foreach ($log->all() as $locale => $keys) {
            $locales[$locale] = ['strings' => count($keys), 'worst' => $log->worst($locale, 15)];
        }

        return ['total' => $log->total(), 'locales' => $locales];
    }

    /**
     * How much of a rendered page came out in the reader's language.
     *
     * The public face of the parity audit, so a test or a report can measure a
     * page without reaching into this module's Services.
     *
     * @return array{rendered: int, resolved: int, fell_back: int, leaked: array<string, string>}
     */
    public static function parity(string $html, string $locale, array $names = []): array
    {
        return app(\App\Translation\Services\ParityAudit::class)->report($html, $locale, $names);
    }

    /**
     * Which model is currently writing the translations, or null for
     * "follow the default provider".
     */
    public static function translatorId(): ?int
    {
        return ProviderChain::primaryId();
    }

    /**
     * Choose the model that writes the translations — Claude, a self-hosted
     * Ollama, anything configured under Admin → AI Providers. Null returns to
     * automatic.
     *
     * Takes effect on the next translation; nothing already written changes,
     * because what is written is stored, not re-derived.
     */
    public static function useTranslator(?int $providerId): void
    {
        ProviderChain::setPrimary($providerId);
    }

    /**
     * The models that would be tried, in order, as plain data for a screen.
     *
     * Shown to the super-admin because the honest thing about a chain is that
     * they should be able to see what happens when the first one is down.
     *
     * @return array<int, array{label: string, model: string, provider_id: ?int}>
     */
    public static function chain(): array
    {
        return array_map(
            fn ($link) => ['label' => $link->label, 'model' => $link->model, 'provider_id' => $link->providerId],
            app(ProviderChain::class)->links(),
        );
    }

    /**
     * The languages this platform can render CONTENT in.
     *
     * Not `config('locales')` — that is the two the INTERFACE speaks. See
     * config/content_locales.php for why the two lists are different things.
     */
    public static function locales(): ContentLocales
    {
        return app(ContentLocales::class);
    }
}
