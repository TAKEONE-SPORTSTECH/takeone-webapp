<?php

namespace App\Translation\Services;

use App\Translation\Contracts\TranslatableContent;
use App\Translation\Jobs\TranslateContent;
use App\Translation\Models\TranslationDocument;
use App\Translation\TranslatedDocument;
use App\Translation\Translations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The read path, and the only door the rest of the platform uses.
 *
 * ⚠️ NOTHING here may throw, block, or slow a page down. A public event poster
 * is the most-hit page on this platform and it is read by strangers on hotel
 * wifi; a translation layer that can 500 it, or hold it open while an API
 * thinks, is a worse outcome than a page in the wrong language. So: one indexed
 * query, no network, no queue wait, and every failure path returns the original
 * text (RULE #1).
 *
 * Since 2026-09-09 the store is ONE JSON document per record holding every
 * language (App\Translation\Models\TranslationDocument), so rendering a page is
 * a single row read. The old per-field tables are untouched and unread — see
 * the migration for what was traded and why.
 */
class Translator
{
    /**
     * Documents already read during THIS request, keyed morph:id:locale.
     *
     * ⚠️ Load-bearing since the model accessors landed (App\Traits\
     * TranslatesAttributes). A page that prints forty division names asks this
     * class forty times for the same event's Chinese; without the memo that is
     * forty cache round-trips, and on the `file` cache driver forty stat+read
     * syscalls, to answer a question whose answer cannot change mid-request.
     *
     * In-request only: it is dropped when the process ends and by `forget()`,
     * so it can never serve a write's stale value back to the same request that
     * made the write.
     *
     * @var array<string, TranslatedDocument>
     */
    private array $memo = [];

    public function __construct(
        private ContentLocales $locales,
        private FieldLanguage $fieldLanguage,
    ) {}

    /**
     * The record's words in the given locale (default: the active one).
     *
     * Cached: a poster is rendered far more often than it is translated, and
     * the cache is dropped the moment anything writes to that record.
     */
    public function for(TranslatableContent&Model $record, ?string $locale = null, bool $useCache = true): TranslatedDocument
    {
        $locale = $this->locales->normalise($locale ?: app()->getLocale());

        if ($useCache) {
            $memo = $this->memoKey($record, $locale ?? '-');

            return $this->memo[$memo] ??= $this->read($record, $locale, true);
        }

        return $this->read($record, $locale, false);
    }

    /** The un-memoised read. `for()` is the door; this is the work. */
    private function read(TranslatableContent&Model $record, ?string $locale, bool $useCache): TranslatedDocument
    {

        // A language we do not serve, or the one it was written in: the
        // original text IS the answer, and no query is needed to know that.
        if ($locale === null) {
            return TranslatedDocument::none(app()->getLocale());
        }

        $source = self::document($record);

        if ($source === []) {
            return TranslatedDocument::none($locale, true);
        }

        /*
         * ⚠️ No whole-document short-circuit on the source language.
         *
         * It used to return early whenever the reader's locale matched the
         * event's `source_locale`, on the reasoning that an event needs no
         * translation into the language it was written in. Real events are not
         * written in one language: event 65 has an English title, an Arabic
         * description and Arabic fee labels, and its column says English. So
         * an English reader was handed the Arabic paragraph raw, and the system
         * was certain it had done the right thing.
         *
         * Now every field is asked about individually (see `required()`), and
         * a monolingual event asked for in its own language falls through to an
         * empty `$values` and behaves exactly as it did before — one extra warm
         * cache read, and the same original text.
         */

        $fields = $this->fields($record, $locale, $useCache);

        if ($fields === null) {
            return TranslatedDocument::none($locale, false);
        }

        $values = [];

        foreach ($fields as $field => $entry) {
            if (! isset($source[$field]) || ! is_array($entry)) {
                continue;   // a field that no longer exists on the record
            }

            $fresh = ($entry['h'] ?? null) === self::hash($source[$field]);

            /*
             * A machine entry is shown only while it matches the text it was
             * made from. A HUMAN entry is shown even when the source has moved
             * on — a person's words do not become wrong because the English
             * moved, and discarding them over a typo fix would be worse than
             * showing a slightly dated sentence. It is flagged for review
             * instead, never dropped.
             */
            if ($fresh || ($entry['o'] ?? '') === TranslationDocument::HUMAN) {
                $value = (string) ($entry['v'] ?? '');

                if (trim($value) !== '') {
                    $values[$field] = $value;
                }
            }
        }

        return new TranslatedDocument($locale, $values === [] && $locale === $record->sourceLocale(), $values);
    }

    /**
     * The fields that genuinely need translating for this reader.
     *
     * ⚠️ THREAD THIS EVERYWHERE a field list is needed. `pending()`,
     * `hasStale()`, `isComplete()` and the job's own readable check all run
     * through it, and they must: if `isComplete()` asked about a field that
     * `required()` excludes, that field could never be satisfied, the language
     * would be marked failed forever, and every visitor would re-queue the same
     * job after the cool-off. That is the one mistake here that costs money
     * rather than quality.
     *
     * @return array<string, string> field => source text
     */
    public function required(TranslatableContent&Model $record, string $locale): array
    {
        $source = self::document($record);

        if (! config('translation.detect_source', true)) {
            return $source;
        }

        $sourceLocale = $record->sourceLocale();

        return array_filter(
            $source,
            fn ($text) => $this->fieldLanguage->needsTranslation($text, $sourceLocale, $locale),
        );
    }

    /**
     * Make sure this language exists for this record, queueing the work if it
     * does not, and say where it stands.
     *
     * Returns: 'source' | 'ready' | 'preparing' | 'failed' | 'unavailable'.
     *
     * ⚠️ The expensive door, reachable by strangers, so it is guarded three
     * ways: the locale must be one we serve, a lock stops a hundred
     * simultaneous visitors queueing a hundred identical jobs, and the caller
     * is rate-limited on top (see the controller).
     */
    public function ensure(TranslatableContent&Model $record, string $locale): string
    {
        $locale = $this->locales->normalise($locale);

        if ($locale === null) {
            return 'unavailable';
        }

        // Written in this language AND nothing in it is foreign — then there
        // is genuinely nothing to do.
        if ($locale === $record->sourceLocale() && $this->required($record, $locale) === []) {
            return 'source';
        }

        if ($this->isComplete($record, $locale)) {
            return 'ready';
        }

        $entry = $this->entry($record, $locale);
        $stuck = $this->isStuck($entry);

        // Already being worked on. A run "running" for ten minutes is not
        // running (a killed worker), and may be started again — otherwise one
        // crash costs that language forever.
        if (in_array($entry['status'], ['queued', 'running'], true) && ! $stuck) {
            return 'preparing';
        }

        /*
         * A finished run whose remaining gaps are REFUSALS rather than
         * omissions. Some fields cannot be translated safely and are left in
         * the source language on purpose (TranslationAgent::numbersSurvived);
         * the same model on the same text refuses them again, so re-running
         * costs money and changes nothing. Only a STALE field — the organiser
         * edited the source — is worth another generation.
         */
        if ($entry['status'] === 'ready' && ! $this->hasStale($record, $locale)) {
            return 'ready';
        }

        /*
         * A failure is not retried on every page view. A provider that is down,
         * a wrong key or a model that refuses would otherwise be re-attempted
         * by every visitor, turning one outage into a bill. Retried after a
         * cool-off, or immediately when an organiser asks.
         */
        if ($entry['status'] === 'failed' && ! $this->olderThan($entry, 60)) {
            return 'failed';
        }

        // One dispatch per record+locale per minute, whatever the traffic.
        if (! Cache::lock($this->lockKey($record, $locale), 60)->get()) {
            return 'preparing';
        }

        TranslationDocument::mutate(
            $record->getMorphClass(),
            (int) $record->getKey(),
            fn (array $document) => TranslationDocument::setStatus($document, $locale, 'queued'),
        );

        $this->forget($record, $locale);

        TranslateContent::dispatch($record->getMorphClass(), (int) $record->getKey(), $locale);

        return 'preparing';
    }

    /** Where a language stands, without starting anything. */
    public function status(TranslatableContent&Model $record, string $locale): string
    {
        $locale = $this->locales->normalise($locale);

        if ($locale === null) {
            return 'unavailable';
        }

        if ($locale === $record->sourceLocale() && $this->required($record, $locale) === []) {
            return 'source';
        }

        if ($this->isComplete($record, $locale)) {
            return 'ready';
        }

        $entry = $this->entry($record, $locale);

        return match ($entry['status']) {
            'ready' => 'ready',     // ready, though the source may have moved
            'failed' => 'failed',
            'missing' => 'missing',
            default => $this->isStuck($entry) ? 'missing' : 'preparing',
        };
    }

    /**
     * Every field either translated-and-fresh, or written by a human.
     *
     * Deliberately strict: a poster showing four Portuguese paragraphs and one
     * English one looks broken, and the fix (translate the one field) is cheap.
     */
    public function isComplete(TranslatableContent&Model $record, string $locale, bool $useCache = true): bool
    {
        // What this reader NEEDS, not everything the record holds — a field
        // already in their language is complete by being left alone.
        $source = $this->required($record, $locale);

        if ($source === []) {
            return true;
        }

        $have = $this->for($record, $locale, $useCache)->all();

        foreach (array_keys($source) as $field) {
            if (! isset($have[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * The fields still needing a machine translation: missing or stale, minus
     * anything a human wrote.
     *
     * @return array<string, string> field => source text
     */
    public function pending(TranslatableContent&Model $record, string $locale): array
    {
        $source = $this->required($record, $locale);
        $out = $source;

        foreach ($this->fields($record, $locale, useCache: false) ?? [] as $field => $entry) {
            if (! isset($out[$field]) || ! is_array($entry)) {
                continue;
            }

            // A person's translation is never re-done, fresh or not.
            if (($entry['o'] ?? '') === TranslationDocument::HUMAN
                || ($entry['h'] ?? null) === self::hash($source[$field])) {
                unset($out[$field]);
            }
        }

        return $out;
    }

    /**
     * Has any field's source text changed since a MACHINE translated it?
     *
     * The question separating "as done as it will get" from "the organiser
     * edited the event and the translation now describes something else". A
     * human entry going stale is a review prompt for a person, not work for a
     * machine.
     */
    public function hasStale(TranslatableContent&Model $record, string $locale): bool
    {
        $source = $this->required($record, $locale);

        foreach ($this->fields($record, $locale, useCache: false) ?? [] as $field => $entry) {
            if (! isset($source[$field]) || ! is_array($entry)) {
                continue;
            }

            if (($entry['o'] ?? '') !== TranslationDocument::HUMAN
                && ($entry['h'] ?? null) !== self::hash($source[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Everything stored about one language: status, provenance, fields.
     *
     * @return array<string, mixed>
     */
    public function entry(Model $record, string $locale): array
    {
        return TranslationDocument::entry($this->documentFor($record), $locale);
    }

    /** The whole stored document — every language. For the review screen. */
    public function documentFor(Model $record): array
    {
        try {
            return TranslationDocument::forRecord($record->getMorphClass(), (int) $record->getKey());
        } catch (\Throwable $e) {
            Log::warning('translation.read_failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * One language's stored fields, or null when the store cannot be read.
     *
     * ⚠️ `$useCache = false` is not an optimisation, it is a guard. The queue
     * worker on this box runs as ROOT while the web app runs as www-data, and
     * CACHE_STORE is `file`: a cache entry WRITTEN by a job is a root-owned
     * file that www-data cannot overwrite afterwards — the exact failure that
     * has taken this site down before. So jobs read straight from the database
     * and only ever DELETE cache entries, which is safe from either user.
     *
     * @return array<string, mixed>|null
     */
    private function fields(Model $record, string $locale, bool $useCache): ?array
    {
        try {
            $read = fn () => TranslationDocument::entry(
                TranslationDocument::forRecord($record->getMorphClass(), (int) $record->getKey()),
                $locale,
            )['fields'];

            return $useCache
                ? Cache::remember($this->cacheKey($record, $locale), now()->addHours(6), $read)
                : $read();
        } catch (\Throwable $e) {
            // A missing table (deploy order), a locked SQLite write, a cache
            // backend having a bad day: the page still renders.
            Log::warning('translation.read_failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** A language marked in-flight long after anything could still be running. */
    private function isStuck(array $entry): bool
    {
        return in_array($entry['status'] ?? '', ['queued', 'running'], true)
            && $this->olderThan($entry, 10);
    }

    private function olderThan(array $entry, int $minutes): bool
    {
        $at = $entry['updated_at'] ?? null;

        if (! is_string($at) || $at === '') {
            return true;    // no timestamp: treat as old rather than wedged forever
        }

        return strtotime($at) < now()->subMinutes($minutes)->getTimestamp();
    }

    /**
     * The record's non-empty words. The single definition of "what gets
     * translated", used by the read path, the writer and the agent alike, so
     * the three can never disagree about which fields exist.
     *
     * @return array<string, string>
     */
    public static function document(TranslatableContent $record): array
    {
        $out = [];

        /*
         * ⚠️ SOURCE SCOPE. `translatableDocument()` reads the very columns that
         * App\Traits\TranslatesAttributes now translates on read. Asked
         * normally, this method would hand the translator the CHINESE it
         * previously wrote as the thing to translate into Chinese — the source
         * text quietly replaced by a translation of itself, and the hash that
         * decides freshness computed over the wrong string.
         *
         * One scope here covers every caller: the read path, the queued job,
         * the review screen, the MCP tools and the validator all come through
         * this method, which is the reason it was made the single definition in
         * the first place.
         */
        $fields = Translations::source(fn () => $record->translatableDocument());

        foreach ($fields as $field => $text) {
            if (! is_string($text)) {
                continue;
            }

            $text = trim($text);

            /*
             * Skip what is not worth a translator's time or a token: empty
             * fields, and values with no letter in them at all — a "—", a
             * "2026", a "10 BHD". Those read the same in every language, and
             * sending one invites a model to "helpfully" rewrite a number.
             */
            if ($text === '' || ! preg_match('/\p{L}/u', $text)) {
                continue;
            }

            $out[(string) $field] = $text;
        }

        return $out;
    }

    /** The hash that decides whether a stored translation still matches. */
    public static function hash(string $text): string
    {
        return hash('sha256', trim($text));
    }

    /**
     * Forget the cached read. Called by anything that writes — without it the
     * page keeps serving the old answer for up to six hours, which on a first
     * translation means the visitor who waited for it does not see it.
     */
    public function forget(Model $record, ?string $locale = null): void
    {
        foreach ($locale ? [$locale] : array_keys($this->locales->all()) as $one) {
            Cache::forget($this->cacheKey($record, $one));
            unset($this->memo[$this->memoKey($record, $one)]);
        }

        // A locale-less forget clears the whole request memo as well: the
        // caller is saying "everything I knew about this record is stale", and
        // a memo entry for a locale that is no longer in the config list would
        // otherwise outlive it.
        if ($locale === null) {
            $prefix = $record->getMorphClass().':'.$record->getKey().':';

            foreach (array_keys($this->memo) as $key) {
                if (str_starts_with($key, $prefix)) {
                    unset($this->memo[$key]);
                }
            }
        }
    }

    private function memoKey(Model $record, string $locale): string
    {
        return $record->getMorphClass().':'.$record->getKey().':'.$locale;
    }

    private function cacheKey(Model $record, string $locale): string
    {
        return 'tr:'.$record->getMorphClass().':'.$record->getKey().':'.$locale;
    }

    private function lockKey(Model $record, string $locale): string
    {
        return 'tr-lock:'.$record->getMorphClass().':'.$record->getKey().':'.$locale;
    }
}
