<?php

namespace App\Traits;

use App\Translation\Translations;
use Illuminate\Database\Eloquent\Model;

/**
 * The organiser's words, in the reader's language, without anybody remembering
 * to ask.
 *
 * ── Why this is a trait on the MODEL and not a call in the view ──────────────
 *
 * Before this existed, content translation was resolved in exactly two places
 * platform-wide: `App\Events\Support\PublicEvent::payload()` and one action of
 * `PersonalEventController`. Every other surface read the column. The result
 * was a page that was Chinese where somebody had remembered and Arabic where
 * nobody had — the entry form printing raw Arabic fee names inside a Chinese
 * session, while the poster three taps away printed the same three fee names
 * in Chinese, from the same stored translation. One record, two answers, decided
 * by which code path the reader happened to walk down.
 *
 * A rule that has to be remembered at every call site is not a rule, it is a
 * lottery. So the resolution moves to the one place every call site already
 * goes through: reading the attribute.
 *
 *     {{ $option->label }}     ← Chinese, in a Chinese session. Always.
 *     {{ $event->title }}      ← same.
 *     $event->toJson()         ← same. Serialisers were leaking too.
 *
 * ── The escape hatch, and why it is narrow ───────────────────────────────────
 *
 * Three callers legitimately need the ORGANISER'S OWN WORDS rather than the
 * reader's:
 *
 *   1. An edit form. An organiser who picked Chinese to check their poster and
 *      then opened the edit screen must not be shown the machine's Chinese in
 *      the box, because saving it would overwrite their Arabic with a
 *      translation of itself — the source destroyed by the act of looking at
 *      it. This is the single most dangerous thing in this file.
 *   2. The translator itself. `Translator::document()` asks a record what its
 *      words are in order to translate them; if that read were translated it
 *      would translate its own output, for ever.
 *   3. Anything matching, hashing or de-duplicating on the text.
 *
 * All three say so out loud:
 *
 *     Translations::source(fn () => $event->title);   // the organiser's Arabic
 *     $event->sourceAttribute('title');               // the same, one field
 *
 * There is deliberately no third way. A caller that wants source text has to
 * name itself, which is what makes the ~600 that do not want it safe by default.
 *
 * ── What a model declares ────────────────────────────────────────────────────
 *
 *     protected function translatedAttributes(): array
 *     {
 *         return ['title' => 'title', 'description' => 'about'];
 *     }
 *
 * The key is the COLUMN; the value is the field key in the translation
 * document. They differ often enough (`description` is stored as `about`) that
 * conflating them would have been a bug waiting to happen.
 *
 * A row whose words are stored on its PARENT's document — a division, a fee
 * line, a checklist item, all of which travel inside the event's one document
 * so the agent sees them together — says so instead:
 *
 *     protected function translationOwner(): ?Model  { return $this->event; }
 *     protected function translatedAttributes(): array
 *     {
 *         return ['label' => 'fees.'.$this->getKey()];
 *     }
 *
 * ── What it never does ───────────────────────────────────────────────────────
 *
 * It never queries, never blocks and never throws: `Translations::of()` is a
 * memoised read that returns the original text for a missing translation, a
 * language we do not serve, an unreachable store or a record nobody has ever
 * translated. With the whole translation module removed, every model carrying
 * this trait renders exactly what it rendered before (RULE #1).
 */
trait TranslatesAttributes
{
    /**
     * Column => document field key.
     *
     * @return array<string, string>
     */
    abstract protected function translatedAttributes(): array;

    /**
     * Owners already resolved during this request, keyed `Class:id`.
     *
     * @var array<string, ?Model>
     */
    private static array $ownerMemo = [];

    /**
     * The record whose translation document holds these words.
     *
     * `null` means "this record's own". Overridden by rows that travel inside a
     * parent's document.
     */
    protected function translationOwner(): ?Model
    {
        return $this;
    }

    /**
     * A parent record for a row whose words live on its parent's document.
     *
     * Prefers the loaded relation and falls back to ONE query per parent per
     * request, memoised. The query matters: the common path loads
     * `$event->categories` with `chaperone()`, so the relation is already
     * there and nothing is fetched — but a row loaded on its own
     * (`EventCategory::find()`, a queued job, an MCP tool) would otherwise
     * silently fall back to the source language, which is the exact class of
     * "translated here, not there" bug this whole trait exists to end. One
     * memoised read per event is a cheaper price than a page that is Chinese
     * in one column and Arabic in the next.
     *
     * @param  class-string<Model>  $class
     */
    protected function translationOwnerVia(string $relation, string $class, mixed $foreignKey): ?Model
    {
        if ($this->relationLoaded($relation)) {
            $loaded = $this->getRelation($relation);

            return $loaded instanceof Model ? $loaded : null;
        }

        if (empty($foreignKey)) {
            return null;
        }

        $memo = $class.':'.$foreignKey;

        if (array_key_exists($memo, self::$ownerMemo)) {
            return self::$ownerMemo[$memo];
        }

        try {
            return self::$ownerMemo[$memo] = $class::query()->find($foreignKey);
        } catch (\Throwable $e) {
            return self::$ownerMemo[$memo] = null;
        }
    }

    /**
     * One attribute as the organiser wrote it, whatever language is active.
     *
     * The named, greppable way to ask. Use it in edit forms and anywhere the
     * text is being compared or hashed rather than read.
     */
    public function sourceAttribute(string $key): mixed
    {
        return Translations::source(fn () => $this->getAttribute($key));
    }

    /**
     * ⚠️ READ A TRANSLATABLE COLUMN WITHOUT THE `->column` MAGIC. Required
     * inside `translatableDocument()` and `translationContext()`.
     *
     * PHP does not re-enter `__get()` for a property whose `__get()` is already
     * on the stack — it falls through to the real (non-existent) property and
     * raises "Undefined property". Eloquent keeps every column in an array, not
     * a property, so on a model that translates on read this bites exactly
     * once, and invisibly:
     *
     *     {{ $option->label }}                      → __get('label') opens
     *       → translate → Translations::of($event)
     *         → $event->translatableDocument()
     *           → $option->label                    ← SAME object, SAME key.
     *                                                 No __get. Notice. Throw.
     *
     * The throw lands in the catch below, which returns the original text — so
     * the page renders the organiser's Arabic, the translation is never used,
     * and NOTHING says so. Every fee label and every division name on this
     * platform behaved that way for the first hour this trait existed.
     *
     * An explicit method call has no such guard, which is the whole fix:
     * `$option->translationSource('label')` works where `$option->label` cannot.
     */
    public function translationSource(string $key): string
    {
        return (string) Translations::source(fn () => $this->getAttribute($key));
    }

    /**
     * ⚠️ The single point every attribute read goes through.
     *
     * Kept as narrow as it can be: anything that is not a declared, non-empty
     * string column falls straight through to Eloquent, so relations, casts,
     * dates, integers and every other attribute behave exactly as before.
     */
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if (Translations::readingSource() || ! is_string($value) || trim($value) === '') {
            return $value;
        }

        if (! config('translation.translate_attributes', true)) {
            return $value;
        }

        return $this->translateAttributeValue((string) $key, $value);
    }

    /**
     * ⚠️ Serialisation is a separate door, and it was leaking.
     *
     * Eloquent's `attributesToArray()` does NOT go through `getAttribute()` for
     * a plain column — it reads the attribute bag and applies casts. So a model
     * handed to `response()->json()`, dropped into a Blade `@js()`, or pushed
     * over MQTT would have serialised the source language while the very same
     * page printed the translation. Every AJAX patch on the event console did
     * exactly that.
     */
    public function attributesToArray()
    {
        $array = parent::attributesToArray();

        if (Translations::readingSource() || ! config('translation.translate_attributes', true)) {
            return $array;
        }

        foreach (array_keys($this->translatedAttributes()) as $column) {
            if (isset($array[$column]) && is_string($array[$column]) && trim($array[$column]) !== '') {
                $array[$column] = $this->translateAttributeValue($column, $array[$column]);
            }
        }

        return $array;
    }

    /**
     * The lookup. Cheap enough to sit on an attribute read: the document for
     * one record and one locale is read once per request and memoised inside the
     * translation module for the life of the request.
     */
    private function translateAttributeValue(string $column, string $original): string
    {
        $field = $this->translatedAttributes()[$column] ?? null;

        if ($field === null) {
            return $original;
        }

        $owner = $this->translationOwner();

        // A child row whose parent is not loaded and cannot be: nothing to look
        // the words up in, so the organiser's own text is the honest answer.
        // Never a query — an attribute read must not be able to hit the
        // database, or printing a list of forty divisions becomes forty
        // selects.
        if (! $owner instanceof Model || ! $owner->exists) {
            return $original;
        }

        /*
         * ⚠️ Source scope around the whole lookup — NOT optional.
         *
         * `Translations::of()` asks the owner for its `translatableDocument()`,
         * which reads these same columns. Without the scope the first
         * translated read would ask for a translation in order to answer what
         * to translate, and recurse until the stack ended.
         *
         * The counter lives on `Translations`, not on this trait: a static
         * property declared IN a trait gets one copy PER USING CLASS, so a
         * scope opened while reading a ClubEvent would not have covered the
         * EventFeeOption rows read inside it — which is every case that matters.
         */
        try {
            return Translations::source(
                fn () => (string) Translations::of($owner)->get($field, $original),
            );
        } catch (\Throwable $e) {
            /*
             * RULE #1: a translation layer may never take a page down, so the
             * organiser's own words are always a correct answer.
             *
             * But it is NEVER a silent one. This catch swallowed the `__get`
             * re-entrancy bug described on `translationSource()` above for
             * every fee label and division name on the platform, and the only
             * symptom was a page in the wrong language. A fallback that nobody
             * can see is indistinguishable from a feature that does not work.
             */
            \Illuminate\Support\Facades\Log::warning('translation.attribute_failed', [
                'model' => static::class,
                'id' => $this->getKey(),
                'column' => $column,
                'field' => $field,
                'locale' => app()->getLocale(),
                'error' => $e->getMessage(),
            ]);

            return $original;
        }
    }
}
