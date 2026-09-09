<?php

namespace App\Translation;

/**
 * One record's words in one language, ready to render.
 *
 * Every accessor takes the ORIGINAL as its fallback and returns it whenever the
 * translation is missing, stale or empty. That is not defensive habit — it is
 * the contract that lets this be wired into a live page under RULE #1: with no
 * translations, no provider, no queue worker and no network, every call here
 * returns exactly what the page rendered before this module existed.
 *
 * A page can therefore ask for a language that has never been translated and
 * still draw correctly, in the source language, on the first paint.
 */
class TranslatedDocument
{
    /**
     * @param  array<string, string>  $values  field => translated text
     */
    public function __construct(
        public readonly string $locale,
        public readonly bool $isSourceLanguage,
        private readonly array $values = [],
    ) {}

    /** An empty document — the answer whenever anything at all is unavailable. */
    public static function none(string $locale, bool $isSource = true): self
    {
        return new self($locale, $isSource, []);
    }

    /** Translated text for a field, or the original. */
    public function get(string $field, ?string $original = null): ?string
    {
        $value = $this->values[$field] ?? null;

        return (is_string($value) && trim($value) !== '') ? $value : $original;
    }

    /**
     * A list field ('requirements') rebuilt from its dotted parts, in the
     * order of the ORIGINAL list.
     *
     * Order comes from the original on purpose: the source is the truth about
     * how many bullets there are and what order they run in. A translation that
     * is missing item 3 shows item 3 in the source language rather than
     * shortening the list — a competitor reading a requirements list must never
     * be shown a shorter one than the organiser wrote.
     *
     * @param  array<int, string>  $original
     * @return array<int, string>
     */
    public function list(string $field, array $original): array
    {
        $out = [];

        foreach (array_values($original) as $i => $line) {
            $out[] = $this->get($field.'.'.$i, is_string($line) ? $line : '') ?? '';
        }

        return $out;
    }

    /** True when this document actually carries something translated. */
    public function any(): bool
    {
        return $this->values !== [];
    }

    /** @return array<string, string> */
    public function all(): array
    {
        return $this->values;
    }
}
