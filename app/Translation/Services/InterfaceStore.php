<?php

namespace App\Translation\Services;

use App\Translation\Models\InterfaceTranslation;
use Illuminate\Support\Facades\DB;

/**
 * Reading and writing the product's own words.
 *
 * The store behind Translations::correctInterface() and the admin screen. The
 * LOADER (DatabaseTranslationLoader) is the read path a page render uses; this
 * is everything else — importing what the files already hold, writing what a
 * run produces, and answering "where does this language stand?".
 *
 * ⚠️ ONE INVARIANT, and it is the same one the content translations carry:
 * a machine run never overwrites what a person wrote. It is what makes
 * correcting a word worth the effort, because the correction survives the next
 * re-translation, the next provider change and the next source edit.
 */
class InterfaceStore
{
    public function __construct(private LangFileCatalog $catalog) {}

    /**
     * Write one string.
     *
     * Returns false when a machine tried to overwrite a person — the caller has
     * not failed, it has been refused, and that distinction belongs to it.
     *
     * An empty value REMOVES a human correction rather than storing a blank,
     * so "clear my edit" hands the string back to the machine's version (or to
     * the lang file) instead of leaving it empty forever.
     */
    public function put(
        string $locale,
        string $fileId,
        string $key,
        ?string $value,
        string $origin = InterfaceTranslation::MACHINE,
        ?string $model = null,
        ?string $sourceHash = null,
    ): bool {
        [$namespace, $group] = InterfaceTranslation::splitFileId($fileId);

        $existing = InterfaceTranslation::query()
            ->where('locale', $locale)
            ->where('group', $group)
            ->when($namespace === null,
                fn ($q) => $q->whereNull('namespace'),
                fn ($q) => $q->where('namespace', $namespace))
            ->where('key', $key)
            ->first();

        // A person's words are never overwritten by a run.
        if ($existing && $existing->origin === InterfaceTranslation::HUMAN
            && $origin !== InterfaceTranslation::HUMAN) {
            return false;
        }

        if ($value === null || trim($value) === '') {
            $existing?->delete();
            DatabaseTranslationLoader::forget($locale, $namespace, $group);

            return true;
        }

        InterfaceTranslation::updateOrCreate(
            ['locale' => $locale, 'namespace' => $namespace, 'group' => $group, 'key' => $key],
            ['value' => trim($value), 'origin' => $origin, 'model' => $model, 'source_hash' => $sourceHash],
        );

        DatabaseTranslationLoader::forget($locale, $namespace, $group);

        return true;
    }

    /**
     * Write a whole file's worth at once.
     *
     * ⚠️ One transaction and one cache-forget, not one per string. A file can
     * carry a thousand strings and doing this row-by-row through `put()` is a
     * thousand SELECTs on a database that is SQLite in production.
     *
     * @param  array<string, string>  $values  key => translated text
     * @return int how many were written (a human's row is skipped, not counted)
     */
    public function putMany(string $locale, string $fileId, array $values, ?string $model = null, array $sourceHashes = []): int
    {
        if ($values === []) {
            return 0;
        }

        [$namespace, $group] = InterfaceTranslation::splitFileId($fileId);

        $human = InterfaceTranslation::query()
            ->where('locale', $locale)
            ->where('group', $group)
            ->when($namespace === null,
                fn ($q) => $q->whereNull('namespace'),
                fn ($q) => $q->where('namespace', $namespace))
            ->where('origin', InterfaceTranslation::HUMAN)
            ->pluck('key')
            ->flip();

        $written = 0;

        DB::transaction(function () use ($locale, $namespace, $group, $values, $model, $sourceHashes, $human, &$written) {
            foreach ($values as $key => $value) {
                if (isset($human[$key]) || ! is_string($value) || trim($value) === '') {
                    continue;
                }

                InterfaceTranslation::updateOrCreate(
                    ['locale' => $locale, 'namespace' => $namespace, 'group' => $group, 'key' => (string) $key],
                    [
                        'value' => trim($value),
                        'origin' => InterfaceTranslation::MACHINE,
                        'model' => $model,
                        'source_hash' => $sourceHashes[$key] ?? null,
                    ],
                );

                $written++;
            }
        });

        DatabaseTranslationLoader::forget($locale, $namespace, $group);

        return $written;
    }

    /**
     * What this locale currently HAS for one file — database first, file behind.
     *
     * The single answer to "is this string translated yet?", and it has to
     * merge both stores for the same reason the loader does: `lang/ar` is
     * tracked in git and is the hand-written source of truth for Arabic, while
     * everything generated since lives in rows. A completeness check that read
     * only one of them would either declare Arabic empty or declare a generated
     * language empty, and both answers are wrong.
     *
     * @return array<string, string> dotted key => value
     */
    public function stringsFor(string $locale, string $fileId, ?string $targetPath = null): array
    {
        [$namespace, $group] = InterfaceTranslation::splitFileId($fileId);

        $fromFiles = $targetPath !== null && is_file($targetPath)
            ? $this->catalog->strings($targetPath)
            : [];

        try {
            $rows = InterfaceTranslation::query()
                ->where('locale', $locale)
                ->where('group', $group)
                ->when($namespace === null,
                    fn ($q) => $q->whereNull('namespace'),
                    fn ($q) => $q->where('namespace', $namespace))
                ->pluck('value', 'key')
                ->all();
        } catch (\Throwable $e) {
            return $fromFiles;
        }

        return array_merge($fromFiles, $rows);
    }

    /**
     * Load every string a locale's lang FILES already hold into the database.
     *
     * The one-off that makes the move lossless: eleven languages were generated
     * onto disk before this table existed, and they are untracked files on a box
     * whose next deploy would delete them.
     *
     * Never overwrites a human row, and skips `en` — English is the source and
     * lives in `lang/en`, which is tracked and is not this table's business.
     *
     * @return array{files: int, strings: int}
     */
    public function importFiles(string $locale): array
    {
        if ($locale === 'en') {
            return ['files' => 0, 'strings' => 0];
        }

        $files = 0;
        $strings = 0;

        foreach ($this->catalog->files($locale) as $id => $file) {
            if (! is_file($file['target'])) {
                continue;
            }

            $values = $this->catalog->strings($file['target']);

            if ($values === []) {
                continue;
            }

            $files++;
            $strings += $this->putMany($locale, $id, $values, 'imported from lang files');
        }

        return ['files' => $files, 'strings' => $strings];
    }

    /**
     * One language's strings, page by page, beside their English.
     *
     * The screen's read. Every string the tier asks for is listed — including
     * the ones with no translation at all, because "what is still English?" is
     * the question somebody opens this to answer, and a list that showed only
     * what exists could never answer it.
     *
     * @return array{rows: array<int, array<string, mixed>>, page: int, pages: int, total: int, counts: array<string, int>}
     */
    public function strings(string $locale, string $tier, ?string $search, string $only, int $page): array
    {
        $perPage = 60;
        $rows = [];
        $missing = 0;
        $human = 0;

        /*
         * ⚠️ The catalog is built for THIS LOCALE, not for 'en'.
         *
         * `filesFor($tier, $locale)` is what sets each file's `target` to
         * `lang/<locale>/<group>.php`. Asking for 'en' sets the target to the
         * ENGLISH file — so every string found its own source sitting in the
         * "translation" slot, and a language with five thousand gaps reported
         * zero. Caught on Turkish, which had 446 strings and claimed to be
         * complete (2026-09-09).
         */
        foreach ($this->catalog->filesFor($tier, $locale) as $id => $file) {
            $english = $this->catalog->strings($file['source']);

            if ($english === []) {
                continue;
            }

            [$namespace, $group] = InterfaceTranslation::splitFileId($id);

            $stored = InterfaceTranslation::query()
                ->where('locale', $locale)
                ->where('group', $group)
                ->when($namespace === null,
                    fn ($q) => $q->whereNull('namespace'),
                    fn ($q) => $q->where('namespace', $namespace))
                ->get(['key', 'value', 'origin'])
                ->keyBy('key');

            // The tracked hand-written files (ar) are a translation too.
            $fromFile = is_file($file['target']) ? $this->catalog->strings($file['target']) : [];

            foreach ($english as $key => $source) {
                if (! is_string($source)) {
                    continue;
                }

                $row = $stored->get($key);
                $value = $row->value ?? ($fromFile[$key] ?? null);
                $origin = $row->origin ?? (isset($fromFile[$key]) ? 'file' : null);

                if ($value === null) {
                    $missing++;
                }

                if ($origin === InterfaceTranslation::HUMAN) {
                    $human++;
                }

                if ($only === 'missing' && $value !== null) {
                    continue;
                }

                if ($only === 'human' && $origin !== InterfaceTranslation::HUMAN) {
                    continue;
                }

                if ($search !== null && $search !== ''
                    && ! str_contains(mb_strtolower($source), mb_strtolower($search))
                    && ! str_contains(mb_strtolower((string) $value), mb_strtolower($search))
                    && ! str_contains(mb_strtolower($key), mb_strtolower($search))) {
                    continue;
                }

                $rows[] = [
                    'file' => $id,
                    'key' => $key,
                    'source' => $source,
                    'value' => $value,
                    'origin' => $origin,
                ];
            }
        }

        $total = count($rows);

        return [
            'rows' => array_slice($rows, ($page - 1) * $perPage, $perPage),
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
            'total' => $total,
            'counts' => ['missing' => $missing, 'human' => $human],
        ];
    }

    /**
     * Where each language stands: how many of the tier's strings it has.
     *
     * Counted against what a page can actually ASK for, per tier, so the number
     * means what a reader would say it means.
     *
     * @return array<int, array{locale: string, stored: int, expected: int, human: int, missing: int}>
     */
    public function coverage(?string $tier = null): array
    {
        $tier ??= 'event';
        $expected = 0;

        /*
         * ⚠️ Both halves of the fraction must be scoped to the SAME tier.
         *
         * Counting every stored row against the tier's expected total gave
         * Arabic "9,538 / 5,511" — it has the whole tree imported (23 files)
         * while the event tier asks for a fraction of it. A progress bar that
         * can exceed 100% is telling somebody a number it does not mean.
         */
        $wanted = [];

        foreach ($this->catalog->filesFor($tier, 'en') as $id => $file) {
            $expected += count($this->catalog->strings($file['source']));

            [$namespace, $group] = InterfaceTranslation::splitFileId($id);
            $wanted[($namespace ?? '').'|'.$group] = true;
        }

        $rows = InterfaceTranslation::query()
            ->selectRaw('locale, namespace, "group" as grp, COUNT(*) as stored, '
                .'SUM(CASE WHEN origin = ? THEN 1 ELSE 0 END) as human', [InterfaceTranslation::HUMAN])
            ->groupBy('locale', 'namespace', 'group')
            ->get();

        $byLocale = [];

        foreach ($rows as $row) {
            if (! isset($wanted[((string) $row->namespace).'|'.$row->grp])) {
                continue;   // a file outside this tier
            }

            $locale = (string) $row->locale;

            $byLocale[$locale]['stored'] = ($byLocale[$locale]['stored'] ?? 0) + (int) $row->stored;
            $byLocale[$locale]['human'] = ($byLocale[$locale]['human'] ?? 0) + (int) $row->human;
        }

        $out = [];

        foreach ($byLocale as $locale => $counts) {
            $stored = min($counts['stored'], $expected);

            $out[] = [
                'locale' => $locale,
                'stored' => $stored,
                'expected' => $expected,
                'human' => $counts['human'],
                'missing' => max(0, $expected - $stored),
            ];
        }

        usort($out, fn ($a, $b) => $b['stored'] <=> $a['stored']);

        return $out;
    }
}
