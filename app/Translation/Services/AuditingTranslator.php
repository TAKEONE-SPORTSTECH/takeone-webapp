<?php

namespace App\Translation\Services;

use Illuminate\Translation\Translator;

/**
 * Laravel's translator, with the fallback made visible.
 *
 * ── What it changes, and what it deliberately does not ───────────────────────
 *
 * It does NOT change what any string resolves to. A key missing in the active
 * locale still falls back to English and the page still renders — that is the
 * behaviour that means a half-generated locale is a cosmetic problem rather
 * than an outage, and it is not up for negotiation (RULE #1).
 *
 * What it changes is that the fallback is now COUNTED (MissingStrings), so the
 * platform can answer "how much of this page is still English?" without
 * somebody reading it, and can be MARKED, so a person auditing a screen can see
 * at a glance which words are the untranslated ones.
 *
 * ── The marker ───────────────────────────────────────────────────────────────
 *
 * With marking on, an unresolved string comes back wrapped:
 *
 *     Weigh-in & draw      →      ⟦Weigh-in & draw⟧
 *
 * Chosen because those brackets appear in no language's ordinary text, survive
 * `strip_tags`, and are legible in a screenshot — the three things a marker has
 * to do. It is never on for an ordinary visitor: see
 * `App\Providers\AppServiceProvider` for the gate.
 */
class AuditingTranslator extends Translator
{
    private ?MissingStrings $log = null;

    private bool $mark = false;

    public function watch(MissingStrings $log, bool $mark): void
    {
        $this->log = $log;
        $this->mark = $mark;
    }

    /**
     * ⚠️ Detect the fallback BEFORE delegating, not after.
     *
     * `parent::get()` resolves the fallback internally and returns a string
     * that looks exactly like a hit, so there is nothing to inspect afterwards
     * — the information is gone by the time the caller sees it. Asking the
     * loader for this locale alone is the only way to know whether the answer
     * came from the reader's language or from English.
     */
    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        $value = parent::get($key, $replace, $locale, $fallback);

        if ($this->log === null || ! is_string($value)) {
            return $value;
        }

        $locale = $locale ?: $this->locale;

        // Nothing to fall back FROM.
        if ($locale === $this->fallback) {
            return $value;
        }

        // Asked without the fallback: null (or the key echoed back) means this
        // locale has no entry and the value above came from English.
        $own = parent::get($key, $replace, $locale, false);

        if ($own !== $value) {
            return $value;   // resolved in the reader's own language
        }

        /*
         * `get()` returns the KEY itself when nothing matches anywhere, which
         * is not a fallback — it is a missing key, a different defect, and
         * counting it here would bury the real ones.
         */
        if ($value === $key) {
            return $value;
        }

        // Same string with and without fallback CAN mean "identical in both
        // languages" ("OK", "Email"). Only count it when this locale genuinely
        // has no line for the key.
        if ($this->hasLine($key, $locale)) {
            return $value;
        }

        $this->log->record($locale, $key);

        return $this->mark ? '⟦'.$value.'⟧' : $value;
    }

    /** Does this locale carry a line for the key at all? */
    private function hasLine(string $key, string $locale): bool
    {
        [$namespace, $group, $item] = $this->parseKey($key);

        $this->load($namespace, $group, $locale);

        return $item !== null
            && \Illuminate\Support\Arr::has($this->loaded[$namespace][$group][$locale] ?? [], $item);
    }
}
