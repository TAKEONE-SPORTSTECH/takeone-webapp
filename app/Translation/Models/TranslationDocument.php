<?php

namespace App\Translation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

/**
 * One record's translations, in every language, as one JSON document.
 *
 * ⚠️ EVERY WRITE GOES THROUGH `mutate()`. Never `$doc->document = …; save()`.
 *
 * The row carries all sixty languages, so a naive write is a read-modify-write
 * over the whole thing — and two organisers correcting two different languages
 * at the same moment would each save a copy that did not contain the other's
 * work. `mutate()` re-reads inside a transaction and merges, which is the
 * price of the single-record shape and the reason it is safe to pay.
 *
 * @property array<string, mixed> $document
 */
class TranslationDocument extends Model
{
    public const MACHINE = 'machine';

    public const HUMAN = 'human';

    protected $fillable = ['translatable_type', 'translatable_id', 'document'];

    protected $casts = ['document' => 'array'];

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }

    // ── Reading ─────────────────────────────────────────────────────────────

    /** The whole document for one record, or an empty array. */
    public static function forRecord(string $type, int $id): array
    {
        return (array) (static::query()
            ->where('translatable_type', $type)
            ->where('translatable_id', $id)
            ->value('document') ?? []);
    }

    /**
     * One language's entry: status, provenance and fields.
     *
     * @param  array<string, mixed>  $document
     * @return array{status: string, error: ?string, provider: ?string, model: ?string, fields: array<string, array{v: string, h: string, o: string}>}
     */
    public static function entry(array $document, string $locale): array
    {
        $entry = (array) ($document[$locale] ?? []);

        return [
            'status' => (string) ($entry['status'] ?? 'missing'),
            'error' => $entry['error'] ?? null,
            'provider' => $entry['provider'] ?? null,
            'model' => $entry['model'] ?? null,
            'updated_at' => $entry['updated_at'] ?? null,
            'fields' => (array) ($entry['fields'] ?? []),
        ];
    }

    /**
     * Every locale the document holds something for.
     *
     * Keys beginning with `_` are reserved for the document's own metadata and
     * are never languages — without this filter anything doing `array_keys()`
     * would offer "_src" to a reader as a language to pick.
     */
    public static function locales(array $document): array
    {
        return array_values(array_filter(
            array_keys($document),
            fn ($key) => ! str_starts_with((string) $key, '_'),
        ));
    }

    // ── Writing ─────────────────────────────────────────────────────────────

    /**
     * Change the document under a lock, and save the merged result.
     *
     * The callback receives the CURRENT document (re-read inside the
     * transaction, not the copy the caller was holding) and returns the new
     * one. That is what makes concurrent writes to different languages safe.
     *
     * @param  callable(array): array  $mutator
     */
    public static function mutate(string $type, int $id, callable $mutator): array
    {
        return DB::transaction(function () use ($type, $id, $mutator) {
            /*
             * `lockForUpdate` on the row if it exists. On SQLite the
             * transaction itself is the lock and this is a no-op, which is
             * correct; on MySQL — where this platform is heading — it is what
             * actually serialises two organisers.
             */
            $row = static::query()
                ->where('translatable_type', $type)
                ->where('translatable_id', $id)
                ->lockForUpdate()
                ->first();

            $document = $mutator((array) ($row?->document ?? []));

            // An empty document is a deleted document: nothing to keep a row for.
            if ($document === []) {
                $row?->delete();

                return [];
            }

            if ($row) {
                $row->update(['document' => $document]);
            } else {
                static::query()->create([
                    'translatable_type' => $type,
                    'translatable_id' => $id,
                    'document' => $document,
                ]);
            }

            return $document;
        });
    }

    /**
     * Merge translated fields into a language, never over a human's words.
     *
     * @param  array<string, string>  $values  field => translated text
     * @param  array<string, string>  $hashes  field => hash of the source it came from
     * @return array{0: array, 1: int}  the new document, and how many fields were written
     */
    public static function mergeMachine(array $document, string $locale, array $values, array $hashes, ?string $provider, ?string $model): array
    {
        $fields = (array) ($document[$locale]['fields'] ?? []);
        $written = 0;

        foreach ($values as $field => $value) {
            // A person has spoken; the machine does not answer back. This is
            // the module's central invariant and it lives here.
            if ((($fields[$field]['o'] ?? null) === self::HUMAN)) {
                continue;
            }

            if (! isset($hashes[$field])) {
                continue;
            }

            $fields[$field] = ['v' => $value, 'h' => $hashes[$field], 'o' => self::MACHINE];
            $written++;
        }

        $document[$locale]['fields'] = $fields;
        $document[$locale]['provider'] = $provider;
        $document[$locale]['model'] = $model;
        $document[$locale]['updated_at'] = now()->toIso8601String();

        return [$document, $written];
    }

    /** Record (or clear) a person's own wording for one field. */
    public static function setHuman(array $document, string $locale, string $field, ?string $value, string $sourceHash): array
    {
        $value = trim((string) $value);

        if ($value === '') {
            /*
             * Removed, not blanked. A blank human entry would out-rank the
             * machine's and leave the field empty on the page forever, which is
             * not what "drop my correction" means to anybody.
             */
            unset($document[$locale]['fields'][$field]);
        } else {
            $document[$locale]['fields'][$field] = ['v' => $value, 'h' => $sourceHash, 'o' => self::HUMAN];
            $document[$locale]['status'] = $document[$locale]['status'] ?? 'ready';
            $document[$locale]['updated_at'] = now()->toIso8601String();
        }

        // A language whose last entry just went is gone entirely.
        if (($document[$locale]['fields'] ?? []) === [] && ($document[$locale]['status'] ?? '') !== 'queued') {
            unset($document[$locale]);
        }

        return $document;
    }

    public static function setStatus(array $document, string $locale, string $status, ?string $error = null): array
    {
        $document[$locale]['status'] = $status;
        $document[$locale]['error'] = $error;
        $document[$locale]['fields'] ??= [];
        $document[$locale]['updated_at'] = now()->toIso8601String();

        return $document;
    }
}
