<?php

namespace App\Translation;

use App\Support\Modules\AbstractModule;

/**
 * The organiser writes once, in their own language. Everyone else reads it in
 * theirs.
 *
 * A club in Manama announces a championship in Arabic. A Brazilian coach opens
 * the link, picks Português, and reads the whole thing — the title, what the
 * event is, what to bring, what it costs — in Portuguese that reads as though a
 * Brazilian wrote it. Nobody was asked to fill in a second form.
 *
 * WHAT THIS IS NOT: a dictionary pass. Word-for-word machine translation
 * produces text that is technically correct and obviously foreign, and on a
 * page whose job is to convince a stranger to enter a competition, obviously
 * foreign reads as untrustworthy. The agent (Services\TranslationAgent) is
 * given the WHOLE event at once, with its sport, its type and its host, and
 * asked to write the same event in the target language — reordering a sentence,
 * splitting a clause, choosing the idiom that language actually uses. That is
 * why one call translates a whole event rather than one call per field: a
 * translator who cannot see the rest of the page cannot write for it.
 *
 * WHAT IT REFUSES TO TOUCH: names. A club's name, a person's name, a venue, a
 * sport's own vocabulary (`ippon`, `gi`, `kata`), a currency code, a belt
 * colour that is really a rank. Translating those is how "Victory BJJ Academy"
 * becomes something nobody in the hall recognises and a competitor turns up at
 * the wrong building.
 *
 * ── The shape ────────────────────────────────────────────────────────────────
 *
 *   Contracts\TranslatableContent   a model says which of its words are words
 *                                   (ClubEvent is the first).
 *   Services\Translator             the READ path. Given a record and a locale,
 *                                   hands back the translated strings or the
 *                                   originals — never blocks, never throws.
 *   Services\TranslationAgent       the one place that talks to a model.
 *   Jobs\TranslateContent           the write path, queued, one per (record,
 *                                   language).
 *   Models\TranslationDocument      ONE record per event holding every language
 *                                   as JSON — status, provenance and words.
 *   Translations                    the module's front door; the only class
 *                                   the rest of the platform may call.
 *
 * ⚠️ `Models\ContentTranslation` and `Models\TranslationRun` are the previous
 * store — one row per field per language — and are now DORMANT. Their data was
 * copied into the document by the 2026-09-09 migration and nothing reads them.
 * They are registered for removal in Documentation/HOUSE-CLEANING.md rather
 * than deleted here, because nothing is deleted in passing (CLAUDE.md).
 *
 * ── When it runs ─────────────────────────────────────────────────────────────
 *
 * The FIRST time anybody asks for a language, and never again: the answer is
 * stored, and the ten-thousandth Portuguese visitor costs nothing. A field
 * whose source text is edited re-translates itself; the rest of the event does
 * not. That is the whole cost model, and it is why the source hash exists.
 */
class Translation extends AbstractModule
{
    public function key(): string
    {
        return 'translation';
    }
}
