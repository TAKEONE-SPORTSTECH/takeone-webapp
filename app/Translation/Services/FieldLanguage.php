<?php

namespace App\Translation\Services;

/**
 * One question, asked per field: does this need translating for this reader?
 *
 * The rule that fixes the reported bug — an Arabic paragraph on an event
 * declared English, shown raw to an English reader — and the rule that keeps
 * the bill down, because a field already in the reader's language is neither
 * sent nor paid for.
 */
class FieldLanguage
{
    public function __construct(private ScriptDetector $scripts) {}

    /**
     * @param  string  $text          the source field
     * @param  string  $sourceLocale  what the event says it was written in
     * @param  string  $target        the language being read
     */
    public function needsTranslation(string $text, string $sourceLocale, string $target): bool
    {
        $script = $this->scripts->scriptOf($text);

        // Too short or too mixed to judge — translate. Spending a few tokens
        // beats showing a reader a language they cannot read.
        if ($script === 'mixed') {
            return true;
        }

        $candidates = $this->scripts->localesFor($script, $text);

        // The script proves it IS the reader's language. Leave it alone: this
        // is the organiser's own wording, and rewriting it would cost money to
        // make it worse.
        if ($candidates === [$target]) {
            return false;
        }

        // The script proves it is NOT the reader's language. This is the case
        // the whole class exists for: Arabic text, English reader, on an event
        // whose column claims English.
        if ($candidates !== [] && ! in_array($target, $candidates, true)) {
            return true;
        }

        /*
         * Latin script, and no way to tell English from Portuguese by looking
         * at it — no regex ever will. Fall back to what the event declares,
         * which is the behaviour that existed before any of this.
         *
         * ⚠️ The honest limit: a Portuguese description on an event declared
         * English, read in English, stays Portuguese. Only a person can settle
         * that, which is what the organiser's review screen is for.
         */
        return $target !== $sourceLocale;
    }
}
