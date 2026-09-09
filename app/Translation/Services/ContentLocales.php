<?php

namespace App\Translation\Services;

/**
 * The one place that answers "is that a language we serve?".
 *
 * Every locale that reaches this module comes from outside — a form post, a
 * query string, a stored preference, an Accept-Language header — and a locale
 * code is used as a cache key, a database value, an `<html lang>`, a directory
 * name in a fallback lookup and part of a rate-limit key. So it is validated
 * against config/content_locales.php ONCE, here, and everything downstream
 * takes only what came out.
 *
 * Never trust a locale string. `../../` is a locale string.
 */
class ContentLocales
{
    /** @return array<string, array{name:string,native:string,dir:string,flag:?string}> */
    public function all(): array
    {
        return config('content_locales', []);
    }

    public function has(?string $code): bool
    {
        return $code !== null && array_key_exists($code, $this->all());
    }

    /**
     * The given code if we serve it, otherwise null. The ONLY way a locale
     * should enter this module.
     */
    public function normalise(?string $code): ?string
    {
        if (! is_string($code)) {
            return null;
        }

        $code = trim($code);

        if ($this->has($code)) {
            return $code;
        }

        /*
         * A browser sends 'pt-BR', 'en-GB', 'ar-EG'. We serve the base language
         * unless the region is one we list separately (zh-TW is a different
         * script, not a regional accent). Falling back to the base is right for
         * content: Brazilian and European Portuguese differ, and both are far
         * closer to each other than either is to English.
         */
        $base = strtok($code, '-_');

        return $this->has($base) ? $base : null;
    }

    /** @return array{name:string,native:string,dir:string,flag:?string}|null */
    public function meta(string $code): ?array
    {
        return $this->all()[$code] ?? null;
    }

    public function name(string $code): string
    {
        return $this->meta($code)['name'] ?? $code;
    }

    public function native(string $code): string
    {
        return $this->meta($code)['native'] ?? $code;
    }

    public function dir(string $code): string
    {
        return ($this->meta($code)['dir'] ?? 'ltr') === 'rtl' ? 'rtl' : 'ltr';
    }

    /**
     * The flag-icons country code, stripped to letters because it lands in a
     * CSS class name. Null when the language has no honest flag.
     */
    public function flag(string $code): ?string
    {
        $flag = $this->meta($code)['flag'] ?? null;

        if (! is_string($flag) || $flag === '') {
            return null;
        }

        $clean = preg_replace('/[^a-z]/', '', strtolower($flag));

        return $clean !== '' ? $clean : null;
    }

    /**
     * True when the whole INTERFACE exists in this language, not just the
     * content — i.e. it is in config/locales.php with a lang/<code>/ set behind
     * it. The picker says so, because "the buttons will still be in English" is
     * information a reader deserves before choosing.
     */
    public function isInterfaceLocale(string $code): bool
    {
        return array_key_exists($code, config('locales', []));
    }

    /**
     * The languages to show first, before the long list: the ones the interface
     * itself speaks, then the reader's own browser language when we serve it.
     *
     * @return array<int, string>
     */
    public function suggested(?string $accept = null): array
    {
        $out = array_keys(array_intersect_key($this->all(), config('locales', [])));

        if ($browser = $this->normalise($this->preferred($accept))) {
            $out[] = $browser;
        }

        return array_values(array_unique($out));
    }

    /**
     * The first language in an Accept-Language header that we serve.
     *
     * Hand-parsed rather than taken from Request::getPreferredLanguage(), which
     * only ever answers with a locale from a list you give it — and the list
     * that matters here (sixty content languages) is not the list the framework
     * knows about (two interface ones).
     */
    public function preferred(?string $accept): ?string
    {
        if (! is_string($accept) || $accept === '') {
            return null;
        }

        // "fr-CH, fr;q=0.9, en;q=0.8" → ['fr-CH' => 1.0, 'fr' => 0.9, ...]
        $ranked = [];

        foreach (explode(',', $accept) as $chunk) {
            $parts = explode(';', trim($chunk));
            $tag = trim($parts[0]);

            if ($tag === '' || $tag === '*' || strlen($tag) > 35) {
                continue;
            }

            $q = 1.0;
            if (isset($parts[1]) && preg_match('/q=([0-9.]+)/', $parts[1], $m)) {
                $q = (float) $m[1];
            }

            $ranked[$tag] = $q;
        }

        arsort($ranked);

        foreach (array_keys($ranked) as $tag) {
            if ($hit = $this->normalise($tag)) {
                return $hit;
            }
        }

        return null;
    }
}
