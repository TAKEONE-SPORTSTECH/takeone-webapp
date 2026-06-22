<?php

namespace App\Traits;

/**
 * Adds per-field, per-locale translations to a model via a single nullable
 * `translations` JSON column — without disturbing the base text columns.
 *
 * The base column (e.g. `name`) keeps holding the DEFAULT/fallback value
 * (English). The JSON column stores only non-default locales, shaped:
 *
 *   { "name": { "ar": "..." }, "description": { "ar": "..." } }
 *
 * Each model using the trait declares the translatable fields:
 *
 *   protected array $translatable = ['name', 'description'];
 */
trait HasTranslations
{
    /**
     * Cast `translations` to array without clobbering the model's own $casts.
     */
    public function initializeHasTranslations(): void
    {
        $this->mergeCasts(['translations' => 'array']);
    }

    /**
     * Value of $field in the given locale (defaults to the active app locale),
     * falling back to the base column when the translation is empty/missing.
     */
    public function tr(string $field, ?string $locale = null): ?string
    {
        $locale = $locale ?: app()->getLocale();

        // The fallback locale always lives in the base column.
        if ($locale !== config('app.fallback_locale', 'en')) {
            $value = data_get($this->translations, "{$field}.{$locale}");
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return $this->getAttribute($field);
    }

    /**
     * Set a single translation, pruning empties so the column returns to null
     * when nothing meaningful is stored.
     */
    public function setTranslation(string $field, string $locale, ?string $value): static
    {
        $translations = $this->translations ?? [];

        if ($value === null || trim($value) === '') {
            unset($translations[$field][$locale]);
            if (empty($translations[$field])) {
                unset($translations[$field]);
            }
        } else {
            $translations[$field][$locale] = $value;
        }

        $this->translations = $translations ?: null;

        return $this;
    }

    /**
     * Bulk-set from request input. Shape: ['field' => ['ar' => '...'], ...].
     * Silently ignores fields not declared in $translatable.
     */
    public function setTranslations(array $input): static
    {
        foreach ($input as $field => $locales) {
            if (! in_array($field, $this->translatable ?? [], true)) {
                continue;
            }
            foreach ((array) $locales as $locale => $value) {
                $this->setTranslation($field, (string) $locale, is_string($value) ? $value : null);
            }
        }

        return $this;
    }
}
